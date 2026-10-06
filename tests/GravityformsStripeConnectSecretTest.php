<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Proud\GravityformsStripe\ProudGravityformsStripe;

/**
 * Tests for the interim Stripe Connect secret swap in
 * plugin_override/gravityformsstripe/proud-gravityformsstripe.php (issue #2951).
 *
 * GF Stripe 7.0's "Connect with Stripe" OAuth token (live_auth_token) makes
 * our platform a connected account of GF's own Stripe app, so Stripe rejects
 * our destination charges. use_platform_secret_for_connect() swaps the
 * stored live_secret_key for our own platform secret when GF reads its
 * settings; restore_stored_secret() puts the original back before the
 * settings are ever written to the DB.
 */
class GravityformsStripeConnectSecretTest extends TestCase
{
    private $options;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        putenv('PROUDCITY_PAYMENTS_SECRET');

        $this->options = [];

        Functions\when('get_option')->alias(function ($name, $default = false) {
            return array_key_exists($name, $this->options) ? $this->options[$name] : $default;
        });

        // Default to "not an AJAX request"; tests for the AJAX paths override this.
        Functions\when('wp_doing_ajax')->justReturn(false);

        // Only reached when wp_doing_ajax() is true, to read $_REQUEST['action'].
        Functions\when('wp_unslash')->alias(function ($value) {
            return is_array($value) ? array_map('stripslashes', $value) : stripslashes((string) $value);
        });
        Functions\when('sanitize_key')->alias(function ($value) {
            return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value));
        });

        $this->resetStash();
    }

    protected function tearDown(): void
    {
        putenv('PROUDCITY_PAYMENTS_SECRET');
        unset($_REQUEST['action']);
        $this->resetStash();
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * use_platform_secret_for_connect() and restore_stored_secret() stash the
     * original live_secret_key in private static state so each test must
     * start from a clean slate.
     */
    private function resetStash(): void
    {
        $ref = new ReflectionClass(ProudGravityformsStripe::class);

        $stashed = $ref->getProperty('stashed_live_secret_key');
        $stashed->setAccessible(true);
        $stashed->setValue(null, null);

        $active = $ref->getProperty('stash_active');
        $active->setAccessible(true);
        $active->setValue(null, false);
    }

    private function connectSettings(array $overrides = []): array
    {
        return array_merge([
            'live_secret_key'   => 'sk_live_GFOAUTHTOKEN',
            'live_auth_token'   => ['acct_1GfConnect', 'refresh_token_abc', '2026-10-06'],
            'test_secret_key'   => 'sk_test_UNTOUCHED',
            'sandbox_secret_key' => 'sk_test_SANDBOXUNTOUCHED',
            'some_other_setting' => 'untouched',
        ], $overrides);
    }

    public function test_connect_with_env_secret_on_front_end_swaps_secret(): void
    {
        putenv('PROUDCITY_PAYMENTS_SECRET=sk_live_PLATFORMTEST');
        Functions\when('is_admin')->justReturn(false);

        $settings = $this->connectSettings();
        $result   = ProudGravityformsStripe::use_platform_secret_for_connect($settings);

        $this->assertSame('sk_live_PLATFORMTEST', $result['live_secret_key']);
        $this->assertSame($settings['live_auth_token'], $result['live_auth_token']);
        $this->assertSame($settings['test_secret_key'], $result['test_secret_key']);
        $this->assertSame($settings['sandbox_secret_key'], $result['sandbox_secret_key']);
        $this->assertSame($settings['some_other_setting'], $result['some_other_setting']);
    }

    public function test_connect_in_admin_ajax_swaps_secret(): void
    {
        putenv('PROUDCITY_PAYMENTS_SECRET=sk_live_PLATFORMTEST');
        Functions\when('is_admin')->justReturn(true);
        Functions\when('wp_doing_ajax')->justReturn(true);

        $result = ProudGravityformsStripe::use_platform_secret_for_connect($this->connectSettings());

        $this->assertSame('sk_live_PLATFORMTEST', $result['live_secret_key']);
    }

    public function test_connect_in_admin_non_ajax_is_unchanged(): void
    {
        putenv('PROUDCITY_PAYMENTS_SECRET=sk_live_PLATFORMTEST');
        Functions\when('is_admin')->justReturn(true);
        Functions\when('wp_doing_ajax')->justReturn(false);

        $settings = $this->connectSettings();
        $result   = ProudGravityformsStripe::use_platform_secret_for_connect($settings);

        $this->assertSame($settings, $result);
    }

    public function test_no_live_auth_token_is_unchanged(): void
    {
        $settings = $this->connectSettings(['live_auth_token' => []]);
        $result   = ProudGravityformsStripe::use_platform_secret_for_connect($settings);

        $this->assertSame($settings, $result);
    }

    public function test_empty_env_and_no_option_is_unchanged(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $settings = $this->connectSettings();
        $result   = ProudGravityformsStripe::use_platform_secret_for_connect($settings);

        $this->assertSame($settings, $result);
    }

    public function test_option_secret_takes_precedence_over_env(): void
    {
        $this->options['proudcity_payments_secret'] = 'sk_live_OPTIONSECRET';
        putenv('PROUDCITY_PAYMENTS_SECRET=sk_live_ENVSECRET');
        Functions\when('is_admin')->justReturn(false);

        $result = ProudGravityformsStripe::use_platform_secret_for_connect($this->connectSettings());

        $this->assertSame('sk_live_OPTIONSECRET', $result['live_secret_key']);
    }

    public function test_non_live_secret_is_not_swapped(): void
    {
        putenv('PROUDCITY_PAYMENTS_SECRET=sk_test_NOTLIVE');
        Functions\when('is_admin')->justReturn(false);

        $settings = $this->connectSettings();
        $result   = ProudGravityformsStripe::use_platform_secret_for_connect($settings);

        $this->assertSame($settings, $result);
    }

    public function test_test_and_sandbox_secret_keys_are_never_touched(): void
    {
        putenv('PROUDCITY_PAYMENTS_SECRET=sk_live_PLATFORMTEST');
        Functions\when('is_admin')->justReturn(false);

        $settings = $this->connectSettings();
        $result   = ProudGravityformsStripe::use_platform_secret_for_connect($settings);

        $this->assertSame('sk_test_UNTOUCHED', $result['test_secret_key']);
        $this->assertSame('sk_test_SANDBOXUNTOUCHED', $result['sandbox_secret_key']);
    }

    public function test_non_array_input_is_returned_as_is(): void
    {
        $this->assertFalse(ProudGravityformsStripe::use_platform_secret_for_connect(false));

        $json = '{"live_secret_key":"sk_live_GFOAUTHTOKEN"}';
        $this->assertSame($json, ProudGravityformsStripe::use_platform_secret_for_connect($json));
    }

    public function test_restore_puts_original_back_after_a_swap(): void
    {
        putenv('PROUDCITY_PAYMENTS_SECRET=sk_live_PLATFORMTEST');
        Functions\when('is_admin')->justReturn(false);

        $swapped = ProudGravityformsStripe::use_platform_secret_for_connect($this->connectSettings());

        $restored = ProudGravityformsStripe::restore_stored_secret($swapped, []);

        $this->assertSame('sk_live_GFOAUTHTOKEN', $restored['live_secret_key']);
        $this->assertSame($swapped['live_auth_token'], $restored['live_auth_token']);
    }

    public function test_restore_leaves_a_newly_entered_key_alone(): void
    {
        putenv('PROUDCITY_PAYMENTS_SECRET=sk_live_PLATFORMTEST');
        Functions\when('is_admin')->justReturn(false);

        ProudGravityformsStripe::use_platform_secret_for_connect($this->connectSettings());

        $adminEntered = $this->connectSettings(['live_secret_key' => 'sk_live_NEWLYENTERED']);

        $result = ProudGravityformsStripe::restore_stored_secret($adminEntered, []);

        $this->assertSame($adminEntered, $result);
    }

    public function test_restore_without_a_prior_swap_is_unchanged(): void
    {
        $value = $this->connectSettings(['live_secret_key' => 'sk_live_PLATFORMTEST']);

        $result = ProudGravityformsStripe::restore_stored_secret($value, []);

        $this->assertSame($value, $result);
    }

    /**
     * ajax_configure_webhooks() (wp_ajax_gfstripe_configure_webhooks) lists,
     * deletes, and recreates this site's Stripe webhook endpoint, overwriting
     * live_signing_secret at GF's own API version. Signing that request with
     * the platform key would undo our webhook consolidation (#2339/#2849).
     */
    public function test_configure_webhooks_ajax_action_is_not_swapped(): void
    {
        putenv('PROUDCITY_PAYMENTS_SECRET=sk_live_PLATFORMTEST');
        Functions\when('is_admin')->justReturn(true);
        Functions\when('wp_doing_ajax')->justReturn(true);
        $_REQUEST['action'] = 'gfstripe_configure_webhooks';

        $settings = $this->connectSettings();
        $result   = ProudGravityformsStripe::use_platform_secret_for_connect($settings);

        $this->assertSame($settings, $result);
    }

    public function test_a_different_admin_ajax_action_is_still_swapped(): void
    {
        putenv('PROUDCITY_PAYMENTS_SECRET=sk_live_PLATFORMTEST');
        Functions\when('is_admin')->justReturn(true);
        Functions\when('wp_doing_ajax')->justReturn(true);
        $_REQUEST['action'] = 'gfstripe_create_payment_intent';

        $result = ProudGravityformsStripe::use_platform_secret_for_connect($this->connectSettings());

        $this->assertSame('sk_live_PLATFORMTEST', $result['live_secret_key']);
    }

    /**
     * GF's deauthorize flow (gfstripe_deauthorize) clears live_secret_key
     * entirely. restore_stored_secret() must not reinject the stashed
     * original in that case -- the key is meant to stay gone.
     */
    public function test_restore_does_not_reinject_key_unset_by_deauthorize(): void
    {
        putenv('PROUDCITY_PAYMENTS_SECRET=sk_live_PLATFORMTEST');
        Functions\when('is_admin')->justReturn(false);

        $swapped = ProudGravityformsStripe::use_platform_secret_for_connect($this->connectSettings());
        unset($swapped['live_secret_key']);

        $result = ProudGravityformsStripe::restore_stored_secret($swapped, []);

        $this->assertArrayNotHasKey('live_secret_key', $result);
        $this->assertSame($swapped, $result);
    }

    /**
     * If the stored settings had no live_secret_key at all at swap time, the
     * stashed "original" is null. restoring sets live_secret_key back to
     * null rather than leaving the platform secret in place -- documenting
     * that behaviour as acceptable, since null is what was actually there.
     */
    public function test_restore_with_a_null_stashed_original_sets_key_to_null(): void
    {
        putenv('PROUDCITY_PAYMENTS_SECRET=sk_live_PLATFORMTEST');
        Functions\when('is_admin')->justReturn(false);

        $settings = $this->connectSettings();
        unset($settings['live_secret_key']);

        $swapped = ProudGravityformsStripe::use_platform_secret_for_connect($settings);
        $this->assertSame('sk_live_PLATFORMTEST', $swapped['live_secret_key']);

        $result = ProudGravityformsStripe::restore_stored_secret($swapped, []);

        $this->assertArrayHasKey('live_secret_key', $result);
        $this->assertNull($result['live_secret_key']);
    }
}
