<?php

namespace Proud\GravityformsStripe;

class ProudGravityformsStripe {

    /**
     * Constructor
     */
    public function __construct() {
        add_filter('gform_stripe_post_include_api', [ $this, 'gform_stripe_post_include_api' ], 10, 5);
        //add_filter('gform_stripe_charge_pre_create', [ $this, 'gform_stripe_charge_pre_create' ], 10, 5);
		//

		add_filter( 'gform_stripe_payment_intent_pre_create', [ $this, 'add_transfer_meta' ], 10, 2 );

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
	 * Adds our transfer metadata to the Stripe payment intent
	 *
	 * @since 2023.01.04
	 * @author Curtis
	 *
	 * @param       array           $data               required                The payment information
	 *      - payment_method => some long key
	 *      - amount => the payment amount for original payment
	 *      - currency => USD
	 *      - capture_method => manual
	 *      - confirmation_method => manual
	 *      - confirm => (nothing here)
	 * @param       array           $feed               required                Feed information
	 *      - docs here: https://github.com/proudcity/developers/blob/main/Github%20Issue%20Notes/2153%20-%20Gravity%20Forms%20and%20Stripe%20issue.md
	 */
	public static function add_transfer_meta( $data, $feed ){

		// Stripe connect destination so payments are sent to customers directly
		$transfer_account = get_option( 'proudcity_payments_account', false );

		if ( $transfer_account ){

			// getting fee amount
			$percent = getenv('PROUDCITY_PAYMENTS_PERCENT') ? (float)getenv('PROUDCITY_PAYMENTS_PERCENT') : 3;
			$fee_amount = round(30 + (int) $data['amount'] * $percent); // In cents

			// getting form suffix
			$suffix = get_option('proudcity_payments_descriptor', get_bloginfo('name'));

			// getting form title
			$form = \GFAPI::get_form( absint( $feed['form_id'] ) );
			$form_title = $form['title'];

			$data['statement_descriptor_suffix'] = (string) $suffix;
			$data['application_fee_amount'] = (int) $fee_amount;
			$data['transfer_data']['destination'] = (string) $transfer_account;
			$data['transfer_group'] = (string) $form_title;

		} // if $transfer_account

		return $data;

	}

    function __return_false($stripe_connect_enabled) {
        if (get_option('proudcity_payments_gravityformsstripe_legacy_settings', false)) {
            $stripe_connect_enabled = false;
        }
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
			//  - it all looks good but it says 'uncaptured' so why is that
			//  - our fee didn't get added probably as well1
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


    // function gform_stripe_create_customer($customer_meta) {

    //     $account = get_option('proudcity_payments_account', false);
    //     $customer = \Stripe\Customer::create( $customer_meta, ['stripe_account' => $account] );
    //     //print_r($customer);
    //     return $customer;

    // }

    // function gform_stripe_create_plan($plan_meta) {

    //     $account = get_option('proudcity_payments_account', false);
    //     $plan = \Stripe\Plan::create( $plan_meta, ['stripe_account' => $account] );
    //     return $plan;

    // }

    // function gform_stripe_get_plan($plan_id) {

    //     try {

    //         // Get Stripe plan.
    //         $account = get_option('proudcity_payments_account', false);
    //         $plan = \Stripe\Plan::retrieve( $plan_id, ['stripe_account' => $account] );

    //     } catch ( \Exception $e ) {

    //         /**
    //          * There is no error type specific to failing to retrieve a subscription when an invalid plan ID is passed. We assume here
    //          * that any 'invalid_request_error' means that the subscription does not exist even though other errors (like providing
    //          * incorrect API keys) will also generate the 'invalid_request_error'. There is no way to differentiate these requests
    //          * without relying on the error message which is more likely to change and not reliable.
    //          */

    //         // Get error response.
    //         $response = $e->getJsonBody();

    //         // If error is an invalid request error, return error message.
    //         if ( rgars( $response, 'error/type' ) !== 'invalid_request_error' ) {
    //             $plan = $this->authorization_error( $e->getMessage() );
    //         } else {
    //             $plan = false;
    //         }

    //     }

    //     return $plan;

    // }


    // function gform_stripe_update_subscription($customer, $plan) {

    //     $account = get_option('proudcity_payments_account', false);
    //     $subscription = $customer->updateSubscription( array( 'plan' => $plan->id ), ['stripe_account' => $account] );

    //     return $subscription;

    // }

    // function gform_stripe_subscription_trial_period_days($trial_period_days, $form, $submission_data) {

    //     $day = get_option('proudcity_payments_recurring_date', false);
    //     $dayDouble = get_option('proudcity_payments_recurring_date_double', false);

    //     if ($day) {

    //         // The ProudCity Stripe account is set the America/Los_Angeles
    //         date_default_timezone_set('America/Los_Angeles');
    //         $date = time();
    //         $curDay = (int) date('d', $date);
    //         $curMonth = (int) date('n', $date);
    //         $curYear  = (int) date('Y', $date);

    //         // No trial necessary
    //         if ($curDay <= $day) {
    //             return $day - $curDay;
    //         }

    //         // Trial logic for $dayDouble is done in gform_stripe_post_create_subscription() below


    //         // We need to trial until $day next month
    //         if ($curMonth == 12) {
    //             $firstDayNextMonth = mktime(0, 0, 0, 1, $day, $curYear+1);
    //         }
    //         else {
    //             $firstDayNextMonth = mktime(0, 0, 0, $curMonth+1, $day, $curYear);
    //         }

    //         $daysTilNextMonth = ($firstDayNextMonth - mktime(0, 0, 0, $curMonth, $curDay, $curYear)) / (24 * 3600);

    //         $daysTilNextMonth = ceil($daysTilNextMonth) - 1;
    //         return $daysTilNextMonth;
    //     }

    //     return $trial_period_days;

    // }



    // function gform_stripe_post_create_subscription($customer, $plan) {

    //     $account = get_option('proudcity_payments_account', false);
    //     $day = get_option('proudcity_payments_recurring_date', false);
    //     $dayDouble = get_option('proudcity_payments_recurring_date_double', false);

    //     // The ProudCity Stripe account is set the America/Los_Angeles
    //     date_default_timezone_set('America/Los_Angeles');
    //     $date = time();
    //     $curDay = (int)date('d', $date);
    //     $curMonth = (int)date('n', $date);
    //     $curYear = (int)date('Y', $date);

    //     if ($curDay > $day && $curDay <= $dayDouble) {
    //         $charge_meta = [
    //             "customer" => $customer->id,
    //             "amount" => $plan->amount,
    //             "currency" => "usd",
    //             "description" => date('F', $date) . ': ' . $plan->name,
    //         ];
    //         $charge = \Stripe\Charge::create($charge_meta, ['stripe_account' => $account]);
    //     }
    // }



    // function gform_stripe_subscription_single_payment_amount($single_payment_amount, $payment_amount, $form, $submission_data) {

    //     // @todo
    //     return $single_payment_amount;

    // }





}

new ProudGravityformsStripe;
