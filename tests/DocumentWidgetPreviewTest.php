<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for proud_document_preview_callback() in document-widget-ajax.php.
 *
 * Covers: nonce gate, capability gate, structured error for missing ID,
 * and HTML rendering for a valid document ID.
 */
class DocumentWidgetPreviewTest extends TestCase
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
     */
    public function test_preview_returns_403_without_nonce(): void
    {
        Functions\when('check_ajax_referer')->alias(static function (): void {
            throw new \RuntimeException('nonce_failed');
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('nonce_failed');

        proud_document_preview_callback();
    }

    /**
     * With a valid nonce but without the edit_posts capability, the callback
     * must send a JSON error with status 403.
     */
    public function test_preview_returns_403_without_capability(): void
    {
        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('wp_die')->justReturn(null);

        $capturedStatus = null;
        Functions\when('wp_send_json_error')->alias(static function ($data, $status = null) use (&$capturedStatus): void {
            $capturedStatus = $status;
        });

        proud_document_preview_callback();

        $this->assertSame(403, $capturedStatus, 'Unauthorized response must use HTTP 403 status.');
    }

    /**
     * When post_id is missing or 0, the callback must return a structured
     * error response (not an exception) so the JS can display a message.
     */
    public function test_preview_for_nonexistent_id_returns_structured_error(): void
    {
        $_GET['post_id'] = '0';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_die')->justReturn(null);

        $capturedPayload = null;
        Functions\when('wp_send_json_error')->alias(static function ($data) use (&$capturedPayload): void {
            $capturedPayload = $data;
        });

        proud_document_preview_callback();

        $this->assertNotNull($capturedPayload, 'wp_send_json_error must be called for an invalid post_id.');

        unset($_GET['post_id']);
    }

    /**
     * For a valid post_id the callback must return HTML containing the
     * document heading. The template is included via ob_start() so any
     * output it generates lands in the 'html' key of the JSON payload.
     */
    public function test_preview_renders_html_for_valid_document_id(): void
    {
        $_GET['post_id'] = '42';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('wp_die')->justReturn(null);

        // Stub document helpers so the template renders without a real DB.
        Functions\when('get_post_meta')->justReturn('https://example.com/budget.pdf');
        Functions\when('wp_get_post_terms')->justReturn([]);
        Functions\when('get_the_title')->justReturn('Budget Report 2024');
        Functions\when('get_permalink')->justReturn('https://example.com/doc/budget');
        Functions\when('esc_url')->returnArg();
        Functions\when('esc_attr')->returnArg();
        Functions\when('esc_html')->returnArg();
        Functions\when('wp_die')->justReturn(null);

        // Stub the Proud\Document namespace functions.
        Functions\when('Proud\Document\get_document_type')->justReturn('pdf');
        Functions\when('Proud\Document\get_document_icon')->justReturn('fa-file-pdf-o');

        $capturedPayload = [];
        Functions\when('wp_send_json')->alias(static function (array $data) use (&$capturedPayload): void {
            $capturedPayload = $data;
        });

        proud_document_preview_callback();

        $this->assertNotEmpty($capturedPayload, 'wp_send_json must be called for a valid post_id.');
        $this->assertArrayHasKey('html', $capturedPayload, 'Response must contain an "html" key.');
        $this->assertIsString($capturedPayload['html'], 'html value must be a string.');

        \Patchwork\restoreAll();
        unset($_GET['post_id']);
    }
}
