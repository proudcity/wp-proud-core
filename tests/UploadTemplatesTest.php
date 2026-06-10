<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the file-upload.php and image-upload.php form templates.
 *
 * Verifies that user-controlled $url is escaped on output (esc_url for
 * href/src, esc_html for visible link text).
 */
class UploadTemplatesTest extends TestCase
{
    private const FILE_TEMPLATE  = __DIR__ . '/../modules/proud-form/templates/file-upload.php';
    private const IMAGE_TEMPLATE = __DIR__ . '/../modules/proud-form/templates/image-upload.php';

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Real-ish escape functions so the test can detect missing escaping.
        Functions\when('esc_url')->alias(static function ($url) {
            return htmlspecialchars(strip_tags((string) $url), ENT_QUOTES, 'UTF-8');
        });
        Functions\when('esc_html')->alias(static function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        });
        Functions\when('esc_attr')->alias(static function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        });
        Functions\when('__')->returnArg();
        Functions\when('do_action')->justReturn(null);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Render a template with the supplied variables and return its output.
     */
    private function render(string $template, array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        include $template;
        return (string) ob_get_clean();
    }

    public function test_file_upload_escapes_url_attribute(): void
    {
        $output = $this->render(self::FILE_TEMPLATE, [
            'media_id'  => 42,
            'url'       => "https://example.com/x.pdf' onerror='alert(1)",
            'translate' => 'wp-proud-core',
            'field'     => [],
        ]);

        $this->assertStringNotContainsString(
            "onerror='alert(1)",
            $output,
            'Raw $url must not break out of the href attribute.'
        );
        $this->assertStringContainsString('https://example.com/x.pdf', $output);
    }

    public function test_file_upload_escapes_basename_text(): void
    {
        $output = $this->render(self::FILE_TEMPLATE, [
            'media_id'  => 42,
            'url'       => 'https://example.com/<script>alert(1)</script>.pdf',
            'translate' => 'wp-proud-core',
            'field'     => [],
        ]);

        $this->assertStringNotContainsString(
            '<script>alert(1)</script>',
            $output,
            'basename($url) must be escaped before being printed as link text.'
        );
    }

    public function test_file_upload_renders_no_link_when_url_empty(): void
    {
        $output = $this->render(self::FILE_TEMPLATE, [
            'media_id'  => 0,
            'url'       => '',
            'translate' => 'wp-proud-core',
            'field'     => [],
        ]);

        $this->assertStringNotContainsString('<a href', $output);
    }

    public function test_image_upload_escapes_src_attribute(): void
    {
        $output = $this->render(self::IMAGE_TEMPLATE, [
            'media_id'  => 42,
            'url'       => 'https://example.com/x.jpg" onerror="alert(1)',
            'translate' => 'wp-proud-core',
            'field'     => [],
        ]);

        $this->assertStringNotContainsString(
            'onerror="alert(1)',
            $output,
            'Raw $url must not break out of the src attribute.'
        );
        $this->assertStringContainsString('https://example.com/x.jpg', $output);
    }

    public function test_file_upload_fires_action_hook(): void
    {
        $captured = [];
        Functions\when('do_action')->alias(static function (...$args) use (&$captured) {
            $captured[] = $args;
        });

        $field = ['#name' => 'agenda_file', '#id' => 'agenda_file'];
        $this->render(self::FILE_TEMPLATE, [
            'media_id'  => 7,
            'url'       => 'https://example.com/a.pdf',
            'translate' => 'wp-proud-core',
            'field'     => $field,
        ]);

        $this->assertCount(1, $captured);
        $this->assertSame('proud_form_after_file_upload', $captured[0][0]);
        $this->assertSame(7, $captured[0][1]);
        $this->assertSame('https://example.com/a.pdf', $captured[0][2]);
        $this->assertSame($field, $captured[0][3]);
    }

    public function test_image_upload_fires_action_hook(): void
    {
        $captured = [];
        Functions\when('do_action')->alias(static function (...$args) use (&$captured) {
            $captured[] = $args;
        });

        $field = ['#name' => 'agenda_image', '#id' => 'agenda_image'];
        $this->render(self::IMAGE_TEMPLATE, [
            'media_id'  => 9,
            'url'       => 'https://example.com/a.jpg',
            'translate' => 'wp-proud-core',
            'field'     => $field,
        ]);

        $this->assertCount(1, $captured);
        $this->assertSame('proud_form_after_image_upload', $captured[0][0]);
        $this->assertSame(9, $captured[0][1]);
        $this->assertSame('https://example.com/a.jpg', $captured[0][2]);
        $this->assertSame($field, $captured[0][3]);
    }
}
