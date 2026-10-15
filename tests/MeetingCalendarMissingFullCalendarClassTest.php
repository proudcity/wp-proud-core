<?php

use PHPUnit\Framework\TestCase;

/**
 * Covers the "wp-fullcalendar deactivated" branch of issue #2768's security
 * review (Low 1): ProudWidget::widget() calls enqueueFrontend() before
 * hasContent() -- see widget-base.class.php -- so a saved Calendar/Meeting
 * Calendar widget instance (e.g. in SiteOrigin panels_data) that renders
 * while the wp-fullcalendar plugin is deactivated must not fatal.
 *
 * class_exists() is a PHP internal function, not a userland one, so neither
 * Brain Monkey nor Patchwork can make it return false for 'WP_FullCalendar'
 * inside this process -- tests/fullcalendar-stubs.php has already defined
 * that class for every other test in the suite, and PHP classes cannot be
 * undefined once declared.
 *
 * The only way to exercise the real "class genuinely absent" branch is a
 * separate PHP process that never loads that stub. tests/fixtures/
 * meeting-calendar-missing-fullcalendar-class.php does exactly that: it
 * requires the real, unmodified production files and nothing else.
 */
class MeetingCalendarMissingFullCalendarClassTest extends TestCase
{
    private function runFixture(): array
    {
        $fixture = __DIR__ . '/fixtures/meeting-calendar-missing-fullcalendar-class.php';
        $cmd     = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($fixture) . ' 2>&1';
        $output  = shell_exec($cmd);

        return array('output' => $output, 'decoded' => json_decode((string) $output, true));
    }

    public function test_both_widgets_degrade_without_fatal_when_wp_fullcalendar_is_absent(): void
    {
        $result = $this->runFixture();

        $this->assertIsArray($result['decoded'], 'Fixture process must not fatal. Raw output: ' . $result['output']);

        $this->assertFalse($result['decoded']['fc_has_content'], 'FullcalendarWidget::hasContent() must be false without WP_FullCalendar.');
        $this->assertSame('', $result['decoded']['fc_print_widget_output'], 'FullcalendarWidget::printWidget() must render nothing without WP_FullCalendar.');
        $this->assertSame('', $result['decoded']['fc_enqueue_scripts_output'], 'FullcalendarWidget::enqueue_scripts() must return early without WP_FullCalendar.');

        $this->assertFalse($result['decoded']['mc_has_content'], 'MeetingCalendarWidget::hasContent() must be false without WP_FullCalendar, even though the meeting post type exists.');
        $this->assertSame('', $result['decoded']['mc_enqueue_frontend_output'], 'MeetingCalendarWidget::enqueueFrontend() must return early without WP_FullCalendar.');
    }
}
