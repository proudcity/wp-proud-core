<?php


if ( class_exists( 'BuddyDrive' ) ) {
    class ProudBuddyDrive {


        /**
         * Constructor
         */
        public function __construct() {
            add_filter('buddydrive_get_name', [ $this, 'buddydrive_get_name']);
        }


        /**
         * Custom BuddyDrive label
         */
        public function buddydrive_get_name() {

            return 'Resources';
        }


    }

    new ProudBuddyDrive();
}
