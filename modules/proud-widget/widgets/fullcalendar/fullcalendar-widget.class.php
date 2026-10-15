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
        add_filter( 'wpmfc_calendar_taxonomy_args', array($this, 'wpmfc_calendar_taxonomy_args'), 10, 2 );

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
            $options[ absint( $cat->term_id ) ] = esc_attr( $cat->name );
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
     * Filter FullCalender to make "All Event Categories" the default
     *
     * @param $taxonomy_args
     * @param $taxonomy
     *
     * @return array
     */
    public function wpmfc_calendar_taxonomy_args($taxonomy_args, $taxonomy) {
        $taxonomy_args['selected'] = 0;
        return $taxonomy_args;
    }

    /**
     * Determines if content empty, show widget, title ect?
     *
     * @see self::widget()
     *
     * ProudWidget::widget() calls enqueueFrontend() -- here, the static
     * enqueue_scripts() called from printWidget() -- before it checks
     * hasContent(), so a saved widget instance (e.g. in SiteOrigin
     * panels_data) still has its enqueue path run even when this returns
     * false. That path is itself guarded (see enqueue_scripts()), but
     * hasContent() returning false here is what keeps printWidget() -- and
     * its direct WP_FullCalendar::calendar() call -- from running at all
     * once wp-fullcalendar is deactivated.
     *
     * @param array $args     Widget arguments.
     * @param array $instance Saved values from database.
     */
    public function hasContent($args, &$instance) {
        return class_exists( 'WP_FullCalendar' );
    }

    /**
     * Outputs the content of the widget
     *
     * @param array $args
     * @param array $instance
     */
    public function printWidget( $args, $instance ) {
        // Belt-and-suspenders: hasContent() already keeps this from being
        // reached through ProudWidget::widget(), but printWidget() is public
        // and can be called directly (e.g. via the_widget()).
        if ( ! class_exists( 'WP_FullCalendar' ) ) {
            return;
        }

        // Explicit read instead of extract($instance) -- the only key this
        // method uses is 'categories'.
        $categories = isset( $instance['categories'] ) ? $instance['categories'] : array();
        // Shortcode usage (e.g. [widget widget_name="FullcalendarWidget" categories="1"])
        // passes categories as a string, not an array. implode() on a string
        // is a PHP 8 TypeError, so anything that isn't already an array is
        // treated as empty rather than parsed.
        $categories = is_array( $categories ) ? wp_parse_id_list( $categories ) : array();

        // Build a clean args array for WP_FullCalendar::calendar() rather than
        // passing the widget's own $args (before_widget, after_widget, etc.)
        // through -- those get printed into the inline WPFC.data block and
        // sent back as ajax query params.
        $calendar_args = array();
        if ( ! empty( $categories ) ) {
            $calendar_args['event-categories'] = implode( ',', $categories );
        }

        $this->enqueue_scripts();
        echo WP_FullCalendar::calendar( $calendar_args );
    }


    public static function enqueue_scripts(){
      // Called from ProudWidget::widget() via printWidget() -- and, for
      // MeetingCalendarWidget, directly from enqueueFrontend() -- before
      // hasContent() is consulted. Without this, a saved widget instance
      // with wp-fullcalendar deactivated fatals on localize_script() below.
      if ( ! class_exists( 'WP_FullCalendar' ) ) {
        return;
      }
      $path = 'wp-fullcalendar/wp-fullcalendar.php';
      wp_enqueue_script('wp-fullcalendar', plugins_url('includes/js/main.js',$path), array('jquery', 'jquery-ui-core','jquery-ui-widget','jquery-ui-position', 'jquery-ui-selectmenu', 'moment'), self::get_wpfc_version()); //jQuery will load as dependency
      WP_FullCalendar::localize_script();
      //Styles
      wp_enqueue_style('wp-fullcalendar', plugins_url('includes/css/main.css',$path), array(), self::get_wpfc_version());
  }

    /**
     * Version of the bundled wp-fullcalendar plugin, read from its own plugin
     * header rather than relying on the WPFC_VERSION constant it defines --
     * this still works if that plugin is ever updated without a code change
     * here. Memoised: get_file_data() reads the file, and this can be called
     * more than once per request.
     *
     * @return string
     */
    private static function get_wpfc_version() {
        static $version = null;
        if ( null === $version ) {
            $data    = get_file_data( WP_PLUGIN_DIR . '/wp-fullcalendar/wp-fullcalendar.php', array( 'Version' => 'Version' ) );
            $version = ! empty( $data['Version'] ) ? $data['Version'] : '';
        }
        return $version;
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
