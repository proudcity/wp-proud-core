<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use function Proud\Core\proud_html_preview_artifact_key;
use function Proud\Core\proud_html_preview_local_path;
use function Proud\Core\proud_html_preview_queue_cleanup;
use function Proud\Core\proud_html_preview_record;
use function Proud\Core\proud_html_preview_resolve_request;
use function Proud\Core\proud_html_preview_run_cleanup;
use function Proud\Core\proud_html_preview_source_identity;
use function Proud\Core\proud_html_preview_trusted_artifact_url;
use function Proud\Core\proud_html_preview_url;

class HtmlPreviewTest extends TestCase
{
    private string $uploadsDir;
    private array $record;
    private string $attachedFile;
    private array $options;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->uploadsDir = sys_get_temp_dir() . '/proud-html-preview-' . uniqid();
        mkdir($this->uploadsDir, 0777, true);
        $this->attachedFile = 'agenda.pdf';
        $this->options = [
            \Proud\Core\PROUD_HTML_PREVIEW_PROVIDERS_OPTION => ['filetoweb' => true],
        ];
        $this->record = [
            'version' => 1,
            'provider' => 'filetoweb',
            'source_url' => 'https://example.test/uploads/agenda.pdf',
            'source_fingerprint' => 'fingerprint-123',
            'artifact_key' => 'filetoweb-integration/previews/44/fingerprint123/index.html',
            'artifact_url' => 'https://example.test/uploads/filetoweb-integration/previews/44/fingerprint123/index.html',
            'token' => 'token-123',
            'published_at' => '2026-07-20 12:00:00',
        ];

        Functions\when('absint')->alias(static fn ($value): int => abs((int) $value));
        Functions\when('sanitize_key')->alias(static fn ($value): string => strtolower(preg_replace('/[^a-z0-9_-]/', '', (string) $value)));
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_url_raw')->returnArg();
        Functions\when('wp_normalize_path')->alias(static fn ($value): string => str_replace('\\', '/', (string) $value));
        Functions\when('trailingslashit')->alias(static fn ($value): string => rtrim((string) $value, '/') . '/');
        Functions\when('untrailingslashit')->alias(static fn ($value): string => rtrim((string) $value, '/'));
        Functions\when('wp_upload_dir')->alias(function (): array {
            return [
                'basedir' => $this->uploadsDir,
                'baseurl' => 'https://example.test/uploads',
            ];
        });
        Functions\when('get_option')->alias(function ($name, $default = false) {
            return array_key_exists($name, $this->options) ? $this->options[$name] : $default;
        });
        Functions\when('update_option')->alias(function ($name, $value): bool {
            $this->options[$name] = $value;

            return true;
        });
        Functions\when('delete_option')->alias(function ($name): bool {
            unset($this->options[$name]);

            return true;
        });
        Functions\when('wp_next_scheduled')->justReturn(false);
        Functions\when('wp_schedule_single_event')->justReturn(true);
        Functions\when('get_post_type')->alias(static fn ($postId): string => 44 === (int) $postId ? 'attachment' : '');
        Functions\when('get_post_meta')->alias(function ($postId, $key) {
            if (44 !== (int) $postId) {
                return '';
            }

            if (\Proud\Core\PROUD_HTML_PREVIEW_META === $key) {
                return $this->record;
            }

            return '_wp_attached_file' === $key ? $this->attachedFile : '';
        });
        Functions\when('get_attached_file')->justReturn('');
        Functions\when('wp_get_attachment_url')->alias(function ($postId): string {
            return 44 === (int) $postId ? 'https://example.test/uploads/' . $this->attachedFile : '';
        });
        Functions\when('apply_filters')->alias(static fn ($tag, $value) => $value);
        Functions\when('home_url')->alias(static fn ($path = ''): string => 'https://example.test' . $path);
        Functions\when('add_query_arg')->alias(static function ($args, $url): string {
            return $url . '?' . http_build_query($args);
        });
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->uploadsDir);
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_url_and_ready_request_are_provider_neutral(): void
    {
        $path = proud_html_preview_local_path($this->record['artifact_key']);
        mkdir(dirname($path), 0777, true);
        file_put_contents($path, '<html><body>Agenda</body></html>');

        $url = proud_html_preview_url(44, $this->record['source_url']);
        $this->assertStringContainsString('proud_html_preview=44', $url);
        $this->assertStringContainsString('preview_token=token-123', $url);

        $result = proud_html_preview_resolve_request(44, 'token-123', proud_html_preview_source_identity($this->record));
        $this->assertSame('ready', $result['status']);
        $this->assertSame($path, $result['path']);
    }

    public function test_invalid_token_provider_and_source_are_rejected(): void
    {
        $this->assertSame('not_found', proud_html_preview_resolve_request(44, 'wrong', proud_html_preview_source_identity($this->record))['status']);
        $this->assertSame('', proud_html_preview_url(44, 'https://example.test/uploads/other.pdf'));

        Functions\when('get_option')->justReturn(['filetoweb' => false]);
        $this->assertNull(proud_html_preview_record(44));
    }

    public function test_old_endpoint_url_falls_back_after_attachment_changes(): void
    {
        $this->attachedFile = 'replacement.pdf';

        $result = proud_html_preview_resolve_request(44, 'token-123', proud_html_preview_source_identity($this->record));

        $this->assertSame('redirect', $result['status']);
        $this->assertSame('https://example.test/uploads/replacement.pdf', $result['url']);
    }

    public function test_path_traversal_and_untrusted_storage_are_rejected(): void
    {
        $this->assertSame('', proud_html_preview_artifact_key('../private/index.html'));
        $this->assertFalse(proud_html_preview_trusted_artifact_url(
            'https://evil.test/uploads/filetoweb-integration/previews/44/fingerprint123/index.html',
            $this->record['artifact_key']
        ));
        $this->assertFalse(proud_html_preview_trusted_artifact_url(
            'https://example.test/uploads/other/index.html',
            $this->record['artifact_key']
        ));
        $this->assertFalse(proud_html_preview_trusted_artifact_url(
            'https://example.test/other/filetoweb-integration/previews/44/fingerprint123/index.html',
            $this->record['artifact_key']
        ));
        $this->assertFalse(proud_html_preview_trusted_artifact_url(
            'https://storage.googleapis.com/evil-bucket/filetoweb-integration/previews/44/fingerprint123/index.html',
            $this->record['artifact_key']
        ));
    }

    public function test_explicit_storage_base_url_is_trusted(): void
    {
        Functions\when('apply_filters')->alias(static function ($tag, $value) {
            if ('proud_html_preview_trusted_storage_base_urls' === $tag) {
                return ['https://storage.googleapis.com/proudcity-uploads'];
            }

            return $value;
        });

        $this->assertTrue(proud_html_preview_trusted_artifact_url(
            'https://storage.googleapis.com/proudcity-uploads/filetoweb-integration/previews/44/fingerprint123/index.html',
            $this->record['artifact_key']
        ));
    }

    public function test_missing_artifact_restores_from_trusted_storage(): void
    {
        Functions\when('wp_safe_remote_get')->justReturn(['body' => '<html><body>Restored</body></html>']);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->alias(static fn ($response): string => $response['body']);
        Functions\when('wp_mkdir_p')->alias(static fn ($dir): bool => is_dir($dir) || mkdir($dir, 0777, true));

        $result = proud_html_preview_resolve_request(44, 'token-123', proud_html_preview_source_identity($this->record));
        $this->assertSame('ready', $result['status']);
        $this->assertStringContainsString('Restored', file_get_contents($result['path']));
    }

    public function test_missing_artifact_falls_back_to_original_pdf(): void
    {
        Functions\when('wp_safe_remote_get')->justReturn(['body' => '']);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(404);
        Functions\when('wp_remote_retrieve_body')->justReturn('');

        $result = proud_html_preview_resolve_request(44, 'token-123', proud_html_preview_source_identity($this->record));
        $this->assertSame('redirect', $result['status']);
        $this->assertSame($this->record['source_url'], $result['url']);
    }

    public function test_remote_cleanup_is_verified_before_queue_removal(): void
    {
        $deleted = [];
        Functions\when('has_action')->justReturn(1);
        Functions\when('do_action')->alias(static function ($hook, $name) use (&$deleted): void {
            if ('sm:sync::deleteFile' === $hook) {
                $deleted[] = $name;
            }
        });
        Functions\when('wp_safe_remote_head')->justReturn(['code' => 404]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->alias(static fn ($response): int => $response['code']);

        $this->assertTrue(proud_html_preview_queue_cleanup(
            'filetoweb',
            $this->record['artifact_key'],
            $this->record['artifact_url']
        ));
        $this->assertCount(1, $this->options[\Proud\Core\PROUD_HTML_PREVIEW_CLEANUP_OPTION]);

        proud_html_preview_run_cleanup();

        $this->assertSame([$this->record['artifact_key']], $deleted);
        $this->assertArrayNotHasKey(\Proud\Core\PROUD_HTML_PREVIEW_CLEANUP_OPTION, $this->options);
        $this->assertFalse(proud_html_preview_queue_cleanup(
            'filetoweb',
            $this->record['artifact_key'],
            'https://evil.test/uploads/' . $this->record['artifact_key']
        ));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob(rtrim($dir, '/') . '/*') ?: [] as $path) {
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
