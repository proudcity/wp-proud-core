<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FullcalendarWidget -- issue #2768, findings F1-F4.
 *
 *   F1 (Low)       printWidget() ran implode(',', $categories) on whatever
 *                  'categories' held. Shortcode usage
 *                  ([widget widget_name="FullcalendarWidget" categories="1"])
 *                  passes a string, and implode() on a string is a PHP 8
 *                  TypeError -- a fatal. wp_parse_id_list() is only applied
 *                  when the value is already an array; anything else is
 *                  treated as empty.
 *   F2 (Low/Info)  The widget's own $args (before_widget, after_widget, the
 *                  sidebar name, etc.) were passed straight into
 *                  WP_FullCalendar::calendar(), which prints them into the
 *                  inline WPFC.data block and sends them back as ajax query
 *                  params on every calendar navigation. Fixed by building a
 *                  clean args array containing only event-categories.
 *   F3 (Info)      extract($instance) replaced with an explicit read.
 *   F4 (Info/bug)  enqueue_scripts() was missing the 'moment' dependency
 *                  main.js actually needs, hard-coded WPFC_VERSION, and
 *                  carried three unused variables ($min, $obj_id,
 *                  $wpfc_scripts_limit) left over from a commented-out
 *                  conditional.
 *
 * Also covers the security-review Low 1 fix: hasContent()/printWidget()/
 * enqueue_scripts() must do nothing when WP_FullCalendar is missing (e.g.
 * wp-fullcalendar deactivated) rather than fatal. The positive branch (class
 * present) is covered here; the actually-missing-class branch can't be --
 * class_exists() is a PHP internal, so Brain Monkey/Patchwork can't make it
 * return false once this stub exists for the rest of the suite -- and is
 * instead covered by a separate, real PHP process in
 * MeetingCalendarMissingFullCalendarClassTest.
 */
class FullcalendarWidgetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        WP_FullCalendar::$args = array();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function render(array $instance): string
    {
        $widget = new FullcalendarWidget();
        ob_start();
        try {
            $widget->printWidget(array('before_widget' => '<section class="widget">', 'name' => 'sidebar'), $instance);
        } finally {
            $output = (string) ob_get_clean();
        }
        return $output;
    }

    // -----------------------------------------------------------------------
    // F1
    // -----------------------------------------------------------------------

    /**
     * The reason this file exists. A string 'categories' (as a shortcode
     * would pass) must not reach implode() and fatal.
     */
    public function test_string_categories_does_not_fatal(): void
    {
        $this->render(array('categories' => '1'));

        $this->assertArrayNotHasKey('event-categories', WP_FullCalendar::$args, 'A non-array categories value must not populate event-categories.');
    }

    public function test_array_categories_are_parsed_into_event_categories(): void
    {
        $this->render(array('categories' => array('3', '7', '7')));

        $this->assertSame('3,7', WP_FullCalendar::$args['event-categories']);
    }

    public function test_missing_categories_key_sets_no_event_categories(): void
    {
        $this->render(array());

        $this->assertArrayNotHasKey('event-categories', WP_FullCalendar::$args);
    }

    // -----------------------------------------------------------------------
    // F2
    // -----------------------------------------------------------------------

    /**
     * Widget $args (before_widget, the sidebar name, etc.) must never reach
     * WP_FullCalendar::calendar() -- only event-categories should.
     */
    public function test_widget_args_are_not_passed_to_calendar(): void
    {
        $this->render(array('categories' => array('5')));

        $this->assertArrayNotHasKey('before_widget', WP_FullCalendar::$args);
        $this->assertArrayNotHasKey('name', WP_FullCalendar::$args);
        $this->assertSame(array('event-categories' => '5'), WP_FullCalendar::$args);
    }

    // -----------------------------------------------------------------------
    // F4
    // -----------------------------------------------------------------------

    public function test_enqueue_scripts_declares_moment_dependency(): void
    {
        $captured = array();
        Functions\when('wp_enqueue_script')->alias(static function ($handle, $src, $deps = array(), $ver = false, $in_footer = false) use (&$captured) {
            if ('wp-fullcalendar' === $handle) {
                $captured = $deps;
            }
        });

        FullcalendarWidget::enqueue_scripts();

        $this->assertContains('moment', $captured, 'main.js requires the moment dependency to be registered.');
    }

    /**
     * Version must come from wp-fullcalendar's own plugin header, not a
     * hard-coded constant.
     */
    public function test_enqueue_scripts_versions_from_plugin_header(): void
    {
        $capturedVersions = array();
        Functions\when('wp_enqueue_script')->alias(static function ($handle, $src, $deps = array(), $ver = false) use (&$capturedVersions) {
            $capturedVersions[] = $ver;
        });
        Functions\when('wp_enqueue_style')->alias(static function ($handle, $src, $deps = array(), $ver = false) use (&$capturedVersions) {
            $capturedVersions[] = $ver;
        });

        FullcalendarWidget::enqueue_scripts();

        $expected = get_file_data(WP_PLUGIN_DIR . '/wp-fullcalendar/wp-fullcalendar.php', array('Version' => 'Version'))['Version'];

        $this->assertNotEmpty($expected, 'Precondition: wp-fullcalendar.php must declare a Version header.');
        foreach ($capturedVersions as $version) {
            $this->assertSame($expected, $version);
        }
    }

    // -----------------------------------------------------------------------
    // Low 1 (positive branch -- WP_FullCalendar present)
    // -----------------------------------------------------------------------

    public function test_has_content_true_when_wp_fullcalendar_class_exists(): void
    {
        $widget = new FullcalendarWidget();
        $instance = array();

        $this->assertTrue($widget->hasContent(array(), $instance));
    }
}
