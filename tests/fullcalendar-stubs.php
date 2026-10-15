<?php

/**
 * Stubs for the Calendar / Meeting Calendar widgets' third-party dependency
 * and the plugin-version lookups both widgets' enqueue_scripts() use.
 *
 * WP_FullCalendar is the only wp-fullcalendar class our code touches
 * (FullcalendarWidget::printWidget()/::enqueue_scripts()). A bare stand-in
 * captures what's passed to calendar() so FullcalendarWidgetTest can assert
 * on it without the real third-party plugin loaded.
 *
 * wp_parse_id_list() and get_file_data() are faithful copies of the
 * WordPress core implementations (wp-includes/formatting.php,
 * wp-includes/functions.php) rather than permissive stubs: F1 depends on
 * wp_parse_id_list() actually rejecting non-numeric junk, and
 * FullcalendarWidget::get_wpfc_version() / MeetingCalendarWidget's own
 * version helper depend on get_file_data() actually reading header comments
 * out of the real plugin files on disk.
 *
 * \Proud\Meeting\ProudMeeting is a bare stand-in for the wp-proud-meeting
 * plugin class. register_meeting_calendar_widget() gates registration on
 * class_exists() for both this and WP_FullCalendar, which Brain Monkey
 * cannot intercept (class_exists() is a PHP internal, not a redefinable
 * userland function) -- the only way to exercise that gate at all is for
 * both classes to genuinely exist in the test run.
 */

namespace Proud\Meeting {
    if (!class_exists(__NAMESPACE__ . '\ProudMeeting')) {
        class ProudMeeting {}
    }
}

namespace {

if (!defined('WP_PLUGIN_DIR')) {
    // tests/ -> wp-proud-core -> plugins. Matches the real WP_PLUGIN_DIR,
    // which holds every sibling plugin directory wp-fullcalendar lives in.
    define('WP_PLUGIN_DIR', dirname(__DIR__, 2));
}

if (!class_exists('WP_FullCalendar')) {
    class WP_FullCalendar {
        public static $args = array();

        public static function calendar($args = array()) {
            self::$args = $args;
            return '<div class="wpfc-calendar-wrapper"></div>';
        }

        public static function localize_script() {}
    }
}

if (!function_exists('wp_parse_id_list')) {
    function wp_parse_id_list($list) {
        if (!is_array($list)) {
            $list = preg_split('/[\s,]+/', (string) $list);
        }
        return array_unique(array_map('absint', $list));
    }
}

if (!function_exists('get_file_data')) {
    function get_file_data($file, $default_headers, $context = '') {
        $file_data = @file_get_contents((string) $file, false, null, 0, 8192);
        if (false === $file_data) {
            $file_data = '';
        }
        $file_data = str_replace("\r", "\n", $file_data);

        $headers = array();
        foreach ($default_headers as $field => $regex) {
            if (preg_match('/^[ \t\/*#@]*' . preg_quote($regex, '/') . ':(.*)$/mi', $file_data, $match) && $match[1]) {
                $headers[$field] = trim(preg_replace('/\s*(?:\*\/|\?>).*/', '', $match[1]));
            } else {
                $headers[$field] = '';
            }
        }

        return $headers;
    }
}

}
