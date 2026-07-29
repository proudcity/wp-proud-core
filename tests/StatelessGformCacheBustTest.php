<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for GF-context cache-busting in proudcity_stateless_suffix_cache_bust()
 * and the proudcity_stateless_is_gform_upload() helper.
 *
 * Issue #2876: GF file uploads collide onto one GCS object when the source
 * filename already carries the upstream prefix format (^[a-f0-9]{8}-). The
 * prefix guard short-circuits the cache-buster and returns the same name for
 * every submission, overwriting the same GCS object.
 *
 * The fix: when a GF upload is in progress, bypass both idempotency guards and
 * always mint a fresh unique suffix.
 */
class StatelessGformCacheBustTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        // Always clear the GF flag before each test.
        unset( $GLOBALS['proudcity_gform_upload_context'] );
    }

    protected function tearDown(): void
    {
        unset( $GLOBALS['proudcity_gform_upload_context'] );
        Monkey\tearDown();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // proudcity_stateless_is_gform_upload() helper
    // -------------------------------------------------------------------------

    /**
     * Helper returns true when the explicit GF flag is set, even if
     * doing_filter is false.
     */
    public function test_is_gform_upload_true_when_flag_set(): void
    {
        $GLOBALS['proudcity_gform_upload_context'] = true;

        Functions\when('doing_filter')->justReturn( false );

        $this->assertTrue( proudcity_stateless_is_gform_upload() );
    }

    /**
     * Helper returns true when doing_filter('gform_save_field_value') is true,
     * even if the explicit flag is not set.
     */
    public function test_is_gform_upload_true_when_doing_filter(): void
    {
        // Flag not set.
        Functions\expect('doing_filter')
            ->once()
            ->with('gform_save_field_value')
            ->andReturn( true );

        $this->assertTrue( proudcity_stateless_is_gform_upload() );
    }

    /**
     * Helper returns false when neither signal is active.
     */
    public function test_is_gform_upload_false_when_no_signal(): void
    {
        Functions\when('doing_filter')->justReturn( false );

        $this->assertFalse( proudcity_stateless_is_gform_upload() );
    }

    // -------------------------------------------------------------------------
    // GF-context: prefix guard is bypassed
    // -------------------------------------------------------------------------

    /**
     * GF context + upstream-prefixed name (the exact #2876 collision scenario):
     * a fresh unique suffix must be appended even though the name already starts
     * with ^[a-f0-9]{8}-.
     *
     * Input:  c264795b-employmentappliation.pdf
     * Expect: c264795b-employmentappliation-XXXXXXXX.pdf  (differs from input)
     */
    public function test_gf_context_bypasses_prefix_guard(): void
    {
        $GLOBALS['proudcity_gform_upload_context'] = true;
        Functions\when('doing_filter')->justReturn( false );
        Functions\when('wp_rand')->justReturn( 42000 );

        $filename = 'c264795b-employmentappliation.pdf';
        $result   = proudcity_stateless_suffix_cache_bust( null, $filename );

        $this->assertNotSame( $filename, $result, 'GF context must bypass the prefix guard.' );
        $this->assertMatchesRegularExpression(
            '/^c264795b-employmentappliation-[a-f0-9]{8}\.pdf$/',
            $result,
            'Existing prefix kept; fresh suffix appended before extension.'
        );
    }

    /**
     * GF context + already-suffixed name: the suffix guard must also be
     * bypassed — every GF submission is a genuinely new file.
     *
     * Input:  report-abcd1234.pdf
     * Expect: report-abcd1234-XXXXXXXX.pdf  (differs from input)
     */
    public function test_gf_context_bypasses_suffix_guard(): void
    {
        $GLOBALS['proudcity_gform_upload_context'] = true;
        Functions\when('doing_filter')->justReturn( false );
        Functions\when('wp_rand')->justReturn( 99001 );

        $filename = 'report-abcd1234.pdf';
        $result   = proudcity_stateless_suffix_cache_bust( null, $filename );

        $this->assertNotSame( $filename, $result, 'GF context must bypass the suffix guard.' );
        $this->assertMatchesRegularExpression(
            '/^report-abcd1234-[a-f0-9]{8}\.pdf$/',
            $result
        );
    }

    /**
     * Two GF uploads of the same file in the same second with different
     * wp_rand() values must produce different filenames. This is the direct
     * reproduction of the #2876 collision: two applicants upload the same
     * pre-named blank form.
     */
    public function test_gf_context_two_same_second_uploads_differ(): void
    {
        $GLOBALS['proudcity_gform_upload_context'] = true;
        Functions\when('doing_filter')->justReturn( false );
        Functions\expect('wp_rand')
            ->twice()
            ->andReturn( 0, PHP_INT_MAX );

        $filename = 'c264795b-employmentappliation.pdf';
        $first    = proudcity_stateless_suffix_cache_bust( null, $filename );
        $second   = proudcity_stateless_suffix_cache_bust( null, $filename );

        $this->assertNotSame(
            $first,
            $second,
            'Two GF uploads of the same prefixed filename in one second must be distinct.'
        );
    }

    // -------------------------------------------------------------------------
    // Non-GF context: both guards still hold (regression locks)
    // -------------------------------------------------------------------------

    /**
     * NON-GF context + upstream-prefixed name -> returned unchanged.
     * This locks the existing media-library guard on lines 43-45.
     */
    public function test_non_gf_prefix_guard_still_holds(): void
    {
        // No GF flag, doing_filter returns false.
        Functions\when('doing_filter')->justReturn( false );
        Functions\when('wp_rand')->justReturn( 55555 );

        $filename = 'abcd1234-report.pdf';
        $result   = proudcity_stateless_suffix_cache_bust( null, $filename );

        $this->assertSame( $filename, $result, 'Non-GF prefix guard must remain intact.' );
    }

    /**
     * NON-GF context + already-suffixed name -> returned unchanged.
     * This locks the idempotency guard on lines 37-39.
     */
    public function test_non_gf_suffix_guard_still_holds(): void
    {
        Functions\when('doing_filter')->justReturn( false );
        Functions\when('wp_rand')->justReturn( 77777 );

        $filename = 'report-abcd1234.pdf';
        $result   = proudcity_stateless_suffix_cache_bust( null, $filename );

        $this->assertSame( $filename, $result, 'Non-GF idempotency guard must remain intact.' );
    }

    // -------------------------------------------------------------------------
    // GF context: retina handling
    // -------------------------------------------------------------------------

    /**
     * GF context + retina @2x name: suffix must be placed before the @2x
     * marker, exactly as the normal (non-GF) retina path does.
     */
    public function test_gf_context_retina_suffix_placed_before_at_marker(): void
    {
        $GLOBALS['proudcity_gform_upload_context'] = true;
        Functions\when('doing_filter')->justReturn( false );
        Functions\when('wp_rand')->justReturn( 11111 );

        $result = proudcity_stateless_suffix_cache_bust( null, 'photo@2x.png' );

        $this->assertMatchesRegularExpression(
            '/^photo-[a-f0-9]{8}@2x\.png$/',
            $result,
            'Retina marker must stay in the correct position in GF context.'
        );
    }

    /**
     * GF context + prefixed retina name: suffix still placed before @2x.
     */
    public function test_gf_context_prefixed_retina_suffix_placed_correctly(): void
    {
        $GLOBALS['proudcity_gform_upload_context'] = true;
        Functions\when('doing_filter')->justReturn( false );
        Functions\when('wp_rand')->justReturn( 22222 );

        $result = proudcity_stateless_suffix_cache_bust( null, 'c264795b-photo@2x.png' );

        $this->assertMatchesRegularExpression(
            '/^c264795b-photo-[a-f0-9]{8}@2x\.png$/',
            $result,
            'Prefixed retina name in GF context: suffix before @2x.'
        );
    }

    // -------------------------------------------------------------------------
    // $return respected in both contexts
    // -------------------------------------------------------------------------

    /**
     * If a prior filter already set $return, our callback must respect it in GF
     * context too — the $return short-circuit (line 28) must fire first.
     */
    public function test_pre_existing_return_value_respected_in_gf_context(): void
    {
        $GLOBALS['proudcity_gform_upload_context'] = true;
        Functions\when('doing_filter')->justReturn( false );
        Functions\when('wp_rand')->justReturn( 33333 );

        $result = proudcity_stateless_suffix_cache_bust( 'already-set.pdf', 'c264795b-report.pdf' );

        $this->assertSame( 'already-set.pdf', $result );
    }

    // -------------------------------------------------------------------------
    // doing_filter fallback covers the add-on path
    // -------------------------------------------------------------------------

    /**
     * When the WP-Stateless GF add-on is active (our flag never gets set), the
     * doing_filter('gform_save_field_value') fallback must still trigger the
     * GF-context branch and bypass the prefix guard.
     */
    public function test_doing_filter_fallback_bypasses_prefix_guard(): void
    {
        // Explicit flag NOT set — simulates the add-on path.
        Functions\expect('doing_filter')
            ->once()
            ->with('gform_save_field_value')
            ->andReturn( true );
        Functions\when('wp_rand')->justReturn( 44444 );

        $filename = 'c264795b-employmentappliation.pdf';
        $result   = proudcity_stateless_suffix_cache_bust( null, $filename );

        $this->assertNotSame( $filename, $result );
        $this->assertMatchesRegularExpression(
            '/^c264795b-employmentappliation-[a-f0-9]{8}\.pdf$/',
            $result
        );
    }

    // -------------------------------------------------------------------------
    // proudcity_stateless_gform_naming_upload() — backtrace detector
    // -------------------------------------------------------------------------

    /**
     * Detector returns true when GFFormsModel::get_file_upload_path is present
     * in the supplied frame list (the production case at forms_model.php:6185).
     */
    public function test_gform_naming_upload_true_when_get_file_upload_path_on_stack(): void
    {
        $frames = [
            [ 'function' => 'sanitize_file_name' ],
            [ 'class' => 'GFFormsModel', 'function' => 'get_file_upload_path' ],
            [ 'class' => 'GF_Field_FileUpload', 'function' => 'upload_file' ],
        ];

        $this->assertTrue( proudcity_stateless_gform_naming_upload( $frames ) );
    }

    /**
     * Detector returns false when GFFormsModel::get_file_upload_path is absent
     * from the frame list (non-GF context, e.g. media library).
     */
    public function test_gform_naming_upload_false_when_absent(): void
    {
        $frames = [
            [ 'function' => 'sanitize_file_name' ],
            [ 'class' => 'WP_HTTP', 'function' => 'request' ],
        ];

        $this->assertFalse( proudcity_stateless_gform_naming_upload( $frames ) );
    }

    /**
     * GFExport carve-out: if GFExport is also on the stack alongside
     * GFFormsModel::get_file_upload_path, detector returns false to avoid
     * treating an export as an upload.
     */
    public function test_gform_naming_upload_false_during_gfexport(): void
    {
        $frames = [
            [ 'function' => 'sanitize_file_name' ],
            [ 'class' => 'GFFormsModel', 'function' => 'get_file_upload_path' ],
            [ 'class' => 'GFExport', 'function' => 'start_export' ],
        ];

        $this->assertFalse( proudcity_stateless_gform_naming_upload( $frames ) );
    }

    /**
     * proudcity_stateless_is_gform_upload() returns true when the backtrace
     * signal fires, even with the explicit flag unset and doing_filter false.
     * Exercises the third OR branch added to is_gform_upload().
     */
    public function test_is_gform_upload_true_via_backtrace_signal(): void
    {
        // Neither of the existing two signals.
        Functions\when('doing_filter')->justReturn( false );

        // Inject a frame list that matches get_file_upload_path.
        $frames = [
            [ 'class' => 'GFFormsModel', 'function' => 'get_file_upload_path' ],
        ];

        $this->assertTrue( proudcity_stateless_is_gform_upload( $frames ) );
    }

    // -------------------------------------------------------------------------
    // Regression lock: plain media-library upload still gets its suffix
    // -------------------------------------------------------------------------

    /**
     * NON-GF context with a plain filename: the normal cache-bust suffix is
     * still minted. Ensures the backtrace branch does not short-circuit the
     * normal path for media-library uploads.
     */
    public function test_plain_media_name_still_gets_suffix_outside_gform(): void
    {
        Functions\when('doing_filter')->justReturn( false );
        Functions\when('wp_rand')->justReturn( 66666 );

        $result = proudcity_stateless_suffix_cache_bust( null, 'report.pdf' );

        $this->assertMatchesRegularExpression(
            '/^report-[a-f0-9]{8}\.pdf$/',
            $result,
            'Normal media-library upload must still receive a cache-bust suffix.'
        );
    }
}
