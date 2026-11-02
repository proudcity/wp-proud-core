<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Proud\GravityformsStripe\ProudGravityformsStripe;

/**
 * Tests for the separate-charges-and-transfers (SC&T) support added for
 * issue #2955, in plugin_override/gravityformsstripe/proud-gravityformsstripe.php.
 *
 * Reuses the GFAPI / gf_stripe() stub pattern from GravityformsStripeOnBehalfOfTest.php
 * (GfStripeOnBehalfOfStub, GFAPI) -- both are class_exists()-guarded there, so this file
 * configures them through their public statics rather than redefining them.
 */
class GravityformsStripeTransfersTest extends TestCase
{
    private array $options;
    private string $siteUrl;
    private array $entryMeta;
    private array $transients;
    private array $notes;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        putenv('PROUDCITY_PAYMENTS_CHARGE_MODEL');
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY');
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY_TEST');
        putenv('PROUDCITY_PAYMENTS_PERCENT');

        $this->options = [
            'proudcity_payments_account' => 'acct_city123',
        ];
        $this->siteUrl = 'https://example.gov';
        $this->entryMeta = [];
        $this->transients = [];
        $this->notes = [];

        GfStripeOnBehalfOfStub::$paymentElementEnabled = true;
        GfStripeOnBehalfOfStub::$feedConnectEnabled = false;
        GfStripeOnBehalfOfStub::$apiMode = 'live';
        GfStripeOnBehalfOfStub::$pluginSettings = [];
        GfStripeOnBehalfOfStub::$paymentFeed = null;
        GfStripeOnBehalfOfStub::$notes = [];
        GFAPI::$entries = [];

        Functions\when('get_option')->alias(function ($name, $default = false) {
            return array_key_exists($name, $this->options) ? $this->options[$name] : $default;
        });
        Functions\when('update_option')->justReturn(true);
        Functions\when('site_url')->alias(function () {
            return $this->siteUrl;
        });
        Functions\when('home_url')->justReturn('https://example.gov');
        Functions\when('get_current_blog_id')->justReturn(1);
        Functions\when('get_bloginfo')->justReturn('Example');
        Functions\when('do_action')->justReturn(null);
        Functions\when('apply_filters')->alias(static function ($tag, $value) {
            return $value;
        });
        Functions\when('absint')->alias(static fn ($value): int => abs((int) $value));
        Functions\when('wp_json_encode')->alias('json_encode');

        Functions\when('gform_get_meta')->alias(function ($entry_id, $key) {
            return $this->entryMeta[$entry_id][$key] ?? null;
        });
        Functions\when('gform_update_meta')->alias(function ($entry_id, $key, $value, $form_id = null) {
            $this->entryMeta[$entry_id][$key] = $value;
            return true;
        });

        Functions\when('get_transient')->alias(function ($key) {
            return $this->transients[$key] ?? false;
        });
        Functions\when('set_transient')->alias(function ($key, $value, $expiration = 0) {
            $this->transients[$key] = $value;
            return true;
        });

        Functions\when('error_log')->justReturn(true);

        Functions\when('wp_remote_retrieve_response_code')->alias(static fn ($response) => $response['code'] ?? 200);
        Functions\when('wp_remote_retrieve_body')->alias(static fn ($response) => $response['body'] ?? '');
    }

    protected function tearDown(): void
    {
        putenv('PROUDCITY_PAYMENTS_CHARGE_MODEL');
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY');
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY_TEST');
        putenv('PROUDCITY_PAYMENTS_PERCENT');
        Monkey\tearDown();
        parent::tearDown();
    }

    private function jsonResponse(int $code, array $body): array
    {
        return ['code' => $code, 'body' => json_encode($body)];
    }

    private function samplePi(array $metadataOverrides = [], array $chargeOverrides = []): array
    {
        return [
            'id' => 'pi_123',
            'metadata' => array_merge([
                'gf_entry_id' => '42',
                'proudcity_charge_model' => 'transfers',
                'proudcity_transfer_account' => 'acct_city123',
                'proudcity_fee' => '89',
                'proudcity_fee_percent' => '3.05',
                'proudcity_site' => '1:example.gov',
                'proudcity_form_id' => '7',
            ], $metadataOverrides),
            'latest_charge' => array_merge([
                'id' => 'ch_123',
                'status' => 'succeeded',
                'captured' => true,
                'amount' => 2000,
                'amount_captured' => 2000,
                'currency' => 'usd',
                'transfer_group' => 'Test Form',
                'refunded' => false,
            ], $chargeOverrides),
        ];
    }

    // -------------------------------------------------------------------
    // Config
    // -------------------------------------------------------------------

    public function test_get_charge_model_defaults_to_destination(): void
    {
        $this->assertSame('destination', ProudGravityformsStripe::get_charge_model());
    }

    public function test_get_charge_model_env_transfers(): void
    {
        putenv('PROUDCITY_PAYMENTS_CHARGE_MODEL=transfers');
        $this->assertSame('transfers', ProudGravityformsStripe::get_charge_model());
    }

    public function test_get_charge_model_option_overrides_env_both_ways(): void
    {
        putenv('PROUDCITY_PAYMENTS_CHARGE_MODEL=transfers');
        $this->options['proudcity_payments_charge_model'] = 'destination';
        $this->assertSame('destination', ProudGravityformsStripe::get_charge_model());

        $this->options['proudcity_payments_charge_model'] = 'transfers';
        putenv('PROUDCITY_PAYMENTS_CHARGE_MODEL=destination');
        $this->assertSame('transfers', ProudGravityformsStripe::get_charge_model());
    }

    public function test_get_charge_model_garbage_option_gives_destination(): void
    {
        putenv('PROUDCITY_PAYMENTS_CHARGE_MODEL=transfers');
        $this->options['proudcity_payments_charge_model'] = 'yolo';
        $this->assertSame('destination', ProudGravityformsStripe::get_charge_model());
    }

    public function test_get_transfer_key_live_and_test_prefixes(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY_TEST=rk_test_PLATFORM');

        $this->assertSame('rk_live_PLATFORM', ProudGravityformsStripe::get_transfer_key('live'));
        $this->assertSame('rk_test_PLATFORM', ProudGravityformsStripe::get_transfer_key('test'));
    }

    public function test_get_transfer_key_rejects_sk_and_wrong_mode(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=sk_live_PLATFORM');
        $this->assertSame('', ProudGravityformsStripe::get_transfer_key('live'));

        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_test_PLATFORM');
        $this->assertSame('', ProudGravityformsStripe::get_transfer_key('live'));
    }

    public function test_get_transfer_key_sandbox_is_empty(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->assertSame('', ProudGravityformsStripe::get_transfer_key('sandbox'));
    }

    public function test_get_transfer_key_option_before_env(): void
    {
        $this->options['proudcity_payments_transfer_key'] = 'rk_live_OPTION';
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_ENV');

        $this->assertSame('rk_live_OPTION', ProudGravityformsStripe::get_transfer_key('live'));
    }

    public function test_is_transfer_mode_false_without_account(): void
    {
        putenv('PROUDCITY_PAYMENTS_CHARGE_MODEL=transfers');
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->options['proudcity_payments_account'] = false;

        $this->assertFalse(ProudGravityformsStripe::is_transfer_mode('live'));
    }

    public function test_is_transfer_mode_false_on_proudcity(): void
    {
        putenv('PROUDCITY_PAYMENTS_CHARGE_MODEL=transfers');
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->siteUrl = 'https://proudcity.com';

        $this->assertFalse(ProudGravityformsStripe::is_transfer_mode('live'));
    }

    public function test_is_transfer_mode_false_without_key(): void
    {
        putenv('PROUDCITY_PAYMENTS_CHARGE_MODEL=transfers');

        $this->assertFalse(ProudGravityformsStripe::is_transfer_mode('live'));
    }

    public function test_is_transfer_mode_false_when_destination_model(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $this->assertFalse(ProudGravityformsStripe::is_transfer_mode('live'));
    }

    public function test_is_transfer_mode_true_when_everything_lines_up(): void
    {
        putenv('PROUDCITY_PAYMENTS_CHARGE_MODEL=transfers');
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $this->assertTrue(ProudGravityformsStripe::is_transfer_mode('live'));
    }

    // -------------------------------------------------------------------
    // Charge
    // -------------------------------------------------------------------

    public function test_calculate_fee_matches_spike_value(): void
    {
        $this->assertSame(89, ProudGravityformsStripe::calculate_fee(2000));
    }

    public function test_calculate_fee_table(): void
    {
        foreach ([500, 1000, 10000] as $amount) {
            $percent_dec = 3.05 / 100;
            $expected = (int) ($amount - (round($amount / (1 + $percent_dec)) - 30));
            $this->assertSame($expected, ProudGravityformsStripe::calculate_fee($amount));
        }
    }

    public function test_calculate_fee_honours_env_percent(): void
    {
        putenv('PROUDCITY_PAYMENTS_PERCENT=5');

        $expected = (int) (2000 - (round(2000 / 1.05) - 30));
        $this->assertSame($expected, ProudGravityformsStripe::calculate_fee(2000));
    }

    public function test_add_transfer_meta_destination_mode_matches_today(): void
    {
        $data = ProudGravityformsStripe::add_transfer_meta(['amount' => 2000], ['form_id' => 1]);

        $this->assertSame('acct_city123', $data['on_behalf_of']);
        $this->assertSame('Example', $data['statement_descriptor_suffix']);
        $this->assertSame('Test Form', $data['transfer_group']);
        $this->assertSame(89, $data['application_fee_amount']);
        $this->assertSame('acct_city123', $data['transfer_data']['destination']);
        $this->assertArrayNotHasKey('metadata', $data);
    }

    public function test_add_transfer_meta_transfers_mode_has_no_transfer_data_or_fee(): void
    {
        putenv('PROUDCITY_PAYMENTS_CHARGE_MODEL=transfers');
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $data = ProudGravityformsStripe::add_transfer_meta(['amount' => 2000], ['form_id' => 1]);

        $this->assertSame('acct_city123', $data['on_behalf_of']);
        $this->assertSame('Example', $data['statement_descriptor_suffix']);
        $this->assertSame('Test Form', $data['transfer_group']);
        $this->assertSame(['card'], $data['payment_method_types']);
        $this->assertArrayNotHasKey('transfer_data', $data);
        $this->assertArrayNotHasKey('application_fee_amount', $data);
    }

    public function test_add_transfer_meta_transfers_mode_merges_metadata_keeping_feed_keys(): void
    {
        putenv('PROUDCITY_PAYMENTS_CHARGE_MODEL=transfers');
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $data = ProudGravityformsStripe::add_transfer_meta(
            ['amount' => 2000, 'metadata' => ['gf_entry_id' => '99', 'feed_key' => 'feed_value']],
            ['form_id' => 1]
        );

        $this->assertSame('99', $data['metadata']['gf_entry_id']);
        $this->assertSame('feed_value', $data['metadata']['feed_key']);
        $this->assertSame('transfers', $data['metadata']['proudcity_charge_model']);
        $this->assertSame('acct_city123', $data['metadata']['proudcity_transfer_account']);
        $this->assertSame('89', $data['metadata']['proudcity_fee']);
        $this->assertSame('3.05', $data['metadata']['proudcity_fee_percent']);
        $this->assertSame('1:example.gov', $data['metadata']['proudcity_site']);
        $this->assertSame('1', $data['metadata']['proudcity_form_id']);

        foreach ($data['metadata'] as $value) {
            $this->assertIsString($value);
        }
    }

    public function test_add_transfer_meta_transfers_mode_our_keys_override_feed_keys(): void
    {
        putenv('PROUDCITY_PAYMENTS_CHARGE_MODEL=transfers');
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $data = ProudGravityformsStripe::add_transfer_meta(
            ['amount' => 2000, 'metadata' => ['proudcity_charge_model' => 'destination']],
            ['form_id' => 1]
        );

        $this->assertSame('transfers', $data['metadata']['proudcity_charge_model']);
    }

    public function test_add_transfer_meta_on_proudcity_only_payment_method_types_changes(): void
    {
        $this->siteUrl = 'https://proudcity.com';

        $data = ProudGravityformsStripe::add_transfer_meta(['amount' => 2000], ['form_id' => 1]);

        $this->assertSame(['card'], $data['payment_method_types']);
        $this->assertArrayNotHasKey('on_behalf_of', $data);
        $this->assertArrayNotHasKey('metadata', $data);
        $this->assertArrayNotHasKey('application_fee_amount', $data);
    }

    public function test_use_platform_secret_for_connect_no_swap_in_transfers_mode(): void
    {
        putenv('PROUDCITY_PAYMENTS_CHARGE_MODEL=transfers');
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        putenv('PROUDCITY_PAYMENTS_SECRET=sk_live_PLATFORMTEST');
        Functions\when('is_admin')->justReturn(false);

        $settings = [
            'live_secret_key' => 'sk_live_GFOAUTHTOKEN',
            'live_publishable_key' => 'pk_live_GFOAUTHPUBLIC',
            'live_auth_token' => ['acct_1GfConnect', 'refresh_token_abc', '2026-10-06'],
        ];

        $result = ProudGravityformsStripe::use_platform_secret_for_connect($settings);

        $this->assertSame($settings, $result);

        putenv('PROUDCITY_PAYMENTS_SECRET');
    }

    // -------------------------------------------------------------------
    // Transfer
    // -------------------------------------------------------------------

    public function test_build_transfer_args_happy_path(): void
    {
        $args = ProudGravityformsStripe::build_transfer_args($this->samplePi());

        $this->assertSame(1911, $args['amount']);
        $this->assertSame('acct_city123', $args['destination']);
        $this->assertSame('ch_123', $args['source_transaction']);
        $this->assertSame('Test Form', $args['transfer_group']);
        $this->assertSame('42', $args['metadata']['entry_id']);
        $this->assertSame('7', $args['metadata']['form_id']);
        $this->assertSame('pi_123', $args['metadata']['payment_intent']);
        $this->assertSame('ch_123', $args['metadata']['charge']);
        $this->assertSame('1:example.gov', $args['metadata']['site']);
    }

    public function test_build_transfer_args_errors(): void
    {
        $noMarker = ProudGravityformsStripe::build_transfer_args($this->samplePi(['proudcity_charge_model' => 'destination']));
        $this->assertInstanceOf(\WP_Error::class, $noMarker);
        $this->assertSame('no_transfer_marker', $noMarker->get_error_code());

        $foreignSite = ProudGravityformsStripe::build_transfer_args($this->samplePi(['proudcity_site' => '2:other.gov']));
        $this->assertInstanceOf(\WP_Error::class, $foreignSite);
        $this->assertSame('site_tag_mismatch', $foreignSite->get_error_code());

        $badAccount = ProudGravityformsStripe::build_transfer_args($this->samplePi(['proudcity_transfer_account' => 'not-an-account']));
        $this->assertInstanceOf(\WP_Error::class, $badAccount);
        $this->assertSame('invalid_transfer_account', $badAccount->get_error_code());

        $notCaptured = ProudGravityformsStripe::build_transfer_args($this->samplePi([], ['captured' => false]));
        $this->assertInstanceOf(\WP_Error::class, $notCaptured);
        $this->assertSame('charge_not_captured', $notCaptured->get_error_code());

        $notPositive = ProudGravityformsStripe::build_transfer_args($this->samplePi(['proudcity_fee' => '5000'], ['amount_captured' => 2000]));
        $this->assertInstanceOf(\WP_Error::class, $notPositive);
        $this->assertSame('transfer_amount_not_positive', $notPositive->get_error_code());
    }

    public function test_ensure_transfer_sends_idempotency_key_and_our_own_key(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $captured = [];

        Functions\when('wp_remote_request')->alias(function ($url, $args) use (&$captured) {
            $captured[] = ['url' => $url, 'args' => $args];

            if (str_contains($url, 'payment_intents/pi_123')) {
                return $this->jsonResponse(200, $this->samplePi());
            }

            if (str_contains($url, '/transfers') && 'POST' === $args['method']) {
                return $this->jsonResponse(200, ['id' => 'tr_123', 'amount' => 1911]);
            }

            return $this->jsonResponse(404, []);
        });

        $result = ProudGravityformsStripe::ensure_transfer('pi_123', 'live', 42);

        $this->assertSame('tr_123', $result['id']);
        $this->assertSame('tr_123', $this->entryMeta[42]['proudcity_stripe_transfer_id']);
        $this->assertSame('created', $this->entryMeta[42]['proudcity_stripe_transfer_status']);

        $post = $captured[1];
        $this->assertSame('transfer-ch_123', $post['args']['headers']['Idempotency-Key']);
        $this->assertSame('Basic ' . base64_encode('rk_live_PLATFORM:'), $post['args']['headers']['Authorization']);
        $this->assertSame(ProudGravityformsStripe::STRIPE_API_VERSION, $post['args']['headers']['Stripe-Version']);
    }

    public function test_ensure_transfer_skips_when_meta_already_has_transfer_id(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_existing';

        Functions\when('wp_remote_request')->alias(function () {
            $this->fail('wp_remote_request should not be called when a transfer id already exists.');
        });

        $result = ProudGravityformsStripe::ensure_transfer('pi_123', 'live', 42);

        $this->assertSame('tr_existing', $result['id']);
    }

    public function test_ensure_transfer_wp_error_response_sets_failed_and_does_not_throw(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        Functions\when('wp_remote_request')->alias(function ($url, $args) {
            if (str_contains($url, 'payment_intents/pi_123')) {
                return $this->jsonResponse(200, $this->samplePi());
            }

            return $this->jsonResponse(402, ['error' => ['code' => 'balance_insufficient', 'message' => 'Not enough funds.']]);
        });

        $result = ProudGravityformsStripe::ensure_transfer('pi_123', 'live', 42);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('balance_insufficient', $result->get_error_code());
        $this->assertSame('failed', $this->entryMeta[42]['proudcity_stripe_transfer_status']);
    }

    public function test_handle_payment_completed_ignores_non_stripe_transaction_id(): void
    {
        Functions\when('wp_remote_request')->alias(function () {
            $this->fail('wp_remote_request should not be called for a non-Stripe transaction id.');
        });

        ProudGravityformsStripe::handle_payment_completed(
            ['id' => 42, 'transaction_id' => 'not-a-stripe-id'],
            ['type' => 'complete_payment']
        );

        $this->assertArrayNotHasKey(42, $this->entryMeta);
    }

    public function test_handle_payment_completed_ignores_pi_without_marker(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        GfStripeOnBehalfOfStub::$paymentFeed = ['id' => 1, 'meta' => []];

        Functions\when('wp_remote_request')->alias(function ($url, $args) {
            return $this->jsonResponse(200, $this->samplePi(['proudcity_charge_model' => 'destination']));
        });

        ProudGravityformsStripe::handle_payment_completed(
            ['id' => 42, 'transaction_id' => 'pi_123'],
            ['type' => 'complete_payment']
        );

        $this->assertArrayNotHasKey('proudcity_stripe_transfer_status', $this->entryMeta[42] ?? []);
    }

    // -------------------------------------------------------------------
    // Refund / failure
    // -------------------------------------------------------------------

    public function test_calculate_reversal_amount_matches_spike_value(): void
    {
        $this->assertSame(478, ProudGravityformsStripe::calculate_reversal_amount(500, 1911, 2000, []));
    }

    public function test_calculate_reversal_amount_applies_filter(): void
    {
        Functions\when('apply_filters')->alias(function ($tag, $amount, $refund_amount, $transfer_amount, $charge_amount, $context) {
            if ('proudcity_payments_reversal_amount' === $tag) {
                $this->assertSame(500, $refund_amount);
                $this->assertSame(1911, $transfer_amount);
                $this->assertSame(2000, $charge_amount);
                return $refund_amount;
            }

            return $amount;
        });

        $this->assertSame(500, ProudGravityformsStripe::calculate_reversal_amount(500, 1911, 2000, ['entry_id' => 42]));
    }

    public function test_sync_reversals_partial_refund(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_123';

        $captured = [];

        Functions\when('wp_remote_request')->alias(function ($url, $args) use (&$captured) {
            $captured[] = ['url' => $url, 'args' => $args];

            if (str_contains($url, 'payment_intents/pi_123')) {
                return $this->jsonResponse(200, $this->samplePi());
            }

            if (str_contains($url, 'charges/ch_123')) {
                return $this->jsonResponse(200, [
                    'id' => 'ch_123',
                    'status' => 'succeeded',
                    'captured' => true,
                    'amount' => 2000,
                    'refunded' => false,
                    'refunds' => ['data' => [
                        ['id' => 're_1', 'status' => 'succeeded', 'amount' => 500],
                    ]],
                ]);
            }

            if (str_contains($url, 'transfers/tr_123/reversals')) {
                return $this->jsonResponse(200, ['id' => 'trr_1', 'amount' => 478]);
            }

            if (str_contains($url, 'transfers/tr_123')) {
                return $this->jsonResponse(200, ['id' => 'tr_123', 'amount' => 1911, 'amount_reversed' => 0, 'reversals' => ['data' => []]]);
            }

            return $this->jsonResponse(404, []);
        });

        $result = ProudGravityformsStripe::sync_reversals('pi_123', 'live', 42);

        $this->assertSame(1, $result['reversed']);
        $this->assertSame(0, $result['errors']);

        $reversal = $captured[array_search('POST', array_column(array_column($captured, 'args'), 'method'), true)];
        $this->assertSame('reversal-re_1', $reversal['args']['headers']['Idempotency-Key']);
    }

    public function test_sync_reversals_skips_an_already_reversed_refund(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_123';

        $postCount = 0;

        Functions\when('wp_remote_request')->alias(function ($url, $args) use (&$postCount) {
            if (str_contains($url, 'payment_intents/pi_123')) {
                return $this->jsonResponse(200, $this->samplePi());
            }

            if (str_contains($url, 'charges/ch_123')) {
                return $this->jsonResponse(200, [
                    'id' => 'ch_123',
                    'status' => 'succeeded',
                    'captured' => true,
                    'amount' => 2000,
                    'refunded' => false,
                    'refunds' => ['data' => [
                        ['id' => 're_1', 'status' => 'succeeded', 'amount' => 500],
                    ]],
                ]);
            }

            if (str_contains($url, 'transfers/tr_123/reversals')) {
                $postCount++;
                return $this->jsonResponse(200, ['id' => 'trr_1', 'amount' => 478]);
            }

            if (str_contains($url, 'transfers/tr_123')) {
                return $this->jsonResponse(200, [
                    'id' => 'tr_123',
                    'amount' => 1911,
                    'amount_reversed' => 478,
                    'reversals' => ['data' => [
                        ['metadata' => ['refund_id' => 're_1']],
                    ]],
                ]);
            }

            return $this->jsonResponse(404, []);
        });

        $result = ProudGravityformsStripe::sync_reversals('pi_123', 'live', 42);

        $this->assertSame(0, $postCount);
        $this->assertSame(0, $result['reversed']);
    }

    public function test_sync_reversals_full_refund_reverses_exact_remainder(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_123';

        $reversalAmount = null;

        Functions\when('wp_remote_request')->alias(function ($url, $args) use (&$reversalAmount) {
            if (str_contains($url, 'payment_intents/pi_123')) {
                return $this->jsonResponse(200, $this->samplePi());
            }

            if (str_contains($url, 'charges/ch_123')) {
                return $this->jsonResponse(200, [
                    'id' => 'ch_123',
                    'status' => 'succeeded',
                    'captured' => true,
                    'amount' => 2000,
                    'amount_refunded' => 2000,
                    'refunded' => true,
                    'refunds' => ['data' => [
                        ['id' => 're_1', 'status' => 'succeeded', 'amount' => 1000],
                        ['id' => 're_2', 'status' => 'succeeded', 'amount' => 1000],
                    ]],
                ]);
            }

            if (str_contains($url, 'transfers/tr_123/reversals')) {
                parse_str($args['body'], $parsed);
                $reversalAmount = (int) $parsed['amount'];
                return $this->jsonResponse(200, ['id' => 'trr_x', 'amount' => $reversalAmount]);
            }

            if (str_contains($url, 'transfers/tr_123')) {
                return $this->jsonResponse(200, ['id' => 'tr_123', 'amount' => 1911, 'amount_reversed' => 0, 'reversals' => ['data' => []]]);
            }

            return $this->jsonResponse(404, []);
        });

        // Both refunds are processed in one call; the second (last) one takes
        // exactly whatever is left on the transfer rather than the
        // proportional formula, so no 1c rounding is stranded.
        $result = ProudGravityformsStripe::sync_reversals('pi_123', 'live', 42);

        $this->assertSame(2, $result['reversed']);
    }

    public function test_sync_reversals_balance_insufficient_sets_pending_and_alerts(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_123';

        Functions\when('wp_remote_request')->alias(function ($url, $args) {
            if (str_contains($url, 'payment_intents/pi_123')) {
                return $this->jsonResponse(200, $this->samplePi());
            }

            if (str_contains($url, 'charges/ch_123')) {
                return $this->jsonResponse(200, [
                    'id' => 'ch_123',
                    'status' => 'succeeded',
                    'captured' => true,
                    'amount' => 2000,
                    'refunded' => false,
                    'refunds' => ['data' => [
                        ['id' => 're_1', 'status' => 'succeeded', 'amount' => 500],
                    ]],
                ]);
            }

            if (str_contains($url, 'transfers/tr_123/reversals')) {
                return $this->jsonResponse(402, ['error' => ['code' => 'balance_insufficient', 'message' => 'Not enough funds.']]);
            }

            if (str_contains($url, 'transfers/tr_123')) {
                return $this->jsonResponse(200, ['id' => 'tr_123', 'amount' => 1911, 'amount_reversed' => 0, 'reversals' => ['data' => []]]);
            }

            return $this->jsonResponse(404, []);
        });

        $result = ProudGravityformsStripe::sync_reversals('pi_123', 'live', 42);

        $this->assertSame(1, $result['errors']);
        $this->assertSame(1, $this->entryMeta[42]['proudcity_stripe_reversal_pending']);
    }

    public function test_handle_payment_action_fail_payment_gives_full_reversal(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_123';
        GfStripeOnBehalfOfStub::$paymentFeed = ['id' => 1, 'meta' => []];

        $reversalAmount = null;

        Functions\when('wp_remote_request')->alias(function ($url, $args) use (&$reversalAmount) {
            if (str_contains($url, 'payment_intents/pi_123')) {
                return $this->jsonResponse(200, $this->samplePi());
            }

            if (str_contains($url, 'charges/ch_123')) {
                return $this->jsonResponse(200, [
                    'id' => 'ch_123',
                    'status' => 'failed',
                    'amount' => 2000,
                    'refunded' => false,
                    'refunds' => ['data' => []],
                ]);
            }

            if (str_contains($url, 'transfers/tr_123/reversals')) {
                parse_str($args['body'], $parsed);
                $reversalAmount = (int) $parsed['amount'];
                return $this->jsonResponse(200, ['id' => 'trr_fail', 'amount' => $reversalAmount]);
            }

            if (str_contains($url, 'transfers/tr_123')) {
                return $this->jsonResponse(200, ['id' => 'tr_123', 'amount' => 1911, 'amount_reversed' => 0, 'reversals' => ['data' => []]]);
            }

            return $this->jsonResponse(404, []);
        });

        ProudGravityformsStripe::handle_payment_action(
            ['id' => 42, 'transaction_id' => 'pi_123'],
            ['type' => 'fail_payment']
        );

        $this->assertSame(1911, $reversalAmount);
    }

    // -------------------------------------------------------------------
    // Plumbing
    // -------------------------------------------------------------------

    public function test_stripe_request_non_2xx_gives_wp_error_and_never_leaks_the_key(): void
    {
        Functions\when('wp_remote_request')->justReturn($this->jsonResponse(402, [
            'error' => ['code' => 'balance_insufficient', 'message' => 'Not enough available funds.'],
        ]));

        $result = ProudGravityformsStripe::stripe_request('POST', 'transfers/tr_123/reversals', [], 'rk_live_SECRETVALUE');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('balance_insufficient', $result->get_error_code());
        $this->assertSame('Not enough available funds.', $result->get_error_message());
        $this->assertStringNotContainsString('rk_live_SECRETVALUE', $result->get_error_message());
        $this->assertStringNotContainsString('rk_live_SECRETVALUE', $result->get_error_code());
    }

    public function test_reconcile_transfers_search_query_and_retries(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $searchQuery = null;

        $this->entryMeta[55]['proudcity_stripe_transfer_status'] = 'failed';
        GFAPI::$entries = [
            ['id' => 55, 'transaction_id' => 'pi_555'],
        ];
        GfStripeOnBehalfOfStub::$paymentFeed = ['id' => 1, 'meta' => []];

        Functions\when('wp_remote_request')->alias(function ($url, $args) use (&$searchQuery) {
            if (str_contains($url, 'payment_intents/search')) {
                parse_str(substr($url, strpos($url, '?') + 1), $parsed);
                $searchQuery = $parsed['query'];
                return $this->jsonResponse(200, ['data' => [], 'has_more' => false]);
            }

            if (str_contains($url, 'payment_intents/pi_555')) {
                return $this->jsonResponse(200, $this->samplePi(['gf_entry_id' => '55']));
            }

            if (str_contains($url, '/transfers') && 'POST' === $args['method']) {
                return $this->jsonResponse(200, ['id' => 'tr_555', 'amount' => 1911]);
            }

            return $this->jsonResponse(404, []);
        });

        $counts = ProudGravityformsStripe::reconcile_transfers();

        $this->assertStringContainsString('1:example.gov', $searchQuery);
        $this->assertStringContainsString("proudcity_charge_model']:'transfers'", $searchQuery);
        $this->assertStringContainsString("status:'succeeded'", $searchQuery);
        $this->assertSame(1, $counts['retried']);
        $this->assertSame('tr_555', $this->entryMeta[55]['proudcity_stripe_transfer_id']);
    }

    public function test_reconcile_transfers_skips_pi_found_by_find_existing_transfer(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[55]['proudcity_stripe_transfer_status'] = 'failed';

        Functions\when('wp_remote_request')->alias(function ($url, $args) {
            if (str_contains($url, 'payment_intents/search')) {
                return $this->jsonResponse(200, ['data' => [['id' => 'pi_555', 'metadata' => ['gf_entry_id' => '55']]], 'has_more' => false]);
            }

            if (str_contains($url, 'payment_intents/pi_555')) {
                return $this->jsonResponse(200, $this->samplePi(['gf_entry_id' => '55']));
            }

            if (str_contains($url, '/transfers') && 'GET' === $args['method']) {
                return $this->jsonResponse(200, ['data' => [['id' => 'tr_already', 'amount' => 1911, 'source_transaction' => 'ch_123']], 'has_more' => false]);
            }

            if (str_contains($url, '/transfers') && 'POST' === $args['method']) {
                $this->fail('A transfer already found by find_existing_transfer() should not be re-created.');
            }

            return $this->jsonResponse(404, []);
        });

        $counts = ProudGravityformsStripe::reconcile_transfers();

        $this->assertSame(1, $counts['skipped']);
        $this->assertSame('tr_already', $this->entryMeta[55]['proudcity_stripe_transfer_id']);
    }

    public function test_alert_is_throttled_for_24h_per_code_and_charge(): void
    {
        $calls = 0;
        Functions\when('error_log')->alias(function () use (&$calls) {
            $calls++;
        });

        ProudGravityformsStripe::alert('transfer_failed', 'first', ['charge' => 'ch_123']);
        ProudGravityformsStripe::alert('transfer_failed', 'second', ['charge' => 'ch_123']);

        $this->assertSame(1, $calls);
    }

    public function test_alert_notes_the_entry(): void
    {
        ProudGravityformsStripe::alert('transfer_failed', 'boom', ['entry_id' => 42, 'charge' => 'ch_123']);

        $this->assertCount(1, GfStripeOnBehalfOfStub::$notes);
        $this->assertSame(42, GfStripeOnBehalfOfStub::$notes[0]['entry_id']);
    }
}
