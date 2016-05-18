<?php

use Proud\Core;

class Submenu extends Core\ProudWidget {

    function __construct() {
        parent::__construct(
            'submenu', // Base ID
            __( 'Primary links submenu', 'wp-agency' ), // Name
            array( 'description' => __( "A submenu of the Primary Links menu", 'wp-agency' ), ) // Args
        );
    }

    function initialize() {
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
        $instance['menu_class'] = new Core\ProudMenu('primary_navigation');
        return true;
    //     if ( !empty($pageInfo['parent_link']) && $pageInfo['parent_link'] > 0 ) {
    //       $instance['pageInfo'] = $pageInfo;
    //       return true;
    //     }
    //     return false;
    }

    /**
     * Outputs the content of the widget
     *
     * @param array $args
     * @param array $instance
     */
    public function printWidget( $args, $instance ) {
        extract($instance);
        $menu_class->print_menu();
    }
}

// register Foo_Widget widget
function register_submenu_widget() {
    register_widget( 'Submenu' );
}
add_action( 'widgets_init', 'register_submenu_widget' );