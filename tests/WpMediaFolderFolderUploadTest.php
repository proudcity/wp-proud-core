<?php

/**
 * Covers the WP Media Folder "Upload folder" kill switch in
 * plugin_override/wp-media-folder/proud-wp-media-folder.php.
 *
 * Issue #2887: WPMF's chunked folder uploader bypasses wp_handle_upload() and
 * only calls wp_update_attachment_metadata() for image/video/audio, so
 * documents never reach WP Stateless and are lost when the pod restarts.
 *
 * The security property under test is *termination* — the guard must stop the
 * request before WPMF's own handler runs. A test that only checks the response
 * body would pass against an implementation that returns and lets WPMF write
 * files anyway, so the wp_send_json() stub throws and the tests assert on that.
 */

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

class WpMediaFolderFolderUploadTest extends TestCase
{
    /** Marker thrown by the wp_send_json() stub to represent wp_die(). */
    private const TERMINATED = 'wp_send_json terminated the request';

    /** Scratch file error_log() is pointed at so the guard's log line stays out of test output. */
    private string $logFile;

    /** @var string|false */
    private $previousLog;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $this->logFile = (string) tempnam(sys_get_temp_dir(), 'proud-wpmf-log-');
        $this->previousLog = ini_set('error_log', $this->logFile);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', false === $this->previousLog ? '' : $this->previousLog);

        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }

        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Stub wp_send_json() so it records its arguments and then terminates, the
     * way the real function does via wp_die().
     */
    private function captureJsonResponse(?array &$sent): void
    {
        Functions\when('wp_send_json')->alias(function ($data, $status_code = null) use (&$sent) {
            $sent = ['data' => $data, 'status' => $status_code];

            throw new RuntimeException(self::TERMINATED);
        });
    }

    /**
     * The AJAX guard must answer in WPMF's own response shape. WPMF discards the
     * message client-side, but the shape is what its uploader parses.
     */
    public function testBlockFolderUploadRespondsInWpmfErrorShape(): void
    {
        $sent = null;
        $this->captureJsonResponse($sent);
        Functions\when('get_current_user_id')->justReturn(7);

        try {
            proudcity_wpmf_block_folder_upload();
        } catch (RuntimeException $e) {
            // Expected: the guard terminated. Asserted separately below.
        }

        $this->assertIsArray($sent, 'The guard must send a JSON response.');
        $this->assertArrayHasKey('status', $sent['data']);
        $this->assertFalse($sent['data']['status'], 'WPMF treats status=false as an upload failure.');
        $this->assertArrayHasKey('msg', $sent['data']);
        $this->assertNotSame('', trim((string) $sent['data']['msg']), 'The failure needs an operator-readable reason.');
        $this->assertSame(403, $sent['status'], 'The request is refused, not merely unsuccessful.');
    }

    /**
     * The whole point of the guard is that WPMF's handler never runs. If it
     * returns instead of terminating, the priority-10 callback executes and
     * files get written.
     */
    public function testBlockFolderUploadTerminatesTheRequest(): void
    {
        $sent = null;
        $this->captureJsonResponse($sent);
        Functions\when('get_current_user_id')->justReturn(7);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(self::TERMINATED);

        proudcity_wpmf_block_folder_upload();
    }

    /**
     * The refusal is logged because WPMF's fileError handler is empty
     * (assets/js/script.js:1668) and its complete handler shows a success
     * snackbar regardless — without a server-side record the block is invisible.
     */
    public function testBlockFolderUploadIsRecordedServerSide(): void
    {
        $sent = null;
        $this->captureJsonResponse($sent);
        Functions\when('get_current_user_id')->justReturn(42);

        try {
            proudcity_wpmf_block_folder_upload();
        } catch (RuntimeException $e) {
            // Expected.
        }

        $contents = (string) file_get_contents($this->logFile);

        $this->assertStringContainsString('WP Media Folder', $contents);
        $this->assertStringContainsString('42', $contents, 'The acting user must be identifiable.');
    }

    /**
     * Registration is asserted directly. Previously this only checked that the
     * priority constant was below 10, which passed even with the add_action()
     * call deleted outright.
     */
    public function testRegistrationHooksBothGuards(): void
    {
        $hooks = [];

        Functions\when('add_action')->alias(function ($hook, $callback, $priority = 10, $args = 1) use (&$hooks) {
            $hooks[] = ['hook' => $hook, 'callback' => $callback, 'priority' => $priority];

            return true;
        });

        proudcity_wpmf_register_folder_upload_block();

        $direct = array_values(array_filter($hooks, static fn (array $h): bool => 'wp_ajax_wpmf_upload_folder' === $h['hook']));
        $this->assertCount(1, $direct, 'The AJAX action must be hooked exactly once at registration.');
        $this->assertSame('proudcity_wpmf_block_folder_upload', $direct[0]['callback']);
        $this->assertLessThan(
            10,
            $direct[0]['priority'],
            'The guard must outrank WpmfMediaFolder::uploadFolder() at priority 10.'
        );

        $seize = array_values(array_filter($hooks, static fn (array $h): bool => 'admin_init' === $h['hook']));
        $this->assertCount(1, $seize, 'The priority-independent fallback must be registered on admin_init.');
        $this->assertSame('proudcity_wpmf_seize_folder_upload_action', $seize[0]['callback']);
        $this->assertSame(
            PHP_INT_MAX,
            $seize[0]['priority'],
            'The fallback must run after every other admin_init callback has registered its hooks.'
        );
    }

    /**
     * The fallback must clear whatever WPMF registered and then put our guard
     * back — admin-ajax.php wp_die()s with a 400 if the action has no handler
     * at all (wp-admin/admin-ajax.php:180).
     */
    public function testSeizeClearsTheActionThenRestoresTheGuard(): void
    {
        $calls = [];

        Functions\when('remove_all_actions')->alias(function ($hook) use (&$calls) {
            $calls[] = ['remove_all_actions', $hook];

            return true;
        });
        Functions\when('add_action')->alias(function ($hook, $callback, $priority = 10) use (&$calls) {
            $calls[] = ['add_action', $hook, $callback];

            return true;
        });

        proudcity_wpmf_seize_folder_upload_action();

        $this->assertSame(['remove_all_actions', 'wp_ajax_wpmf_upload_folder'], $calls[0]);
        $this->assertSame(['add_action', 'wp_ajax_wpmf_upload_folder', 'proudcity_wpmf_block_folder_upload'], $calls[1]);
        $this->assertCount(2, $calls, 'Clearing the hook without re-adding would turn the refusal into a 400.');
    }

    /**
     * The button is injected by WPMF's JavaScript after page load, so the only
     * reliable way to remove it from the admin is CSS.
     */
    public function testHideFolderUploadButtonEmitsCssForTheButtonSelector(): void
    {
        ob_start();
        proudcity_wpmf_hide_folder_upload_button();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('<style', $output);
        $this->assertStringContainsString('.wpmf_btn_upload_folder', $output);
        $this->assertMatchesRegularExpression(
            '/display\s*:\s*none/i',
            $output,
            'The button must be hidden, not merely restyled.'
        );
    }
}
