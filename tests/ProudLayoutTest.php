<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ProudLayout in modules/proud-layout/proud-layout.php.
 *
 * Covers the bug where the "Don't display image on individual page" checkbox
 * does not save on first publish (#2804).
 *
 * Root cause: save_featured_image_meta() had no autosave/revision guard and no
 * nonce check. In the Gutenberg publish flow, save_post fires during autosaves
 * (which don't carry the meta box fields), resetting the meta to 0 before the
 * real save can record a checked value.
 */
class ProudLayoutTest extends TestCase
{
    private ProudLayout $layout;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->layout = new ProudLayout();
        $_POST    = [];
        $_REQUEST = [];
    }

    protected function tearDown(): void
    {
        $_POST    = [];
        $_REQUEST = [];
        Monkey\tearDown();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // save_featured_image_meta — guard rails
    // -------------------------------------------------------------------------

    /**
     * Autosave requests must be silently skipped.
     *
     * Autosaves don't include classic meta box fields, so running the save
     * would overwrite a checked value with 0.
     */
    public function test_save_skips_autosaves(): void
    {
        $post     = new stdClass();
        $post->ID = 1;

        Functions\when('wp_is_post_autosave')->justReturn(1);
        Functions\expect('update_post_meta')->never();

        $this->layout->save_featured_image_meta(1, $post, false);
        $this->addToAssertionCount(1); // Mockery expectation verified in tearDown
    }

    /**
     * Revision saves must be silently skipped.
     *
     * Revisions are not the canonical post record; saving meta against the
     * revision ID would have no effect on the published post.
     */
    public function test_save_skips_revisions(): void
    {
        $post     = new stdClass();
        $post->ID = 1;

        Functions\when('wp_is_post_autosave')->justReturn(false);
        Functions\when('wp_is_post_revision')->justReturn(10);
        Functions\expect('update_post_meta')->never();

        $this->layout->save_featured_image_meta(1, $post, true);
        $this->addToAssertionCount(1);
    }

    /**
     * A request without the nonce field must be silently skipped.
     *
     * This covers REST API saves and other programmatic post updates that
     * do not originate from the edit screen form.
     */
    public function test_save_skips_when_nonce_is_absent(): void
    {
        $post     = new stdClass();
        $post->ID = 1;

        Functions\when('wp_is_post_autosave')->justReturn(false);
        Functions\when('wp_is_post_revision')->justReturn(false);
        // No nonce key in $_POST.
        Functions\expect('update_post_meta')->never();

        $this->layout->save_featured_image_meta(1, $post, true);
        $this->addToAssertionCount(1);
    }

    /**
     * An invalid nonce must cause a silent skip (no meta update).
     */
    public function test_save_skips_when_nonce_is_invalid(): void
    {
        $post     = new stdClass();
        $post->ID = 1;

        $_POST['hide_featured_image_nonce'] = 'tampered';

        Functions\when('wp_is_post_autosave')->justReturn(false);
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('wp_verify_nonce')->justReturn(false);
        Functions\expect('update_post_meta')->never();

        $this->layout->save_featured_image_meta(1, $post, true);
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // save_featured_image_meta — correct values written
    // -------------------------------------------------------------------------

    /**
     * When the checkbox is checked, meta must be saved as 1.
     *
     * This is the core regression: on first publish the value was being reset
     * to 0 by an autosave before this save could run. With the autosave guard
     * in place, only real form submissions reach this branch.
     */
    public function test_save_writes_1_when_checkbox_is_checked(): void
    {
        $post     = new stdClass();
        $post->ID = 42;

        $_POST['hide_featured_image_nonce'] = 'valid';
        $_POST['hide_featured_image']       = '1';

        Functions\when('wp_is_post_autosave')->justReturn(false);
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('wp_verify_nonce')->justReturn(true);
        Functions\expect('update_post_meta')
            ->once()
            ->with(42, 'hide_featured_image', 1);

        $this->layout->save_featured_image_meta(42, $post, true);
        $this->addToAssertionCount(1);
    }

    /**
     * When the checkbox is unchecked (absent from POST), meta must be saved as 0.
     */
    public function test_save_writes_0_when_checkbox_is_unchecked(): void
    {
        $post     = new stdClass();
        $post->ID = 42;

        $_POST['hide_featured_image_nonce'] = 'valid';
        // hide_featured_image is absent — unchecked checkboxes are not submitted.

        Functions\when('wp_is_post_autosave')->justReturn(false);
        Functions\when('wp_is_post_revision')->justReturn(false);
        Functions\when('wp_verify_nonce')->justReturn(true);
        Functions\expect('update_post_meta')
            ->once()
            ->with(42, 'hide_featured_image', 0);

        $this->layout->save_featured_image_meta(42, $post, true);
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // hide_featured_image — checkbox rendering
    // -------------------------------------------------------------------------

    // -------------------------------------------------------------------------
    // hide_featured_image — checkbox rendering
    // The function now accepts $post_id as its second argument and calls
    // get_post() directly, making it reliable in the AJAX context that fires
    // when a featured image is set via JavaScript.
    // -------------------------------------------------------------------------

    private function makePost( int $id, string $type ): stdClass
    {
        $post            = new stdClass();
        $post->ID        = $id;
        $post->post_type = $type;
        return $post;
    }

    /**
     * When the post meta is 1 the rendered checkbox must include checked="checked".
     */
    public function test_checkbox_is_checked_when_meta_is_1(): void
    {
        Functions\when('get_post')->justReturn( $this->makePost( 10, 'post' ) );
        Functions\when('get_post_meta')->justReturn('1');
        Functions\when('wp_nonce_field')->justReturn('');
        Functions\when('esc_html')->returnArg();

        $result = $this->layout->hide_featured_image('', 10);

        $this->assertStringContainsString('checked', $result,
            'Checkbox must be marked checked when meta value is 1.');
        $this->assertStringContainsString('hide_featured_image', $result);
    }

    /**
     * When the post meta is empty (new post or explicitly unchecked) the
     * checkbox must be rendered without checked="checked".
     */
    public function test_checkbox_is_unchecked_when_meta_is_empty(): void
    {
        Functions\when('get_post')->justReturn( $this->makePost( 11, 'post' ) );
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('wp_nonce_field')->justReturn('');
        Functions\when('esc_html')->returnArg();

        $result = $this->layout->hide_featured_image('some existing content', 11);

        $this->assertStringNotContainsString('checked', $result,
            'Checkbox must not be checked when meta value is empty.');
        $this->assertStringContainsString('hide_featured_image', $result);
    }

    /**
     * Ineligible post types (e.g. proud_location) must not have the checkbox
     * appended — content must be returned unchanged.
     */
    public function test_checkbox_not_added_for_ineligible_post_type(): void
    {
        Functions\when('get_post')->justReturn( $this->makePost( 5, 'proud_location' ) );

        $result = $this->layout->hide_featured_image('original content', 5);

        $this->assertSame('original content', $result,
            'Content must be unchanged for post types that do not show the checkbox.');
    }

    /**
     * When get_post() returns null (e.g. called in an unexpected context),
     * content must be returned untouched.
     */
    public function test_checkbox_not_added_when_post_not_found(): void
    {
        Functions\when('get_post')->justReturn( null );

        $result = $this->layout->hide_featured_image('original content', 0);

        $this->assertSame('original content', $result,
            'Content must be unchanged when get_post() returns null.');
    }

    /**
     * The checkbox value attribute must be the fixed string "1", not the
     * current meta value. An empty meta on a new post previously produced
     * value="" which, while still captured by isset(), is non-standard.
     */
    public function test_checkbox_value_attribute_is_always_1(): void
    {
        Functions\when('get_post')->justReturn( $this->makePost( 20, 'post' ) );
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('wp_nonce_field')->justReturn('');
        Functions\when('esc_html')->returnArg();

        $result = $this->layout->hide_featured_image('', 20);

        $this->assertStringContainsString('value="1"', $result,
            'Checkbox value attribute must always be "1", not the meta value.');
    }
}

/**
 * Tests for ProudLayout::make_tables_responsive().
 *
 * Covers issue #2266: tables in post content are not responsive on mobile.
 * The fix wraps <table> elements in <div class="table-responsive"> (Bootstrap 3).
 */
class ProudLayoutTableTest extends TestCase
{
    private ProudLayout $layout;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->layout = new ProudLayout();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Content with no tables must be returned unchanged.
     */
    public function test_content_without_tables_is_unchanged(): void
    {
        $content = '<p>No tables here.</p>';

        $result = $this->layout->make_tables_responsive( $content );

        $this->assertSame( $content, $result );
    }

    /**
     * A plain <table> must be wrapped in <div class="table-responsive">.
     */
    public function test_plain_table_gets_wrapped(): void
    {
        $content = '<table><tr><td>Cell</td></tr></table>';

        $result = $this->layout->make_tables_responsive( $content );

        $this->assertSame(
            '<div class="table-responsive"><table><tr><td>Cell</td></tr></table></div>',
            $result
        );
    }

    /**
     * A table already wrapped in table-responsive must not be double-wrapped.
     */
    public function test_already_wrapped_table_is_not_double_wrapped(): void
    {
        $content = '<div class="table-responsive"><table><tr><td>Cell</td></tr></table></div>';

        $result = $this->layout->make_tables_responsive( $content );

        $this->assertSame( $content, $result );
    }

    /**
     * Multiple tables must all be individually wrapped.
     */
    public function test_multiple_tables_all_get_wrapped(): void
    {
        $content = '<table><tr><td>One</td></tr></table><table><tr><td>Two</td></tr></table>';

        $result = $this->layout->make_tables_responsive( $content );

        $this->assertSame(
            '<div class="table-responsive"><table><tr><td>One</td></tr></table></div>'
            . '<div class="table-responsive"><table><tr><td>Two</td></tr></table></div>',
            $result
        );
    }

    /**
     * HTML attributes on the <table> tag must be preserved after wrapping.
     */
    public function test_table_attributes_are_preserved(): void
    {
        $content = '<table class="data-table" id="mytable"><tr><td>Cell</td></tr></table>';

        $result = $this->layout->make_tables_responsive( $content );

        $this->assertSame(
            '<div class="table-responsive"><table class="data-table" id="mytable"><tr><td>Cell</td></tr></table></div>',
            $result
        );
    }
}
