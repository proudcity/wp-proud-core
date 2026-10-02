<?php
// phpcs:disable
namespace Proud\GravityformsStripe;

class ProudGravityformsStripe {

 /**
  * Constructor
  */
 public function __construct() {
  add_filter('gform_stripe_post_include_api', [ $this, 'gform_stripe_post_include_api' ], 10, 5);
  //add_filter('gform_stripe_charge_pre_create', [ $this, 'gform_stripe_charge_pre_create' ], 10, 5);
  //
  add_filter( 'gform_stripe_enable_rate_limits', [ $this, 'rate_limits' ] );

  add_filter( 'gform_stripe_payment_intent_pre_create', [ $this, 'add_transfer_meta' ], 10, 2 );

  add_filter( 'gform_get_form_filter', [ $this, 'payment_element_on_behalf_of' ], 10, 2 );

  add_filter( 'gform_stripe_connect_enabled', [ $this, '__return_false' ] );
  // add_filter('gform_stripe_create_customer', [$this, 'gform_stripe_create_customer'], 10, 1);
  // add_filter('gform_stripe_create_plan', [$this, 'gform_stripe_create_plan'], 10, 1);
  // add_filter('gform_stripe_get_plan', [$this, 'gform_stripe_get_plan'], 10, 1);
  // add_filter('gform_stripe_update_subscription', [$this, 'gform_stripe_update_subscription'], 10, 2);

  // add_filter('gform_stripe_subscription_single_payment_amount', [$this, 'gform_stripe_subscription_single_payment_amount'], 10, 4);
  // add_filter('gform_stripe_subscription_trial_period_days', [$this, 'gform_stripe_subscription_trial_period_days'], 10, 3);
  // add_filter('gform_stripe_post_create_subscription', [$this, 'gform_stripe_post_create_subscription'], 10, 2);

 }

 /**
  *  Turning off rate limits for local development
  *
  *  @author Curtis
  *  @since 2023.01.19
  */
 public static function rate_limits(){

 if ( 'local' === wp_get_environment_type() ){
  return false;
 }

 return true;

 } // rate_limits

 /**
  * Adds our transfer metadata to the Stripe payment intent
  *
  * @since 2023.01.04
  * @author Curtis
  *
  * @param  array   $data    required    The payment information
  *  - payment_method => some long key
  *  - amount => the payment amount for original payment
  *  - currency => USD
  *  - capture_method => manual
  *  - confirmation_method => manual
  *  - confirm => (nothing here)
  * @param  array   $feed    required    Feed information
  *  - docs here: https://github.com/proudcity/developers/blob/main/Github%20Issue%20Notes/2153%20-%20Gravity%20Forms%20and%20Stripe%20issue.md
  */
 public static function add_transfer_meta( $data, $feed ){

	// GF Stripe 7.0 stopped sending payment_method_types for the Card Element, so
	// Stripe fell back to automatic payment methods evaluated against on_behalf_of
	// and rejected the intent with "No valid payment method types". The Payment
	// Element always sets one of these, so this only restores the 6.x Card Element value.
	// https://github.com/proudcity/wp-proudcity/issues/2951
	if ( empty( $data['payment_method_types'] ) && empty( $data['automatic_payment_methods'] ) ){
	 $data['payment_method_types'] = [ 'card' ];
	}

	// skip all this stuff if we're on proudcity
	if ( "https://proudcity.com" === site_url() ){ return $data; }

	update_option( 'proud_log_payments_ran', time(), false );

	// Stripe connect destination so payments are sent to customers directly
	$transfer_account = self::get_transfer_account();

	do_action( 'proud_gfstripe_pre_payment_fees', $transfer_account, $data, $feed );

	if ( $transfer_account ){

	// getting percentage fee for a customer
	$percent = getenv('PROUDCITY_PAYMENTS_PERCENT') ? (float)getenv('PROUDCITY_PAYMENTS_PERCENT') : 3.05;

	// converting int to decimal value
	$percent_dec = $percent / 100;

	// figuring out what the value is less the fee
	$fee_amount_percent = round( (int) $data['amount'] / ( 1 + $percent_dec ) ); // In cents
	$fee_minus_thirty = $fee_amount_percent - 30;

	// subtracting the value without the fee from the incoming
	// value to get the fee we should be charging via stripe
	$pc_fee = $data['amount'] - $fee_minus_thirty;

	// getting form suffix
	$suffix = get_option('proudcity_payments_descriptor', get_bloginfo('name'));

	// getting form title
	$form = \GFAPI::get_form( absint( $feed['form_id'] ) );
	$form_title = $form['title'];

	$data['on_behalf_of'] = (string) $transfer_account;
	$data['statement_descriptor_suffix'] = (string) $suffix;
	$data['application_fee_amount'] = (int) $pc_fee;
	$data['transfer_data']['destination'] = (string) $transfer_account;
	$data['transfer_group'] = (string) $form_title;

	do_action( 'proud_gfstripe_post_payment_fees', $transfer_account, $data, $feed );

  } // if $transfer_account

  //error_log( print_r( $data, true ) );

  return $data;

 }

 /**
  * Connected account payments are transferred to, or false when there isn't one
  *
  * Shared by add_transfer_meta() and payment_element_on_behalf_of() so the
  * server-side on_behalf_of and the client-side onBehalfOf can't drift apart.
  *
  * @since 2026.09.29
  * @author Curtis
  *
  * @return string|false
  */
 public static function get_transfer_account(){

	if ( "https://proudcity.com" === site_url() ){ return false; }

	return get_option( 'proudcity_payments_account', false );

 } // get_transfer_account

 /**
  * Passes our connected account to the Stripe Payment Element as onBehalfOf
  *
  * The Payment Element initializes Stripe Elements client-side before the
  * PaymentIntent exists. add_transfer_meta() then sets on_behalf_of on the
  * PaymentIntent, and Stripe rejects confirmation unless both match:
  * "The provided on_behalf_of (acct_...) does not match the expected on_behalf_of (null)."
  *
  * Uses the gform/stripe/elements/config/ JS filter added in GF Stripe 7.0.
  * Only applies in payment mode. add_transfer_meta() hooks PaymentIntents,
  * not subscriptions, so subscriptions carry no on_behalf_of to match.
  *
  * @since 2026.09.29
  * @author Curtis
  * @link https://docs.gravityforms.com/gform-stripe-elements-config
  * @link https://github.com/proudcity/wp-proudcity/issues/2947
  *
  * @param  string  $form_string  required  The form markup
  * @param  array   $form         required  The form object
  * @return string
  */
 public static function payment_element_on_behalf_of( $form_string, $form ){

	if ( ! function_exists( 'gf_stripe' ) || ! gf_stripe()->is_payment_element_enabled( $form ) ){
	 return $form_string;
	}

	$transfer_account = self::get_transfer_account();

	if ( empty( $transfer_account ) ){
	 return $form_string;
	}

	$account = wp_json_encode( (string) $transfer_account );

	$script = "<script>
	gform.initializeOnLoaded( function() {
	 if ( window.proudStripeOnBehalfOf || ! window.gform.utils || ! window.gform.utils.addAsyncFilter ) { return; }
	 window.proudStripeOnBehalfOf = true;
	 window.gform.utils.addAsyncFilter( 'gform/stripe/elements/config/', function( config ) {
	  if ( config && 'payment' === config.mode ) {
	   config.onBehalfOf = {$account};
	  }
	  return config;
	 } );
	} );
	</script>";

	return $form_string . $script;

 } // payment_element_on_behalf_of

 function __return_false($stripe_connect_enabled) {
  if (get_option('proudcity_payments_gravityformsstripe_legacy_settings', false)) {
   $stripe_connect_enabled = false;
  }

  return $stripe_connect_enabled;
 }

 /**
  * We override the secret from GF settings, with the PC master secret, typically passed from ENV vars.
  *
  * @return void
  */
 function gform_stripe_post_include_api() {
  $secret = get_option('proudcity_payments_secret', false);
  $secret = !empty($secret) ? $secret : getenv('PROUDCITY_PAYMENTS_SECRET');
  \Stripe\Stripe::setApiKey( $secret );
 }


 function gform_stripe_charge_pre_create($charge_meta, $feed, $submission_data, $form, $entry) {
  $account = get_option('proudcity_payments_account', false);

  // Stripe Connect stuff
  if ($account) {
   // Add the ProudCity Payments fee
   $percent = getenv('PROUDCITY_PAYMENTS_PERCENT') ? (float)getenv('PROUDCITY_PAYMENTS_PERCENT') : 3;
   $charge_meta['application_fee_amount'] = round(30 + $submission_data['payment_amount'] * $percent); // In cents

   // Set up Stripe Connect destination
   // destination is the connect account ID for the customer. It's set as the option 'proudcity_payments_account
   // percent is set as environment variable or defaults to 3%
   // connected accounts in the test Stripe account is NOT a stripe "connected" account - confirm that our test account can be used in Connect and that we have a valid connect parameter set for 'destination'
   // is Gravity Forms doing something with Stripe connect to make some extra money?? (curtis doesn't think this feels right)
   // payment here: https://dashboard.stripe.com/payments/ch_3MGoYRK3yBBQrr5C0jeITQTp
   // - it all looks good but it says 'uncaptured' so why is that
   // - our fee didn't get added probably as well1
   $charge_meta['transfer_data']['destination'] = $account;
   $charge_meta['transfer_group'] = $form['title'];

   // Add the statement descriptor suffix
   // Will be in the form `ProudCity * $descriptor` (can be 22 characters total)
   // Example: `ProudCity * San Rafael` (22 chars)
   // https://stripe.com/docs/statement-descriptors
   $descriptor = get_option('proudcity_payments_descriptor', get_bloginfo('name'));
   $charge_meta['statement_descriptor_suffix'] = $descriptor;
  }

  // Add Metadata
  $charge_meta['description'] = $form['title'];
  $charge_meta['metadata']['form_title'] = $form['title'];
  $charge_meta['metadata']['form_id'] = $form['id'];
  $charge_meta['metadata']['entry_id'] = $entry['id'];

  // print_r($charge_meta);exit;

  return $charge_meta;

  //print_R($submission_data);
  //print_r($feed);
  //print_r($entry);
  //print_r($form);
  //print_r($charge_meta);
  //exit;
 }

}

new ProudGravityformsStripe;
