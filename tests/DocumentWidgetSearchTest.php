<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for proud_document_search_callback() in document-widget-ajax.php.
 *
 * Covers: nonce gate, capability gate, input sanitization, and WP_Query shape.
 */
class DocumentWidgetSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Without a valid nonce, check_ajax_referer() should deny the request.
     * We simulate this by having check_ajax_referer throw so the callback
     * never reaches WP_Query.
     */
    public function test_search_returns_403_without_nonce(): void
    {
        Functions\when('check_ajax_referer')->alias(static function (): void {
            throw new \RuntimeException('nonce_failed');
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('nonce_failed');

        proud_document_search_callback();
    }

    /**
     * With a valid nonce but without the edit_posts capability, the callback
     * must send a JSON error with status 403 and not run WP_Query.
     */
    public function test_search_returns_403_without_capability(): void
    {
        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('wp_die')->justReturn(null);

        $capturedStatus = null;
        Functions\when('wp_send_json_error')->alias(static function ($data, $status = null) use (&$capturedStatus): void {
            $capturedStatus = $status;
        });

        proud_document_search_callback();

        $this->assertSame(403, $capturedStatus, 'Unauthorized response must use HTTP 403 status.');
    }

    /**
     * sanitize_text_field() must be called on the query before it reaches
     * WP_Query — raw GET input must never be passed directly.
     */
    public function test_search_sanitizes_query_input(): void
    {
        $_GET['q'] = '<script>xss</script>';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);

        $sanitized = false;
        Functions\when('sanitize_text_field')->alias(static function (string $input) use (&$sanitized): string {
            $sanitized = true;
            return strip_tags($input);
        });

        $mockQuery = new class {
            public array $capturedArgs = [];
            public array $posts = [];

            public function __construct() {}
        };

        Functions\when('wp_send_json')->justReturn(null);
        Functions\when('wp_die')->justReturn(null);

        // Patch WP_Query so it doesn't actually hit the DB.
        \Patchwork\redefine(
            'WP_Query::__construct',
            function (array $args) use ($mockQuery): void {
                $mockQuery->capturedArgs = $args;
            }
        );

        proud_document_search_callback();

        $this->assertTrue($sanitized, 'sanitize_text_field() must be called on the query input.');

        \Patchwork\restoreAll();
        unset($_GET['q']);
    }

    /**
     * Happy path: WP_Query receives post_type=document, posts_per_page=10,
     * and the sanitized search string, and the callback returns an array
     * payload with at least an 'items' key.
     */
    public function test_search_runs_wp_query_with_document_post_type(): void
    {
        $_GET['q'] = 'budget report';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('get_post_meta')->justReturn('');
        Functions\when('get_permalink')->justReturn('https://example.com/doc/1');
        Functions\when('get_admin_url')->justReturn('https://example.com/wp-admin/');
        Functions\when('esc_html')->returnArg();
        Functions\when('esc_url')->returnArg();
        Functions\when('esc_attr')->returnArg();
        Functions\when('wp_die')->justReturn(null);

        $capturedArgs    = [];
        $capturedPayload = [];

        \Patchwork\redefine(
            'WP_Query::__construct',
            function (array $args) use (&$capturedArgs): void {
                $capturedArgs = $args;
            }
        );

        Functions\when('wp_send_json')->alias(static function (array $data) use (&$capturedPayload): void {
            $capturedPayload = $data;
        });

        proud_document_search_callback();

        $this->assertSame('document', $capturedArgs['post_type'], 'WP_Query post_type must be "document".');
        $this->assertSame(10, $capturedArgs['posts_per_page'], 'WP_Query posts_per_page must be 10.');
        $this->assertSame('publish', $capturedArgs['post_status'], 'WP_Query post_status must be "publish".');
        $this->assertSame('budget report', $capturedArgs['s'], 'WP_Query search string must match sanitized input.');
        $this->assertArrayHasKey('items', $capturedPayload, 'Response payload must contain an "items" key.');

        \Patchwork\restoreAll();
        unset($_GET['q']);
    }
}
