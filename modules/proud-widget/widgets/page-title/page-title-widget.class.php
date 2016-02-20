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
      $this->settings = [];
    }

    /**
     * Determines if content empty, show widget, title ect?  
     *
     * @see self::widget()
     *
     * @param array $args     Widget arguments.
     * @param array $instance Saved values from database.
     */
    public function hasContent($args, &$instance) {
        $instance['page-title'] = get_the_title (  );
        return true;
    }

    /**
     * Outputs the content of the widget
     *
     * @param array $args
     * @param array $instance
     */
    public function printWidget( $args, $instance ) {
      
      ?><h1><?php print $instance['page-title'] ?></h1><?php
    }
}

// register Foo_Widget widget
function register_page_title_widget() {
    register_widget( 'PageTitle' );
}
add_action( 'widgets_init', 'register_page_title_widget' );