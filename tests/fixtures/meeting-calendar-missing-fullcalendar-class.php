<?php

/**
 * Standalone fixture for the "wp-fullcalendar deactivated" branch (security
 * review Low 1, issue #2768).
 *
 * class_exists() is a PHP internal, not a userland function, so Brain
 * Monkey/Patchwork cannot intercept it -- there is no way to make
 * class_exists('WP_FullCalendar') return false inside the main PHPUnit
 * process, where tests/fullcalendar-stubs.php has already defined that
 * class for every other test in the suite.
 *
 * This script is run in its own PHP process (see
 * MeetingCalendarMissingFullCalendarClassTest), where WP_FullCalendar is
 * genuinely never defined -- the same situation as a site with wp-fullcalendar
 * deactivated but a saved Meeting Calendar/Calendar widget instance still in
 * panels_data. It requires the real production files (not stubs) and proves
 * that both widgets degrade to "render nothing" instead of a fatal
 * "Class WP_FullCalendar not found" when SiteOrigin's widget() call chain
 * reaches enqueueFrontend()/enqueue_scripts() before hasContent() gets a
 * chance to say no.
 */

function add_action() {}
function add_filter() {}
function __($text, $domain = '') { return $text; }
function post_type_exists($post_type) { return true; }
function admin_url($path = '', $scheme = 'admin') { return ''; }
function get_option($option, $default = false) { return $default; }
function wp_json_encode($data, $options = 0, $depth = 512) { return json_encode($data, $options, $depth); }
function get_file_data($file, $default_headers, $context = '') {
    $headers = array();
    foreach ($default_headers as $field => $regex) {
        $headers[$field] = '';
    }
    return $headers;
}
function absint($n) { return abs((int) $n); }
function esc_attr($text) { return $text; }
function plugins_url() { return ''; }
function plugin_dir_path() { return ''; }
function wp_enqueue_script() {}
function wp_enqueue_style() {}
function wp_parse_id_list($list) {
    if (!is_array($list)) {
        $list = preg_split('/[\s,]+/', (string) $list);
    }
    return array_unique(array_map('absint', $list));
}
function register_widget($class) {}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', '/nonexistent');
}

class WP_Widget {
    public $id_base;
    public $name;
    public $option_name;
    public $id;
    public $number;
    public $widget_options = [];
    public $control_options = [];
    public function __construct($id_base = '', $name = '', $widget_options = [], $control_options = []) {
        $this->id_base         = $id_base;
        $this->name            = $name;
        $this->option_name     = 'widget_' . $id_base;
        $this->widget_options  = $widget_options;
        $this->control_options = $control_options;
    }
}

require_once __DIR__ . '/../../modules/proud-widget/widget-base.class.php';

// Deliberately NOT defining WP_FullCalendar -- that's the whole point.
require_once __DIR__ . '/../../modules/proud-widget/widgets/fullcalendar/fullcalendar-widget.class.php';
require_once __DIR__ . '/../../modules/proud-widget/widgets/meeting-calendar/meeting-calendar-widget.class.php';

$result = array();

// --- FullcalendarWidget ------------------------------------------------
$fc = new FullcalendarWidget();
$instance = array();
$result['fc_has_content'] = $fc->hasContent(array(), $instance);

ob_start();
$fc->printWidget(array(), array());
$result['fc_print_widget_output'] = ob_get_clean();

ob_start();
FullcalendarWidget::enqueue_scripts();
$result['fc_enqueue_scripts_output'] = ob_get_clean();

// --- MeetingCalendarWidget -----------------------------------------------
$mc = new MeetingCalendarWidget();
$instance = array();
$result['mc_has_content'] = $mc->hasContent(array(), $instance);

ob_start();
$mc->enqueueFrontend();
$result['mc_enqueue_frontend_output'] = ob_get_clean();

// register_meeting_calendar_widget()'s class_exists('WP_FullCalendar') gate
// must not fatal either. (register_fullcalendar_widget() is out of scope
// here -- it gates on is_plugin_active(), not class_exists(), and calling it
// needs a real ABSPATH/wp-admin/includes/plugin.php this fixture doesn't have.)
register_meeting_calendar_widget();

echo json_encode($result);
exit(0);
