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
            'home' => 'https://example.gov',
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
        GFAPI::$entriesById = [];
        GFAPI::$entriesByTransactionId = [];

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
                'amount_refunded' => 0,
                'currency' => 'usd',
                'transfer_group' => 'Test Form',
                'refunded' => false,
                'created' => time() - 3600,
                // add_transfer_meta() always sets on_behalf_of in both charge
                // models (NEW-4), so a 'transfers'-marked charge without it
                // matching the current account is itself a red flag.
                'on_behalf_of' => 'acct_city123',
            ], $chargeOverrides),
        ];
    }

    /**
     * Ordered Stripe endpoint handlers covering the full happy path a
     * single ensure_transfer()/sync_reversals() call touches: the PI fetch,
     * the pre-POST transfer lookup (HIGH #2), the transfer create, the
     * charge/refunds/reversals reads sync_reversals() always does (MEDIUM
     * #3's post-create sync, MEDIUM #6's pagination), and the single
     * transfer fetch sync_reversals() uses for its binding check (MEDIUM
     * #4). Tests override just the one or two entries they care about via
     * array_merge()/direct key assignment -- the key's position (and so its
     * priority against the more specific 'reversals'/'search' matches
     * checked first) is preserved.
     */
    private function defaultStripeHandlers(): array
    {
        return [
            'reversal_create' => ['method' => 'POST', 'contains' => '/reversals', 'response' => $this->jsonResponse(200, ['id' => 'trr_default', 'amount' => 0])],
            'reversal_list'   => ['method' => 'GET', 'contains' => '/reversals', 'response' => $this->jsonResponse(200, ['data' => [], 'has_more' => false])],
            'transfer_create' => ['method' => 'POST', 'contains' => '/transfers', 'response' => $this->jsonResponse(200, ['id' => 'tr_default', 'amount' => 0])],
            'transfer_lookup' => ['method' => 'GET', 'contains' => 'transfers?', 'response' => $this->jsonResponse(200, ['data' => [], 'has_more' => false])],
            'transfer_fetch'  => ['method' => 'GET', 'contains' => '/transfers/', 'response' => $this->jsonResponse(200, [
                'id' => 'tr_123', 'amount' => 1911, 'amount_reversed' => 0,
                'source_transaction' => 'ch_123', 'destination' => 'acct_city123',
            ])],
            'charge_fetch'    => ['method' => 'GET', 'contains' => 'charges/', 'response' => $this->jsonResponse(200, [
                'id' => 'ch_123', 'status' => 'succeeded', 'captured' => true,
                'amount' => 2000, 'amount_refunded' => 0, 'refunded' => false,
            ])],
            'refunds_list'    => ['method' => 'GET', 'contains' => 'refunds?', 'response' => $this->jsonResponse(200, ['data' => [], 'has_more' => false])],
            'pi_fetch'        => ['method' => 'GET', 'contains' => 'payment_intents/pi_123', 'response' => $this->jsonResponse(200, $this->samplePi())],
        ];
    }

    /**
     * Routes a mocked wp_remote_request() call through an ordered list of
     * [method, contains, response] handlers -- the first whose method
     * matches and whose 'contains' is a substring of the URL wins.
     * 'response' can be a plain jsonResponse() array or a callable
     * ($url, $args) for a test that needs to capture or compute per-call.
     */
    private function stripeRouter(array $handlers): \Closure
    {
        return function ($url, $args) use ($handlers) {
            $method = $args['method'] ?? 'GET';

            foreach ($handlers as $handler) {
                if (($handler['method'] ?? 'GET') !== $method) {
                    continue;
                }
                if (!str_contains($url, $handler['contains'])) {
                    continue;
                }
                $response = $handler['response'];
                return is_callable($response) ? $response($url, $args) : $response;
            }

            return $this->jsonResponse(404, ['error' => ['code' => 'resource_missing', 'message' => 'Unhandled test URL: ' . $url]]);
        };
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

    /**
     * N2: a feed's custom metadata could carry a forged proudcity_* key
     * (e.g. proudcity_charge_model=transfers) on an otherwise ordinary
     * destination-mode charge -- stripped here in BOTH branches, not just
     * transfers mode, so that marker never even reaches the PI in the
     * first place.
     */
    public function test_add_transfer_meta_destination_mode_strips_feed_supplied_proudcity_keys(): void
    {
        $data = ProudGravityformsStripe::add_transfer_meta(
            ['amount' => 2000, 'metadata' => ['proudcity_charge_model' => 'transfers', 'feed_key' => 'feed_value']],
            ['form_id' => 1]
        );

        $this->assertArrayNotHasKey('proudcity_charge_model', $data['metadata']);
        $this->assertSame('feed_value', $data['metadata']['feed_key']);
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
        $this->assertSame('42', $args['metadata']['proudcity_entry_id']);
        $this->assertSame('7', $args['metadata']['proudcity_form_id']);
        $this->assertSame('pi_123', $args['metadata']['proudcity_payment_intent']);
        $this->assertSame('ch_123', $args['metadata']['proudcity_charge']);
        $this->assertSame('1:example.gov', $args['metadata']['proudcity_site']);
        $this->assertSame('2000', $args['metadata']['proudcity_base_amount']);
        $this->assertSame('0', $args['metadata']['proudcity_base_refunded']);
    }

    /**
     * N1: proudcity_base_amount/proudcity_base_refunded capture the
     * available amount and the refunded-so-far amount at THIS PI fetch,
     * which is what sync_reversals() later diffs against.
     */
    public function test_build_transfer_args_writes_base_amount_and_base_refunded(): void
    {
        $args = ProudGravityformsStripe::build_transfer_args($this->samplePi([], ['amount_refunded' => 500]));

        $this->assertSame('1500', $args['metadata']['proudcity_base_amount']);
        $this->assertSame('500', $args['metadata']['proudcity_base_refunded']);
    }

    public function test_build_transfer_args_errors(): void
    {
        $noMarker = ProudGravityformsStripe::build_transfer_args($this->samplePi(['proudcity_charge_model' => 'destination']));
        $this->assertInstanceOf(\WP_Error::class, $noMarker);
        $this->assertSame('no_transfer_marker', $noMarker->get_error_code());

        // Foreign-site PIs are now caught by pi_structurally_ours()'s own
        // site check (N2), surfaced as marker_forged -- there's no
        // legitimate way for this site to see a 'transfers' PI tagged for
        // another site on the shared platform account.
        $foreignSite = ProudGravityformsStripe::build_transfer_args($this->samplePi(['proudcity_site' => '2:other.gov']));
        $this->assertInstanceOf(\WP_Error::class, $foreignSite);
        $this->assertSame('marker_forged', $foreignSite->get_error_code());

        $badAccount = ProudGravityformsStripe::build_transfer_args($this->samplePi(['proudcity_transfer_account' => 'not-an-account']));
        $this->assertInstanceOf(\WP_Error::class, $badAccount);
        $this->assertSame('invalid_transfer_account', $badAccount->get_error_code());

        $notCaptured = ProudGravityformsStripe::build_transfer_args($this->samplePi([], ['captured' => false]));
        $this->assertInstanceOf(\WP_Error::class, $notCaptured);
        $this->assertSame('charge_not_captured', $notCaptured->get_error_code());

        // Fee is always recomputed from the charge, never metadata, so a
        // non-positive amount has to come from a genuinely tiny charge --
        // calculate_fee(20) (31) exceeds the 20 cent amount_captured itself.
        $notPositive = ProudGravityformsStripe::build_transfer_args($this->samplePi([], ['amount_captured' => 20]));
        $this->assertInstanceOf(\WP_Error::class, $notPositive);
        $this->assertSame('transfer_amount_not_positive', $notPositive->get_error_code());
    }

    /**
     * HIGH #1: PI metadata is only a hint. The destination has to equal
     * THIS site's current get_transfer_account(), never whatever a feed
     * (or GF's own later metadata merges) wrote on the PI.
     */
    public function test_build_transfer_args_refuses_destination_mismatch(): void
    {
        // on_behalf_of (immutable once the charge exists) still matches the
        // current account -- only the metadata's own proudcity_transfer_account
        // is wrong, isolating this from the marker_forged/on_behalf_of check.
        $result = ProudGravityformsStripe::build_transfer_args($this->samplePi(['proudcity_transfer_account' => 'acct_DIFFERENT']));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('destination_mismatch', $result->get_error_code());
    }

    /**
     * MEDIUM #3: refuse a late transfer for a charge that was already fully
     * refunded or disputed before one ever existed -- distinct from the
     * normal refund-after-transfer case sync_reversals() handles.
     */
    public function test_build_transfer_args_refuses_refunded_or_disputed_charge(): void
    {
        $refunded = ProudGravityformsStripe::build_transfer_args($this->samplePi([], ['refunded' => true]));
        $this->assertInstanceOf(\WP_Error::class, $refunded);
        $this->assertSame('charge_refunded_before_transfer', $refunded->get_error_code());

        $disputed = ProudGravityformsStripe::build_transfer_args($this->samplePi([], ['disputed' => true]));
        $this->assertInstanceOf(\WP_Error::class, $disputed);
        $this->assertSame('charge_disputed', $disputed->get_error_code());
    }

    /**
     * HIGH #1: metadata['proudcity_fee'] is never used for the money math
     * in either direction -- only calculate_fee() recomputed from the
     * charge decides the fee. A max(metadata, recomputed) was tried first,
     * but GF's nopriv gfstripe_update_payment_intent AJAX action can
     * legitimately lower the PaymentIntent's amount after
     * add_transfer_meta() wrote that metadata (a quantity change on the
     * form), which would leave a stale, too-large metadata fee and win a
     * max() against the correctly recomputed, smaller fee -- underpaying
     * the city. A metadata fee SMALLER than the recomputed value is
     * likewise ignored, never trusted to reduce ProudCity's share.
     */
    public function test_build_transfer_args_ignores_metadata_fee_smaller_than_recomputed(): void
    {
        $args = ProudGravityformsStripe::build_transfer_args($this->samplePi(['proudcity_fee' => '1']));

        // calculate_fee(2000) is 89, so the amount is exactly as if the
        // metadata fee had never been set to 1.
        $this->assertSame(1911, $args['amount']);
    }

    /**
     * A metadata fee LARGER than the recomputed value is also ignored --
     * recomputed, not metadata, decides the fee either way.
     */
    public function test_build_transfer_args_ignores_metadata_fee_larger_than_recomputed(): void
    {
        $args = ProudGravityformsStripe::build_transfer_args($this->samplePi(['proudcity_fee' => '500']));

        $this->assertSame(1911, $args['amount']);
    }

    /**
     * HIGH #1: a negative or non-numeric metadata fee is rejected outright
     * and the recomputed value is used instead, rather than being clamped
     * or otherwise half-trusted.
     */
    public function test_build_transfer_args_invalid_metadata_fee_uses_recomputed(): void
    {
        $negative = ProudGravityformsStripe::build_transfer_args($this->samplePi(['proudcity_fee' => '-5']));
        $this->assertSame(1911, $negative['amount']);

        $nonNumeric = ProudGravityformsStripe::build_transfer_args($this->samplePi(['proudcity_fee' => 'not-a-number']));
        $this->assertSame(1911, $nonNumeric['amount']);
    }

    /**
     * A metadata fee that differs from the recomputed value is logged
     * (non-ALERT, for reporting/reconciliation) but never changes the
     * money math.
     */
    public function test_build_transfer_args_logs_a_fee_metadata_mismatch(): void
    {
        $logged = [];
        Functions\when('error_log')->alias(function ($line) use (&$logged) {
            $logged[] = $line;
        });

        ProudGravityformsStripe::build_transfer_args($this->samplePi(['proudcity_fee' => '500']));

        $this->assertCount(1, $logged);
        $this->assertStringStartsWith('[proudcity-payments] fee_metadata_mismatch', $logged[0]);
        $this->assertStringNotContainsString('ALERT', $logged[0]);
    }

    /**
     * MEDIUM #3: a partial refund issued before the transfer was ever
     * created lowers the base the fee is recomputed from, so both sides
     * get their proportional share of what's actually left rather than a
     * flat fee sized for the original, now-stale, total.
     */
    public function test_build_transfer_args_partial_refund_before_transfer_reduces_available_and_fee(): void
    {
        $args = ProudGravityformsStripe::build_transfer_args($this->samplePi([], ['amount_refunded' => 500]));

        // available = 2000 - 500 = 1500; calculate_fee(1500) = 74, used
        // outright regardless of the stale metadata fee of 89.
        $this->assertSame(1426, $args['amount']);
    }

    /**
     * N2: the transfers marker alone is forgeable (GF's own feed
     * custom-meta mechanism, or a code path outside our filter, can write
     * it onto an ordinary destination charge's PI/charge metadata) -- a
     * marker with no matching destination-charge-free structure is never
     * trusted.
     */
    public function test_build_transfer_args_marker_forged_when_charge_has_transfer_data(): void
    {
        $result = ProudGravityformsStripe::build_transfer_args($this->samplePi([], ['transfer_data' => ['destination' => 'acct_city123']]));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('marker_forged', $result->get_error_code());
    }

    public function test_build_transfer_args_marker_forged_when_pi_has_transfer_data(): void
    {
        $pi = $this->samplePi();
        $pi['transfer_data'] = ['destination' => 'acct_city123'];

        $result = ProudGravityformsStripe::build_transfer_args($pi);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('marker_forged', $result->get_error_code());
    }

    public function test_build_transfer_args_marker_forged_when_charge_has_application_fee_amount(): void
    {
        $result = ProudGravityformsStripe::build_transfer_args($this->samplePi([], ['application_fee_amount' => 89]));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('marker_forged', $result->get_error_code());
    }

    public function test_build_transfer_args_marker_forged_when_charge_has_transfer(): void
    {
        $result = ProudGravityformsStripe::build_transfer_args($this->samplePi([], ['transfer' => 'tr_someone_elses']));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('marker_forged', $result->get_error_code());
    }

    public function test_build_transfer_args_marker_forged_when_on_behalf_of_differs_from_current_account(): void
    {
        $result = ProudGravityformsStripe::build_transfer_args($this->samplePi([], ['on_behalf_of' => 'acct_NOT_US']));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('marker_forged', $result->get_error_code());
    }

    public function test_build_transfer_args_accepts_on_behalf_of_matching_current_account(): void
    {
        $result = ProudGravityformsStripe::build_transfer_args($this->samplePi([], ['on_behalf_of' => 'acct_city123']));

        $this->assertIsArray($result);
    }

    /**
     * N2 reverse case: a real SC&T charge (on_behalf_of to our account, no
     * destination-charge fields) whose marker got lost or overwritten
     * alerts marker_missing, rather than the silent no_transfer_marker
     * outcome meant for an ordinary destination charge.
     */
    public function test_build_transfer_args_alerts_marker_missing_when_charge_looks_like_ours(): void
    {
        $logged = [];
        Functions\when('error_log')->alias(function ($line) use (&$logged) {
            $logged[] = $line;
        });

        $result = ProudGravityformsStripe::build_transfer_args($this->samplePi(
            ['proudcity_charge_model' => 'destination'],
            ['on_behalf_of' => 'acct_city123']
        ));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('no_transfer_marker', $result->get_error_code());
        $this->assertNotEmpty(array_filter($logged, static fn ($line) => str_contains($line, 'marker_missing')));
    }

    public function test_build_transfer_args_does_not_alert_marker_missing_for_an_ordinary_destination_charge(): void
    {
        $logged = [];
        Functions\when('error_log')->alias(function ($line) use (&$logged) {
            $logged[] = $line;
        });

        // No on_behalf_of to our account at all -- nothing about this
        // charge looks like one of ours.
        $result = ProudGravityformsStripe::build_transfer_args($this->samplePi(['proudcity_charge_model' => 'destination'], ['on_behalf_of' => '']));

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('no_transfer_marker', $result->get_error_code());
        $this->assertEmpty(array_filter($logged, static fn ($line) => str_contains($line, 'marker_missing')));
    }

    public function test_ensure_transfer_sends_idempotency_key_and_our_own_key(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $captured = [];

        $handlers = $this->defaultStripeHandlers();
        $handlers['transfer_create']['response'] = function ($url, $args) use (&$captured) {
            $captured[] = $args;
            return $this->jsonResponse(200, ['id' => 'tr_123', 'amount' => 1911]);
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::ensure_transfer('pi_123', 'live', 42);

        $this->assertSame('tr_123', $result['id']);
        $this->assertSame('tr_123', $this->entryMeta[42]['proudcity_stripe_transfer_id']);
        $this->assertSame('created', $this->entryMeta[42]['proudcity_stripe_transfer_status']);
        $this->assertSame('ch_123', $this->entryMeta[42]['proudcity_stripe_charge_id']);

        $this->assertCount(1, $captured);
        $this->assertSame('transfer-ch_123', $captured[0]['headers']['Idempotency-Key']);
        $this->assertSame('Basic ' . base64_encode('rk_live_PLATFORM:'), $captured[0]['headers']['Authorization']);
        $this->assertSame(ProudGravityformsStripe::STRIPE_API_VERSION, $captured[0]['headers']['Stripe-Version']);
    }

    public function test_ensure_transfer_skips_when_meta_already_has_transfer_id_for_the_same_charge(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_existing';
        $this->entryMeta[42]['proudcity_stripe_charge_id'] = 'ch_123';

        $handlers = $this->defaultStripeHandlers();
        $handlers['transfer_create']['response'] = function () {
            $this->fail('A transfer should not be created when the entry already has one for this charge.');
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::ensure_transfer('pi_123', 'live', 42);

        $this->assertSame('tr_existing', $result['id']);
        $this->assertTrue($result['skipped']);
    }

    /**
     * MEDIUM #4: an entry's existing transfer meta only gets trusted (and
     * skipped) if it's bound to the SAME charge this call is for. A
     * different stored charge id is a real binding problem, not something
     * to silently skip.
     */
    public function test_ensure_transfer_alerts_on_charge_mismatch_instead_of_skipping(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_existing';
        $this->entryMeta[42]['proudcity_stripe_charge_id'] = 'ch_SOMETHING_ELSE';

        Functions\when('wp_remote_request')->alias($this->stripeRouter($this->defaultStripeHandlers()));

        $result = ProudGravityformsStripe::ensure_transfer('pi_123', 'live', 42);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('charge_mismatch', $result->get_error_code());
    }

    public function test_ensure_transfer_wp_error_response_sets_failed_and_does_not_throw(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $handlers = $this->defaultStripeHandlers();
        $handlers['transfer_create']['response'] = $this->jsonResponse(402, ['error' => ['code' => 'balance_insufficient', 'message' => 'Not enough funds.']]);

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::ensure_transfer('pi_123', 'live', 42);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('balance_insufficient', $result->get_error_code());
        $this->assertSame('failed', $this->entryMeta[42]['proudcity_stripe_transfer_status']);
    }

    /**
     * HIGH #2: the lookup before the POST fails CLOSED -- any error there
     * means no POST ever happens, and the entry is left 'pending' (written
     * before the lookup) rather than 'failed', so the next reconcile pass
     * looks up again instead of blindly retrying a POST that might already
     * have succeeded.
     */
    public function test_ensure_transfer_fails_closed_when_the_lookup_itself_errors(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $handlers = $this->defaultStripeHandlers();
        $handlers['transfer_lookup']['response'] = $this->jsonResponse(500, ['error' => ['code' => 'api_error', 'message' => 'Stripe is down.']]);
        $handlers['transfer_create']['response'] = function () {
            $this->fail('Must not POST a transfer when the existing-transfer lookup itself failed.');
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::ensure_transfer('pi_123', 'live', 42);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('api_error', $result->get_error_code());
        $this->assertSame('pending', $this->entryMeta[42]['proudcity_stripe_transfer_status']);
        $this->assertSame('ch_123', $this->entryMeta[42]['proudcity_stripe_charge_id']);
    }

    /**
     * HIGH #2: the lookup runs before EVERY POST, not only on a retry --
     * even a entry's very first call finds and uses an already-existing
     * Transfer instead of creating a second one.
     */
    public function test_ensure_transfer_finds_existing_transfer_before_posting_on_the_very_first_call(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $handlers = $this->defaultStripeHandlers();
        $handlers['transfer_lookup']['response'] = $this->jsonResponse(200, ['data' => [
            ['id' => 'tr_already', 'amount' => 1911, 'source_transaction' => 'ch_123'],
        ], 'has_more' => false]);
        $handlers['transfer_create']['response'] = function () {
            $this->fail('Must not POST a transfer when find_existing_transfer() already found one.');
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::ensure_transfer('pi_123', 'live', 42);

        $this->assertTrue($result['skipped']);
        $this->assertSame('tr_already', $this->entryMeta[42]['proudcity_stripe_transfer_id']);
        $this->assertSame('created', $this->entryMeta[42]['proudcity_stripe_transfer_status']);
    }

    /**
     * NEW-2(a): finding an already-existing Transfer syncs reversals
     * immediately, same as a fresh POST -- a refund landing in the gap
     * before we found out about it must not wait for the next pass.
     */
    public function test_ensure_transfer_syncs_reversals_when_transfer_already_existed(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $reversalCalls = 0;

        $handlers = $this->defaultStripeHandlers();
        $handlers['transfer_lookup']['response'] = $this->jsonResponse(200, ['data' => [
            ['id' => 'tr_already', 'amount' => 1911, 'source_transaction' => 'ch_123'],
        ], 'has_more' => false]);
        $handlers['transfer_fetch']['response'] = $this->jsonResponse(200, [
            'id' => 'tr_already', 'amount' => 1911, 'amount_reversed' => 0,
            'source_transaction' => 'ch_123', 'destination' => 'acct_city123',
        ]);
        $handlers['charge_fetch']['response'] = $this->jsonResponse(200, [
            'id' => 'ch_123', 'status' => 'succeeded', 'captured' => true,
            'amount' => 2000, 'amount_refunded' => 500, 'refunded' => false,
        ]);
        $handlers['refunds_list']['response'] = $this->jsonResponse(200, ['data' => [
            ['id' => 're_1', 'status' => 'succeeded', 'amount' => 500],
        ], 'has_more' => false]);
        $handlers['transfer_create']['response'] = function () {
            $this->fail('Must not POST a transfer when find_existing_transfer() already found one.');
        };
        $handlers['reversal_create']['response'] = function () use (&$reversalCalls) {
            $reversalCalls++;
            return $this->jsonResponse(200, ['id' => 'trr_1', 'amount' => 478]);
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        ProudGravityformsStripe::ensure_transfer('pi_123', 'live', 42);

        $this->assertSame(1, $reversalCalls);
    }

    /**
     * NEW-2(b): a charge refused as charge_refunded_before_transfer or
     * charge_disputed might already have a real Transfer -- a POST that
     * actually succeeded on Stripe's side but whose response was lost (a
     * transport timeout) looks identical otherwise. Found here, it's
     * 'skipped' and synced, never marked 'invalid'.
     */
    public function test_ensure_transfer_finds_existing_transfer_instead_of_marking_invalid_on_now_refunded_charge(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $handlers = $this->defaultStripeHandlers();
        $handlers['pi_fetch']['response'] = $this->jsonResponse(200, $this->samplePi([], ['refunded' => true]));
        $handlers['transfer_lookup']['response'] = $this->jsonResponse(200, ['data' => [
            ['id' => 'tr_already', 'amount' => 1911, 'source_transaction' => 'ch_123'],
        ], 'has_more' => false]);
        $handlers['transfer_create']['response'] = function () {
            $this->fail('A transfer already exists on Stripe -- must never POST a new one.');
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::ensure_transfer('pi_123', 'live', 42);

        $this->assertTrue($result['skipped']);
        $this->assertSame('tr_already', $this->entryMeta[42]['proudcity_stripe_transfer_id']);
        $this->assertSame('created', $this->entryMeta[42]['proudcity_stripe_transfer_status']);
    }

    public function test_ensure_transfer_marks_invalid_when_refunded_and_no_existing_transfer(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $handlers = $this->defaultStripeHandlers();
        $handlers['pi_fetch']['response'] = $this->jsonResponse(200, $this->samplePi([], ['refunded' => true]));

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::ensure_transfer('pi_123', 'live', 42);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('charge_refunded_before_transfer', $result->get_error_code());
        $this->assertSame('invalid', $this->entryMeta[42]['proudcity_stripe_transfer_status']);
    }

    /**
     * NEW-2(c): a transport failure on the POST (we don't know if Stripe
     * ever received it) leaves the entry 'pending', not 'failed', but
     * still alerts once so a human knows.
     */
    public function test_ensure_transfer_transport_failure_on_post_leaves_pending_and_alerts_once(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $loggedAlerts = [];
        Functions\when('error_log')->alias(function ($line) use (&$loggedAlerts) {
            $loggedAlerts[] = $line;
        });

        $handlers = $this->defaultStripeHandlers();
        $handlers['transfer_create']['response'] = function () {
            return new \WP_Error('http_request_failed', 'cURL error 28: Operation timed out after 20000 milliseconds.');
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::ensure_transfer('pi_123', 'live', 42);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('http_request_failed', $result->get_error_code());
        $this->assertSame('pending', $this->entryMeta[42]['proudcity_stripe_transfer_status']);
        $this->assertNotEmpty(array_filter($loggedAlerts, static fn ($line) => str_contains($line, 'transfer_failed')));
    }

    /**
     * HIGH #2: a 409 idempotency conflict means a concurrent call (the hook
     * racing a reconcile pass, or two reconcile runs) is already POSTing
     * this exact transfer -- leave the entry 'pending', not 'failed', so
     * the next pass looks it up instead of endlessly retrying a POST.
     */
    public function test_ensure_transfer_idempotency_conflict_leaves_status_pending(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $handlers = $this->defaultStripeHandlers();
        $handlers['transfer_create']['response'] = $this->jsonResponse(409, ['error' => ['code' => 'idempotency_key_in_use', 'message' => 'Keys for idempotent requests can only be used for the same endpoint.']]);

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::ensure_transfer('pi_123', 'live', 42);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('idempotency_key_in_use', $result->get_error_code());
        $this->assertSame('pending', $this->entryMeta[42]['proudcity_stripe_transfer_status']);
    }

    /**
     * Leftover: idempotency_error (the error.type fallback stripe_request()
     * surfaces when Stripe's response has no error.code) is treated exactly
     * like idempotency_key_in_use -- in progress, left pending, no alert.
     */
    public function test_ensure_transfer_idempotency_error_code_also_leaves_status_pending(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $handlers = $this->defaultStripeHandlers();
        $handlers['transfer_create']['response'] = $this->jsonResponse(409, ['error' => ['type' => 'idempotency_error', 'message' => 'Keys for idempotent requests can only be used for the same endpoint.']]);

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::ensure_transfer('pi_123', 'live', 42);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('idempotency_error', $result->get_error_code());
        $this->assertSame('pending', $this->entryMeta[42]['proudcity_stripe_transfer_status']);
    }

    /**
     * LOW #7: a terminal build_transfer_args() error (here, a destination
     * mismatch) is marked 'invalid', not 'failed' -- reconcile_transfers()'
     * retry pass only looks for 'failed', so this is never retried forever.
     */
    public function test_ensure_transfer_terminal_error_sets_invalid_status_not_failed(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $handlers = $this->defaultStripeHandlers();
        $handlers['pi_fetch']['response'] = $this->jsonResponse(200, $this->samplePi(['proudcity_transfer_account' => 'acct_DIFFERENT']));
        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::ensure_transfer('pi_123', 'live', 42);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('destination_mismatch', $result->get_error_code());
        $this->assertSame('invalid', $this->entryMeta[42]['proudcity_stripe_transfer_status']);
    }

    /**
     * N3: an entry already bound to a created Transfer for this charge is
     * never downgraded to invalid/failed just because build_transfer_args()
     * would now reject the charge's CURRENT state (here, refunded) -- the
     * stored-meta/charge-binding check runs before build_transfer_args() at
     * all, so build_transfer_args() is never even reached.
     */
    public function test_ensure_transfer_never_downgrades_a_created_transfer_on_a_now_refunded_charge(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_existing';
        $this->entryMeta[42]['proudcity_stripe_charge_id'] = 'ch_123';
        $this->entryMeta[42]['proudcity_stripe_transfer_status'] = 'created';

        $handlers = $this->defaultStripeHandlers();
        $handlers['pi_fetch']['response'] = $this->jsonResponse(200, $this->samplePi([], ['refunded' => true]));
        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::ensure_transfer('pi_123', 'live', 42);

        $this->assertTrue($result['skipped']);
        $this->assertSame('created', $this->entryMeta[42]['proudcity_stripe_transfer_status']);
    }

    /**
     * MEDIUM #3: once the Transfer is created, sync_reversals() runs
     * immediately, so a refund that landed in the window between the PI
     * fetch and the POST doesn't have to wait for the next webhook/reconcile.
     */
    public function test_ensure_transfer_syncs_reversals_immediately_after_creating_a_transfer(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $reversalCalls = 0;

        $handlers = $this->defaultStripeHandlers();
        $handlers['charge_fetch']['response'] = $this->jsonResponse(200, [
            'id' => 'ch_123', 'status' => 'succeeded', 'captured' => true,
            'amount' => 2000, 'amount_refunded' => 500, 'refunded' => false,
        ]);
        $handlers['refunds_list']['response'] = $this->jsonResponse(200, ['data' => [
            ['id' => 're_1', 'status' => 'succeeded', 'amount' => 500],
        ], 'has_more' => false]);
        $handlers['reversal_create']['response'] = function () use (&$reversalCalls) {
            $reversalCalls++;
            return $this->jsonResponse(200, ['id' => 'trr_1', 'amount' => 478]);
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        ProudGravityformsStripe::ensure_transfer('pi_123', 'live', 42);

        $this->assertSame(1, $reversalCalls);
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

    /**
     * N4: with no connected account at all, nothing could ever be
     * transferred regardless of the PI's marker -- returns before even a
     * key lookup.
     */
    public function test_handle_payment_completed_returns_early_without_a_transfer_account(): void
    {
        $this->options['proudcity_payments_account'] = false;
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        Functions\when('wp_remote_request')->alias(function () {
            $this->fail('wp_remote_request should not be called with no transfer account configured.');
        });

        ProudGravityformsStripe::handle_payment_completed(
            ['id' => 42, 'transaction_id' => 'pi_123'],
            ['type' => 'complete_payment']
        );

        $this->assertArrayNotHasKey(42, $this->entryMeta);
    }

    /**
     * N4: a destination-mode site (is_transfer_mode() false for this
     * api_mode) still has to attempt the PI fetch for mid-flight safety
     * (a PI marked transfers before a flip-back must still be
     * transferred), but a resource_missing error while not in transfers
     * mode is swallowed quietly rather than alerted -- routine noise from
     * probing every payment while off.
     */
    public function test_ensure_transfer_quietly_ignores_resource_missing_when_not_in_transfers_mode(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        // get_charge_model() defaults to 'destination', so is_transfer_mode() is false.

        $loggedAlerts = [];
        Functions\when('error_log')->alias(function ($line) use (&$loggedAlerts) {
            $loggedAlerts[] = $line;
        });

        Functions\when('wp_remote_request')->justReturn($this->jsonResponse(404, [
            'error' => ['code' => 'resource_missing', 'message' => 'No such PaymentIntent.'],
        ]));

        $result = ProudGravityformsStripe::ensure_transfer('pi_123', 'live', 42);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('resource_missing', $result->get_error_code());
        $this->assertEmpty(array_filter($loggedAlerts, static fn ($line) => str_contains($line, 'ALERT')));
    }

    public function test_ensure_transfer_alerts_resource_missing_when_in_transfers_mode(): void
    {
        putenv('PROUDCITY_PAYMENTS_CHARGE_MODEL=transfers');
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $loggedAlerts = [];
        Functions\when('error_log')->alias(function ($line) use (&$loggedAlerts) {
            $loggedAlerts[] = $line;
        });

        Functions\when('wp_remote_request')->justReturn($this->jsonResponse(404, [
            'error' => ['code' => 'resource_missing', 'message' => 'No such PaymentIntent.'],
        ]));

        $result = ProudGravityformsStripe::ensure_transfer('pi_123', 'live', 42);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertNotEmpty(array_filter($loggedAlerts, static fn ($line) => str_contains($line, 'ALERT')));
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

        $handlers = $this->defaultStripeHandlers();
        $handlers['charge_fetch']['response'] = $this->jsonResponse(200, [
            'id' => 'ch_123', 'status' => 'succeeded', 'captured' => true,
            'amount' => 2000, 'amount_refunded' => 500, 'refunded' => false,
        ]);
        $handlers['refunds_list']['response'] = $this->jsonResponse(200, ['data' => [
            ['id' => 're_1', 'status' => 'succeeded', 'amount' => 500],
        ], 'has_more' => false]);
        $handlers['reversal_create']['response'] = function ($url, $args) use (&$captured) {
            $captured[] = $args;
            return $this->jsonResponse(200, ['id' => 'trr_1', 'amount' => 478]);
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::sync_reversals('pi_123', 'live', 42);

        $this->assertSame(1, $result['reversed']);
        $this->assertSame(0, $result['errors']);
        $this->assertCount(1, $captured);
        $this->assertSame('reversal-re_1', $captured[0]['headers']['Idempotency-Key']);
    }

    public function test_sync_reversals_skips_an_already_reversed_refund(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_123';

        $postCount = 0;

        $handlers = $this->defaultStripeHandlers();
        $handlers['refunds_list']['response'] = $this->jsonResponse(200, ['data' => [
            ['id' => 're_1', 'status' => 'succeeded', 'amount' => 500],
        ], 'has_more' => false]);
        $handlers['reversal_list']['response'] = $this->jsonResponse(200, ['data' => [
            ['metadata' => ['refund_id' => 're_1']],
        ], 'has_more' => false]);
        $handlers['transfer_fetch']['response'] = $this->jsonResponse(200, [
            'id' => 'tr_123', 'amount' => 1911, 'amount_reversed' => 478,
            'source_transaction' => 'ch_123', 'destination' => 'acct_city123',
        ]);
        $handlers['reversal_create']['response'] = function () use (&$postCount) {
            $postCount++;
            return $this->jsonResponse(200, ['id' => 'trr_1', 'amount' => 478]);
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::sync_reversals('pi_123', 'live', 42);

        $this->assertSame(0, $postCount);
        $this->assertSame(0, $result['reversed']);
    }

    public function test_sync_reversals_full_refund_reverses_exact_remainder(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_123';

        $reversalAmounts = [];

        $handlers = $this->defaultStripeHandlers();
        $handlers['charge_fetch']['response'] = $this->jsonResponse(200, [
            'id' => 'ch_123', 'status' => 'succeeded', 'captured' => true,
            'amount' => 2000, 'amount_refunded' => 2000, 'refunded' => true,
        ]);
        $handlers['refunds_list']['response'] = $this->jsonResponse(200, ['data' => [
            ['id' => 're_1', 'status' => 'succeeded', 'amount' => 1000],
            ['id' => 're_2', 'status' => 'succeeded', 'amount' => 1000],
        ], 'has_more' => false]);
        $handlers['reversal_create']['response'] = function ($url, $args) use (&$reversalAmounts) {
            parse_str($args['body'], $parsed);
            $reversalAmounts[] = (int) $parsed['amount'];
            return $this->jsonResponse(200, ['id' => 'trr_x', 'amount' => (int) $parsed['amount']]);
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        // Both refunds are processed in one call; the second (last) one takes
        // exactly whatever is left on the transfer rather than the
        // proportional formula, so no 1c rounding is stranded.
        $result = ProudGravityformsStripe::sync_reversals('pi_123', 'live', 42);

        $this->assertSame(2, $result['reversed']);
        $this->assertSame(1911, array_sum($reversalAmounts));
    }

    public function test_sync_reversals_balance_insufficient_sets_pending_and_alerts(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_123';

        $handlers = $this->defaultStripeHandlers();
        $handlers['charge_fetch']['response'] = $this->jsonResponse(200, [
            'id' => 'ch_123', 'status' => 'succeeded', 'captured' => true,
            'amount' => 2000, 'amount_refunded' => 500, 'refunded' => false,
        ]);
        $handlers['refunds_list']['response'] = $this->jsonResponse(200, ['data' => [
            ['id' => 're_1', 'status' => 'succeeded', 'amount' => 500],
        ], 'has_more' => false]);
        $handlers['reversal_create']['response'] = $this->jsonResponse(402, ['error' => ['code' => 'balance_insufficient', 'message' => 'Not enough funds.']]);

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::sync_reversals('pi_123', 'live', 42);

        $this->assertSame(1, $result['errors']);
        $this->assertSame(1, $this->entryMeta[42]['proudcity_stripe_reversal_pending']);
    }

    /**
     * MEDIUM #4: a Transfer only gets reversed if it's bound to THIS
     * charge, on THIS site's current account. A mismatch aborts rather
     * than moving money against the wrong transfer.
     */
    public function test_sync_reversals_aborts_on_transfer_binding_mismatch(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_123';

        $handlers = $this->defaultStripeHandlers();
        $handlers['transfer_fetch']['response'] = $this->jsonResponse(200, [
            'id' => 'tr_123', 'amount' => 1911, 'amount_reversed' => 0,
            'source_transaction' => 'ch_SOMETHING_ELSE', 'destination' => 'acct_city123',
        ]);
        $handlers['reversal_create']['response'] = function () {
            $this->fail('Must never reverse a transfer bound to a different charge.');
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::sync_reversals('pi_123', 'live', 42);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('transfer_binding_mismatch', $result->get_error_code());
    }

    /**
     * MEDIUM #6: the TOTAL reversed for this charge is capped at the
     * proportional share of charge.amount_refunded (Stripe's own running
     * total), so a single inconsistent-looking refund object can never
     * push the sum past what's actually been refunded -- idempotent by
     * sum, not just by refund id.
     */
    public function test_sync_reversals_caps_total_reversed_to_proportional_share_of_amount_refunded(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_123';

        $reversalAmounts = [];

        $handlers = $this->defaultStripeHandlers();
        $handlers['charge_fetch']['response'] = $this->jsonResponse(200, [
            'id' => 'ch_123', 'status' => 'succeeded', 'captured' => true,
            'amount' => 2000, 'amount_refunded' => 500, 'refunded' => false,
        ]);
        $handlers['refunds_list']['response'] = $this->jsonResponse(200, ['data' => [
            ['id' => 're_1', 'status' => 'succeeded', 'amount' => 2000],
        ], 'has_more' => false]);
        $handlers['reversal_create']['response'] = function ($url, $args) use (&$reversalAmounts) {
            parse_str($args['body'], $parsed);
            $reversalAmounts[] = (int) $parsed['amount'];
            return $this->jsonResponse(200, ['id' => 'trr_1', 'amount' => (int) $parsed['amount']]);
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        ProudGravityformsStripe::sync_reversals('pi_123', 'live', 42);

        // calculate_reversal_amount(500, 1911, 2000) = 478 -- the proportional
        // share of what's ACTUALLY been refunded, even though the one refund
        // object here claims the full 2000.
        $this->assertSame(478, array_sum($reversalAmounts));
    }

    /**
     * LOW #10: a malformed transfer id on the entry (which this code would
     * otherwise interpolate straight into a Stripe request path) is
     * refused before any further Stripe call is made.
     */
    public function test_sync_reversals_refuses_a_malformed_transfer_id(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_123; DROP TABLE';

        Functions\when('wp_remote_request')->alias(function ($url, $args) {
            if (str_contains($url, 'payment_intents/pi_123')) {
                return $this->jsonResponse(200, $this->samplePi());
            }
            $this->fail('Must not build a Stripe request path from a malformed transfer id.');
        });

        $result = ProudGravityformsStripe::sync_reversals('pi_123', 'live', 42);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('invalid_stripe_id', $result->get_error_code());
    }

    /**
     * Leftover: pyr_ (a Payment Record refund id shape) is an accepted
     * Stripe id prefix, same as pi/ch/py/tr/re.
     */
    public function test_sync_reversals_accepts_a_pyr_refund_id(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_123';

        $captured = [];

        $handlers = $this->defaultStripeHandlers();
        $handlers['charge_fetch']['response'] = $this->jsonResponse(200, [
            'id' => 'ch_123', 'status' => 'succeeded', 'captured' => true,
            'amount' => 2000, 'amount_refunded' => 500, 'refunded' => false,
        ]);
        $handlers['refunds_list']['response'] = $this->jsonResponse(200, ['data' => [
            ['id' => 'pyr_1', 'status' => 'succeeded', 'amount' => 500],
        ], 'has_more' => false]);
        $handlers['reversal_create']['response'] = function ($url, $args) use (&$captured) {
            $captured[] = $args;
            return $this->jsonResponse(200, ['id' => 'trr_1', 'amount' => 478]);
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::sync_reversals('pi_123', 'live', 42);

        $this->assertSame(1, $result['reversed']);
        $this->assertSame('reversal-pyr_1', $captured[0]['headers']['Idempotency-Key']);
    }

    /**
     * Leftover: an unparseable/invalid refund id is never silent -- it sets
     * the pending-reversal meta and alerts reversal_skipped rather than
     * just being dropped.
     */
    public function test_sync_reversals_alerts_reversal_skipped_for_an_invalid_refund_id(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_123';

        $loggedAlerts = [];
        Functions\when('error_log')->alias(function ($line) use (&$loggedAlerts) {
            $loggedAlerts[] = $line;
        });

        $handlers = $this->defaultStripeHandlers();
        $handlers['charge_fetch']['response'] = $this->jsonResponse(200, [
            'id' => 'ch_123', 'status' => 'succeeded', 'captured' => true,
            'amount' => 2000, 'amount_refunded' => 500, 'refunded' => false,
        ]);
        $handlers['refunds_list']['response'] = $this->jsonResponse(200, ['data' => [
            ['id' => 're_123; DROP TABLE', 'status' => 'succeeded', 'amount' => 500],
        ], 'has_more' => false]);
        $handlers['reversal_create']['response'] = function () {
            $this->fail('Must not build a Stripe request path from a malformed refund id.');
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::sync_reversals('pi_123', 'live', 42);

        $this->assertSame(1, $result['errors']);
        $this->assertSame(1, $this->entryMeta[42]['proudcity_stripe_reversal_pending']);
        $this->assertNotEmpty(array_filter($loggedAlerts, static fn ($line) => str_contains($line, 'reversal_skipped')));
    }

    /**
     * NEW-5: an ACH-style refund still settling (status pending, not yet
     * succeeded or failed) means the charge's refunded state isn't final --
     * proudcity_stripe_reversal_pending is never cleared while one exists,
     * keeping the entry in reconcile's retry set even though this call
     * itself had no errors.
     */
    public function test_sync_reversals_keeps_reversal_pending_set_while_a_refund_is_still_pending(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_123';
        $this->entryMeta[42]['proudcity_stripe_reversal_pending'] = 1;

        $handlers = $this->defaultStripeHandlers();
        $handlers['refunds_list']['response'] = $this->jsonResponse(200, ['data' => [
            ['id' => 're_1', 'status' => 'pending', 'amount' => 500],
        ], 'has_more' => false]);

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::sync_reversals('pi_123', 'live', 42);

        $this->assertSame(0, $result['errors']);
        $this->assertSame(1, $this->entryMeta[42]['proudcity_stripe_reversal_pending']);
    }

    /**
     * N1: a $100 charge refunded $40 in the gap between the PI fetch and
     * the transfer's creation is priced into proudcity_base_amount AND
     * proudcity_base_refunded -- sync_reversals() must see a zero delta
     * (amount_refunded already equals base_refunded) and reverse nothing.
     */
    public function test_sync_reversals_reverses_nothing_when_nothing_changed_since_base_refunded(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_n1';

        $handlers = $this->defaultStripeHandlers();
        $handlers['pi_fetch'] = ['method' => 'GET', 'contains' => 'payment_intents/pi_n1', 'response' => $this->jsonResponse(200, [
            'id' => 'pi_n1', 'metadata' => ['proudcity_charge_model' => 'transfers'], 'latest_charge' => ['id' => 'ch_n1'],
        ])];
        $handlers['charge_fetch']['response'] = $this->jsonResponse(200, [
            'id' => 'ch_n1', 'status' => 'succeeded', 'captured' => true,
            'amount' => 10000, 'amount_refunded' => 4000, 'refunded' => false,
        ]);
        $handlers['transfer_fetch']['response'] = $this->jsonResponse(200, [
            'id' => 'tr_n1', 'amount' => 5792, 'amount_reversed' => 0,
            'source_transaction' => 'ch_n1', 'destination' => 'acct_city123',
            'metadata' => [ 'proudcity_base_amount' => '6000', 'proudcity_base_refunded' => '4000' ],
        ]);
        $handlers['refunds_list']['response'] = $this->jsonResponse(200, ['data' => [
            [ 'id' => 're_pre', 'status' => 'succeeded', 'amount' => 4000 ],
        ], 'has_more' => false]);
        $handlers['reversal_create']['response'] = function () {
            $this->fail('Nothing has been refunded beyond proudcity_base_refunded -- there is nothing new to reverse.');
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::sync_reversals('pi_n1', 'live', 42);

        $this->assertSame(0, $result['reversed']);
        $this->assertSame(0, $result['errors']);
    }

    /**
     * N1: a refund landing in the fetch->POST gap (so NOT reflected in
     * proudcity_base_refunded) is reversed by the very next sync, in full,
     * proportioned against proudcity_base_amount -- not the charge's full
     * original amount.
     */
    public function test_sync_reversals_reverses_a_refund_from_the_fetch_to_post_gap(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_n1';

        $handlers = $this->defaultStripeHandlers();
        $handlers['pi_fetch'] = ['method' => 'GET', 'contains' => 'payment_intents/pi_n1', 'response' => $this->jsonResponse(200, [
            'id' => 'pi_n1', 'metadata' => ['proudcity_charge_model' => 'transfers'], 'latest_charge' => ['id' => 'ch_n1'],
        ])];
        // base_refunded (4000) only reflects the pre-transfer refund;
        // amount_refunded (7000) now also includes the gap refund.
        $handlers['charge_fetch']['response'] = $this->jsonResponse(200, [
            'id' => 'ch_n1', 'status' => 'succeeded', 'captured' => true,
            'amount' => 10000, 'amount_refunded' => 7000, 'refunded' => false,
        ]);
        $handlers['transfer_fetch']['response'] = $this->jsonResponse(200, [
            'id' => 'tr_n1', 'amount' => 5792, 'amount_reversed' => 0,
            'source_transaction' => 'ch_n1', 'destination' => 'acct_city123',
            'metadata' => [ 'proudcity_base_amount' => '6000', 'proudcity_base_refunded' => '4000' ],
        ]);
        $handlers['refunds_list']['response'] = $this->jsonResponse(200, ['data' => [
            [ 'id' => 're_pre', 'status' => 'succeeded', 'amount' => 4000 ],
            [ 'id' => 're_gap', 'status' => 'succeeded', 'amount' => 3000 ],
        ], 'has_more' => false]);

        $captured = [];
        $handlers['reversal_create']['response'] = function ($url, $args) use (&$captured) {
            $captured[] = $args;
            parse_str($args['body'], $parsed);
            return $this->jsonResponse(200, ['id' => 'trr_gap', 'amount' => (int) $parsed['amount']]);
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::sync_reversals('pi_n1', 'live', 42);

        // round((7000 - 4000) * 5792 / 6000) = 2896.
        $this->assertSame(1, $result['reversed']);
        parse_str($captured[0]['body'], $posted);
        $this->assertSame('2896', $posted['amount']);
        $this->assertSame('reversal-ch_n1-7000', $captured[0]['headers']['Idempotency-Key']);
    }

    /**
     * N1: calling sync_reversals() again with the SAME cumulative
     * amount_refunded (the webhook firing twice, or reconcile running
     * again with nothing new) never POSTs a second reversal -- idempotent
     * by total, not by any single refund id.
     */
    public function test_sync_reversals_does_not_double_reverse_the_same_total(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_n1';

        $handlers = $this->defaultStripeHandlers();
        $handlers['pi_fetch'] = ['method' => 'GET', 'contains' => 'payment_intents/pi_n1', 'response' => $this->jsonResponse(200, [
            'id' => 'pi_n1', 'metadata' => ['proudcity_charge_model' => 'transfers'], 'latest_charge' => ['id' => 'ch_n1'],
        ])];
        $handlers['charge_fetch']['response'] = $this->jsonResponse(200, [
            'id' => 'ch_n1', 'status' => 'succeeded', 'captured' => true,
            'amount' => 10000, 'amount_refunded' => 7000, 'refunded' => false,
        ]);
        // amount_reversed deliberately stale (0, not yet reflecting the
        // reversal below) -- this is what forces the budget-vs-already-made
        // guard to actually be exercised rather than the budget already
        // coming out to 0 on its own.
        $handlers['transfer_fetch']['response'] = $this->jsonResponse(200, [
            'id' => 'tr_n1', 'amount' => 5792, 'amount_reversed' => 0,
            'source_transaction' => 'ch_n1', 'destination' => 'acct_city123',
            'metadata' => [ 'proudcity_base_amount' => '6000', 'proudcity_base_refunded' => '4000' ],
        ]);
        // A reversal for this exact total (7000) already exists.
        $handlers['reversal_list']['response'] = $this->jsonResponse(200, ['data' => [
            [ 'metadata' => [ 'charge_amount_refunded' => '7000' ] ],
        ], 'has_more' => false]);
        $handlers['refunds_list']['response'] = $this->jsonResponse(200, ['data' => [
            [ 'id' => 're_pre', 'status' => 'succeeded', 'amount' => 4000 ],
            [ 'id' => 're_gap', 'status' => 'succeeded', 'amount' => 3000 ],
        ], 'has_more' => false]);
        $handlers['reversal_create']['response'] = function () {
            $this->fail('A reversal already exists for this exact cumulative amount_refunded.');
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::sync_reversals('pi_n1', 'live', 42);

        $this->assertSame(0, $result['reversed']);
        $this->assertSame(0, $result['errors']);
    }

    /**
     * N1: once the charge is fully refunded, the budget is exactly whatever
     * is left on the transfer -- clearing the remainder regardless of
     * proportional rounding.
     */
    public function test_sync_reversals_fully_refunded_charge_clears_remainder_exactly(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_n1';

        $handlers = $this->defaultStripeHandlers();
        $handlers['pi_fetch'] = ['method' => 'GET', 'contains' => 'payment_intents/pi_n1', 'response' => $this->jsonResponse(200, [
            'id' => 'pi_n1', 'metadata' => ['proudcity_charge_model' => 'transfers'], 'latest_charge' => ['id' => 'ch_n1'],
        ])];
        $handlers['charge_fetch']['response'] = $this->jsonResponse(200, [
            'id' => 'ch_n1', 'status' => 'succeeded', 'captured' => true,
            'amount' => 10000, 'amount_refunded' => 10000, 'refunded' => true,
        ]);
        $handlers['transfer_fetch']['response'] = $this->jsonResponse(200, [
            'id' => 'tr_n1', 'amount' => 5792, 'amount_reversed' => 2896,
            'source_transaction' => 'ch_n1', 'destination' => 'acct_city123',
            'metadata' => [ 'proudcity_base_amount' => '6000', 'proudcity_base_refunded' => '4000' ],
        ]);
        $handlers['reversal_list']['response'] = $this->jsonResponse(200, ['data' => [
            [ 'metadata' => [ 'charge_amount_refunded' => '7000' ] ],
        ], 'has_more' => false]);
        $handlers['refunds_list']['response'] = $this->jsonResponse(200, ['data' => [
            [ 'id' => 're_pre', 'status' => 'succeeded', 'amount' => 4000 ],
            [ 'id' => 're_gap', 'status' => 'succeeded', 'amount' => 3000 ],
            [ 'id' => 're_final', 'status' => 'succeeded', 'amount' => 3000 ],
        ], 'has_more' => false]);

        $captured = [];
        $handlers['reversal_create']['response'] = function ($url, $args) use (&$captured) {
            $captured[] = $args;
            parse_str($args['body'], $parsed);
            return $this->jsonResponse(200, ['id' => 'trr_final', 'amount' => (int) $parsed['amount']]);
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $result = ProudGravityformsStripe::sync_reversals('pi_n1', 'live', 42);

        // Clears exactly what's left (5792 - 2896 = 2896), not a fresh
        // proportional calculation.
        $this->assertSame(1, $result['reversed']);
        parse_str($captured[0]['body'], $posted);
        $this->assertSame('2896', $posted['amount']);
        $this->assertSame('reversal-ch_n1-10000', $captured[0]['headers']['Idempotency-Key']);
    }

    public function test_handle_payment_action_fail_payment_gives_full_reversal(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        $this->entryMeta[42]['proudcity_stripe_transfer_id'] = 'tr_123';
        GfStripeOnBehalfOfStub::$paymentFeed = ['id' => 1, 'meta' => []];

        $reversalAmount = null;

        $handlers = $this->defaultStripeHandlers();
        $handlers['charge_fetch']['response'] = $this->jsonResponse(200, [
            'id' => 'ch_123', 'status' => 'failed', 'captured' => false,
            'amount' => 2000, 'amount_refunded' => 0, 'refunded' => false,
        ]);
        $handlers['reversal_create']['response'] = function ($url, $args) use (&$reversalAmount) {
            parse_str($args['body'], $parsed);
            $reversalAmount = (int) $parsed['amount'];
            return $this->jsonResponse(200, ['id' => 'trr_fail', 'amount' => $reversalAmount]);
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

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

    /**
     * HIGH #2: Stripe's idempotency conflict sometimes comes back with no
     * error.code, only error.type -- stripe_request() has to surface that
     * too, since ensure_transfer() keys its 409 handling off the code.
     */
    public function test_stripe_request_falls_back_to_error_type_when_code_is_absent(): void
    {
        Functions\when('wp_remote_request')->justReturn($this->jsonResponse(409, [
            'error' => ['type' => 'idempotency_error', 'message' => 'Keys for idempotent requests can only be used for the same endpoint.'],
        ]));

        $result = ProudGravityformsStripe::stripe_request('POST', 'transfers', [], 'rk_live_PLATFORM', 'transfer-ch_123');

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('idempotency_error', $result->get_error_code());
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

        $handlers = $this->defaultStripeHandlers();
        $handlers['pi_search'] = ['method' => 'GET', 'contains' => 'payment_intents/search', 'response' => function ($url) use (&$searchQuery) {
            parse_str(substr($url, strpos($url, '?') + 1), $parsed);
            $searchQuery = $parsed['query'];
            return $this->jsonResponse(200, ['data' => [], 'has_more' => false]);
        }];
        $handlers['pi_fetch'] = ['method' => 'GET', 'contains' => 'payment_intents/pi_555', 'response' => $this->jsonResponse(200, $this->samplePi(['gf_entry_id' => '55']))];
        $handlers['transfer_create']['response'] = $this->jsonResponse(200, ['id' => 'tr_555', 'amount' => 1911]);

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

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
        GFAPI::$entriesById[55] = ['id' => 55, 'transaction_id' => 'pi_555'];

        $handlers = $this->defaultStripeHandlers();
        $handlers['pi_search'] = ['method' => 'GET', 'contains' => 'payment_intents/search', 'response' => $this->jsonResponse(200, [
            'data' => [['id' => 'pi_555', 'created' => time() - 700, 'metadata' => ['gf_entry_id' => '55']]],
            'has_more' => false,
        ])];
        $handlers['pi_fetch'] = ['method' => 'GET', 'contains' => 'payment_intents/pi_555', 'response' => $this->jsonResponse(200, $this->samplePi(['gf_entry_id' => '55']))];
        $handlers['transfer_lookup']['response'] = $this->jsonResponse(200, ['data' => [
            ['id' => 'tr_already', 'amount' => 1911, 'source_transaction' => 'ch_123'],
        ], 'has_more' => false]);
        $handlers['transfer_create']['response'] = function () {
            $this->fail('A transfer already found by find_existing_transfer() should not be re-created.');
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $counts = ProudGravityformsStripe::reconcile_transfers();

        $this->assertSame(1, $counts['skipped']);
        $this->assertSame('tr_already', $this->entryMeta[55]['proudcity_stripe_transfer_id']);
    }

    /**
     * NEW-5: the PI-search pass has no bound entry (gf_entry_id doesn't
     * check out and no entry matches by transaction_id), and the PI is now
     * refunded -- find_existing_transfer() still runs before giving up to
     * 'transfer_invalid', so a Transfer that actually exists counts as
     * skipped, with no alert.
     */
    public function test_reconcile_transfers_pi_search_finds_existing_transfer_for_unbound_refunded_pi(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $loggedAlerts = [];
        Functions\when('error_log')->alias(function ($line) use (&$loggedAlerts) {
            $loggedAlerts[] = $line;
        });

        $handlers = $this->defaultStripeHandlers();
        $handlers['pi_search'] = ['method' => 'GET', 'contains' => 'payment_intents/search', 'response' => $this->jsonResponse(200, [
            'data' => [['id' => 'pi_123', 'created' => time() - 700, 'metadata' => ['gf_entry_id' => 0]]],
            'has_more' => false,
        ])];
        $handlers['pi_fetch']['response'] = $this->jsonResponse(200, $this->samplePi([], ['refunded' => true]));
        $handlers['transfer_lookup']['response'] = $this->jsonResponse(200, ['data' => [
            ['id' => 'tr_already', 'amount' => 1911, 'source_transaction' => 'ch_123'],
        ], 'has_more' => false]);
        $handlers['transfer_create']['response'] = function () {
            $this->fail('A transfer already found by find_existing_transfer() should not be re-created.');
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $counts = ProudGravityformsStripe::reconcile_transfers();

        $this->assertSame(1, $counts['skipped']);
        $this->assertSame(0, $counts['errors']);
        $this->assertEmpty(array_filter($loggedAlerts, static fn ($line) => str_contains($line, 'ALERT')));
    }

    /**
     * LOW #7: a PaymentIntent younger than 10 minutes is left for the
     * completed-payment hook to transfer first, rather than racing it.
     */
    public function test_reconcile_transfers_skips_pis_younger_than_10_minutes(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $handlers = $this->defaultStripeHandlers();
        $handlers['pi_search'] = ['method' => 'GET', 'contains' => 'payment_intents/search', 'response' => $this->jsonResponse(200, [
            'data' => [['id' => 'pi_fresh', 'created' => time() - 60, 'metadata' => ['gf_entry_id' => 0]]],
            'has_more' => false,
        ])];
        $handlers['transfer_create']['response'] = function () {
            $this->fail('A PaymentIntent younger than 10 minutes should not be transferred by reconcile yet.');
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $counts = ProudGravityformsStripe::reconcile_transfers();

        $this->assertSame(0, $counts['transferred']);
        $this->assertSame(0, $counts['skipped']);
        $this->assertSame(0, $counts['errors']);
    }

    /**
     * LOW #7: every page of the PI search is fetched, following Stripe
     * search's next_page cursor, not just the first.
     */
    public function test_reconcile_transfers_paginates_pi_search(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $pages = 0;

        $handlers = $this->defaultStripeHandlers();
        $handlers['pi_search'] = ['method' => 'GET', 'contains' => 'payment_intents/search', 'response' => function ($url) use (&$pages) {
            $pages++;
            parse_str(substr($url, strpos($url, '?') + 1), $parsed);
            if (empty($parsed['page'])) {
                return $this->jsonResponse(200, [
                    'data' => [['id' => 'pi_page1', 'created' => time() - 700, 'metadata' => ['gf_entry_id' => 0]]],
                    'has_more' => true,
                    'next_page' => 'cursor-2',
                ]);
            }
            return $this->jsonResponse(200, [
                'data' => [['id' => 'pi_page2', 'created' => time() - 700, 'metadata' => ['gf_entry_id' => 0]]],
                'has_more' => false,
            ]);
        }];
        $handlers['pi_fetch'] = ['method' => 'GET', 'contains' => 'payment_intents/pi_page', 'response' => function ($url) {
            $id = str_contains($url, 'pi_page1') ? 'pi_page1' : 'pi_page2';
            return $this->jsonResponse(200, array_merge($this->samplePi(), ['id' => $id]));
        }];
        $handlers['transfer_create']['response'] = $this->jsonResponse(200, ['id' => 'tr_x', 'amount' => 1911]);

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $counts = ProudGravityformsStripe::reconcile_transfers();

        $this->assertSame(2, $pages);
        $this->assertSame(2, $counts['transferred']);
    }

    /**
     * MEDIUM #4: an entry id read off the PI's own metadata is only trusted
     * if that entry's OWN transaction_id still points back at this exact
     * PI -- otherwise it's treated as unknown (0), not bound to the wrong
     * entry's meta.
     */
    public function test_reconcile_transfers_ignores_stale_entry_id_from_pi_metadata(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        GFAPI::$entriesById[55] = ['id' => 55, 'transaction_id' => 'pi_SOMETHING_ELSE'];

        $handlers = $this->defaultStripeHandlers();
        $handlers['pi_search'] = ['method' => 'GET', 'contains' => 'payment_intents/search', 'response' => $this->jsonResponse(200, [
            'data' => [['id' => 'pi_123', 'created' => time() - 700, 'metadata' => ['gf_entry_id' => '55']]],
            'has_more' => false,
        ])];
        $handlers['transfer_create']['response'] = $this->jsonResponse(200, ['id' => 'tr_x', 'amount' => 1911]);

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $counts = ProudGravityformsStripe::reconcile_transfers();

        $this->assertSame(1, $counts['transferred']);
        $this->assertArrayNotHasKey(55, $this->entryMeta);
    }

    /**
     * Leftover: when the PI's own gf_entry_id metadata doesn't check out,
     * the entry is looked up by its transaction_id directly before giving
     * up to entry_id 0.
     */
    public function test_reconcile_transfers_finds_entry_by_transaction_id_when_gf_entry_id_is_stale(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        GFAPI::$entriesById[55] = ['id' => 55, 'transaction_id' => 'pi_SOMETHING_ELSE'];
        GFAPI::$entriesByTransactionId['pi_123'] = ['id' => 77, 'transaction_id' => 'pi_123'];

        $handlers = $this->defaultStripeHandlers();
        $handlers['pi_search'] = ['method' => 'GET', 'contains' => 'payment_intents/search', 'response' => $this->jsonResponse(200, [
            'data' => [['id' => 'pi_123', 'created' => time() - 700, 'metadata' => ['gf_entry_id' => '55']]],
            'has_more' => false,
        ])];
        $handlers['transfer_create']['response'] = $this->jsonResponse(200, ['id' => 'tr_x', 'amount' => 1911]);

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $counts = ProudGravityformsStripe::reconcile_transfers();

        $this->assertSame(1, $counts['transferred']);
        $this->assertArrayNotHasKey(55, $this->entryMeta);
        $this->assertSame('tr_x', $this->entryMeta[77]['proudcity_stripe_transfer_id']);
    }

    /**
     * N3: the PI-search pass never re-processes (or re-alerts) an entry
     * already known 'created' or 'invalid'.
     */
    public function test_reconcile_transfers_skips_pis_already_created_or_invalid(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');
        GFAPI::$entriesById[55] = ['id' => 55, 'transaction_id' => 'pi_created'];
        GFAPI::$entriesById[56] = ['id' => 56, 'transaction_id' => 'pi_invalid'];
        $this->entryMeta[55]['proudcity_stripe_transfer_status'] = 'created';
        $this->entryMeta[56]['proudcity_stripe_transfer_status'] = 'invalid';

        $handlers = $this->defaultStripeHandlers();
        $handlers['pi_search'] = ['method' => 'GET', 'contains' => 'payment_intents/search', 'response' => $this->jsonResponse(200, [
            'data' => [
                ['id' => 'pi_created', 'created' => time() - 700, 'metadata' => ['gf_entry_id' => '55']],
                ['id' => 'pi_invalid', 'created' => time() - 700, 'metadata' => ['gf_entry_id' => '56']],
            ],
            'has_more' => false,
        ])];
        $handlers['pi_fetch'] = ['method' => 'GET', 'contains' => 'payment_intents/pi_', 'response' => function () {
            $this->fail('An entry already created or invalid should never be re-fetched by the PI-search pass.');
        }];

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $counts = ProudGravityformsStripe::reconcile_transfers();

        $this->assertSame(0, $counts['transferred']);
        $this->assertSame(0, $counts['skipped']);
        $this->assertSame(0, $counts['errors']);
    }

    /**
     * Leftover: a pending entry is retried once it's old enough (not
     * immediately, to avoid racing an in-flight request), and alerted once
     * it's been pending for over 24h.
     */
    public function test_reconcile_transfers_retries_old_pending_entries_and_alerts_past_24h(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        GFAPI::$entries = [
            ['id' => 55, 'transaction_id' => 'pi_123'],
        ];
        $this->entryMeta[55]['proudcity_stripe_transfer_status'] = 'pending';
        $this->entryMeta[55]['proudcity_stripe_charge_id'] = 'ch_123';
        $this->entryMeta[55]['proudcity_stripe_transfer_pending_since'] = (string) ( time() - DAY_IN_SECONDS - 100 );

        $loggedAlerts = [];
        Functions\when('error_log')->alias(function ($line) use (&$loggedAlerts) {
            $loggedAlerts[] = $line;
        });

        $handlers = $this->defaultStripeHandlers();
        $handlers['pi_search'] = ['method' => 'GET', 'contains' => 'payment_intents/search', 'response' => $this->jsonResponse(200, ['data' => [], 'has_more' => false])];
        $handlers['transfer_create']['response'] = $this->jsonResponse(200, ['id' => 'tr_pending', 'amount' => 1911]);

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $counts = ProudGravityformsStripe::reconcile_transfers();

        $this->assertSame(1, $counts['retried']);
        $this->assertSame('tr_pending', $this->entryMeta[55]['proudcity_stripe_transfer_id']);
        $this->assertNotEmpty(array_filter($loggedAlerts, static fn ($line) => str_contains($line, 'transfer_pending_too_long')));
    }

    public function test_reconcile_transfers_does_not_retry_a_freshly_pending_entry(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        GFAPI::$entries = [
            ['id' => 55, 'transaction_id' => 'pi_123'],
        ];
        $this->entryMeta[55]['proudcity_stripe_transfer_status'] = 'pending';
        $this->entryMeta[55]['proudcity_stripe_charge_id'] = 'ch_123';
        $this->entryMeta[55]['proudcity_stripe_transfer_pending_since'] = (string) ( time() - 30 );

        $handlers = $this->defaultStripeHandlers();
        $handlers['pi_search'] = ['method' => 'GET', 'contains' => 'payment_intents/search', 'response' => $this->jsonResponse(200, ['data' => [], 'has_more' => false])];
        $handlers['transfer_create']['response'] = function () {
            $this->fail('A pending entry younger than 10 minutes should not be retried yet.');
        };

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        $counts = ProudGravityformsStripe::reconcile_transfers();

        $this->assertSame(0, $counts['retried']);
    }

    /**
     * LOW #7: every page of GF entries is fetched too, not just the first
     * page_size's worth.
     */
    public function test_reconcile_transfers_paginates_failed_entries(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        GFAPI::$entries = [];
        for ($i = 1; $i <= 101; $i++) {
            GFAPI::$entries[] = ['id' => $i, 'transaction_id' => 'pi_' . $i];
            $this->entryMeta[$i]['proudcity_stripe_transfer_status'] = 'failed';
        }

        $attempts = 0;

        $handlers = $this->defaultStripeHandlers();
        $handlers['pi_search'] = ['method' => 'GET', 'contains' => 'payment_intents/search', 'response' => $this->jsonResponse(200, ['data' => [], 'has_more' => false])];
        $handlers['pi_fetch'] = ['method' => 'GET', 'contains' => 'payment_intents/pi_', 'response' => function () use (&$attempts) {
            $attempts++;
            return $this->jsonResponse(404, ['error' => ['code' => 'resource_missing', 'message' => 'nope']]);
        }];

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));

        ProudGravityformsStripe::reconcile_transfers();

        $this->assertSame(101, $attempts);
    }

    /**
     * LOW #7: an exception anywhere in the pass is caught rather than left
     * to break the cron run.
     */
    public function test_reconcile_transfers_catches_exceptions(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        $this->entryMeta[77]['proudcity_stripe_transfer_status'] = 'failed';
        GFAPI::$entries = [
            ['id' => 77, 'transaction_id' => 'pi_77'],
        ];

        $handlers = $this->defaultStripeHandlers();
        $handlers['pi_search'] = ['method' => 'GET', 'contains' => 'payment_intents/search', 'response' => $this->jsonResponse(200, ['data' => [], 'has_more' => false])];

        Functions\when('wp_remote_request')->alias($this->stripeRouter($handlers));
        Functions\when('gform_get_meta')->alias(function () {
            throw new \RuntimeException('boom');
        });

        $counts = ProudGravityformsStripe::reconcile_transfers();

        $this->assertSame(1, $counts['errors']);
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

    // -------------------------------------------------------------------
    // strip_reverse_transfer_for_our_refunds() (GF Refund button on an
    // SC&T charge)
    // -------------------------------------------------------------------

    private function gfRefundArgs(string $key, array $bodyOverrides = [], bool $bodyAsString = false): array
    {
        $body = array_merge([
            'payment_intent' => 'pi_123',
            'reverse_transfer' => 'true',
            'refund_application_fee' => 'true',
        ], $bodyOverrides);

        return [
            'method' => 'POST',
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($key . ':'),
            ],
            'body' => $bodyAsString ? http_build_query($body) : $body,
        ];
    }

    public function test_refund_filter_strips_flags_for_marked_pi_array_body(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        Functions\when('wp_remote_request')->alias(function ($url, $args) {
            return $this->jsonResponse(200, $this->samplePi());
        });

        $result = ProudGravityformsStripe::strip_reverse_transfer_for_our_refunds(
            $this->gfRefundArgs('sk_live_GFOAUTHTOKEN'),
            ProudGravityformsStripe::STRIPE_API_BASE . 'refunds'
        );

        $this->assertArrayNotHasKey('reverse_transfer', $result['body']);
        $this->assertArrayNotHasKey('refund_application_fee', $result['body']);
        $this->assertSame('pi_123', $result['body']['payment_intent']);
    }

    public function test_refund_filter_strips_flags_for_marked_pi_string_body(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        Functions\when('wp_remote_request')->alias(function ($url, $args) {
            return $this->jsonResponse(200, $this->samplePi());
        });

        $result = ProudGravityformsStripe::strip_reverse_transfer_for_our_refunds(
            $this->gfRefundArgs('sk_live_GFOAUTHTOKEN', [], true),
            ProudGravityformsStripe::STRIPE_API_BASE . 'refunds'
        );

        $this->assertIsString($result['body']);
        parse_str($result['body'], $parsed);
        $this->assertArrayNotHasKey('reverse_transfer', $parsed);
        $this->assertArrayNotHasKey('refund_application_fee', $parsed);
        $this->assertSame('pi_123', $parsed['payment_intent']);
    }

    public function test_refund_filter_resolves_a_charge_id_to_its_payment_intent(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        Functions\when('wp_remote_request')->alias(function ($url, $args) {
            if (str_contains($url, 'charges/ch_123')) {
                return $this->jsonResponse(200, ['id' => 'ch_123', 'payment_intent' => 'pi_123']);
            }

            return $this->jsonResponse(200, $this->samplePi());
        });

        $result = ProudGravityformsStripe::strip_reverse_transfer_for_our_refunds(
            $this->gfRefundArgs('sk_live_GFOAUTHTOKEN', ['charge' => 'ch_123', 'payment_intent' => null]),
            ProudGravityformsStripe::STRIPE_API_BASE . 'refunds'
        );

        $this->assertArrayNotHasKey('reverse_transfer', $result['body']);
    }

    public function test_refund_filter_leaves_unmarked_charge_untouched(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        Functions\when('wp_remote_request')->alias(function ($url, $args) {
            return $this->jsonResponse(200, $this->samplePi(['proudcity_charge_model' => 'destination']));
        });

        $args = $this->gfRefundArgs('sk_live_GFOAUTHTOKEN');
        $result = ProudGravityformsStripe::strip_reverse_transfer_for_our_refunds(
            $args,
            ProudGravityformsStripe::STRIPE_API_BASE . 'refunds'
        );

        $this->assertSame($args, $result);
    }

    /**
     * N2: a marker that fails the structural "is ours" check is never
     * trusted to strip GF's reverse_transfer/refund_application_fee
     * flags, same as build_transfer_args() -- doing so on a genuinely
     * destination-model charge would break GF's own refund.
     */
    public function test_refund_filter_does_not_strip_flags_for_a_forged_marker(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        Functions\when('wp_remote_request')->alias(function ($url, $args) {
            return $this->jsonResponse(200, $this->samplePi([], ['transfer_data' => ['destination' => 'acct_city123']]));
        });

        $args = $this->gfRefundArgs('sk_live_GFOAUTHTOKEN');
        $result = ProudGravityformsStripe::strip_reverse_transfer_for_our_refunds(
            $args,
            ProudGravityformsStripe::STRIPE_API_BASE . 'refunds'
        );

        $this->assertSame($args, $result);
    }

    public function test_refund_filter_ignores_non_refund_urls(): void
    {
        Functions\when('wp_remote_request')->alias(function () {
            $this->fail('A non-refund URL should never reach the Stripe API.');
        });

        $args = $this->gfRefundArgs('sk_live_GFOAUTHTOKEN');
        $result = ProudGravityformsStripe::strip_reverse_transfer_for_our_refunds(
            $args,
            ProudGravityformsStripe::STRIPE_API_BASE . 'charges'
        );

        $this->assertSame($args, $result);
    }

    public function test_refund_filter_ignores_get_requests(): void
    {
        Functions\when('wp_remote_request')->alias(function () {
            $this->fail('A GET request should never reach the Stripe API.');
        });

        $args = $this->gfRefundArgs('sk_live_GFOAUTHTOKEN');
        $args['method'] = 'GET';

        $result = ProudGravityformsStripe::strip_reverse_transfer_for_our_refunds(
            $args,
            ProudGravityformsStripe::STRIPE_API_BASE . 'refunds'
        );

        $this->assertSame($args, $result);
    }

    public function test_refund_filter_ignores_unrelated_wordpress_requests(): void
    {
        Functions\when('wp_remote_request')->alias(function () {
            $this->fail('An unrelated WordPress HTTP request should never reach the Stripe API.');
        });

        $args = ['method' => 'POST', 'headers' => [], 'body' => ['anything' => 'here']];

        $result = ProudGravityformsStripe::strip_reverse_transfer_for_our_refunds(
            $args,
            'https://api.wordpress.org/core/version-check/1.7/'
        );

        $this->assertSame($args, $result);
    }

    public function test_refund_filter_is_a_noop_without_a_transfer_key(): void
    {
        Functions\when('wp_remote_request')->alias(function () {
            $this->fail('No transfer key is configured, so there should be no Stripe round trip at all.');
        });

        $args = $this->gfRefundArgs('sk_live_GFOAUTHTOKEN');
        $result = ProudGravityformsStripe::strip_reverse_transfer_for_our_refunds(
            $args,
            ProudGravityformsStripe::STRIPE_API_BASE . 'refunds'
        );

        $this->assertSame($args, $result);
    }

    public function test_refund_filter_returns_args_unchanged_on_stripe_error(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        Functions\when('wp_remote_request')->alias(function ($url, $args) {
            return $this->jsonResponse(404, ['error' => ['code' => 'resource_missing', 'message' => 'No such PaymentIntent.']]);
        });

        $args = $this->gfRefundArgs('sk_live_GFOAUTHTOKEN');
        $result = ProudGravityformsStripe::strip_reverse_transfer_for_our_refunds(
            $args,
            ProudGravityformsStripe::STRIPE_API_BASE . 'refunds'
        );

        $this->assertSame($args, $result);
    }

    public function test_refund_filter_unrecognised_key_prefix_is_untouched(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        Functions\when('wp_remote_request')->alias(function () {
            $this->fail('An unrecognised key prefix should never reach the Stripe API.');
        });

        $args = $this->gfRefundArgs('whatever_this_is');
        $result = ProudGravityformsStripe::strip_reverse_transfer_for_our_refunds(
            $args,
            ProudGravityformsStripe::STRIPE_API_BASE . 'refunds'
        );

        $this->assertSame($args, $result);
    }

    /**
     * LOW #10: a malformed charge id in GF's own refund request body (which
     * this code would otherwise interpolate straight into a Stripe request
     * path to resolve it to a PaymentIntent) is refused before any Stripe
     * call is made.
     */
    public function test_refund_filter_ignores_malformed_charge_id(): void
    {
        putenv('PROUDCITY_PAYMENTS_TRANSFER_KEY=rk_live_PLATFORM');

        Functions\when('wp_remote_request')->alias(function () {
            $this->fail('Must not build a Stripe request path from a malformed charge id.');
        });

        $args = $this->gfRefundArgs('sk_live_GFOAUTHTOKEN', ['charge' => 'ch_123/../transfers', 'payment_intent' => null]);
        $result = ProudGravityformsStripe::strip_reverse_transfer_for_our_refunds(
            $args,
            ProudGravityformsStripe::STRIPE_API_BASE . 'refunds'
        );

        $this->assertSame($args, $result);
    }
}
