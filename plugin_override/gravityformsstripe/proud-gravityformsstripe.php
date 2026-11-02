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
  * Stripe API version every wp_remote_request() call in this file pins via
  * the Stripe-Version header, matching the webhook endpoint GF Stripe
  * recreated for #2339/#2849, so the transfer/reversal code and the webhook
  * handler always agree on event and object shapes.
  *
  * @since 2026.11.02
  */
 const STRIPE_API_VERSION = '2026-06-24.dahlia';

 /**
  * Base URL for the direct wp_remote_request() calls stripe_request() makes
  * with our own platform restricted key, never GF's. No Stripe SDK is used.
  *
  * @since 2026.11.02
  */
 const STRIPE_API_BASE = 'https://api.stripe.com/v1/';

 /**
  * Stable prefix for every alert-worthy log line below, so the GCP
  * log-based alert can filter on " ALERT " without matching the plain
  * [proudcity-payments] notices (for example the account mismatch log in
  * build_transfer_args()) that aren't failures.
  *
  * @since 2026.11.02
  */
 const ALERT_LOG_PREFIX = '[proudcity-payments] ALERT ';

 /**
  * Constructor
  */
 public function __construct() {
  add_filter( 'gform_stripe_enable_rate_limits', [ $this, 'rate_limits' ] );

  add_filter( 'gform_stripe_payment_intent_pre_create', [ $this, 'add_transfer_meta' ], 10, 2 );

  add_filter( 'gform_get_form_filter', [ $this, 'payment_element_on_behalf_of' ], 10, 2 );

  add_filter( 'gform_stripe_connect_enabled', [ $this, '__return_false' ] );

  add_filter( 'option_gravityformsaddon_gravityformsstripe_settings', [ $this, 'use_platform_secret_for_connect' ] );
  add_filter( 'pre_update_option_gravityformsaddon_gravityformsstripe_settings', [ $this, 'restore_stored_secret' ], 10, 2 );

  // Separate charges and transfers (#2955): create/reverse the transfer with
  // our own restricted key once GF has settled the charge, never from inside
  // GF's own request lifecycle.
  add_action( 'gform_post_payment_completed', [ $this, 'handle_payment_completed' ], 10, 2 );
  add_action( 'gform_post_payment_refunded', [ $this, 'handle_payment_refunded' ], 10, 2 );
  add_action( 'gform_post_payment_action', [ $this, 'handle_payment_action' ], 10, 2 );

  add_action( 'init', [ $this, 'maybe_schedule_reconcile' ] );
  add_action( 'proudcity_payments_reconcile', [ $this, 'reconcile_transfers' ] );

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
  * Which charge model this site should use for new PaymentIntents.
  *
  * The per-site option wins over the env var both ways -- an explicit
  * 'destination' option overrides a fleet 'transfers' env default, and vice
  * versa -- so a site can opt in early or opt back out after the fleet flips.
  * An option set to anything other than 'transfers'/'destination' is treated
  * as an explicit 'destination' and does NOT fall back to the env var, since
  * an admin who set it to garbage almost certainly didn't mean "use the fleet
  * default".
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @return string 'transfers' or 'destination'.
  */
 public static function get_charge_model(): string {

	$option = get_option( 'proudcity_payments_charge_model', '' );

	if ( '' !== $option ) {
	 return 'transfers' === $option ? 'transfers' : 'destination';
	}

	$env = getenv( 'PROUDCITY_PAYMENTS_CHARGE_MODEL' );

	return 'transfers' === $env ? 'transfers' : 'destination';

 } // get_charge_model

 /**
  * Our platform's restricted key for Transfers, for the given Stripe API
  * mode. Option first, then env, same order as get_platform_secret().
  *
  * Only ever returns a restricted key (rk_live_/rk_test_) in the matching
  * mode, never a secret key -- this key only ever needs Transfers write
  * access, and accepting sk_ here would be a much higher-privilege key than
  * the transfer/reversal code requires.
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @param  string  $api_mode  required  'live', 'test' or 'sandbox'.
  * @return string
  */
 public static function get_transfer_key( string $api_mode ): string {

	if ( 'live' === $api_mode ){
	 $key = get_option( 'proudcity_payments_transfer_key', '' );
	 $key = '' !== $key ? $key : (string) getenv( 'PROUDCITY_PAYMENTS_TRANSFER_KEY' );

	 return is_string( $key ) && str_starts_with( $key, 'rk_live_' ) ? $key : '';
	}

	if ( 'test' === $api_mode ){
	 $key = get_option( 'proudcity_payments_transfer_key_test', '' );
	 $key = '' !== $key ? $key : (string) getenv( 'PROUDCITY_PAYMENTS_TRANSFER_KEY_TEST' );

	 return is_string( $key ) && str_starts_with( $key, 'rk_test_' ) ? $key : '';
	}

	// 'sandbox' (and anything else) has no transfer key -- Connect doesn't
	// run in GF Stripe's sandbox mode.
	return '';

 } // get_transfer_key

 /**
  * Whether this site should create a Transfer instead of a destination
  * charge, for the given Stripe API mode.
  *
  * Checked cheaply and in this order on purpose: get_charge_model() touches
  * only get_option()/getenv(), so a site that has never configured transfers
  * (the fleet default) returns false here without ever calling
  * get_transfer_account() -- which calls site_url() -- or get_transfer_key().
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @param  string  $api_mode  required  'live', 'test' or 'sandbox'.
  * @return bool
  */
 public static function is_transfer_mode( string $api_mode ): bool {

	if ( 'transfers' !== self::get_charge_model() ){
	 return false;
	}

	if ( ! self::get_transfer_account() ){
	 return false;
	}

	if ( '' === self::get_transfer_key( $api_mode ) ){
	 return false;
	}

	return true;

 } // is_transfer_mode

 /**
  * The Stripe API mode ('live'/'test'/'sandbox') GF Stripe will use for the
  * given feed -- the feed's own Connect mode when the feed is connected,
  * otherwise the add-on's plugin-wide settings. Mirrors how GF Stripe itself
  * picks the mode (class-gf-stripe.php get_api_mode()/get_api()).
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @param  array  $feed  required  The feed object.
  * @return string
  */
 public static function get_api_mode_for_feed( array $feed ): string {

	if ( gf_stripe()->is_feed_stripe_connect_enabled( $feed['id'] ?? null ) ){
	 return gf_stripe()->get_api_mode( $feed['meta'] ?? [], $feed['id'] ?? null );
	}

	return gf_stripe()->get_api_mode( gf_stripe()->get_plugin_settings() );

 } // get_api_mode_for_feed

 /**
  * Tags this blog on the shared platform Stripe account, so a hook or the
  * reconcile cron can tell this site's PaymentIntents apart from every other
  * site on the same Connect platform account. Prod is multisite, so the
  * blog id has to be part of it -- the hostname alone isn't unique enough to
  * rely on (staging mirrors, vanity domains).
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @return string
  */
 public static function get_site_tag(): string {

	return get_current_blog_id() . ':' . wp_parse_url( home_url(), PHP_URL_HOST );

 } // get_site_tag

 /**
  * ProudCity's cut of a payment, in cents. Extracted verbatim from the
  * formula add_transfer_meta() has used since 2023.01.04, so both the
  * destination-charge and transfer code paths compute the same fee.
  *
  * @since 2026.11.02
  * @author Curtis
  *
  * @param  int  $amount  required  The payment amount, in cents.
  * @return int
  */
 public static function calculate_fee( int $amount ): int {

	// getting percentage fee for a customer
	$percent = getenv('PROUDCITY_PAYMENTS_PERCENT') ? (float)getenv('PROUDCITY_PAYMENTS_PERCENT') : 3.05;

	// converting int to decimal value
	$percent_dec = $percent / 100;

	// figuring out what the value is less the fee
	$fee_amount_percent = round( $amount / ( 1 + $percent_dec ) ); // In cents
	$fee_minus_thirty = $fee_amount_percent - 30;

	// subtracting the value without the fee from the incoming
	// value to get the fee we should be charging via stripe
	return (int) ( $amount - $fee_minus_thirty );

 } // calculate_fee

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
  *
  * Destination charges (transfer_data/application_fee_amount) and Transfers
  * (#2955) both keep on_behalf_of, statement_descriptor_suffix and
  * transfer_group set exactly as before -- the 2026-11-02 spike found SC&T
  * with on_behalf_of keeps the city first on the card statement and changes
  * nothing else, so payment_element_on_behalf_of() needs no change either.
  * Which of the two branches runs is read from the PI metadata marker it
  * writes, not the current flag, by every later step (ensure_transfer(),
  * the reconcile cron) -- see is_transfer_mode()'s callers.
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

	$pc_fee = self::calculate_fee( (int) $data['amount'] );

	// getting form suffix
	$suffix = get_option('proudcity_payments_descriptor', get_bloginfo('name'));

	// getting form title
	$form = \GFAPI::get_form( absint( $feed['form_id'] ) );
	$form_title = $form['title'];

	$data['on_behalf_of'] = (string) $transfer_account;
	$data['statement_descriptor_suffix'] = (string) $suffix;
	$data['transfer_group'] = (string) $form_title;

	// Only computed when the charge model is 'transfers' at all -- a site that
	// has never configured transfers (the fleet default) never calls
	// get_api_mode_for_feed(), which is the one call here that reaches into
	// GF Stripe's own settings.
	$transfers_mode = false;

	if ( 'transfers' === self::get_charge_model() ){
	 $transfers_mode = self::is_transfer_mode( self::get_api_mode_for_feed( $feed ) );
	}

	if ( $transfers_mode ){

	 // Transfer happens later, on gform_post_payment_completed -- see
	 // handle_payment_completed(). Our keys are merged in last so feed-mapped
	 // metadata can't override the values ensure_transfer() trusts.
	 $percent = getenv('PROUDCITY_PAYMENTS_PERCENT') ? (float)getenv('PROUDCITY_PAYMENTS_PERCENT') : 3.05;

	 $data['metadata'] = array_merge( (array) ( $data['metadata'] ?? [] ), [
	  'proudcity_charge_model'     => 'transfers',
	  'proudcity_transfer_account' => (string) $transfer_account,
	  'proudcity_fee'              => (string) $pc_fee,
	  'proudcity_fee_percent'      => (string) $percent,
	  'proudcity_site'             => self::get_site_tag(),
	  'proudcity_form_id'          => (string) ( $feed['form_id'] ?? '' ),
	 ] );

	} else {

	 $data['application_fee_amount'] = (int) $pc_fee;
	 $data['transfer_data']['destination'] = (string) $transfer_account;

	}

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
  * Unchanged by #2955: on_behalf_of stays in both the destination-charge and
  * transfer code paths (2026-11-02 spike), so the client and server always
  * agree regardless of charge model.
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
  * Skipped entirely once this site is on the #2955 transfer model for live
  * mode: is_transfer_mode() is checked with the literal string 'live', never
  * by reading GF's own settings here, since that would recurse back into
  * this filter. When transfers mode is on, GF is meant to sign requests with
  * its own OAuth token again -- the setup the 2026-11-02 spike proved works
  * -- so the swap has to stay off.
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
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @param  mixed  $settings  required  The gravityformsaddon_gravityformsstripe_settings option value
  * @return mixed
  */
 public static function use_platform_secret_for_connect( $settings ){

	if ( ! is_array( $settings ) ){
	 return $settings;
	}

	if ( self::is_transfer_mode( 'live' ) ){
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

 /**
  * Makes a direct Stripe API call with our own key -- never GF's -- and
  * never via the Stripe SDK. GF Stripe loads Stripe-PHP for its own calls,
  * but its HTTP client has no hook that would let us sign a request with a
  * different key, so this talks to the HTTP API directly instead.
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @param  string  $method           required  GET, POST, etc.
  * @param  string  $path             required  Path under STRIPE_API_BASE, e.g. 'transfers'.
  * @param  array   $args             required  Request params. GET params are appended to the
  *                                              URL; everything else goes in the body.
  * @param  string  $key              required  The Stripe key to sign with. Never logged.
  * @param  string  $idempotency_key  optional  Sent as the Idempotency-Key header when given.
  * @return array|\WP_Error  Decoded JSON body on success.
  */
 public static function stripe_request( string $method, string $path, array $args, string $key, string $idempotency_key = '' ){

	$url = self::STRIPE_API_BASE . $path;

	$request_args = [
	 'method'  => $method,
	 'timeout' => 20,
	 'headers' => [
	  'Authorization'  => 'Basic ' . base64_encode( $key . ':' ),
	  'Content-Type'   => 'application/x-www-form-urlencoded',
	  'Stripe-Version' => self::STRIPE_API_VERSION,
	 ],
	];

	if ( '' !== $idempotency_key ){
	 $request_args['headers']['Idempotency-Key'] = $idempotency_key;
	}

	if ( 'GET' === $method ){
	 if ( $args ){
	  $url .= '?' . http_build_query( $args );
	 }
	} else {
	 $request_args['body'] = http_build_query( $args );
	}

	$response = wp_remote_request( $url, $request_args );

	if ( is_wp_error( $response ) ){
	 return $response;
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	$body = is_array( $body ) ? $body : [];

	if ( $code < 200 || $code >= 300 ){
	 return new \WP_Error(
	  (string) ( $body['error']['code'] ?? $code ),
	  (string) ( $body['error']['message'] ?? 'Stripe request failed.' )
	 );
	}

	return $body;

 } // stripe_request

 /**
  * Fetches a PaymentIntent with its latest_charge expanded, the shape
  * build_transfer_args() needs. GF never stores the charge id (only the
  * PaymentIntent id), so this is how every caller gets to the charge.
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @param  string  $pi_id  required  PaymentIntent id (pi_...).
  * @param  string  $key    required  The transfer key to sign with.
  * @return array|\WP_Error
  */
 public static function get_payment_intent_with_charge( string $pi_id, string $key ){

	return self::stripe_request( 'GET', 'payment_intents/' . $pi_id, [ 'expand' => [ 'latest_charge' ] ], $key );

 } // get_payment_intent_with_charge

 /**
  * Builds the args for creating a Transfer from an already-fetched
  * PaymentIntent (with latest_charge expanded). Pure -- no network calls, no
  * side effects -- which makes it the main unit-test target for the Transfer
  * logic.
  *
  * Every validation failure returns a WP_Error instead of transferring:
  * the marker check is what makes flipping proudcity_payments_charge_model
  * safe mid-flight (see add_transfer_meta()), and the rest guard against a
  * malformed or foreign PaymentIntent ever reaching a Stripe write.
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @param  array  $pi  required  PaymentIntent, with latest_charge expanded.
  * @return array|\WP_Error
  */
 public static function build_transfer_args( array $pi ){

	$metadata = (array) ( $pi['metadata'] ?? [] );

	if ( 'transfers' !== ( $metadata['proudcity_charge_model'] ?? '' ) ){
	 // Not an error -- this is the normal shape of every pre-cutover
	 // destination charge and every site that has never used transfers.
	 // ensure_transfer() returns this quietly without alerting.
	 return new \WP_Error( 'no_transfer_marker', 'PaymentIntent has no proudcity_charge_model=transfers metadata.' );
	}

	if ( ( $metadata['proudcity_site'] ?? '' ) !== self::get_site_tag() ){
	 return new \WP_Error( 'site_tag_mismatch', 'PaymentIntent metadata belongs to another site on this platform account.' );
	}

	$account = (string) ( $metadata['proudcity_transfer_account'] ?? '' );

	if ( ! preg_match( '/^acct_[A-Za-z0-9]+$/', $account ) ){
	 return new \WP_Error( 'invalid_transfer_account', 'PaymentIntent metadata has a malformed transfer account.' );
	}

	$charge = (array) ( $pi['latest_charge'] ?? [] );

	if ( 'succeeded' !== ( $charge['status'] ?? '' ) || empty( $charge['captured'] ) ){
	 return new \WP_Error( 'charge_not_captured', 'Charge is not succeeded and captured.' );
	}

	// The site may have changed proudcity_payments_account since this PI was
	// created -- the PI's own metadata is always what gets transferred, but
	// a mismatch is worth a note for whoever is reading the logs.
	$current_account = self::get_transfer_account();

	if ( $current_account && $account !== $current_account ){
	 error_log( sprintf(
	  '[proudcity-payments] account_mismatch payment_intent=%s metadata_account=%s current_account=%s',
	  (string) ( $pi['id'] ?? '' ),
	  $account,
	  $current_account
	 ) );
	}

	$fee = (int) ( $metadata['proudcity_fee'] ?? 0 );
	$amount = (int) ( $charge['amount_captured'] ?? 0 ) - $fee;

	if ( $amount <= 0 ){
	 return new \WP_Error( 'transfer_amount_not_positive', 'Transfer amount after fee is not positive.' );
	}

	return [
	 'amount'             => $amount,
	 'currency'           => (string) ( $charge['currency'] ?? '' ),
	 'destination'        => $account,
	 'source_transaction' => (string) ( $charge['id'] ?? '' ),
	 'transfer_group'     => (string) ( $charge['transfer_group'] ?? '' ),
	 'metadata'           => [
	  'entry_id'       => (string) ( $metadata['gf_entry_id'] ?? '' ),
	  'form_id'        => (string) ( $metadata['proudcity_form_id'] ?? '' ),
	  'payment_intent' => (string) ( $pi['id'] ?? '' ),
	  'charge'          => (string) ( $charge['id'] ?? '' ),
	  'site'           => self::get_site_tag(),
	 ],
	];

 } // build_transfer_args

 /**
  * Finds a Transfer already created for this charge, by destination and
  * source_transaction. Stripe idempotency keys expire after 24h, so a retry
  * or the hourly reconcile has to check this before relying on the POST's
  * Idempotency-Key header to dedupe.
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @param  string  $charge_id    required  The source charge id (ch_...).
  * @param  string  $destination  required  The connected account id.
  * @param  int     $since        required  Unix timestamp, created[gte].
  * @param  string  $key          required  The transfer key to sign with.
  * @return array|null
  */
 public static function find_existing_transfer( string $charge_id, string $destination, int $since, string $key ){

	$starting_after = '';
	$page = 0;

	do {

	 $query = [
	  'destination'  => $destination,
	  'created'      => [ 'gte' => $since ],
	  'limit'        => 100,
	 ];

	 if ( '' !== $starting_after ){
	  $query['starting_after'] = $starting_after;
	 }

	 $response = self::stripe_request( 'GET', 'transfers', $query, $key );

	 if ( is_wp_error( $response ) ){
	  return null;
	 }

	 $data = (array) ( $response['data'] ?? [] );

	 foreach ( $data as $transfer ){
	  if ( ( $transfer['source_transaction'] ?? '' ) === $charge_id ){
	   return $transfer;
	  }
	 }

	 $has_more = ! empty( $response['has_more'] );
	 $starting_after = $data ? (string) ( end( $data )['id'] ?? '' ) : '';
	 $page++;

	} while ( $has_more && '' !== $starting_after && $page < 10 );

	return null;

 } // find_existing_transfer

 /**
  * The single entry point for creating a Transfer: the gform_post_payment_completed
  * hook, the `wp proud payments_transfer` retry, and the hourly reconcile
  * cron all call this, so the skip/retry/store logic only lives in one place.
  *
  * Never throws -- every failure is caught by the caller's try/catch and
  * turned into a WP_Error here, since GF has already marked the entry Paid
  * by the time this runs and a Stripe webhook retry would not help us.
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @param  string  $pi_id      required  PaymentIntent id (pi_...).
  * @param  string  $api_mode   required  'live' or 'test'.
  * @param  int     $entry_id   optional  The GF entry id, when known.
  * @return array|\WP_Error
  */
 public static function ensure_transfer( string $pi_id, string $api_mode, int $entry_id = 0 ){

	$existing_status = $entry_id ? (string) gform_get_meta( $entry_id, 'proudcity_stripe_transfer_status' ) : '';
	$existing_id = $entry_id ? (string) gform_get_meta( $entry_id, 'proudcity_stripe_transfer_id' ) : '';

	if ( '' !== $existing_id ){
	 return [ 'id' => $existing_id, 'skipped' => true ];
	}

	$key = self::get_transfer_key( $api_mode );

	if ( '' === $key ){
	 self::alert( 'no_transfer_key', 'No transfer key configured for this Stripe API mode.', [ 'entry_id' => $entry_id, 'payment_intent' => $pi_id ] );
	 return new \WP_Error( 'no_transfer_key', 'No transfer key configured for this mode.' );
	}

	$pi = self::get_payment_intent_with_charge( $pi_id, $key );

	if ( is_wp_error( $pi ) ){
	 self::alert( 'transfer_fetch_failed', $pi->get_error_code() . ': ' . $pi->get_error_message(), [ 'entry_id' => $entry_id, 'payment_intent' => $pi_id ] );
	 return $pi;
	}

	$args = self::build_transfer_args( $pi );

	if ( is_wp_error( $args ) ){

	 if ( 'no_transfer_marker' === $args->get_error_code() ){
	  // Expected for every pre-cutover destination charge -- not an error.
	  return $args;
	 }

	 if ( $entry_id ){
	  gform_update_meta( $entry_id, 'proudcity_stripe_transfer_status', 'failed' );
	 }

	 self::alert( 'transfer_invalid', $args->get_error_code() . ': ' . $args->get_error_message(), [ 'entry_id' => $entry_id, 'payment_intent' => $pi_id ] );
	 return $args;
	}

	$charge_id = $args['metadata']['charge'];

	// Only look for an already-created Transfer on a retry/reconcile pass --
	// a fresh call from the completed-payment hook has no prior status, so
	// its Idempotency-Key header is enough to dedupe on its own.
	if ( 0 === $entry_id || 'failed' === $existing_status ){

	 $existing = self::find_existing_transfer( $charge_id, $args['destination'], time() - ( 7 * DAY_IN_SECONDS ), $key );

	 if ( $existing ){
	  if ( $entry_id ){
	   gform_update_meta( $entry_id, 'proudcity_stripe_charge_id', $charge_id );
	   gform_update_meta( $entry_id, 'proudcity_stripe_transfer_id', $existing['id'] );
	   gform_update_meta( $entry_id, 'proudcity_stripe_transfer_amount', (string) $existing['amount'] );
	   gform_update_meta( $entry_id, 'proudcity_stripe_transfer_status', 'created' );
	  }
	  return array_merge( $existing, [ 'skipped' => true ] );
	 }
	}

	$response = self::stripe_request( 'POST', 'transfers', $args, $key, 'transfer-' . $charge_id );

	if ( is_wp_error( $response ) ){

	 if ( $entry_id ){
	  gform_update_meta( $entry_id, 'proudcity_stripe_transfer_status', 'failed' );
	 }

	 self::alert( 'transfer_failed', $response->get_error_code() . ': ' . $response->get_error_message(), [ 'entry_id' => $entry_id, 'payment_intent' => $pi_id, 'charge' => $charge_id ] );
	 return $response;
	}

	if ( $entry_id ){
	 gform_update_meta( $entry_id, 'proudcity_stripe_charge_id', $charge_id );
	 gform_update_meta( $entry_id, 'proudcity_stripe_transfer_id', $response['id'] );
	 gform_update_meta( $entry_id, 'proudcity_stripe_transfer_amount', (string) $response['amount'] );
	 gform_update_meta( $entry_id, 'proudcity_stripe_transfer_status', 'created' );
	 gf_stripe()->add_note( $entry_id, sprintf( 'ProudCity Payments transferred $%s to %s (transfer %s).', number_format( $response['amount'] / 100, 2 ), $args['destination'], $response['id'] ) );
	}

	return $response;

 } // ensure_transfer

 /**
  * Hooks gform_post_payment_completed. Fires for every payment add-on, on
  * every payment this site ever takes -- so every early return here has to
  * be cheap, since a transfer key is often configured fleet-wide via env
  * long before most sites opt into the transfers charge model.
  *
  * GF never stores the charge id on the entry, only the PaymentIntent id
  * (pi_...) -- except the legacy manual-capture path, which can leave a
  * charge (ch_/py_) id instead, resolved here via the charge's own
  * payment_intent field.
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @param  array  $entry   required  The GF entry.
  * @param  array  $action  required  Unused -- the entry's own transaction_id is trusted instead.
  * @return void
  */
 public static function handle_payment_completed( $entry, $action ): void {

	try {

	 $transaction_id = (string) ( $entry['transaction_id'] ?? '' );

	 if ( ! str_starts_with( $transaction_id, 'pi_' ) && ! str_starts_with( $transaction_id, 'ch_' ) && ! str_starts_with( $transaction_id, 'py_' ) ){
	  return;
	 }

	 $entry_id = (int) ( $entry['id'] ?? 0 );

	 $feed = gf_stripe()->get_payment_feed( $entry );

	 if ( ! $feed ){
	  return;
	 }

	 $api_mode = self::get_api_mode_for_feed( $feed );
	 $key = self::get_transfer_key( $api_mode );

	 // No transfer key for this mode at all: nothing we could ever transfer,
	 // so skip the Stripe round trip entirely rather than fetch a PI only to
	 // find out it has no marker.
	 if ( '' === $key ){
	  return;
	 }

	 $pi_id = $transaction_id;

	 if ( ! str_starts_with( $pi_id, 'pi_' ) ){

	  $charge = self::stripe_request( 'GET', 'charges/' . $transaction_id, [], $key );

	  if ( is_wp_error( $charge ) ){
	   return;
	  }

	  $pi_id = (string) ( $charge['payment_intent'] ?? '' );
	 }

	 if ( ! str_starts_with( $pi_id, 'pi_' ) ){
	  return;
	 }

	 self::ensure_transfer( $pi_id, $api_mode, $entry_id );

	} catch ( \Throwable $e ){
	 self::alert( 'payment_completed_exception', $e->getMessage(), [ 'entry_id' => (int) ( $entry['id'] ?? 0 ) ] );
	}

 } // handle_payment_completed

 /**
  * The reversal amount for a refund against a given transfer, as a filter
  * default. 'proportional' matches today's behaviour: GF's own refund call
  * always sends reverse_transfer=true and refund_application_fee=true,
  * which already hands back ProudCity's fee share proportionally, so this
  * keeps that outcome. The filter is where a different split (e.g. the city
  * keeps none of ProudCity's fee back) would be decided.
  *
  * The caller clamps the result to the transfer's remaining unreversed
  * amount -- this function only computes the proportional share.
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @param  int    $refund_amount    required  The refund amount, in cents.
  * @param  int    $transfer_amount  required  The transfer's original amount, in cents.
  * @param  int    $charge_amount    required  The charge's original amount, in cents.
  * @param  array  $context          required  entry_id, refund_id -- passed through to the filter.
  * @return int
  */
 public static function calculate_reversal_amount( int $refund_amount, int $transfer_amount, int $charge_amount, array $context ): int {

	$amount = $charge_amount > 0 ? (int) round( $refund_amount * $transfer_amount / $charge_amount ) : 0;

	/**
	 * Filters the SC&T reversal amount computed for a single refund.
	 *
	 * @since 2026.11.02
	 *
	 * @param  int    $amount           The default proportional reversal amount.
	 * @param  int    $refund_amount    The refund amount, in cents.
	 * @param  int    $transfer_amount  The transfer's original amount, in cents.
	 * @param  int    $charge_amount    The charge's original amount, in cents.
	 * @param  array  $context          entry_id, refund_id.
	 */
	return (int) apply_filters( 'proudcity_payments_reversal_amount', $amount, $refund_amount, $transfer_amount, $charge_amount, $context );

 } // calculate_reversal_amount

 /**
  * Reverses every succeeded, not-yet-reversed refund on a charge against its
  * Transfer, syncing from Stripe's own state rather than trusting the
  * inconsistent $action array gform_post_payment_refunded passes (GF's entry
  * Refund button and the charge.refunded webhook disagree on amount and
  * transaction_id shape -- see issue notes).
  *
  * Idempotent by design: a refund already present in some reversal's
  * metadata.refund_id is skipped, which is what makes it safe for the
  * webhook to fire twice for one refund and for cumulative dashboard
  * partials to each land here as their own refund object.
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @param  string  $pi_id      required  PaymentIntent id (pi_...).
  * @param  string  $api_mode   required  'live' or 'test'.
  * @param  int     $entry_id   optional  The GF entry id, when known.
  * @return array|\WP_Error
  */
 public static function sync_reversals( string $pi_id, string $api_mode, int $entry_id = 0 ){

	$transfer_id = $entry_id ? (string) gform_get_meta( $entry_id, 'proudcity_stripe_transfer_id' ) : '';

	if ( '' === $transfer_id ){
	 // No transfer exists for this entry, so there's nothing to reverse --
	 // the normal case for every destination-charge refund.
	 return [ 'skipped' => true ];
	}

	$key = self::get_transfer_key( $api_mode );

	if ( '' === $key ){
	 return new \WP_Error( 'no_transfer_key', 'No transfer key configured for this mode.' );
	}

	$pi = self::get_payment_intent_with_charge( $pi_id, $key );

	if ( is_wp_error( $pi ) ){
	 self::alert( 'reversal_fetch_failed', $pi->get_error_code() . ': ' . $pi->get_error_message(), [ 'entry_id' => $entry_id, 'payment_intent' => $pi_id ] );
	 return $pi;
	}

	$charge_id = (string) ( $pi['latest_charge']['id'] ?? '' );

	$charge = self::stripe_request( 'GET', 'charges/' . $charge_id, [ 'expand' => [ 'refunds' ] ], $key );

	if ( is_wp_error( $charge ) ){
	 self::alert( 'reversal_fetch_failed', $charge->get_error_code() . ': ' . $charge->get_error_message(), [ 'entry_id' => $entry_id, 'charge' => $charge_id ] );
	 return $charge;
	}

	$transfer = self::stripe_request( 'GET', 'transfers/' . $transfer_id, [], $key );

	if ( is_wp_error( $transfer ) ){
	 self::alert( 'reversal_fetch_failed', $transfer->get_error_code() . ': ' . $transfer->get_error_message(), [ 'entry_id' => $entry_id, 'charge' => $charge_id ] );
	 return $transfer;
	}

	$reversed_refund_ids = [];

	foreach ( (array) ( $transfer['reversals']['data'] ?? [] ) as $reversal ){
	 $refund_id = $reversal['metadata']['refund_id'] ?? '';
	 if ( '' !== $refund_id ){
	  $reversed_refund_ids[] = $refund_id;
	 }
	}

	$charge_fully_refunded = ! empty( $charge['refunded'] );

	$unreversed = array_values( array_filter( (array) ( $charge['refunds']['data'] ?? [] ), function( $refund ) use ( $reversed_refund_ids ) {
	 return 'succeeded' === ( $refund['status'] ?? '' ) && ! in_array( $refund['id'] ?? '', $reversed_refund_ids, true );
	} ) );

	$remaining = (int) ( $transfer['amount'] ?? 0 ) - (int) ( $transfer['amount_reversed'] ?? 0 );
	$count_unreversed = count( $unreversed );
	$results = [ 'reversed' => 0, 'errors' => 0 ];

	foreach ( $unreversed as $i => $refund ){

	 // The last unreversed refund on a fully-refunded charge takes whatever
	 // is left on the transfer exactly, so a 1c rounding difference from the
	 // proportional formula is never stranded on the transfer.
	 $is_last = $charge_fully_refunded && ( $i === $count_unreversed - 1 );

	 $reversal_amount = $is_last
	  ? $remaining
	  : self::calculate_reversal_amount( (int) $refund['amount'], (int) ( $transfer['amount'] ?? 0 ), (int) ( $charge['amount'] ?? 0 ), [ 'entry_id' => $entry_id, 'refund_id' => $refund['id'] ] );

	 $reversal_amount = min( $reversal_amount, $remaining );

	 if ( $reversal_amount <= 0 ){
	  continue;
	 }

	 $response = self::stripe_request( 'POST', 'transfers/' . $transfer_id . '/reversals', [
	  'amount'   => $reversal_amount,
	  'metadata' => [
	   'refund_id' => $refund['id'],
	   'entry_id'  => (string) $entry_id,
	  ],
	 ], $key, 'reversal-' . $refund['id'] );

	 if ( is_wp_error( $response ) ){
	  $results['errors']++;
	  if ( $entry_id ){
	   gform_update_meta( $entry_id, 'proudcity_stripe_reversal_pending', 1 );
	  }
	  self::alert( 'reversal_failed', $response->get_error_code() . ': ' . $response->get_error_message(), [ 'entry_id' => $entry_id, 'charge' => $charge_id ] );
	  continue;
	 }

	 $remaining -= $reversal_amount;
	 $results['reversed']++;

	 if ( $entry_id ){
	  gf_stripe()->add_note( $entry_id, sprintf( 'ProudCity Payments reversed $%s of the transfer for refund %s.', number_format( $reversal_amount / 100, 2 ), $refund['id'] ) );
	 }
	}

	// Defensive path for a failed/voided charge with no refund object at all
	// (fail_payment, void_authorization) -- reverse whatever is left.
	$charge_voided = 'succeeded' === ( $charge['status'] ?? '' ) && empty( $charge['captured'] );

	if ( ( 'failed' === ( $charge['status'] ?? '' ) || $charge_voided ) && $remaining > 0 ){

	 $response = self::stripe_request( 'POST', 'transfers/' . $transfer_id . '/reversals', [
	  'amount' => $remaining,
	 ], $key, 'reversal-fail-' . $charge_id );

	 if ( is_wp_error( $response ) ){
	  $results['errors']++;
	  if ( $entry_id ){
	   gform_update_meta( $entry_id, 'proudcity_stripe_reversal_pending', 1 );
	  }
	  self::alert( 'reversal_failed', $response->get_error_code() . ': ' . $response->get_error_message(), [ 'entry_id' => $entry_id, 'charge' => $charge_id ] );
	 } else {
	  $remaining -= $remaining;
	  $results['reversed']++;
	 }
	}

	if ( 0 === $results['errors'] && $entry_id ){
	 gform_update_meta( $entry_id, 'proudcity_stripe_reversal_pending', 0 );
	}

	return $results;

 } // sync_reversals

 /**
  * Hooks gform_post_payment_refunded. Only reaches Stripe when the entry
  * already has a transfer -- the normal case for every destination-charge
  * refund fleet-wide, so this stays cheap on the common path.
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @param  array  $entry   required  The GF entry.
  * @param  array  $action  required  Unused -- see sync_reversals().
  * @return void
  */
 public static function handle_payment_refunded( $entry, $action ): void {

	try {

	 $entry_id = (int) ( $entry['id'] ?? 0 );

	 if ( ! $entry_id || ! gform_get_meta( $entry_id, 'proudcity_stripe_transfer_id' ) ){
	  return;
	 }

	 $transaction_id = (string) ( $entry['transaction_id'] ?? '' );

	 if ( ! str_starts_with( $transaction_id, 'pi_' ) ){
	  return;
	 }

	 $feed = gf_stripe()->get_payment_feed( $entry );
	 $api_mode = $feed ? self::get_api_mode_for_feed( $feed ) : 'live';

	 self::sync_reversals( $transaction_id, $api_mode, $entry_id );

	} catch ( \Throwable $e ){
	 self::alert( 'payment_refunded_exception', $e->getMessage(), [ 'entry_id' => (int) ( $entry['id'] ?? 0 ) ] );
	}

 } // handle_payment_refunded

 /**
  * Hooks gform_post_payment_action. Only acts on fail_payment/void_authorization
  * when the entry already has a transfer -- ACH/Link failures and disputes
  * are out of scope and unchanged from today (the platform is debited either
  * way), this only covers a PaymentIntent that failed or was voided after a
  * transfer already went out.
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @param  array  $entry   required  The GF entry.
  * @param  array  $action  required  Used only for its 'type'.
  * @return void
  */
 public static function handle_payment_action( $entry, $action ): void {

	try {

	 $type = (string) ( $action['type'] ?? '' );

	 if ( 'fail_payment' !== $type && 'void_authorization' !== $type ){
	  return;
	 }

	 $entry_id = (int) ( $entry['id'] ?? 0 );

	 if ( ! $entry_id || ! gform_get_meta( $entry_id, 'proudcity_stripe_transfer_id' ) ){
	  return;
	 }

	 $transaction_id = (string) ( $entry['transaction_id'] ?? '' );

	 if ( ! str_starts_with( $transaction_id, 'pi_' ) ){
	  return;
	 }

	 $feed = gf_stripe()->get_payment_feed( $entry );
	 $api_mode = $feed ? self::get_api_mode_for_feed( $feed ) : 'live';

	 self::sync_reversals( $transaction_id, $api_mode, $entry_id );

	} catch ( \Throwable $e ){
	 self::alert( 'payment_action_exception', $e->getMessage(), [ 'entry_id' => (int) ( $entry['id'] ?? 0 ) ] );
	}

 } // handle_payment_action

 /**
  * Schedules the hourly reconcile cron, once a transfer key exists for
  * either mode. Keeps running even after a site flips back to 'destination',
  * so an in-flight transfers-mode payment still gets transferred -- see the
  * mid-flight note on add_transfer_meta().
  *
  * Relies on the cluster's external WP-Cron runner (DISABLE_WP_CRON) to hit
  * every site's due events hourly, same as every other scheduled hook here.
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @return void
  */
 public static function maybe_schedule_reconcile(): void {

	if ( '' === self::get_transfer_key( 'live' ) && '' === self::get_transfer_key( 'test' ) ){
	 return;
	}

	if ( ! wp_next_scheduled( 'proudcity_payments_reconcile' ) ){
	 wp_schedule_event( time(), 'hourly', 'proudcity_payments_reconcile' );
	}

 } // maybe_schedule_reconcile

 /**
  * The hourly proudcity_payments_reconcile cron, also exposed as
  * `wp proud payments_reconcile`.
  *
  * Two passes, for each mode that has a transfer key configured:
  *  (a) Search this site's last 7 days of succeeded, transfers-marked
  *      PaymentIntents and ensure_transfer() each -- covers a webhook that
  *      never reached this site.
  *  (b) Retry every entry GF has recorded with a failed transfer or a
  *      pending reversal.
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @return array{transferred:int,retried:int,skipped:int,errors:int}
  */
 public static function reconcile_transfers(): array {

	$counts = [ 'transferred' => 0, 'retried' => 0, 'skipped' => 0, 'errors' => 0 ];

	foreach ( [ 'live', 'test' ] as $api_mode ){

	 $key = self::get_transfer_key( $api_mode );

	 if ( '' === $key ){
	  continue;
	 }

	 $query = sprintf(
	  "metadata['proudcity_site']:'%s' AND metadata['proudcity_charge_model']:'transfers' AND status:'succeeded' AND created>%d",
	  self::get_site_tag(),
	  time() - ( 7 * DAY_IN_SECONDS )
	 );

	 $response = self::stripe_request( 'GET', 'payment_intents/search', [ 'query' => $query, 'limit' => 100 ], $key );

	 if ( is_wp_error( $response ) ){
	  self::alert( 'reconcile_search_failed', $response->get_error_code() . ': ' . $response->get_error_message(), [ 'api_mode' => $api_mode ] );
	  $counts['errors']++;
	  continue;
	 }

	 foreach ( (array) ( $response['data'] ?? [] ) as $pi ){

	  $entry_id = (int) ( $pi['metadata']['gf_entry_id'] ?? 0 );
	  $result = self::ensure_transfer( (string) ( $pi['id'] ?? '' ), $api_mode, $entry_id );

	  if ( is_wp_error( $result ) ){
	   $counts['errors']++;
	  } elseif ( ! empty( $result['skipped'] ) ){
	   $counts['skipped']++;
	  } else {
	   $counts['transferred']++;
	  }
	 }
	}

	$failed_entries = \GFAPI::get_entries( 0, [
	 'field_filters' => [
	  'mode' => 'any',
	  [ 'key' => 'proudcity_stripe_transfer_status', 'value' => 'failed' ],
	  [ 'key' => 'proudcity_stripe_reversal_pending', 'value' => '1' ],
	 ],
	] );

	foreach ( (array) $failed_entries as $entry ){

	 $entry_id = (int) ( $entry['id'] ?? 0 );
	 $transaction_id = (string) ( $entry['transaction_id'] ?? '' );

	 if ( ! $entry_id || ! str_starts_with( $transaction_id, 'pi_' ) ){
	  continue;
	 }

	 $feed = gf_stripe()->get_payment_feed( $entry );
	 $api_mode = $feed ? self::get_api_mode_for_feed( $feed ) : 'live';

	 if ( 'failed' === gform_get_meta( $entry_id, 'proudcity_stripe_transfer_status' ) ){
	  $result = self::ensure_transfer( $transaction_id, $api_mode, $entry_id );
	  is_wp_error( $result ) ? $counts['errors']++ : $counts['retried']++;
	 }

	 if ( gform_get_meta( $entry_id, 'proudcity_stripe_reversal_pending' ) ){
	  $result = self::sync_reversals( $transaction_id, $api_mode, $entry_id );
	  is_wp_error( $result ) ? $counts['errors']++ : $counts['retried']++;
	 }
	}

	return $counts;

 } // reconcile_transfers

 /**
  * Logs a transfer/reversal failure and notes it on the entry. This IS the
  * alert path: Curtis is wiring a GCP log-based alert on ALERT_LOG_PREFIX
  * rather than email, so there is no mail step here. Never pass a key or
  * card data in $message or $context -- this line is meant to be pasted
  * straight into a dashboard.
  *
  * Throttled to once per 24h per code + charge/payment_intent (transient),
  * so the hourly reconcile retrying the same stuck entry doesn't spam the
  * log on every run.
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @param  string  $code     required  Short machine-friendly reason code, e.g. transfer_failed.
  * @param  string  $message  required  Human-readable detail. Never a key or card data.
  * @param  array   $context  optional  entry_id, payment_intent, charge.
  * @return void
  */
 public static function alert( string $code, string $message, array $context = [] ): void {

	$charge = (string) ( $context['charge'] ?? ( $context['payment_intent'] ?? '' ) );

	$throttle_key = 'proudcity_payments_alert_' . md5( $code . '|' . $charge );

	if ( get_transient( $throttle_key ) ){
	 return;
	}

	set_transient( $throttle_key, 1, DAY_IN_SECONDS );

	error_log( sprintf(
	 '%s%s entry=%s payment_intent=%s charge=%s: %s',
	 self::ALERT_LOG_PREFIX,
	 $code,
	 (string) ( $context['entry_id'] ?? '' ),
	 (string) ( $context['payment_intent'] ?? '' ),
	 $charge,
	 $message
	) );

	$entry_id = (int) ( $context['entry_id'] ?? 0 );

	if ( $entry_id ){
	 gf_stripe()->add_note( $entry_id, sprintf( 'ProudCity Payments error (%s): %s', $code, $message ) );
	}

 } // alert

}

new ProudGravityformsStripe;
