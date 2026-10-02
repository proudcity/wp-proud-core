<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for proudcity_stateless_suffix_cache_bust() in
 * plugin_override/wp-stateless/proud-wp-stateless.php.
 *
 * Verifies the suffix-placement logic, both idempotency guards, retina
 * handling, and the wp_rand() mix-in that prevents same-second hash collisions.
 */
class StatelessSuffixCacheBustTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        unset( $GLOBALS['proudcity_stateless_minted_names'] );
    }

    protected function tearDown(): void
    {
        unset( $GLOBALS['proudcity_stateless_minted_names'] );
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * A plain filename gets the hash appended before the extension.
     */
    public function test_plain_filename_is_suffixed(): void
    {
        Functions\when('wp_rand')->justReturn(12345);

        $result = proudcity_stateless_suffix_cache_bust( null, 'report.pdf' );

        $this->assertMatchesRegularExpression(
            '/^report-[a-f0-9]{8}\.pdf$/',
            $result,
            'Expected suffix format: report-XXXXXXXX.pdf'
        );
    }

    /**
     * Uppercase names are lowercased, matching upstream sanitize_file_name behaviour.
     */
    public function test_uppercase_filename_is_lowercased_and_suffixed(): void
    {
        Functions\when('wp_rand')->justReturn(99999);

        $result = proudcity_stateless_suffix_cache_bust( null, 'Report.PDF' );

        $this->assertMatchesRegularExpression(
            '/^report-[a-f0-9]{8}\.pdf$/',
            $result,
            'Name and extension must both be lowercased.'
        );
    }

    /**
     * A retina @2x filename gets the hash inserted before the @2x marker.
     */
    public function test_retina_filename_gets_hash_before_at_marker(): void
    {
        Functions\when('wp_rand')->justReturn(55555);

        $result = proudcity_stateless_suffix_cache_bust( null, 'photo@2x.png' );

        $this->assertMatchesRegularExpression(
            '/^photo-[a-f0-9]{8}@2x\.png$/',
            $result,
            'Hash must sit before @2x, not after it.'
        );
    }

    /**
     * A file that already carries a suffix hash (-[8 hex]) is returned unchanged.
     */
    public function test_already_suffixed_file_is_not_re_randomized(): void
    {
        Functions\when('wp_rand')->justReturn(77777);

        $filename = 'report-abcd1234.pdf';
        $result   = proudcity_stateless_suffix_cache_bust( null, $filename );

        $this->assertSame( $filename, $result );
    }

    /**
     * A file carrying the upstream prefix format (-[8 hex] at the start) is
     * returned unchanged so historically-uploaded files are not double-hashed.
     */
    public function test_upstream_prefixed_file_is_not_re_randomized(): void
    {
        Functions\when('wp_rand')->justReturn(88888);

        $filename = 'abcd1234-report.pdf';
        $result   = proudcity_stateless_suffix_cache_bust( null, $filename );

        $this->assertSame( $filename, $result );
    }

    /**
     * If a prior filter already set $return, our callback must respect it and
     * return that value unchanged.
     */
    public function test_pre_existing_return_value_is_respected(): void
    {
        Functions\when('wp_rand')->justReturn(11111);

        $result = proudcity_stateless_suffix_cache_bust( 'already-set.pdf', 'report.pdf' );

        $this->assertSame( 'already-set.pdf', $result );
    }

    /**
     * Two calls that receive different wp_rand() values must produce different
     * hashes even when time() returns the same value.
     *
     * This verifies that wp_rand() is actually mixed into the md5 seed. If the
     * seed were md5(time()) alone, both calls would be identical within the same
     * second — the latent upstream bug from issue #2232.
     *
     * We supply two extreme wp_rand() values so the md5 inputs are provably
     * different regardless of what time() returns.
     */
    public function test_different_wp_rand_values_produce_different_hashes(): void
    {
        Functions\expect('wp_rand')
            ->twice()
            ->andReturn( 0, PHP_INT_MAX );

        $first = proudcity_stateless_suffix_cache_bust( null, 'report.pdf' );

        // Names are reused within a request (#2950), so model the second upload
        // as a separate request.
        unset( $GLOBALS['proudcity_stateless_minted_names'] );

        $second = proudcity_stateless_suffix_cache_bust( null, 'report.pdf' );

        $this->assertNotSame(
            $first,
            $second,
            'Different wp_rand() values must yield different hashes — proves it is part of the md5 seed.'
        );
    }

    /**
     * Issue #2950: within one request the same input must always map to the
     * same name. Gravity Forms 3.1.2 sanitizes a temp filename twice in
     * get_tmp_file_details() (once to store it, once to HMAC it) and silently
     * drops the upload when the two differ.
     */
    public function test_same_name_in_one_request_is_minted_once(): void
    {
        Functions\expect('wp_rand')
            ->once()
            ->andReturn( 0 );

        $first  = proudcity_stateless_suffix_cache_bust( null, 'report.pdf' );
        $second = proudcity_stateless_suffix_cache_bust( null, 'report.pdf' );

        $this->assertSame( $first, $second );
    }

    /**
     * Mirrors GF_Field_FileUpload::get_tmp_file_details(): the stored
     * temp_filename and the name passed to the HMAC go through separate
     * sanitize calls, and the hash must still verify against the stored name.
     */
    public function test_gravity_forms_temp_file_hash_verifies(): void
    {
        Functions\expect('wp_rand')
            ->once()
            ->andReturn( 4242 );

        $raw    = '6abff3cc57f5f_input_11_0inoyyl1qnipslau_03f50b3aef744ae2be9b3401b15d5335.txt';
        $stored = proudcity_stateless_suffix_cache_bust( null, $raw );
        $hashed = proudcity_stateless_suffix_cache_bust( null, $raw );

        $this->assertTrue( hash_equals(
            hash_hmac( 'sha256', $hashed . '|delta-upload.txt', 'salt' ),
            hash_hmac( 'sha256', $stored . '|delta-upload.txt', 'salt' )
        ) );
    }

    /**
     * Reusing names is per input, so different files in one request still get
     * their own hashes.
     */
    public function test_different_names_in_one_request_get_their_own_hash(): void
    {
        Functions\expect('wp_rand')
            ->twice()
            ->andReturn( 0, PHP_INT_MAX );

        $report = proudcity_stateless_suffix_cache_bust( null, 'report.pdf' );
        $agenda = proudcity_stateless_suffix_cache_bust( null, 'agenda.pdf' );

        $this->assertMatchesRegularExpression( '/^report-[a-f0-9]{8}\.pdf$/', $report );
        $this->assertMatchesRegularExpression( '/^agenda-[a-f0-9]{8}\.pdf$/', $agenda );
        $this->assertNotSame( substr( $report, 7, 8 ), substr( $agenda, 7, 8 ) );
    }

    /**
     * A reused retina name keeps the hash before the @2x marker.
     */
    public function test_retina_name_is_reused_in_one_request(): void
    {
        Functions\expect('wp_rand')
            ->once()
            ->andReturn( 55555 );

        $first  = proudcity_stateless_suffix_cache_bust( null, 'photo@2x.png' );
        $second = proudcity_stateless_suffix_cache_bust( null, 'photo@2x.png' );

        $this->assertSame( $first, $second );
        $this->assertMatchesRegularExpression( '/^photo-[a-f0-9]{8}@2x\.png$/', $first );
    }
}
