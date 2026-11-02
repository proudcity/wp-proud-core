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
  * Reentrance guard for strip_reverse_transfer_for_our_refunds(): that
  * filter runs on every single wp_remote_request() site-wide (not only
  * Stripe's), and its own PI/charge lookups go through wp_remote_request()
  * too, so this stops it ever evaluating its own inner requests.
  *
  * @since 2026.11.02
  */
 private static $in_refund_filter = false;

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
  * [proudcity-payments] notices (for example the fee metadata mismatch log
  * in build_transfer_args()) that aren't failures.
  *
  * @since 2026.11.02
  */
 const ALERT_LOG_PREFIX = '[proudcity-payments] ALERT ';

 /**
  * build_transfer_args() error codes that will never succeed on retry --
  * the PaymentIntent/charge itself is wrong (foreign site, mismatched or
  * malformed account, refunded/disputed before a transfer existed, or a
  * non-positive amount). ensure_transfer() marks these entries 'invalid'
  * rather than 'failed', so reconcile_transfers()' retry pass (which only
  * looks for 'failed') never retries them forever.
  *
  * @since 2026.11.02
  */
 const TERMINAL_TRANSFER_ERROR_CODES = [
	'destination_mismatch',
	'invalid_transfer_account',
	'transfer_amount_not_positive',
	'charge_refunded_before_transfer',
	'charge_disputed',
	'marker_forged',
 ];

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

  // GF's own Refund button always sends reverse_transfer+refund_application_fee
  // (gravityformsstripe/includes/api/class-gf-stripe-api.php create_refund()),
  // which Stripe rejects on an SC&T charge that has no associated transfer on
  // GF's own account. GF has no filter on that request, so this strips both
  // flags at the WP_Http layer instead, only for charges we marked ourselves.
  add_filter( 'http_request_args', [ $this, 'strip_reverse_transfer_for_our_refunds' ], 10, 2 );

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
  * Reads the raw 'home' option rather than home_url(): home_url() runs
  * through the 'home_url' filter, which a theme or another plugin can
  * change for display purposes, and this tag has to stay stable so a PI
  * tagged under one value is still recognised as "this site" later, by the
  * reconcile cron or a retry, even if something starts filtering home_url().
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @return string
  */
 public static function get_site_tag(): string {

	return get_current_blog_id() . ':' . wp_parse_url( get_option( 'home' ), PHP_URL_HOST );

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

	// Feed custom metadata (GF Stripe get_stripe_meta_data()) maps an
	// admin-chosen key to a form field value, which an ordinary form
	// submission controls -- so any proudcity_* key a feed happens to
	// carry is dropped here FIRST, in BOTH branches below, including
	// destination mode. A destination-mode charge with a forged
	// proudcity_charge_model=transfers key would otherwise pass
	// pi_structurally_ours()'s marker check later (build_transfer_args())
	// since a destination charge has no transfer-of-its-own structure to
	// contradict it -- stripping here is what stops that charge from ever
	// carrying the marker in the first place. The real control once a PI
	// does carry the marker is still build_transfer_args()'s structural
	// check plus the destination/fee validation; this is defense in depth
	// for the one path (this filter) we can see.
	if ( array_key_exists( 'metadata', $data ) ){

	 $feed_metadata = (array) $data['metadata'];

	 foreach ( array_keys( $feed_metadata ) as $meta_key ){
	  if ( str_starts_with( (string) $meta_key, 'proudcity_' ) ){
	   unset( $feed_metadata[ $meta_key ] );
	  }
	 }

	 $data['metadata'] = $feed_metadata;

	}

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
	 // handle_payment_completed(). Our keys are merged in last so nothing
	 // else can stick over the account/fee build_transfer_args() later
	 // computes.
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
  * Whether a PaymentIntent that CLAIMS the transfers marker actually looks
  * structurally like a transfer we created (#2955).
  *
  * The marker alone is not enough: GF's own get_stripe_meta_data() maps an
  * admin-chosen feed custom-meta key to a form field value and merges it
  * into PI/charge metadata in several places outside our own pre_create
  * filter (capture(), Checkout), which means a feed whose custom meta
  * happens to use our key names -- or a deliberately crafted one -- could
  * make an ordinary destination charge carry
  * proudcity_charge_model=transfers. A genuine transfer-model PaymentIntent
  * never has transfer_data on the PI or the charge, never has
  * application_fee_amount on the charge, and never has charge.transfer set
  * (Stripe only sets that for a destination charge) -- add_transfer_meta()
  * never sets any of those in transfers mode. on_behalf_of IS expected
  * (kept in both modes, see add_transfer_meta()), so it's only checked for
  * equality when present, not required.
  *
  * Used by both build_transfer_args() and
  * strip_reverse_transfer_for_our_refunds() so a forged/leaked marker gets
  * the same answer whichever path encounters it first.
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @param  array  $pi  required  PaymentIntent, with latest_charge expanded.
  * @return bool
  */
 public static function pi_structurally_ours( array $pi ): bool {

	$metadata = (array) ( $pi['metadata'] ?? [] );

	if ( 'transfers' !== ( $metadata['proudcity_charge_model'] ?? '' ) ){
	 return false;
	}

	if ( ( $metadata['proudcity_site'] ?? '' ) !== self::get_site_tag() ){
	 return false;
	}

	if ( ! empty( $pi['transfer_data'] ) ){
	 return false;
	}

	$charge = (array) ( $pi['latest_charge'] ?? [] );

	if ( ! empty( $charge['transfer'] ) ){
	 return false;
	}

	if ( ! empty( $charge['transfer_data'] ) ){
	 return false;
	}

	if ( ! empty( $charge['application_fee_amount'] ) ){
	 return false;
	}

	$current_account = self::get_transfer_account();

	if ( ! empty( $charge['on_behalf_of'] ) && $current_account && $charge['on_behalf_of'] !== $current_account ){
	 return false;
	}

	return true;

 } // pi_structurally_ours

 /**
  * The reverse of pi_structurally_ours(): whether a charge that has NO (or
  * the wrong) transfers marker still looks exactly like one of ours --
  * on_behalf_of set to this site's current account, no transfer_data, no
  * application_fee_amount. If GF or some other code path ever overwrites
  * our PI metadata (see pi_structurally_ours()'s docblock) on a charge that
  * really was meant to be a transfer, build_transfer_args() would otherwise
  * return the quiet no_transfer_marker outcome meant for ordinary
  * destination charges -- this is what lets the caller alert marker_missing
  * instead of silently skipping it.
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @param  array  $charge  required  A charge object (e.g. PI.latest_charge).
  * @return bool
  */
 public static function charge_looks_like_ours( array $charge ): bool {

	$current_account = self::get_transfer_account();

	if ( ! $current_account ){
	 return false;
	}

	if ( ( $charge['on_behalf_of'] ?? '' ) !== $current_account ){
	 return false;
	}

	if ( ! empty( $charge['transfer_data'] ) || ! empty( $charge['application_fee_amount'] ) || ! empty( $charge['transfer'] ) ){
	 return false;
	}

	return true;

 } // charge_looks_like_ours

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
	 // Stripe's idempotency conflict (a concurrent POST with the same key
	 // still in flight) comes back as error.type=idempotency_error with no
	 // error.code on some responses, so the type is the fallback that lets
	 // ensure_transfer() recognise it either way.
	 return new \WP_Error(
	  (string) ( $body['error']['code'] ?? $body['error']['type'] ?? $code ),
	  (string) ( $body['error']['message'] ?? 'Stripe request failed.' )
	 );
	}

	return $body;

 } // stripe_request

 /**
  * Validates a Stripe object id before it's interpolated into a request
  * path, and rawurlencode()s it. Every id this file ever builds a path
  * from either came back from a Stripe response we made ourselves, or from
  * GF's own entry/webhook data -- never directly from a site visitor -- but
  * this is cheap insurance against a malformed or unexpected value (for
  * example a path-traversal attempt) ever reaching a Stripe request URL.
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @param  string  $id  required  A PaymentIntent, charge, transfer or refund id.
  * @return string|\WP_Error  The rawurlencode()d id, or a WP_Error if it doesn't look like a Stripe id.
  */
 private static function validated_stripe_id_segment( string $id ){

	if ( ! preg_match( '/^(pi|ch|py|pyr|tr|re)_[A-Za-z0-9]+$/', $id ) ){
	 return new \WP_Error( 'invalid_stripe_id', 'Refusing to build a Stripe request path from a malformed id.' );
	}

	return rawurlencode( $id );

 } // validated_stripe_id_segment

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

	$segment = self::validated_stripe_id_segment( $pi_id );

	if ( is_wp_error( $segment ) ){
	 return $segment;
	}

	return self::stripe_request( 'GET', 'payment_intents/' . $segment, [ 'expand' => [ 'latest_charge' ] ], $key );

 } // get_payment_intent_with_charge

 /**
  * Fetches every page of a Stripe list endpoint, for the two places that
  * need a complete list rather than the first page: sync_reversals()'s
  * refunds and reversals lookups, which both have to see every item to
  * dedupe and sum correctly, not just whatever a default or embedded-list
  * limit happens to return.
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @param  string  $path   required  Path under STRIPE_API_BASE.
  * @param  array   $query  required  Query params, without limit/starting_after.
  * @param  string  $key    required  The transfer key to sign with.
  * @return array|\WP_Error  Every item across every page.
  */
 private static function list_all( string $path, array $query, string $key ){

	$items = [];
	$starting_after = '';
	$pages = 0;

	do {

	 $page_query = $query;
	 $page_query['limit'] = 100;

	 if ( '' !== $starting_after ){
	  $page_query['starting_after'] = $starting_after;
	 }

	 $response = self::stripe_request( 'GET', $path, $page_query, $key );

	 if ( is_wp_error( $response ) ){
	  return $response;
	 }

	 $data = (array) ( $response['data'] ?? [] );
	 $items = array_merge( $items, $data );

	 $has_more = ! empty( $response['has_more'] );
	 $starting_after = $data ? (string) ( end( $data )['id'] ?? '' ) : '';
	 $pages++;

	 // Not a real limit for any realistic volume (100 items/page), just a
	 // backstop against an infinite loop if Stripe's has_more/id pairing
	 // ever misbehaves.
	} while ( $has_more && '' !== $starting_after && $pages < 1000 );

	return $items;

 } // list_all

 /**
  * Builds the args for creating a Transfer from an already-fetched
  * PaymentIntent (with latest_charge expanded). Network calls are limited
  * to the two proactive alert() calls below (marker_forged/marker_missing)
  * -- everything else here is pure, which is what makes this the main
  * unit-test target for the Transfer logic.
  *
  * PI metadata is a HINT, not a trusted source for anything that moves
  * money. GF's own get_stripe_meta_data() maps an admin-chosen feed key to
  * a form field value and merges it into PI/charge metadata in more than
  * one place (capture(), Checkout) outside this plugin's own pre_create
  * filter, so metadata already in a charge GF created cannot be assumed to
  * still be exactly what add_transfer_meta() wrote -- see
  * pi_structurally_ours(). Every value that decides where money goes or how
  * much is independently verified or recomputed below instead of trusted
  * as-is:
  *  - the transfers marker itself is only believed when the PI/charge also
  *    STRUCTURALLY look like one of ours (pi_structurally_ours()); a marker
  *    with no matching structure is treated as forged, not ours.
  *  - destination MUST equal this site's CURRENT get_transfer_account() --
  *    a mismatch is refused outright, never transferred to whatever the
  *    metadata says.
  *  - fee is always calculate_fee() recomputed from the charge; metadata's
  *    proudcity_fee is never used for the money math in either direction
  *    (see the inline note below).
  *  - amount is capped to what the charge can actually still pay out.
  * Every validation failure returns a WP_Error instead of transferring:
  * the marker check is what makes flipping proudcity_payments_charge_model
  * safe mid-flight (see add_transfer_meta()), and the rest guard against a
  * malformed, foreign, mismatched or already-refunded/disputed
  * PaymentIntent ever reaching a Stripe write. TERMINAL_TRANSFER_ERROR_CODES
  * lists which of these codes ensure_transfer() treats as non-retryable.
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
	$charge = (array) ( $pi['latest_charge'] ?? [] );
	$pi_id = (string) ( $pi['id'] ?? '' );

	if ( 'transfers' !== ( $metadata['proudcity_charge_model'] ?? '' ) ){

	 // The marker is gone or wrong, but the charge itself still looks
	 // structurally like one of ours (on_behalf_of to our account, no
	 // destination-charge fields) -- that's not the normal shape of a
	 // pre-cutover destination charge, so it's worth a proactive alert
	 // rather than the silent skip below.
	 if ( self::charge_looks_like_ours( $charge ) ){
	  self::alert( 'marker_missing', sprintf( 'PaymentIntent %s looks like an SC&T transfer but has no (or the wrong) transfers marker.', $pi_id ), [ 'payment_intent' => $pi_id ] );
	 }

	 // Not an error -- this is the normal shape of every pre-cutover
	 // destination charge and every site that has never used transfers.
	 // ensure_transfer() returns this quietly without alerting.
	 return new \WP_Error( 'no_transfer_marker', 'PaymentIntent has no proudcity_charge_model=transfers metadata.' );
	}

	// The marker claims this is ours, but the marker alone is forgeable
	// (see pi_structurally_ours()'s docblock). pi_structurally_ours()
	// itself already checks proudcity_site === get_site_tag(), so a PI
	// genuinely belonging to another site on this shared platform account
	// is also caught here, as marker_forged -- there's no legitimate way
	// for this site to ever see a 'transfers' PI tagged for another site.
	if ( ! self::pi_structurally_ours( $pi ) ){
	 return new \WP_Error( 'marker_forged', 'PaymentIntent carries the transfers marker but fails the structural "is ours" check.' );
	}

	$account = (string) ( $metadata['proudcity_transfer_account'] ?? '' );

	if ( ! preg_match( '/^acct_[A-Za-z0-9]+$/', $account ) ){
	 return new \WP_Error( 'invalid_transfer_account', 'PaymentIntent metadata has a malformed transfer account.' );
	}

	// The destination has to match what this site is configured to pay out
	// to RIGHT NOW -- never whatever the (potentially feed/form-influenced)
	// metadata claims. A site that changed proudcity_payments_account since
	// this PI was created gets refused, not silently paid to the old value.
	$current_account = self::get_transfer_account();

	if ( '' === (string) $current_account || $account !== $current_account ){
	 return new \WP_Error( 'destination_mismatch', "PaymentIntent metadata account does not match this site's current connected account." );
	}

	if ( 'succeeded' !== ( $charge['status'] ?? '' ) || empty( $charge['captured'] ) ){
	 return new \WP_Error( 'charge_not_captured', 'Charge is not succeeded and captured.' );
	}

	// A charge refunded or disputed before we ever got to create a transfer
	// for it is refused outright -- there's no "late" transfer to make for
	// a fully refunded charge, and a disputed charge's funds are frozen.
	// This is a race between the completed-payment hook/reconcile and a
	// very fast refund or dispute, not the normal refund-after-transfer
	// case, which sync_reversals() handles once a transfer exists.
	if ( ! empty( $charge['refunded'] ) ){
	 return new \WP_Error( 'charge_refunded_before_transfer', 'Charge was fully refunded before a transfer existed.' );
	}

	if ( ! empty( $charge['disputed'] ) ){
	 return new \WP_Error( 'charge_disputed', 'Charge is disputed; refusing to create a transfer.' );
	}

	$amount_captured = (int) ( $charge['amount_captured'] ?? 0 );
	$amount_refunded = (int) ( $charge['amount_refunded'] ?? 0 );

	// What the charge can actually still pay out -- amount_refunded is 0 in
	// the overwhelming majority of transfers (no refund yet exists), so this
	// is just amount_captured then. A partial refund issued in the window
	// between the charge succeeding and the transfer being created lowers
	// it, and the fee is recomputed against that lowered figure too, so the
	// city and ProudCity both get their proportional share of what's left
	// rather than the fee being a flat amount sized for the original total.
	// This is also $available, below, as the Transfer's own
	// proudcity_base_amount metadata -- sync_reversals() needs it to avoid
	// re-reversing the SAME pre-transfer refund that already shrank this
	// amount (N1, see sync_reversals()'s docblock).
	$available = $amount_captured - $amount_refunded;

	// The fee is ALWAYS recomputed from the charge, with the currently
	// configured percent -- metadata['proudcity_fee'] is never used for the
	// money math, in either direction. Metadata is still written on the PI
	// (see add_transfer_meta()) for reporting/reconciliation; a difference
	// from the recomputed value is logged (not alerted) below.
	$fee = self::calculate_fee( $available );

	$fee_metadata = filter_var( $metadata['proudcity_fee'] ?? null, FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 0 ] ] );

	if ( false !== $fee_metadata && $fee_metadata !== $fee ){
	 error_log( sprintf(
	  '[proudcity-payments] fee_metadata_mismatch payment_intent=%s metadata_fee=%d recomputed_fee=%d',
	  $pi_id,
	  $fee_metadata,
	  $fee
	 ) );
	}

	$amount = min( $available - $fee, $amount_captured - $fee );

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
	  'proudcity_entry_id'       => (string) ( $metadata['gf_entry_id'] ?? '' ),
	  'proudcity_form_id'        => (string) ( $metadata['proudcity_form_id'] ?? '' ),
	  'proudcity_payment_intent' => $pi_id,
	  'proudcity_charge'         => (string) ( $charge['id'] ?? '' ),
	  'proudcity_site'          => self::get_site_tag(),
	  'proudcity_base_amount'   => (string) $available,
	 ],
	];

 } // build_transfer_args

 /**
  * Finds a Transfer already created for this charge, by destination and
  * source_transaction. Stripe idempotency keys expire after 24h, so this is
  * checked before EVERY transfer POST, not only on a retry or the hourly
  * reconcile -- a crashed or lost response from an earlier POST must never
  * turn into a second Transfer for the same charge (HIGH #2).
  *
  * Fails CLOSED: a lookup error returns a WP_Error, never null, so the
  * caller can tell "definitely no transfer exists yet" apart from "could
  * not find out" and refuse to POST rather than risk a duplicate. Paginates
  * every page (no artificial cap) rather than only the first, since this
  * one city's transfer volume on the shared platform account is unbounded
  * from this function's point of view.
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @param  string  $charge_id    required  The source charge id (ch_...).
  * @param  string  $destination  required  The connected account id.
  * @param  int     $since        required  Unix timestamp, created[gte].
  * @param  string  $key          required  The transfer key to sign with.
  * @return array|null|\WP_Error  The transfer if found, null if definitely not found, WP_Error on a lookup failure.
  */
 public static function find_existing_transfer( string $charge_id, string $destination, int $since, string $key ){

	$starting_after = '';
	$pages = 0;

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
	  return $response;
	 }

	 $data = (array) ( $response['data'] ?? [] );

	 foreach ( $data as $transfer ){
	  if ( ( $transfer['source_transaction'] ?? '' ) === $charge_id ){
	   return $transfer;
	  }
	 }

	 $has_more = ! empty( $response['has_more'] );
	 $starting_after = $data ? (string) ( end( $data )['id'] ?? '' ) : '';
	 $pages++;

	 // Not a real limit for any realistic volume, just a backstop against
	 // an infinite loop if Stripe's has_more/id pairing ever misbehaves.
	} while ( $has_more && '' !== $starting_after && $pages < 1000 );

	return null;

 } // find_existing_transfer

 /**
  * The single entry point for creating a Transfer: the gform_post_payment_completed
  * hook, the `wp proud payments_transfer` retry, and the hourly reconcile
  * cron all call this, so the skip/retry/store logic only lives in one place.
  *
  * Looks up an existing Transfer for this charge before EVERY POST, not
  * only on a retry -- Stripe's idempotency keys expire after 24h, and the
  * hook, a manual retry and the hourly reconcile can all race each other
  * for the same charge, so the Idempotency-Key header alone is not enough
  * to guarantee only one Transfer ever gets created (HIGH #2). The lookup
  * fails CLOSED: if it errors, this returns without ever POSTing, and the
  * entry is left 'pending' rather than 'failed' so the next reconcile pass
  * looks up again instead of blindly retrying a POST.
  *
  * Writes 'pending' + the charge id to entry meta before the lookup/POST,
  * so a crash between here and the POST still leaves a record that this
  * charge needs reconciling, and so a later call (MEDIUM #4) can tell this
  * entry's stored charge id apart from a different charge on the same
  * entry (an unlikely but possible reused/edited entry).
  *
  * The entry's own transfer meta is checked FIRST, before
  * build_transfer_args() ever runs (N3): an entry already bound to a
  * created Transfer for this exact charge is always 'skipped', silently,
  * regardless of what build_transfer_args() would now say about the
  * charge's CURRENT state (for example refunded or disputed AFTER the
  * transfer already went out) -- a created transfer is never downgraded to
  * invalid/failed.
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

	$key = self::get_transfer_key( $api_mode );

	if ( '' === $key ){
	 self::alert( 'no_transfer_key', 'No transfer key configured for this Stripe API mode.', [ 'entry_id' => $entry_id, 'payment_intent' => $pi_id ] );
	 return new \WP_Error( 'no_transfer_key', 'No transfer key configured for this mode.' );
	}

	$pi = self::get_payment_intent_with_charge( $pi_id, $key );

	if ( is_wp_error( $pi ) ){
	 if ( 'resource_missing' === $pi->get_error_code() && ! self::is_transfer_mode( $api_mode ) ){
	  // Not in transfers mode right now, and Stripe says this PI/charge
	  // doesn't exist under this key's mode -- expected noise from probing
	  // every payment while off (N4), not a failure worth alerting on.
	  return $pi;
	 }
	 self::alert( 'transfer_fetch_failed', $pi->get_error_code() . ': ' . $pi->get_error_message(), [ 'entry_id' => $entry_id, 'payment_intent' => $pi_id ] );
	 return $pi;
	}

	$charge_id = (string) ( $pi['latest_charge']['id'] ?? '' );

	// Checked BEFORE build_transfer_args() runs, and with no Stripe call:
	// an entry already bound to a Transfer for THIS charge is always
	// 'skipped', silently, regardless of what build_transfer_args() would
	// say about the charge's CURRENT state (e.g. now refunded/disputed) --
	// a created transfer is never downgraded to invalid/failed (N3).
	if ( $entry_id && '' !== $charge_id ){

	 $existing_id = (string) gform_get_meta( $entry_id, 'proudcity_stripe_transfer_id' );

	 if ( '' !== $existing_id ){

	  $stored_charge_id = (string) gform_get_meta( $entry_id, 'proudcity_stripe_charge_id' );

	  // Only skip if the transfer already recorded on this entry is for
	  // THIS charge. A different stored charge id means this entry's
	  // transfer meta doesn't belong to the charge we were just asked to
	  // transfer -- never silently skip that, since it would hide a real
	  // binding problem (MEDIUM #4).
	  if ( '' !== $stored_charge_id && $stored_charge_id !== $charge_id ){
	   self::alert( 'charge_mismatch', sprintf( 'Entry transfer meta is bound to charge %s, not %s.', $stored_charge_id, $charge_id ), [ 'entry_id' => $entry_id, 'payment_intent' => $pi_id, 'charge' => $charge_id ] );
	   return new \WP_Error( 'charge_mismatch', 'Entry transfer meta is bound to a different charge.' );
	  }

	  return [ 'id' => $existing_id, 'skipped' => true ];
	 }
	}

	$args = self::build_transfer_args( $pi );

	if ( is_wp_error( $args ) ){

	 $error_code = $args->get_error_code();

	 if ( 'no_transfer_marker' === $error_code ){
	  // Expected for every pre-cutover destination charge -- not an error.
	  return $args;
	 }

	 if ( $entry_id ){
	  // Terminal errors (foreign/mismatched/malformed account, forged
	  // marker, already refunded/disputed, non-positive amount) will never
	  // succeed on retry, so they're marked 'invalid' rather than 'failed'
	  // -- reconcile_transfers()' retry pass only looks for 'failed'.
	  $status = in_array( $error_code, self::TERMINAL_TRANSFER_ERROR_CODES, true ) ? 'invalid' : 'failed';
	  gform_update_meta( $entry_id, 'proudcity_stripe_transfer_status', $status );
	  gform_update_meta( $entry_id, 'proudcity_stripe_transfer_pending_since', '' );
	 }

	 $alert_code = 'marker_forged' === $error_code ? 'marker_forged' : 'transfer_invalid';
	 self::alert( $alert_code, $error_code . ': ' . $args->get_error_message(), [ 'entry_id' => $entry_id, 'payment_intent' => $pi_id ] );
	 return $args;
	}

	if ( $entry_id ){
	 gform_update_meta( $entry_id, 'proudcity_stripe_charge_id', $charge_id );
	 gform_update_meta( $entry_id, 'proudcity_stripe_transfer_status', 'pending' );
	 if ( ! gform_get_meta( $entry_id, 'proudcity_stripe_transfer_pending_since' ) ){
	  gform_update_meta( $entry_id, 'proudcity_stripe_transfer_pending_since', (string) time() );
	 }
	}

	// Checked before every POST, not only a retry -- see the docblock above.
	$since = (int) ( $pi['latest_charge']['created'] ?? time() ) - 300;
	$existing = self::find_existing_transfer( $charge_id, $args['destination'], $since, $key );

	if ( is_wp_error( $existing ) ){
	 // Fail closed: leave the entry 'pending' (already written above) and
	 // never POST without knowing whether a Transfer already exists.
	 self::alert( 'transfer_lookup_failed', $existing->get_error_code() . ': ' . $existing->get_error_message(), [ 'entry_id' => $entry_id, 'payment_intent' => $pi_id, 'charge' => $charge_id ] );
	 return $existing;
	}

	if ( $existing ){
	 if ( $entry_id ){
	  gform_update_meta( $entry_id, 'proudcity_stripe_transfer_id', $existing['id'] );
	  gform_update_meta( $entry_id, 'proudcity_stripe_transfer_amount', (string) $existing['amount'] );
	  gform_update_meta( $entry_id, 'proudcity_stripe_transfer_status', 'created' );
	  gform_update_meta( $entry_id, 'proudcity_stripe_transfer_pending_since', '' );
	 }
	 return array_merge( $existing, [ 'skipped' => true ] );
	}

	$response = self::stripe_request( 'POST', 'transfers', $args, $key, 'transfer-' . $charge_id );

	if ( is_wp_error( $response ) ){

	 if ( in_array( $response->get_error_code(), [ 'idempotency_key_in_use', 'idempotency_error' ], true ) ){
	  // A concurrent call (the hook and a reconcile pass, or two reconcile
	  // runs) is already POSTing this same transfer-<charge id> key.
	  // Leave the entry 'pending' rather than 'failed' -- the next
	  // reconcile pass will find the Transfer via find_existing_transfer()
	  // once the in-flight request finishes.
	  return $response;
	 }

	 if ( $entry_id ){
	  gform_update_meta( $entry_id, 'proudcity_stripe_transfer_status', 'failed' );
	  gform_update_meta( $entry_id, 'proudcity_stripe_transfer_pending_since', '' );
	 }

	 self::alert( 'transfer_failed', $response->get_error_code() . ': ' . $response->get_error_message(), [ 'entry_id' => $entry_id, 'payment_intent' => $pi_id, 'charge' => $charge_id ] );
	 return $response;
	}

	if ( $entry_id ){
	 gform_update_meta( $entry_id, 'proudcity_stripe_transfer_id', $response['id'] );
	 gform_update_meta( $entry_id, 'proudcity_stripe_transfer_amount', (string) $response['amount'] );
	 gform_update_meta( $entry_id, 'proudcity_stripe_transfer_status', 'created' );
	 gform_update_meta( $entry_id, 'proudcity_stripe_transfer_pending_since', '' );
	 gf_stripe()->add_note( $entry_id, sprintf( 'ProudCity Payments transferred $%s to %s (transfer %s).', number_format( $response['amount'] / 100, 2 ), $args['destination'], $response['id'] ) );
	}

	// A refund (or even a full charge.refunded) landing in the narrow window
	// between the PI fetch above and this POST would otherwise wait for the
	// next refund webhook/reconcile pass to be reversed -- sync it now.
	self::sync_reversals( $pi_id, $api_mode, $entry_id );

	return $response;

 } // ensure_transfer

 /**
  * Hooks gform_post_payment_completed. Fires for every payment add-on, on
  * every payment this site ever takes -- so every early return here has to
  * be cheap, since a transfer key is often configured fleet-wide via env
  * long before most sites opt into the transfers charge model. Returns
  * immediately when get_transfer_account() is unset (N4) -- with no
  * account, nothing could ever be transferred regardless of the PI's
  * marker. Deliberately does NOT gate on is_transfer_mode() for the
  * current flag: a PI marked transfers has to still get transferred even
  * if the site has since flipped back to 'destination' mid-flight (see
  * add_transfer_meta()); ensure_transfer()'s own PI fetch quietly ignores a
  * resource_missing error while NOT in transfers mode, rather than alerting
  * on routine probing noise for every destination-mode payment.
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

	 if ( ! self::get_transfer_account() ){
	  return;
	 }

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

	  $charge_segment = self::validated_stripe_id_segment( $transaction_id );

	  if ( is_wp_error( $charge_segment ) ){
	   return;
	  }

	  $charge = self::stripe_request( 'GET', 'charges/' . $charge_segment, [], $key );

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
  * partials to each land here as their own refund object. A refund that
  * predates the Transfer (build_transfer_args() already sized it net of
  * that refund, via proudcity_base_amount) is also skipped -- it was
  * priced in once, at creation, and must never be reversed a second time
  * here (N1).
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
	$charge_segment = self::validated_stripe_id_segment( $charge_id );
	$transfer_segment = self::validated_stripe_id_segment( $transfer_id );

	if ( is_wp_error( $charge_segment ) ){
	 return $charge_segment;
	}

	if ( is_wp_error( $transfer_segment ) ){
	 return $transfer_segment;
	}

	$charge = self::stripe_request( 'GET', 'charges/' . $charge_segment, [], $key );

	if ( is_wp_error( $charge ) ){
	 self::alert( 'reversal_fetch_failed', $charge->get_error_code() . ': ' . $charge->get_error_message(), [ 'entry_id' => $entry_id, 'charge' => $charge_id ] );
	 return $charge;
	}

	$transfer = self::stripe_request( 'GET', 'transfers/' . $transfer_segment, [], $key );

	if ( is_wp_error( $transfer ) ){
	 self::alert( 'reversal_fetch_failed', $transfer->get_error_code() . ': ' . $transfer->get_error_message(), [ 'entry_id' => $entry_id, 'charge' => $charge_id ] );
	 return $transfer;
	}

	// The transfer this entry points at must actually be the one made FOR
	// this charge, to THIS site's current account -- otherwise reversing it
	// would move another charge's (or another site's) money (MEDIUM #4).
	$current_account = self::get_transfer_account();

	if ( ( $transfer['source_transaction'] ?? '' ) !== $charge_id || '' === (string) $current_account || ( $transfer['destination'] ?? '' ) !== $current_account ){
	 self::alert( 'transfer_binding_mismatch', sprintf( 'Transfer %s does not match charge %s / account %s.', $transfer_id, $charge_id, (string) $current_account ), [ 'entry_id' => $entry_id, 'charge' => $charge_id ] );
	 return new \WP_Error( 'transfer_binding_mismatch', 'Transfer does not match this charge and account.' );
	}

	$refunds = self::list_all( 'refunds', [ 'charge' => $charge_id ], $key );

	if ( is_wp_error( $refunds ) ){
	 self::alert( 'reversal_fetch_failed', $refunds->get_error_code() . ': ' . $refunds->get_error_message(), [ 'entry_id' => $entry_id, 'charge' => $charge_id ] );
	 return $refunds;
	}

	$reversals = self::list_all( 'transfers/' . $transfer_segment . '/reversals', [], $key );

	if ( is_wp_error( $reversals ) ){
	 self::alert( 'reversal_fetch_failed', $reversals->get_error_code() . ': ' . $reversals->get_error_message(), [ 'entry_id' => $entry_id, 'charge' => $charge_id ] );
	 return $reversals;
	}

	$reversed_refund_ids = [];

	foreach ( $reversals as $reversal ){
	 $refund_id = $reversal['metadata']['refund_id'] ?? '';
	 if ( '' !== $refund_id ){
	  $reversed_refund_ids[] = $refund_id;
	 }
	}

	$charge_amount = (int) ( $charge['amount'] ?? 0 );
	$transfer_amount = (int) ( $transfer['amount'] ?? 0 );
	$transfer_created = (int) ( $transfer['created'] ?? 0 );
	$amount_reversed = (int) ( $transfer['amount_reversed'] ?? 0 );
	$charge_fully_refunded = ! empty( $charge['refunded'] );

	// build_transfer_args() sizes the Transfer on amount_captured minus
	// WHATEVER was already refunded at that moment (proudcity_base_amount),
	// so a refund that predates the transfer is already priced in -- it
	// must never be reversed again here, and the proportional math for
	// every refund that comes AFTER has to be against that smaller base,
	// not the charge's full original amount (N1). Falls back to today's
	// charge-amount-based behaviour when the transfer predates this
	// metadata key (or was created some other way).
	$base_amount = filter_var( $transfer['metadata']['proudcity_base_amount'] ?? null, FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );
	$has_base_amount = false !== $base_amount;
	$proportional_base = $has_base_amount ? $base_amount : $charge_amount;

	$unreversed = array_values( array_filter( $refunds, function( $refund ) use ( $reversed_refund_ids, $has_base_amount, $transfer_created ) {
	 if ( 'succeeded' !== ( $refund['status'] ?? '' ) ){
	  return false;
	 }
	 if ( in_array( $refund['id'] ?? '', $reversed_refund_ids, true ) ){
	  return false;
	 }
	 // Already priced into proudcity_base_amount -- not a new refund to
	 // reverse against the transfer.
	 if ( $has_base_amount && (int) ( $refund['created'] ?? 0 ) < $transfer_created ){
	  return false;
	 }
	 return true;
	} ) );

	// Two independent ceilings on every reversal POST below:
	//  - $remaining: never reverse more than the transfer has left.
	//  - $budget: with a base amount, the transfer was already sized net of
	//    every pre-existing refund, so the only ceiling left is $remaining
	//    itself. Without one (the fallback), never let the TOTAL reversed
	//    for this charge exceed the proportional share of everything
	//    refunded on the charge SO FAR (charge.amount_refunded, Stripe's
	//    own running total) -- idempotent by sum rather than by matching a
	//    specific refund id (MEDIUM #6).
	$remaining = $transfer_amount - $amount_reversed;
	$budget = $has_base_amount
	 ? $remaining
	 : max( 0, self::calculate_reversal_amount( (int) ( $charge['amount_refunded'] ?? 0 ), $transfer_amount, $charge_amount, [ 'entry_id' => $entry_id ] ) - $amount_reversed );
	$count_unreversed = count( $unreversed );
	$results = [ 'reversed' => 0, 'errors' => 0 ];

	foreach ( $unreversed as $i => $refund ){

	 // The last unreversed refund on a fully-refunded charge takes whatever
	 // is left on the transfer exactly, so a 1c rounding difference from the
	 // proportional formula is never stranded on the transfer.
	 $is_last = $charge_fully_refunded && ( $i === $count_unreversed - 1 );

	 $reversal_amount = $is_last
	  ? $remaining
	  : self::calculate_reversal_amount( (int) $refund['amount'], $transfer_amount, $proportional_base, [ 'entry_id' => $entry_id, 'refund_id' => $refund['id'] ] );

	 $reversal_amount = min( $reversal_amount, $remaining, $budget );

	 if ( $reversal_amount <= 0 ){
	  continue;
	 }

	 $refund_segment = self::validated_stripe_id_segment( (string) ( $refund['id'] ?? '' ) );

	 if ( is_wp_error( $refund_segment ) ){
	  // Never silent: an unparseable refund id means money that should be
	  // reversed can't be, so it's flagged for reconcile/a human rather
	  // than just dropped.
	  $results['errors']++;
	  if ( $entry_id ){
	   gform_update_meta( $entry_id, 'proudcity_stripe_reversal_pending', 1 );
	  }
	  self::alert( 'reversal_skipped', sprintf( 'Refund id %s is not a valid Stripe id; skipped.', (string) ( $refund['id'] ?? '' ) ), [ 'entry_id' => $entry_id, 'charge' => $charge_id ] );
	  continue;
	 }

	 $response = self::stripe_request( 'POST', 'transfers/' . $transfer_segment . '/reversals', [
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
	 $budget -= $reversal_amount;
	 $results['reversed']++;

	 if ( $entry_id ){
	  gf_stripe()->add_note( $entry_id, sprintf( 'ProudCity Payments reversed $%s of the transfer for refund %s.', number_format( $reversal_amount / 100, 2 ), $refund['id'] ) );
	 }
	}

	// Defensive path for a failed/voided charge with no refund object at all
	// (fail_payment, void_authorization) -- reverse whatever is left,
	// bounded only by $remaining since no refund amount exists to budget
	// against.
	$charge_voided = 'succeeded' === ( $charge['status'] ?? '' ) && empty( $charge['captured'] );

	if ( ( 'failed' === ( $charge['status'] ?? '' ) || $charge_voided ) && $remaining > 0 ){

	 $response = self::stripe_request( 'POST', 'transfers/' . $transfer_segment . '/reversals', [
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

	 if ( ! self::get_transfer_account() ){
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
	 self::alert( 'payment_refunded_exception', $e->getMessage(), [ 'entry_id' => (int) ( $entry['id'] ?? 0 ) ] );
	}

 } // handle_payment_refunded

 /**
  * Reads the key out of a request's Basic Authorization header, as GF
  * Stripe and stripe_request() both build it: "Basic base64(key:)".
  *
  * @since 2026.11.02
  * @author Curtis
  *
  * @param  array  $args  required  The http_request_args filter's $args.
  * @return string  The key, or '' if the header isn't Basic auth.
  */
 private static function key_from_basic_auth_header( array $args ): string {

	$header = $args['headers']['Authorization'] ?? '';

	if ( ! is_string( $header ) || ! str_starts_with( $header, 'Basic ' ) ){
	 return '';
	}

	$decoded = base64_decode( substr( $header, 6 ), true );

	if ( false === $decoded ){
	 return '';
	}

	$key = strstr( $decoded, ':', true );

	return false === $key ? $decoded : $key;

 } // key_from_basic_auth_header

 /**
  * The Stripe API mode implied by a key's own prefix -- GF signs its
  * refund request with whatever key is active for the feed, so this is how
  * strip_reverse_transfer_for_our_refunds() finds the matching transfer key
  * without needing the feed at all.
  *
  * @since 2026.11.02
  * @author Curtis
  *
  * @param  string  $key  required  A Stripe secret or restricted key.
  * @return string  'live', 'test', or '' when the prefix isn't recognised.
  */
 private static function api_mode_from_key_prefix( string $key ): string {

	if ( str_starts_with( $key, 'sk_live_' ) || str_starts_with( $key, 'rk_live_' ) ){
	 return 'live';
	}

	if ( str_starts_with( $key, 'sk_test_' ) || str_starts_with( $key, 'rk_test_' ) ){
	 return 'test';
	}

	return '';

 } // api_mode_from_key_prefix

 /**
  * Strips reverse_transfer and refund_application_fee from GF's own
  * POST /v1/refunds request when -- and only when -- the charge being
  * refunded is one of ours on the #2955 transfers model.
  *
  * GF's create_refund() (gravityformsstripe/includes/api/class-gf-stripe-api.php)
  * always sends both flags with no filter of its own, and Stripe rejects
  * reverse_transfer on a charge that has no transfer on GF's own Stripe
  * account -- that's always true for an SC&T charge, since the Transfer was
  * created afterwards with our key, not GF's. Every other field GF sends is
  * left exactly as it is.
  *
  * Hooked on the WordPress-core `http_request_args` filter, so this runs on
  * every single wp_remote_request() site-wide, not just Stripe's -- the
  * exact URL/method check has to be the very first thing this does, and it
  * never signs or sends anything with GF's key, only reads which key GF used.
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @param  array   $args  required  The request args about to be sent.
  * @param  string  $url   required  The request URL.
  * @return array
  */
 public static function strip_reverse_transfer_for_our_refunds( $args, $url ){

	if ( self::STRIPE_API_BASE . 'refunds' !== $url ){
	 return $args;
	}

	if ( 'POST' !== ( $args['method'] ?? '' ) ){
	 return $args;
	}

	if ( self::$in_refund_filter ){
	 return $args;
	}

	self::$in_refund_filter = true;

	try {

	 $api_mode = self::api_mode_from_key_prefix( self::key_from_basic_auth_header( $args ) );

	 if ( '' === $api_mode ){
	  return $args;
	 }

	 $transfer_key = self::get_transfer_key( $api_mode );

	 if ( '' === $transfer_key ){
	  // Nothing configured for this mode, so this charge could never be
	  // one of ours -- skip the Stripe round trip entirely.
	  return $args;
	 }

	 $body = $args['body'] ?? null;
	 $body_is_string = is_string( $body );

	 if ( $body_is_string ){
	  parse_str( $body, $parsed );
	 } elseif ( is_array( $body ) ){
	  $parsed = $body;
	 } else {
	  return $args;
	 }

	 $pi_id = (string) ( $parsed['payment_intent'] ?? '' );

	 if ( '' === $pi_id && ! empty( $parsed['charge'] ) ){

	  $charge_segment = self::validated_stripe_id_segment( (string) $parsed['charge'] );

	  if ( is_wp_error( $charge_segment ) ){
	   return $args;
	  }

	  $charge = self::stripe_request( 'GET', 'charges/' . $charge_segment, [], $transfer_key );

	  if ( is_wp_error( $charge ) ){
	   return $args;
	  }

	  $pi_id = (string) ( $charge['payment_intent'] ?? '' );
	 }

	 if ( '' === $pi_id ){
	  return $args;
	 }

	 $pi = self::get_payment_intent_with_charge( $pi_id, $transfer_key );

	 if ( is_wp_error( $pi ) ){
	  error_log( sprintf( '[proudcity-payments] refund_filter_fetch_failed payment_intent=%s: %s', $pi_id, $pi->get_error_message() ) );
	  return $args;
	 }

	 if ( 'transfers' !== ( $pi['metadata']['proudcity_charge_model'] ?? '' ) ){
	  // Not one of ours -- leave GF's reverse_transfer/refund_application_fee
	  // exactly as they are, same as today.
	  return $args;
	 }

	 // The marker claims this PI is ours, but the marker alone is forgeable
	 // (see pi_structurally_ours()'s docblock) -- never strip the flags for
	 // a PI that fails the structural check, same as build_transfer_args().
	 if ( ! self::pi_structurally_ours( $pi ) ){
	  self::alert( 'marker_forged', sprintf( 'PaymentIntent %s carries the transfers marker but fails the structural "is ours" check.', $pi_id ), [ 'payment_intent' => $pi_id ] );
	  return $args;
	 }

	 unset( $parsed['reverse_transfer'], $parsed['refund_application_fee'] );

	 $args['body'] = $body_is_string ? http_build_query( $parsed ) : $parsed;

	 return $args;

	} catch ( \Throwable $e ){
	 error_log( '[proudcity-payments] refund_filter_exception: ' . $e->getMessage() );
	 return $args;
	} finally {
	 self::$in_refund_filter = false;
	}

 } // strip_reverse_transfer_for_our_refunds

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

	 if ( ! self::get_transfer_account() ){
	  return;
	 }

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
  * either mode AND the site has a connected account to pay out to. Keeps
  * running even after a site flips back to 'destination', so an in-flight
  * transfers-mode payment still gets transferred -- see the mid-flight note
  * on add_transfer_meta().
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

	if ( ! self::get_transfer_account() ){
	 return;
	}

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
  *      PaymentIntents (paginated via Stripe search's next_page cursor,
  *      skipping anything younger than 10 minutes to give the
  *      completed-payment hook a chance to transfer it first) and
  *      ensure_transfer() each -- covers a webhook that never reached this
  *      site. An entry id from the PI's own metadata is only trusted if
  *      that entry's own transaction_id still points back at this exact PI
  *      (MEDIUM #4).
  *  (b) Retry every entry GF has recorded with a failed transfer or a
  *      pending reversal, paginated rather than relying on a single page
  *      of results.
  *
  * Guarded end to end: GFAPI/gf_stripe() must both exist (the cron can in
  * principle fire before Gravity Forms Stripe has loaded), and any
  * exception is caught rather than left to break WP-Cron's run.
  *
  * @since 2026.11.02
  * @author Curtis
  * @link https://github.com/proudcity/wp-proudcity/issues/2955
  *
  * @return array{transferred:int,retried:int,skipped:int,errors:int}
  */
 public static function reconcile_transfers(): array {

	$counts = [ 'transferred' => 0, 'retried' => 0, 'skipped' => 0, 'errors' => 0 ];

	if ( ! class_exists( 'GFAPI' ) || ! function_exists( 'gf_stripe' ) ){
	 return $counts;
	}

	try {

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

	  $page = '';
	  $pages_fetched = 0;

	  do {

	   $search_args = [ 'query' => $query, 'limit' => 100 ];

	   if ( '' !== $page ){
	    $search_args['page'] = $page;
	   }

	   $response = self::stripe_request( 'GET', 'payment_intents/search', $search_args, $key );

	   if ( is_wp_error( $response ) ){
	    self::alert( 'reconcile_search_failed', $response->get_error_code() . ': ' . $response->get_error_message(), [ 'api_mode' => $api_mode ] );
	    $counts['errors']++;
	    break;
	   }

	   foreach ( (array) ( $response['data'] ?? [] ) as $pi ){

	    // Give the completed-payment hook a chance to transfer this first --
	    // ensure_transfer()'s own lookup-before-POST makes a race safe, but
	    // it's not worth the extra Stripe calls on every single hourly pass.
	    if ( ( time() - (int) ( $pi['created'] ?? 0 ) ) < 600 ){
	     continue;
	    }

	    $pi_id = (string) ( $pi['id'] ?? '' );
	    $entry_id = (int) ( $pi['metadata']['gf_entry_id'] ?? 0 );

	    if ( $entry_id ){
	     $bound_entry = \GFAPI::get_entry( $entry_id );
	     if ( is_wp_error( $bound_entry ) || (string) ( $bound_entry['transaction_id'] ?? '' ) !== $pi_id ){
	      $entry_id = 0;
	     }
	    }

	    // The PI's own gf_entry_id metadata didn't check out -- look the
	    // entry up by its transaction_id directly before giving up to 0,
	    // since an entry can exist and be correctly bound even when its id
	    // metadata is missing or stale.
	    if ( ! $entry_id ){
	     $found = (array) \GFAPI::get_entries( 0, [
	      'field_filters' => [
	       [ 'key' => 'transaction_id', 'value' => $pi_id ],
	      ],
	     ], null, [ 'offset' => 0, 'page_size' => 1 ] );

	     if ( ! empty( $found[0]['id'] ) ){
	      $entry_id = (int) $found[0]['id'];
	     }
	    }

	    // A transfer already created, or a build_transfer_args() error
	    // already marked terminal, is never retried (N3) -- and re-alerting
	    // an 'invalid' entry every single hourly pass would just be noise
	    // for something already flagged once.
	    if ( $entry_id && in_array( gform_get_meta( $entry_id, 'proudcity_stripe_transfer_status' ), [ 'created', 'invalid' ], true ) ){
	     continue;
	    }

	    $result = self::ensure_transfer( $pi_id, $api_mode, $entry_id );

	    if ( is_wp_error( $result ) ){
	     $counts['errors']++;
	    } elseif ( ! empty( $result['skipped'] ) ){
	     $counts['skipped']++;
	    } else {
	     $counts['transferred']++;
	    }
	   }

	   $page = (string) ( $response['next_page'] ?? '' );
	   $pages_fetched++;

	  } while ( ! empty( $response['has_more'] ) && '' !== $page && $pages_fetched < 1000 );
	 }

	 $page_size = 100;
	 $offset = 0;

	 do {

	  $failed_entries = (array) \GFAPI::get_entries( 0, [
	   'field_filters' => [
	    'mode' => 'any',
	    [ 'key' => 'proudcity_stripe_transfer_status', 'value' => 'failed' ],
	    [ 'key' => 'proudcity_stripe_transfer_status', 'value' => 'pending' ],
	    [ 'key' => 'proudcity_stripe_reversal_pending', 'value' => '1' ],
	   ],
	  ], null, [ 'offset' => $offset, 'page_size' => $page_size ] );

	  foreach ( $failed_entries as $entry ){

	   $entry_id = (int) ( $entry['id'] ?? 0 );
	   $transaction_id = (string) ( $entry['transaction_id'] ?? '' );

	   if ( ! $entry_id || ! str_starts_with( $transaction_id, 'pi_' ) ){
	    continue;
	   }

	   $feed = gf_stripe()->get_payment_feed( $entry );
	   $api_mode = $feed ? self::get_api_mode_for_feed( $feed ) : 'live';

	   $status = gform_get_meta( $entry_id, 'proudcity_stripe_transfer_status' );

	   if ( 'failed' === $status ){
	    $result = self::ensure_transfer( $transaction_id, $api_mode, $entry_id );
	    is_wp_error( $result ) ? $counts['errors']++ : $counts['retried']++;
	   } elseif ( 'pending' === $status ){
	    $pending_since = (int) gform_get_meta( $entry_id, 'proudcity_stripe_transfer_pending_since' );
	    $pending_for = $pending_since ? ( time() - $pending_since ) : 0;

	    // Only retry a pending entry once it's old enough that it's
	    // unlikely to still be the ensure_transfer() call that put it
	    // there (same window handle_payment_completed()'s own in-flight
	    // request would still be running in).
	    if ( $pending_since && $pending_for >= 600 ){
	     $result = self::ensure_transfer( $transaction_id, $api_mode, $entry_id );
	     is_wp_error( $result ) ? $counts['errors']++ : $counts['retried']++;

	     if ( $pending_for > DAY_IN_SECONDS ){
	      self::alert( 'transfer_pending_too_long', sprintf( 'Entry #%d has been pending for over 24h.', $entry_id ), [ 'entry_id' => $entry_id, 'payment_intent' => $transaction_id ] );
	     }
	    }
	   }

	   if ( gform_get_meta( $entry_id, 'proudcity_stripe_reversal_pending' ) ){
	    $result = self::sync_reversals( $transaction_id, $api_mode, $entry_id );
	    is_wp_error( $result ) ? $counts['errors']++ : $counts['retried']++;
	   }
	  }

	  $offset += $page_size;

	 } while ( count( $failed_entries ) === $page_size );

	} catch ( \Throwable $e ){
	 self::alert( 'reconcile_exception', $e->getMessage(), [] );
	 $counts['errors']++;
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
	 // Generic on purpose -- the full Stripe error message (which can echo
	 // back request details) only ever goes to error_log() above, never
	 // onto an entry note any GF admin with entry access can read.
	 gf_stripe()->add_note( $entry_id, sprintf( 'ProudCity Payments could not transfer this payment (ref: %s); ProudCity has been alerted.', $code ) );
	}

 } // alert

}

new ProudGravityformsStripe;
