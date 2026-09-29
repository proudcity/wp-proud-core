<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use Proud\GravityformsStripe\ProudGravityformsStripe;

/**
 * Stand-in for the GFStripe add-on instance returned by gf_stripe().
 */
class GfStripeOnBehalfOfStub
{
    public static $paymentElementEnabled = true;

    public function is_payment_element_enabled($form)
    {
        return self::$paymentElementEnabled;
    }
}

// add_transfer_meta() reads the form title for transfer_group.
if (!class_exists('GFAPI')) {
    class GFAPI
    {
        public static function get_form($form_id)
        {
            return ['id' => $form_id, 'title' => 'Test Form'];
        }
    }
}

if (!function_exists('gf_stripe')) {
    function gf_stripe()
    {
        return new GfStripeOnBehalfOfStub();
    }
}

/**
 * Tests for the Payment Element onBehalfOf handling in
 * plugin_override/gravityformsstripe/proud-gravityformsstripe.php (issue #2947).
 *
 * The Payment Element initializes Stripe Elements client-side before the
 * PaymentIntent exists, and add_transfer_meta() sets on_behalf_of on that
 * PaymentIntent server-side. Stripe refuses confirmation unless both match,
 * so the two must always be derived from the same account.
 */
class GravityformsStripeOnBehalfOfTest extends TestCase
{
    private $options;
    private $siteUrl;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        GfStripeOnBehalfOfStub::$paymentElementEnabled = true;
        $this->options = ['proudcity_payments_account' => 'acct_1TestAccount'];
        $this->siteUrl = 'https://example.gov';

        Functions\when('get_option')->alias(function ($name, $default = false) {
            return array_key_exists($name, $this->options) ? $this->options[$name] : $default;
        });
        Functions\when('site_url')->alias(function () {
            return $this->siteUrl;
        });
        Functions\when('wp_json_encode')->alias('json_encode');
        Functions\when('update_option')->justReturn(true);
        Functions\when('do_action')->justReturn(null);
        Functions\when('get_bloginfo')->justReturn('Example');
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_payment_element_form_gets_on_behalf_of_script(): void
    {
        $out = ProudGravityformsStripe::payment_element_on_behalf_of('<form></form>', ['id' => 1]);

        $this->assertStringStartsWith('<form></form>', $out);
        $this->assertStringContainsString("'gform/stripe/elements/config/'", $out);
        $this->assertStringContainsString('config.onBehalfOf = "acct_1TestAccount";', $out);
    }

    public function test_script_only_sets_on_behalf_of_in_payment_mode(): void
    {
        $out = ProudGravityformsStripe::payment_element_on_behalf_of('', ['id' => 1]);

        // add_transfer_meta() hooks PaymentIntents only; subscriptions carry
        // no on_behalf_of, so setting it there would create the mismatch.
        $this->assertStringContainsString("'payment' === config.mode", $out);
    }

    public function test_card_element_form_is_untouched(): void
    {
        GfStripeOnBehalfOfStub::$paymentElementEnabled = false;

        $this->assertSame('<form></form>', ProudGravityformsStripe::payment_element_on_behalf_of('<form></form>', ['id' => 1]));
    }

    public function test_no_transfer_account_is_untouched(): void
    {
        $this->options = [];

        $this->assertSame('<form></form>', ProudGravityformsStripe::payment_element_on_behalf_of('<form></form>', ['id' => 1]));
    }

    public function test_proudcity_site_is_untouched(): void
    {
        $this->siteUrl = 'https://proudcity.com';

        $this->assertSame('<form></form>', ProudGravityformsStripe::payment_element_on_behalf_of('<form></form>', ['id' => 1]));
    }

    public function test_account_value_cannot_break_out_of_script(): void
    {
        $this->options['proudcity_payments_account'] = 'acct_x"; alert(1); "</script><script>';

        $out = ProudGravityformsStripe::payment_element_on_behalf_of('', ['id' => 1]);

        $this->assertStringNotContainsString('</script><script>', $out);
        $this->assertStringContainsString('config.onBehalfOf = "acct_x\"; alert(1); \"<\/script><script>";', $out);
    }

    public function test_client_and_server_use_the_same_account(): void
    {
        $data = ProudGravityformsStripe::add_transfer_meta(['amount' => 10000], ['form_id' => 1]);
        $out  = ProudGravityformsStripe::payment_element_on_behalf_of('', ['id' => 1]);

        $this->assertSame('acct_1TestAccount', $data['on_behalf_of']);
        $this->assertStringContainsString('config.onBehalfOf = ' . json_encode($data['on_behalf_of']) . ';', $out);
    }
}
