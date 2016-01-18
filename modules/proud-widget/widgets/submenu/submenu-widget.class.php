<?php

use Proud\Core;

class Submenu extends Core\ProudWidget {

    function __construct() {
        parent::__construct(
            'submenu', // Base ID
            __( 'Submenu', 'wp-agency' ), // Name
            array( 'description' => __( "Display a submenu", 'wp-agency' ), ) // Args
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
        global $pageInfo;
        if ( $pageInfo['parent_link'] > 0 ) {
          
            $args = array(
              'menu' => $pageInfo['menu'],
              'submenu' => $pageInfo['parent_link'],
              'menu_class' => 'nav nav-pills nav-stacked submenu',
              'fallback_cb' => false,
            );

            wp_nav_menu( $args );
        }
        
    }
}

// register Foo_Widget widget
function register_submenu_widget() {
    register_widget( 'Submenu' );
}
add_action( 'widgets_init', 'register_submenu_widget' );