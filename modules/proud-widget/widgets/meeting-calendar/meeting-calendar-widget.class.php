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

        $settings['show_filter'] = array(
            '#title'         => __( 'Show category filter', 'wp-proud-core' ),
            '#type'          => 'checkbox',
            '#return_value'  => '1',
            '#default_value' => true,
            '#description'   => __( 'Lets visitors filter the calendar by meeting category. Hidden when fewer than two categories apply.', 'wp-proud-core' ),
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
        // ever parsed (see FullcalendarWidget F1). Saved checkbox instances
        // are keyed by term ID and wp_parse_id_list() keeps keys, so
        // array_values() keeps the JSON a list (the JS calls .join()).
        $categories = is_array( $categories ) ? array_values( wp_parse_id_list( $categories ) ) : array();

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
            <?php $this->printCategoryFilter( $instance, $categories ); ?>
            <div class="proud-meeting-calendar" data-calendar="<?php echo esc_attr( wp_json_encode( $config ) ); ?>"></div>
        </div>
        <?php
    }

    /**
     * Prints the native category <select> for one calendar instance.
     *
     * On unless the saved instance explicitly turns it off, so instances
     * saved before the setting existed gain it. Offers the widget's own
     * categories, or every meeting category when there is no limit, and
     * prints nothing when fewer than two apply. No id attributes (the content
     * cache replays markup), so the <select> sits inside its <label>.
     *
     * @param array $instance   Saved values from database.
     * @param int[]  $categories The widget's category limit, already parsed.
     */
    private function printCategoryFilter( $instance, $categories ) {
        if ( isset( $instance['show_filter'] ) && ! $instance['show_filter'] ) {
            return;
        }

        $args = array(
            'taxonomy'   => 'meeting-taxonomy',
            'hide_empty' => true,
            'orderby'    => 'name',
        );
        if ( ! empty( $categories ) ) {
            $args['include'] = $categories;
        }
        $terms = get_terms( $args );
        if ( empty( $terms ) || is_wp_error( $terms ) || count( $terms ) < 2 ) {
            return;
        }
        ?>
        <label class="proud-meeting-calendar-filter-label">
            <?php echo esc_html__( 'Category', 'wp-proud-core' ); ?>
            <select class="proud-meeting-calendar-filter form-control">
                <option value=""><?php echo esc_html__( 'All meeting categories', 'wp-proud-core' ); ?></option>
                <?php foreach ( self::orderTermsHierarchically( $terms ) as $row ) : ?>
                    <option value="<?php echo esc_attr( absint( $row['term']->term_id ) ); ?>"><?php echo str_repeat( "\u{00A0}\u{00A0}\u{00A0}", $row['depth'] ) . esc_html( $row['term']->name ); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php
    }

    /**
     * Orders terms parent-first with each child under its parent, so the
     * native <select> can show the hierarchy by indenting (non-breaking
     * spaces, as wp_dropdown_categories() does). A term whose parent is not
     * in the set (e.g. excluded by the widget's limit) is treated as a root.
     * Siblings keep the incoming (name) order.
     *
     * @param object[] $terms Terms with term_id, name and optional parent.
     * @return array[] Rows of array( 'term' => object, 'depth' => int ).
     */
    private static function orderTermsHierarchically( $terms ) {
        $ids      = array();
        $children = array();
        foreach ( $terms as $term ) {
            $ids[ (int) $term->term_id ] = true;
        }
        $roots = array();
        foreach ( $terms as $term ) {
            $parent = isset( $term->parent ) ? (int) $term->parent : 0;
            if ( $parent && isset( $ids[ $parent ] ) && $parent !== (int) $term->term_id ) {
                $children[ $parent ][] = $term;
            } else {
                $roots[] = $term;
            }
        }

        $rows    = array();
        $visited = array();
        $walk    = function ( $list, $depth ) use ( &$walk, &$rows, &$visited, $children ) {
            foreach ( $list as $term ) {
                $id = (int) $term->term_id;
                if ( isset( $visited[ $id ] ) ) {
                    continue;
                }
                $visited[ $id ] = true;
                $rows[]         = array( 'term' => $term, 'depth' => $depth );
                if ( ! empty( $children[ $id ] ) ) {
                    $walk( $children[ $id ], $depth + 1 );
                }
            }
        };
        $walk( $roots, 0 );

        // A parent cycle leaves terms unreached; list them flat at the end.
        foreach ( $terms as $term ) {
            if ( ! isset( $visited[ (int) $term->term_id ] ) ) {
                $visited[ (int) $term->term_id ] = true;
                $rows[] = array( 'term' => $term, 'depth' => 0 );
            }
        }

        return $rows;
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
