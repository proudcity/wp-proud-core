<?php

use Proud\Core;

require_once __DIR__ . '/../fullcalendar/fullcalendar-widget.class.php';
require_once __DIR__ . '/meeting-calendar-ajax.php';

/**
 * Standalone Meeting Calendar widget (issue #2768).
 *
 * Cloned from the Events "Calendar" (FullcalendarWidget), but it does not
 * call WP_FullCalendar::calendar() -- that markup supports only one calendar
 * per page (static self::$args, a single global WPFC.data, inline.js
 * initializing only the first .wpfc-calendar on the page). This widget
 * renders its own markup/config per instance and hits its own ajax feed
 * (proud_meeting_calendar_feed()), so it can coexist with the Events
 * calendar -- or more than one of itself -- on the same page. It still
 * reuses the bundled FullCalendar JS/CSS via
 * FullcalendarWidget::enqueue_scripts().
 */
class MeetingCalendarWidget extends Core\ProudWidget {

    public $post_type;

    function __construct() {
        parent::__construct(
            'meeting_calendar', // Base ID
            __( 'Meeting Calendar', 'wp-proud-core' ), // Name
            array( 'description' => __( 'Display meetings on an interactive calendar', 'wp-proud-core' ), ) // Args
        );
    }

    /**
     * Define shortcode settings.
     *
     * @return void
     */
    function initialize() {
        $settings = $this->settings;
        $this->post_type = 'meeting';
        $options = array();

        $categories = get_categories( array(
            'type'       => $this->post_type,
            'taxonomy'   => 'meeting-taxonomy',
            'hide_empty' => 0,
            'depth'      => 0,
        ) );
        if ( ! empty( $categories ) && empty( $categories['errors'] ) ) {
            foreach ( $categories as $cat ) {
                $options[ absint( $cat->term_id ) ] = esc_attr( $cat->name );
            }
        }
        $default = count( $options ) > 20 ? array() : array_keys( $options );
        $settings['categories'] = array(
            '#title'         => __( 'Limit to category', 'wp-proud-core' ),
            '#type'          => 'checkboxes',
            '#options'       => $options,
            '#default_value' => $default,
            '#description'   => '',
        );

        // No proud_teaser_settings filter here -- that filter exists to feed
        // Elastic-backed teaser widgets, and meetings are queried from MySQL
        // (see #2929), so it would only add fields this widget can't use.
        $this->settings = $settings;
    }

    /**
     * Determines if content empty, show widget, title ect?
     *
     * @see self::widget()
     *
     * Also requires WP_FullCalendar: ProudWidget::widget() calls
     * enqueueFrontend() before this, and enqueueFrontend() reuses
     * FullcalendarWidget::enqueue_scripts(), which touches that class
     * directly. hasContent() returning false here is still needed so a
     * saved widget instance renders nothing once wp-fullcalendar is
     * deactivated.
     *
     * @param array $args     Widget arguments.
     * @param array $instance Saved values from database.
     */
    public function hasContent( $args, &$instance ) {
        return post_type_exists( 'meeting' ) && class_exists( 'WP_FullCalendar' );
    }

    /**
     * Outputs the content of the widget
     *
     * @param array $args
     * @param array $instance
     */
    public function printWidget( $args, $instance ) {
        $categories = isset( $instance['categories'] ) ? $instance['categories'] : array();
        // Shortcode usage can pass categories as a string; only an array is
        // ever parsed (see FullcalendarWidget F1).
        $categories = is_array( $categories ) ? wp_parse_id_list( $categories ) : array();

        $config = array(
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'action'      => 'proud_meeting_calendar',
            'categories'  => $categories,
            'timeFormat'  => get_option( 'wpfc_timeFormat', 'h(:mm)t' ),
            'defaultView' => get_option( 'wpfc_defaultView', 'month' ),
            'weekends'    => (bool) get_option( 'wpfc_weekends', true ),
            'firstDay'    => (int) get_option( 'start_of_week', 0 ),
            'header'      => array(
                'left'   => 'prev,next today',
                'center' => 'title',
                'right'  => implode( ',', (array) get_option( 'wpfc_available_views', array( 'month', 'basicWeek', 'basicDay' ) ) ),
            ),
        );
        ?>
        <div class="proud-meeting-calendar-wrapper">
            <div class="proud-meeting-calendar" data-calendar="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"></div>
        </div>
        <?php
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueueFrontend() {
        // ProudWidget::widget() calls this before hasContent(), so a saved
        // widget instance still reaches here with wp-fullcalendar
        // deactivated. enqueue_scripts() below is itself guarded, but
        // returning early keeps this method from enqueueing
        // proud-meeting-calendar.js against a vendor script that was never
        // registered.
        if ( ! class_exists( 'WP_FullCalendar' ) ) {
            return;
        }

        // Reuse the bundled FullCalendar JS/CSS rather than enqueueing it a
        // second time.
        FullcalendarWidget::enqueue_scripts();

        $assets_url = plugins_url( 'assets/', __FILE__ );

        wp_enqueue_script(
            'proud-meeting-calendar',
            $assets_url . 'meeting-calendar.js',
            array( 'wp-fullcalendar' ),
            self::get_plugin_version(),
            true
        );
    }

    /**
     * wp-proud-core's own version, read from its plugin header rather than a
     * hard-coded constant. Memoised: get_file_data() reads the file, and
     * this can be called more than once per request.
     *
     * @return string
     */
    private static function get_plugin_version() {
        static $version = null;
        if ( null === $version ) {
            $data    = get_file_data( WP_PLUGIN_DIR . '/wp-proud-core/wp-proud-core.php', array( 'Version' => 'Version' ) );
            $version = ! empty( $data['Version'] ) ? $data['Version'] : '';
        }
        return $version;
    }

}

// Register MeetingCalendarWidget widget
function register_meeting_calendar_widget() {
    if ( class_exists( '\Proud\Meeting\ProudMeeting' ) && class_exists( 'WP_FullCalendar' ) ) {
        register_widget( 'MeetingCalendarWidget' );
    }
}
add_action( 'widgets_init', 'register_meeting_calendar_widget' );
