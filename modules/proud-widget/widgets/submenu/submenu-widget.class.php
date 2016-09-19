<?php

use Proud\Core;

class Submenu extends Core\ProudWidget {

    function __construct() {
        parent::__construct(
            'submenu', // Base ID
            __( 'Submenu', 'wp-agency' ), // Name
            array( 'description' => __( "Select a menu to display or, display a submenu from the Primary Menu", 'wp-agency' ), ) // Args
        );
    }


    /**
    * Define shortcode settings.
    *
    * @return  void
    */
    function initialize() {
        $menus = [];
        $primary = 'primary-links';
        $default = 'primary-links';

        // Try to grab primary
        global $proud_menu_util;
        foreach ( $proud_menu_util::$menus as $key => $menu ) {
            if( $key === $primary) {
                $menus[$key] = $menu->name . __( ' (Primary Menu)', 'wp-proud-core' );
                $default = $key;
            }
            else {
                $menus[$key] = $menu->name;
            }
        } 
        // No default
        if(!$default) {
            reset($menus);
            $default = key($menus);
        }

        $this->settings += array(
          'menu_id' => array(
            '#title' => __( 'Menu to use', 'wp-proud-core' ),
            '#type' => 'select',
            '#options' => $menus,
            '#default_value' => $default
          ),
          'format' => array(
            '#title' => __( 'Format', 'wp-proud-core' ),
            '#type' => 'radios',
            '#options' => [
              'sidebar' => 'Sidebar',
              'pills' => 'Pills',
            ],
            '#default_value' => 'sidebar',
          ),
        );
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
        $instance['menu_class'] = new Core\ProudMenu( $instance['menu_id'], $instance['format'] );
        return true;
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