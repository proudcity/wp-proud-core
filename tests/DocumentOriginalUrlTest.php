<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use function Proud\Core\proud_document_original_url;
use function Proud\Core\proud_html_preview_is_resolving_source;

/**
 * Tests for Proud\Core\proud_document_original_url() (issue #2917).
 *
 * The helper reads `document` post meta with render-time preview providers
 * stood down, so callers that need the real file -- a download link, or a
 * third-party viewer that fetches the URL itself -- do not receive an HTML
 * rendition of it.
 *
 * It signals that intent through the existing counter, not a raw $wpdb read,
 * so WP-Stateless (which hooks get_post_metadata at priority 2 and does not
 * check the flag) still rewrites the stored upload path to its bucket URL.
 */
class DocumentOriginalUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('absint')->alias(static fn ($value): int => abs((int) $value));

        $GLOBALS['proud_html_preview_resolving_source'] = 0;
    }

    protected function tearDown(): void
    {
        $GLOBALS['proud_html_preview_resolving_source'] = 0;

        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * The flag must be raised for the duration of the meta read. A provider
     * reading it during its get_post_metadata callback is how it knows to
     * stand down.
     */
    public function test_flag_is_raised_during_the_read(): void
    {
        $flagDuringRead = null;

        Functions\when('get_post_meta')->alias(static function () use (&$flagDuringRead) {
            $flagDuringRead = proud_html_preview_is_resolving_source();

            return 'https://example.test/uploads/budget.pdf';
        });

        $url = proud_document_original_url(42);

        $this->assertTrue($flagDuringRead, 'Providers must see the resolving-source flag while the meta is read.');
        $this->assertSame('https://example.test/uploads/budget.pdf', $url);
    }

    /**
     * Leaving the flag raised would silently disable provider replacement for
     * the rest of the request, so it has to come back down.
     */
    public function test_flag_is_lowered_after_the_read(): void
    {
        Functions\when('get_post_meta')->justReturn('https://example.test/uploads/budget.pdf');

        proud_document_original_url(42);

        $this->assertFalse(
            proud_html_preview_is_resolving_source(),
            'The flag must be lowered once the read completes.'
        );
        $this->assertSame(0, $GLOBALS['proud_html_preview_resolving_source']);
    }

    /**
     * A throwing get_post_meta() must not leak the flag. This is why the
     * helper uses try/finally rather than a bare decrement.
     */
    public function test_flag_is_lowered_when_the_read_throws(): void
    {
        Functions\when('get_post_meta')->alias(static function (): void {
            throw new \RuntimeException('meta read failed');
        });

        try {
            proud_document_original_url(42);
            $this->fail('The exception should propagate to the caller.');
        } catch (\RuntimeException $e) {
            $this->assertSame('meta read failed', $e->getMessage());
        }

        $this->assertSame(
            0,
            $GLOBALS['proud_html_preview_resolving_source'],
            'A throwing read must not leave the flag raised for the rest of the request.'
        );
    }

    /**
     * The flag is a counter, not a boolean. An outer caller that is already
     * resolving a source must still be resolving one when this returns.
     */
    public function test_nested_reads_do_not_lower_the_flag_early(): void
    {
        $flagAfterInnerRead = null;

        Functions\when('get_post_meta')->alias(static function () use (&$flagAfterInnerRead) {
            // Simulates proud_html_preview_current_source() calling in while
            // it is itself resolving a source.
            $flagAfterInnerRead = proud_html_preview_is_resolving_source();

            return 'https://example.test/uploads/budget.pdf';
        });

        $GLOBALS['proud_html_preview_resolving_source'] = 1;

        proud_document_original_url(42);

        $this->assertTrue($flagAfterInnerRead);
        $this->assertSame(
            1,
            $GLOBALS['proud_html_preview_resolving_source'],
            'The outer caller must still be marked as resolving a source.'
        );
    }

    /**
     * The template concatenates the result into a URL attribute, so a
     * non-string meta value must not reach it.
     */
    public function test_non_string_meta_returns_empty_string(): void
    {
        Functions\when('get_post_meta')->justReturn(false);

        $this->assertSame('', proud_document_original_url(42));

        Functions\when('get_post_meta')->justReturn(['https://example.test/a.pdf']);

        $this->assertSame('', proud_document_original_url(42));
    }

    /**
     * The read must go through get_post_meta(), not a direct $wpdb query --
     * that is what keeps the WP-Stateless bucket rewrite in play. Asserted by
     * checking the value a lower-priority filter would have produced comes
     * back, rather than the stored one.
     */
    public function test_read_goes_through_get_post_meta_so_other_filters_still_apply(): void
    {
        Functions\when('get_post_meta')->alias(static function ($post_id, $key, $single) {
            // Stands in for WP-Stateless rewriting the stored uploads path.
            return 'https://storage.googleapis.com/proudcity/site/uploads/2026/01/budget.pdf';
        });

        $this->assertSame(
            'https://storage.googleapis.com/proudcity/site/uploads/2026/01/budget.pdf',
            proud_document_original_url(42)
        );
    }

    /**
     * The helper is called with whatever the widget instance stored, which is
     * not guaranteed to be an integer.
     */
    public function test_post_id_is_cast_before_the_read(): void
    {
        $receivedPostId = null;

        Functions\when('get_post_meta')->alias(static function ($post_id, $key, $single) use (&$receivedPostId) {
            $receivedPostId = $post_id;

            return '';
        });

        proud_document_original_url('42');

        $this->assertSame(42, $receivedPostId, 'The post ID must be cast with absint() before the read.');
    }

    /**
     * Guards the meta key itself. Reading anything but `document` here would
     * hand the download link the wrong value.
     */
    public function test_reads_the_document_meta_key_as_a_single_value(): void
    {
        $receivedKey = null;
        $receivedSingle = null;

        Functions\when('get_post_meta')->alias(static function ($post_id, $key, $single) use (&$receivedKey, &$receivedSingle) {
            $receivedKey = $key;
            $receivedSingle = $single;

            return '';
        });

        proud_document_original_url(42);

        $this->assertSame('document', $receivedKey);
        $this->assertTrue($receivedSingle);
    }
}
