<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for MeetingCalendarWidget -- issue #2768.
 *
 * Cloned from the Events "Calendar" (FullcalendarWidget), but it does not
 * call WP_FullCalendar::calendar() -- that markup only supports one calendar
 * per page (static self::$args, a single global WPFC.data) -- so it renders
 * its own markup and config and hits its own ajax feed
 * (proud_meeting_calendar_feed(), see MeetingCalendarAjaxTest).
 */
class MeetingCalendarWidgetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('esc_attr')->alias(static function ($text) {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8', false);
        });
        Functions\when('admin_url')->justReturn('https://example.com/wp-admin/admin-ajax.php');
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // hasContent()
    // -----------------------------------------------------------------------

    public function test_has_content_false_without_meeting_post_type(): void
    {
        Functions\when('post_type_exists')->justReturn(false);

        $widget = new MeetingCalendarWidget();
        $instance = array();

        $this->assertFalse($widget->hasContent(array(), $instance));
    }

    public function test_has_content_true_with_meeting_post_type(): void
    {
        Functions\when('post_type_exists')->justReturn(true);

        $widget = new MeetingCalendarWidget();
        $instance = array();

        $this->assertTrue($widget->hasContent(array(), $instance));
    }

    // -----------------------------------------------------------------------
    // initialize()
    // -----------------------------------------------------------------------

    public function test_initialize_builds_categories_from_meeting_taxonomy(): void
    {
        $capturedArgs = array();
        Functions\when('get_categories')->alias(static function ($args) use (&$capturedArgs) {
            $capturedArgs = $args;
            return array(
                (object) array('term_id' => 4, 'name' => 'Budget'),
                (object) array('term_id' => 9, 'name' => 'Planning'),
            );
        });

        $widget = new MeetingCalendarWidget();
        $widget->initialize();

        $this->assertSame('meeting-taxonomy', $capturedArgs['taxonomy']);
        $this->assertSame('meeting', $capturedArgs['type']);
        $this->assertSame(array(4 => 'Budget', 9 => 'Planning'), $widget->settings['categories']['#options']);
    }

    /**
     * esc_attr() on the term name is load-bearing: option-box.php echoes the
     * checkbox label raw (PCD377).
     */
    public function test_initialize_escapes_category_names(): void
    {
        Functions\when('get_categories')->justReturn(array(
            (object) array('term_id' => 1, 'name' => '<script>alert(1)</script>'),
        ));

        $widget = new MeetingCalendarWidget();
        $widget->initialize();

        $this->assertStringNotContainsString('<script>', $widget->settings['categories']['#options'][1]);
    }

    public function test_initialize_does_not_apply_proud_teaser_settings(): void
    {
        Functions\when('get_categories')->justReturn(array());
        $called = false;
        Functions\when('apply_filters')->alias(static function ($tag, $value = null) use (&$called) {
            if ('proud_teaser_settings' === $tag) {
                $called = true;
            }
            return $value;
        });

        $widget = new MeetingCalendarWidget();
        $widget->initialize();

        $this->assertFalse($called, 'proud_teaser_settings would add Elastic-only fields the meeting widget cannot use.');
    }

    // -----------------------------------------------------------------------
    // printWidget() markup/escaping
    // -----------------------------------------------------------------------

    public function test_print_widget_renders_calendar_container_with_config(): void
    {
        $widget = new MeetingCalendarWidget();

        ob_start();
        $widget->printWidget(array(), array('categories' => array('5', '9')));
        $html = ob_get_clean();

        $this->assertStringContainsString('proud-meeting-calendar', $html);
        $this->assertSame(1, preg_match('/data-calendar="([^"]*)"/', $html, $matches), 'Expected a data-calendar attribute.');
        $config = json_decode(htmlspecialchars_decode($matches[1], ENT_QUOTES), true);

        $this->assertSame(array(5, 9), $config['categories']);
        $this->assertSame('proud_meeting_calendar', $config['action']);
    }

    public function test_print_widget_never_echoes_inline_script(): void
    {
        $widget = new MeetingCalendarWidget();

        ob_start();
        $widget->printWidget(array(), array());
        $html = ob_get_clean();

        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_print_widget_has_no_id_attribute(): void
    {
        $widget = new MeetingCalendarWidget();

        ob_start();
        $widget->printWidget(array(), array());
        $html = ob_get_clean();

        $this->assertDoesNotMatchRegularExpression('/\sid="/', $html, 'No id attribute -- content cache replays this markup verbatim.');
    }

    public function test_print_widget_string_categories_do_not_fatal(): void
    {
        $widget = new MeetingCalendarWidget();

        ob_start();
        $widget->printWidget(array(), array('categories' => '5'));
        $html = ob_get_clean();

        $this->assertStringContainsString('proud-meeting-calendar', $html);
    }

    /**
     * get_option('wpfc_available_views') is expected to hold an array, but
     * it's a plain option value -- nothing stops it from being saved as a
     * string. implode() on a string is a PHP 8 TypeError (the same class of
     * bug as FullcalendarWidget F1), so the option is cast to an array
     * before implode() rather than trusted.
     */
    public function test_print_widget_string_available_views_option_does_not_fatal(): void
    {
        Functions\when('get_option')->alias(static function ($option, $default = false) {
            return 'wpfc_available_views' === $option ? 'month' : $default;
        });

        $widget = new MeetingCalendarWidget();

        ob_start();
        $widget->printWidget(array(), array());
        $html = ob_get_clean();

        $this->assertStringContainsString('proud-meeting-calendar', $html);
    }

    // -----------------------------------------------------------------------
    // enqueueFrontend()
    // -----------------------------------------------------------------------

    public function test_enqueue_frontend_enqueues_meeting_calendar_js_with_plugin_version(): void
    {
        $captured = array();
        Functions\when('wp_enqueue_script')->alias(static function ($handle, $src, $deps = array(), $ver = false, $in_footer = false) use (&$captured) {
            if ('proud-meeting-calendar' === $handle) {
                $captured = array('deps' => $deps, 'ver' => $ver);
            }
        });
        Functions\when('wp_enqueue_style')->justReturn(null);

        $widget = new MeetingCalendarWidget();
        $widget->enqueueFrontend();

        $expected = get_file_data(WP_PLUGIN_DIR . '/wp-proud-core/wp-proud-core.php', array('Version' => 'Version'))['Version'];

        $this->assertNotEmpty($expected, 'Precondition: wp-proud-core.php must declare a Version header.');
        $this->assertSame($expected, $captured['ver']);
        $this->assertContains('wp-fullcalendar', $captured['deps']);
    }

    public function test_enqueue_frontend_reuses_fullcalendar_vendor_assets(): void
    {
        $handles = array();
        Functions\when('wp_enqueue_script')->alias(static function ($handle) use (&$handles) {
            $handles[] = $handle;
        });
        Functions\when('wp_enqueue_style')->alias(static function ($handle) use (&$handles) {
            $handles[] = $handle;
        });

        $widget = new MeetingCalendarWidget();
        $widget->enqueueFrontend();

        $this->assertContains('wp-fullcalendar', $handles, 'Must reuse the vendor JS/CSS enqueue, not duplicate it.');
    }

    // -----------------------------------------------------------------------
    // Registration
    // -----------------------------------------------------------------------

    public function test_registers_only_when_meeting_and_fullcalendar_classes_exist(): void
    {
        $registered = null;
        Functions\when('register_widget')->alias(static function ($class) use (&$registered) {
            $registered = $class;
        });

        register_meeting_calendar_widget();

        $this->assertSame('MeetingCalendarWidget', $registered, '\\Proud\\Meeting\\ProudMeeting and WP_FullCalendar both exist in this test run, so registration must happen.');
    }
}
