<?php

use Proud\Core;

class PageTitle extends Core\ProudWidget {

    function __construct() {
        parent::__construct(
            'pagetitle', // Base ID
            __( 'Page title', 'wp-proud' ), // Name
            array( 'description' => __( "Display the page title", 'wp-proud' ), ) // Args
        );
    }

    function initialize() {
    }

    /**
     * Outputs the content of the widget
     *
     * @param array $args
     * @param array $instance
     */
    public function printWidget( $args, $instance ) {
      $title = get_the_title (  );
      ?><h1><?php print $title; ?></h1><?php
    }
}

// register Foo_Widget widget
function register_page_title_widget() {
    register_widget( 'PageTitle' );
}
add_action( 'widgets_init', 'register_page_title_widget' );