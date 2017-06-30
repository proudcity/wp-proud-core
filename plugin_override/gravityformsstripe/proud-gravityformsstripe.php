<?php

namespace Proud\GravityformsStripe;

class ProudGravityformsStripe {

    /**
     * Constructor
     */
    public function __construct() {
        add_filter('gform_stripe_charge_pre_create', [ $this, 'gform_stripe_charge_pre_create' ], 10, 5);
    }


    function gform_stripe_charge_pre_create($charge_meta, $feed, $submission_data, $form, $entry) {
        $account = get_option('proudcity_payments_account', false);

        // Stripe Connect stuff
        if ($account) {
            // Set up Stripe Connect destination
            $charge_meta['destination'] = $account;
            $charge_meta['transfer_group'] = $form['title'];

            // Add the ProudCity Payments fee
            $percent = getenv('PROUDCITY_PAYMENTS_PERCENT') ? parseFloat(getenv('PROUDCITY_PAYMENTS_PERCENT')) : 3;
            $charge_meta['application_fee'] = round(30 + $submission_data['payment_amount'] * $percent); // In cents
        }

        // Add Metadata
        $charge_meta['description'] = $form['title'];
        $charge_meta['metadata']['form_title'] = $form['title'];
        $charge_meta['metadata']['form_id'] = $form['id'];
        $charge_meta['metadata']['entry_id'] = $entry['id'];

        return $charge_meta;

        print_R($submission_data);
        print_r($feed);
        print_r($entry);
        print_r($form);
        print_r($charge_meta);
        exit;
    }


}

new ProudGravityformsStripe;
