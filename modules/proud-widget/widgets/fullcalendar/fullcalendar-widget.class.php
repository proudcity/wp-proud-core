<?php

use Proud\Core;

class FullcalendarWidget extends Core\ProudWidget {

    function __construct() {
        parent::__construct(
            'fullcalendar', // Base ID
            __( 'Calendar', 'wp-agency' ), // Name
            array( 'description' => __( "Display events on an interactive calendar", 'wp-agency' ), ) // Args
        );
    }


    /**
    * Define shortcode settings.
    *
    * @return  void
    */
    function initialize() {
        $settings = $this->settings;
        $this->post_type = 'event';
        $options = [];

        $categories = get_categories( [
          'type' => $this->post_type, 
          'taxonomy' => 'event-categories', 
          'hide_empty' => 0,
          'depth' => 0,
        ]);
        if( !empty( $categories ) && empty( $categories['errors'] ) ) {
          foreach ($categories as $cat) {
            $options[$cat->term_id] = $cat->name;
          };
        }
        $default = count($options) > 20 ? array() : array_keys($options);
        $settings['categories'] = [
          '#title' => __( 'Limit to category', 'proud-teaser' ),
          '#type' => 'checkboxes',
          '#options' => $options,
          '#default_value' => $default,
          '#description' => ''
        ];

        $this->settings = apply_filters( 'proud_teaser_settings', $settings, $this->post_type );
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
        if ( !empty($categories) ) {
            $args['event-categories'] = implode(',', $categories);
        }

        $this->enqueue_scripts();
        echo WP_FullCalendar::calendar($args);
    }


    public static function enqueue_scripts(){
      global $wp_query;
      $min = defined('WP_DEBUG') && WP_DEBUG ? '':'.min';
      $obj_id = is_home() ? '-1':$wp_query->get_queried_object_id();
      $wpfc_scripts_limit = get_option('wpfc_scripts_limit');
      $path = 'wp-fullcalendar/wp-fullcalendar.php';
      //if( empty($wpfc_scripts_limit) || in_array($obj_id, explode(',',$wpfc_scripts_limit)) ){
        //Scripts
        wp_enqueue_script('wp-fullcalendar', plugins_url('includes/js/main.js',$path), array('jquery', 'jquery-ui-core','jquery-ui-widget','jquery-ui-position', 'jquery-ui-selectmenu'), WPFC_VERSION); //jQuery will load as dependency
        WP_FullCalendar::localize_script();
        //Styles
        wp_enqueue_style('wp-fullcalendar', plugins_url('includes/css/main.css',$path), array(), WPFC_VERSION);
        //Load custom style or jQuery UI Theme
        //$wpfc_theme = '';//get_option('wpfc_theme_css');
        
      //}
  }
    
}

// register Foo_Widget widget
function register_fullcalendar_widget() {
  include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
  if ( is_plugin_active( 'wp-fullcalendar/wp-fullcalendar.php' ) ) {
    register_widget( 'FullcalendarWidget' );
  }  
}
add_action( 'widgets_init', 'register_fullcalendar_widget' );