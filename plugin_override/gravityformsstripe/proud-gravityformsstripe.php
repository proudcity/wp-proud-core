<?php
// phpcs:disable
namespace Proud\GravityformsStripe;

class ProudGravityformsStripe {

 /**
  * Original live_secret_key stashed by use_platform_secret_for_connect() so
  * restore_stored_secret() can put it back before settings are saved.
  *
  * @since 2026.10.06
  */
 private static $stashed_live_secret_key = null;

 /**
  * Original live_publishable_key stashed by use_platform_secret_for_connect()
  * so restore_stored_secret() can put it back before settings are saved.
  *
  * @since 2026.10.06
  */
 private static $stashed_live_publishable_key = null;

 /**
  * Whether use_platform_secret_for_connect() has stashed keys this request.
  *
  * @since 2026.10.06
  */
 private static $stash_active = false;

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

  add_filter( 'option_gravityformsaddon_gravityformsstripe_settings', [ $this, 'use_platform_secret_for_connect' ] );
  add_filter( 'pre_update_option_gravityformsaddon_gravityformsstripe_settings', [ $this, 'restore_stored_secret' ], 10, 2 );
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

 /**
  * Our platform's live Stripe secret, same source order as
  * gform_stripe_post_include_api(): the proudcity_payments_secret option
  * first, falling back to the PROUDCITY_PAYMENTS_SECRET env var.
  *
  * Only ever returns a live key (sk_live_ or rk_live_), never a test key, so
  * a misconfigured test secret can't get swapped into a live GF settings
  * read and break real payments.
  *
  * @since 2026.10.06
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2951
  *
  * @return string
  */
 public static function get_platform_secret(){

	$secret = get_option( 'proudcity_payments_secret', false );
	$secret = ! empty( $secret ) ? $secret : getenv( 'PROUDCITY_PAYMENTS_SECRET' );

	if ( is_string( $secret ) && ( str_starts_with( $secret, 'sk_live_' ) || str_starts_with( $secret, 'rk_live_' ) ) ){
	 return $secret;
	}

	return '';

 } // get_platform_secret

 /**
  * Our platform's live Stripe publishable key, from the PROUDCITY_PAYMENTS_PUBLIC
  * env var. No proudcity_payments_* option fallback exists for this one.
  *
  * Only ever returns a live key (pk_live_), never a test key, for the same
  * reason as get_platform_secret().
  *
  * @since 2026.10.06
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2951
  *
  * @return string
  */
 public static function get_platform_publishable_key(){

	$key = getenv( 'PROUDCITY_PAYMENTS_PUBLIC' );

	if ( is_string( $key ) && str_starts_with( $key, 'pk_live_' ) ){
	 return $key;
	}

	return '';

 } // get_platform_publishable_key

 /**
  * Swaps GF Stripe's stored live_secret_key AND live_publishable_key for our
  * platform's key pair when the add-on is connected via "Connect with
  * Stripe" (GF Stripe 7.0).
  *
  * Connect mode signs every request with GF's own OAuth access token
  * (live_secret_key, set alongside live_auth_token), which makes our
  * platform a connected account of GF's Stripe app rather than our own.
  * That rejects the destination charges add_transfer_meta() builds. Swapping
  * in our platform secret at read time makes GF sign requests with our key
  * instead, with no change to how the token is stored. Interim fix until
  * #2955, which replaces Connect mode entirely.
  *
  * live_publishable_key has to move with it: GF also stores the OAuth-issued
  * publishable key and Stripe.js in the browser confirms the PaymentIntent
  * with whatever pk GF outputs. Swapping only the secret leaves the browser
  * acting as GF's app while the PaymentIntent belongs to our platform, and
  * Stripe rejects the confirm ("client_secret ... does not match"). The pair
  * is swapped all-or-nothing -- if either platform key is missing, nothing
  * is swapped, since a mismatched pair is exactly this bug.
  *
  * Skipped on wp-admin page loads that aren't AJAX: GF renders
  * live_secret_key into a hidden field on its own settings page in Connect
  * mode (gravityformsstripe/class-gf-stripe.php:1192-1195), so without this
  * skip the platform secret would be printed into admin HTML and saved back
  * to the DB as the new live_secret_key.
  *
  * Also skipped for the 'gfstripe_configure_webhooks' AJAX action
  * (gravityformsstripe/includes/webhooks/class-admin.php:168-197): that
  * handler lists/deletes this site's webhook endpoints on the Stripe account
  * and recreates one at GF's own API version, overwriting
  * live_signing_secret -- with the platform key that would undo our webhook
  * consolidation (#2339/#2849 onto 2026-06-24.dahlia).
  *
  * @since 2026.10.06
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2951
  *
  * @param  mixed  $settings  required  The gravityformsaddon_gravityformsstripe_settings option value
  * @return mixed
  */
 public static function use_platform_secret_for_connect( $settings ){

	if ( ! is_array( $settings ) ){
	 return $settings;
	}

	if ( empty( $settings['live_auth_token'] ) ){
	 return $settings;
	}

	$platform_secret = self::get_platform_secret();
	$platform_publishable_key = self::get_platform_publishable_key();

	if ( '' === $platform_secret || '' === $platform_publishable_key ){
	 return $settings;
	}

	if ( is_admin() && ! wp_doing_ajax() ){
	 return $settings;
	}

	if ( wp_doing_ajax() && 'gfstripe_configure_webhooks' === sanitize_key( wp_unslash( $_REQUEST['action'] ?? '' ) ) ){
	 return $settings;
	}

	self::$stashed_live_secret_key = $settings['live_secret_key'] ?? null;
	self::$stashed_live_publishable_key = $settings['live_publishable_key'] ?? null;
	self::$stash_active = true;

	$settings['live_secret_key'] = $platform_secret;
	$settings['live_publishable_key'] = $platform_publishable_key;

	return $settings;

 } // use_platform_secret_for_connect

 /**
  * Restores the stored live_secret_key and live_publishable_key before
  * gravityformsaddon_gravityformsstripe_settings is written to the DB, so
  * the platform key pair swapped in by use_platform_secret_for_connect() is
  * never persisted.
  *
  * Each key is restored independently: live_secret_key is put back only if
  * it still carries the platform secret, and likewise live_publishable_key
  * against the platform publishable key. That way an admin who updated one
  * key (or GF's own deauthorize flow, which clears live_secret_key) while
  * the other still carries the swapped-in value doesn't get the untouched
  * key clobbered.
  *
  * $old_value isn't reliable here: update_option() builds it by calling
  * get_option(), which already runs through use_platform_secret_for_connect()
  * and so would already contain the platform secret, not the stored value.
  *
  * @since 2026.10.06
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2951
  *
  * @param  mixed  $value      required  The new option value about to be saved
  * @param  mixed  $old_value  required  Unused, see above
  * @return mixed
  */
 public static function restore_stored_secret( $value, $old_value ){

	if ( ! is_array( $value ) ){
	 return $value;
	}

	if ( ! self::$stash_active ){
	 return $value;
	}

	if ( ( $value['live_secret_key'] ?? null ) === self::get_platform_secret() ){
	 $value['live_secret_key'] = self::$stashed_live_secret_key;
	}

	if ( ( $value['live_publishable_key'] ?? null ) === self::get_platform_publishable_key() ){
	 $value['live_publishable_key'] = self::$stashed_live_publishable_key;
	}

	return $value;

 } // restore_stored_secret


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
