<?php

namespace Proud\GravityformsStripe;

class ProudGravityformsStripe {

    /**
     * Constructor
     */
    public function __construct() {
        add_filter('gform_stripe_charge_pre_create', [ $this, 'gform_stripe_charge_pre_create' ], 10, 5);

        add_filter('gform_stripe_create_customer', [$this, 'gform_stripe_create_customer'], 10, 1);
        add_filter('gform_stripe_create_plan', [$this, 'gform_stripe_create_plan'], 10, 1);
        add_filter('gform_stripe_get_plan', [$this, 'gform_stripe_get_plan'], 10, 1);
        add_filter('gform_stripe_update_subscription', [$this, 'gform_stripe_update_subscription'], 10, 2);

        add_filter('gform_stripe_subscription_single_payment_amount', [$this, 'gform_stripe_subscription_single_payment_amount'], 10, 4);
        add_filter('gform_stripe_subscription_trial_period_days', [$this, 'gform_stripe_subscription_trial_period_days'], 10, 3);
        add_filter('gform_stripe_post_create_subscription', [$this, 'gform_stripe_post_create_subscription'], 10, 2);

    }


    function gform_stripe_charge_pre_create($charge_meta, $feed, $submission_data, $form, $entry) {
        $account = get_option('proudcity_payments_account', false);

        // Stripe Connect stuff
        if ($account) {
            // Set up Stripe Connect destination
            $charge_meta['destination'] = $account;
            $charge_meta['transfer_group'] = $form['title'];

            // Add the ProudCity Payments fee
            $percent = getenv('PROUDCITY_PAYMENTS_PERCENT') ? (float)getenv('PROUDCITY_PAYMENTS_PERCENT') : 3;
            $charge_meta['application_fee'] = round(30 + $submission_data['payment_amount'] * $percent); // In cents
        }

        // Add Metadata
        $charge_meta['description'] = $form['title'];
        $charge_meta['metadata']['form_title'] = $form['title'];
        $charge_meta['metadata']['form_id'] = $form['id'];
        $charge_meta['metadata']['entry_id'] = $entry['id'];

        return $charge_meta;

        //print_R($submission_data);
        //print_r($feed);
        //print_r($entry);
        //print_r($form);
        //print_r($charge_meta);
        //exit;
    }


    function gform_stripe_create_customer($customer_meta) {

        $account = get_option('proudcity_payments_account', false);
        $customer = \Stripe\Customer::create( $customer_meta, ['stripe_account' => $account] );
        //print_r($customer);
        return $customer;

    }

    function gform_stripe_create_plan($plan_meta) {

        $account = get_option('proudcity_payments_account', false);
        $plan = \Stripe\Plan::create( $plan_meta, ['stripe_account' => $account] );
        return $plan;

    }

    function gform_stripe_get_plan($plan_id) {

        try {

            // Get Stripe plan.
            $account = get_option('proudcity_payments_account', false);
            $plan = \Stripe\Plan::retrieve( $plan_id, ['stripe_account' => $account] );

        } catch ( \Exception $e ) {

            /**
             * There is no error type specific to failing to retrieve a subscription when an invalid plan ID is passed. We assume here
             * that any 'invalid_request_error' means that the subscription does not exist even though other errors (like providing
             * incorrect API keys) will also generate the 'invalid_request_error'. There is no way to differentiate these requests
             * without relying on the error message which is more likely to change and not reliable.
             */

            // Get error response.
            $response = $e->getJsonBody();

            // If error is an invalid request error, return error message.
            if ( rgars( $response, 'error/type' ) !== 'invalid_request_error' ) {
                $plan = $this->authorization_error( $e->getMessage() );
            } else {
                $plan = false;
            }

        }

        return $plan;

    }


    function gform_stripe_update_subscription($customer, $plan) {

        $account = get_option('proudcity_payments_account', false);
        $subscription = $customer->updateSubscription( array( 'plan' => $plan->id ), ['stripe_account' => $account] );

        return $subscription;

    }

    function gform_stripe_subscription_trial_period_days($trial_period_days, $form, $submission_data) {

        $day = get_option('proudcity_payments_recurring_date', false);
        $dayDouble = get_option('proudcity_payments_recurring_date_double', false);

        if ($day) {

            // The ProudCity Stripe account is set the America/Los_Angeles
            date_default_timezone_set('America/Los_Angeles');
            $date = time();
            $curDay = (int) date('d', $date);
            $curMonth = (int) date('n', $date);
            $curYear  = (int) date('Y', $date);

            // No trial necessary
            if ($curDay <= $day) {
                return $day - $curDay;
            }

            // Trial logic for $dayDouble is done in gform_stripe_post_create_subscription() below


            // We need to trial until $day next month
            if ($curMonth == 12) {
                $firstDayNextMonth = mktime(0, 0, 0, 1, $day, $curYear+1);
            }
            else {
                $firstDayNextMonth = mktime(0, 0, 0, $curMonth+1, $day, $curYear);
            }

            $daysTilNextMonth = ($firstDayNextMonth - mktime(0, 0, 0, $curMonth, $curDay, $curYear)) / (24 * 3600);

            $daysTilNextMonth = ceil($daysTilNextMonth) - 1;
            return $daysTilNextMonth;
        }

        return $trial_period_days;

    }



    function gform_stripe_post_create_subscription($customer, $plan) {

        $account = get_option('proudcity_payments_account', false);
        $day = get_option('proudcity_payments_recurring_date', false);
        $dayDouble = get_option('proudcity_payments_recurring_date_double', false);

        // The ProudCity Stripe account is set the America/Los_Angeles
        date_default_timezone_set('America/Los_Angeles');
        $date = time();
        $curDay = (int)date('d', $date);
        $curMonth = (int)date('n', $date);
        $curYear = (int)date('Y', $date);

        if ($curDay > $day && $curDay <= $dayDouble) {
            $charge_meta = [
                "customer" => $customer->id,
                "amount" => $plan->amount,
                "currency" => "usd",
                "description" => date('F', $date) . ': ' . $plan->name,
            ];
            $charge = \Stripe\Charge::create($charge_meta, ['stripe_account' => $account]);
        }
    }



    function gform_stripe_subscription_single_payment_amount($single_payment_amount, $payment_amount, $form, $submission_data) {

        // @todo
        return $single_payment_amount;

    }




}

new ProudGravityformsStripe;
