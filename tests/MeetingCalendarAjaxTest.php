<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for proud_meeting_calendar_feed() in meeting-calendar-ajax.php --
 * issue #2768.
 *
 * The endpoint is public (nopriv) and read-only, so there is no nonce to
 * gate it (see the docblock on the function for why) -- these tests instead
 * cover the validation that stands in its place: GET only, strictly
 * validated start/end dates, a capped/sanitized category list, and a query
 * shape that can never be driven to fetch more than 500 posts.
 */
class MeetingCalendarAjaxTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $_SERVER['REQUEST_METHOD'] = 'GET';
        Functions\when('post_type_exists')->justReturn(true);
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('UTC'));
        Functions\when('wp_die')->justReturn(null);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REQUEST_METHOD'], $_GET['start'], $_GET['end'], $_GET['categories']);
        Monkey\tearDown();
        parent::tearDown();
    }

    private function captureError(): array
    {
        $captured = array();
        Functions\when('wp_send_json_error')->alias(static function ($data, $status = null) use (&$captured) {
            $captured = array('data' => $data, 'status' => $status);
        });
        return $captured;
    }

    // -----------------------------------------------------------------------
    // GET only
    // -----------------------------------------------------------------------

    public function test_non_get_request_is_rejected(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $captured = array();
        Functions\when('wp_send_json_error')->alias(static function ($data, $status = null) use (&$captured) {
            $captured = array('data' => $data, 'status' => $status);
        });

        proud_meeting_calendar_feed();

        $this->assertSame(405, $captured['status']);
    }

    // -----------------------------------------------------------------------
    // No meeting CPT
    // -----------------------------------------------------------------------

    public function test_missing_meeting_post_type_returns_empty_array(): void
    {
        Functions\when('post_type_exists')->justReturn(false);

        $captured = null;
        Functions\when('wp_send_json')->alias(static function ($data) use (&$captured) {
            $captured = $data;
        });

        proud_meeting_calendar_feed();

        $this->assertSame(array(), $captured);
    }

    // -----------------------------------------------------------------------
    // Date validation
    // -----------------------------------------------------------------------

    public function test_missing_dates_are_rejected(): void
    {
        $captured = array();
        Functions\when('wp_send_json_error')->alias(static function ($data, $status = null) use (&$captured) {
            $captured = array('data' => $data, 'status' => $status);
        });

        proud_meeting_calendar_feed();

        $this->assertSame(400, $captured['status']);
    }

    public function test_malformed_date_is_rejected(): void
    {
        $_GET['start'] = '2026-13-45';
        $_GET['end']   = '2026-02-01';

        $captured = array();
        Functions\when('wp_send_json_error')->alias(static function ($data, $status = null) use (&$captured) {
            $captured = array('data' => $data, 'status' => $status);
        });

        proud_meeting_calendar_feed();

        $this->assertSame(400, $captured['status']);
    }

    public function test_end_before_start_is_rejected(): void
    {
        $_GET['start'] = '2026-02-10';
        $_GET['end']   = '2026-02-01';

        $captured = array();
        Functions\when('wp_send_json_error')->alias(static function ($data, $status = null) use (&$captured) {
            $captured = array('data' => $data, 'status' => $status);
        });

        proud_meeting_calendar_feed();

        $this->assertSame(400, $captured['status']);
    }

    public function test_range_over_93_days_is_rejected(): void
    {
        $_GET['start'] = '2026-01-01';
        $_GET['end']   = '2026-12-31';

        $captured = array();
        Functions\when('wp_send_json_error')->alias(static function ($data, $status = null) use (&$captured) {
            $captured = array('data' => $data, 'status' => $status);
        });

        proud_meeting_calendar_feed();

        $this->assertSame(400, $captured['status']);
    }

    public function test_valid_range_is_accepted(): void
    {
        $_GET['start'] = '2026-02-01';
        $_GET['end']   = '2026-03-01';

        $capturedArgs = array();
        \Patchwork\redefine('WP_Query::__construct', function (array $args = array()) use (&$capturedArgs) {
            $capturedArgs = $args;
            $this->posts = array();
        });
        Functions\when('wp_send_json')->justReturn(null);

        proud_meeting_calendar_feed();

        $this->assertSame('meeting', $capturedArgs['post_type']);

        \Patchwork\restoreAll();
    }

    // -----------------------------------------------------------------------
    // Agenda-view datetime format (Y-m-d\TH:i:s)
    //
    // FullCalendar 3.10.2's agenda views (agendaWeek/agendaDay) use min/max
    // time (calendar.msToMoment()'s forceAllDay is false whenever
    // usesMinMaxTime), so they send start/end as a full
    // Y-m-d\TH:i:s -- midnight by default, or whatever minTime/maxTime is
    // configured -- instead of the bare Y-m-d month/basicWeek/basicDay send.
    // Only those two exact forms are accepted; a trailing "Z" or numeric
    // offset, or an out-of-range value that createFromFormat() would
    // otherwise silently normalize, must still 400.
    // -----------------------------------------------------------------------

    public function test_utc_z_suffix_is_rejected(): void
    {
        $_GET['start'] = '2026-10-11T00:00:00';
        $_GET['end']   = '2026-10-11T00:00:00Z';

        $captured = array();
        Functions\when('wp_send_json_error')->alias(static function ($data, $status = null) use (&$captured) {
            $captured = array('data' => $data, 'status' => $status);
        });

        proud_meeting_calendar_feed();

        $this->assertSame(400, $captured['status']);
    }

    public function test_numeric_offset_suffix_is_rejected(): void
    {
        $_GET['start'] = '2026-10-11T00:00:00';
        $_GET['end']   = '2026-10-11T00:00:00+00:00';

        $captured = array();
        Functions\when('wp_send_json_error')->alias(static function ($data, $status = null) use (&$captured) {
            $captured = array('data' => $data, 'status' => $status);
        });

        proud_meeting_calendar_feed();

        $this->assertSame(400, $captured['status']);
    }

    public function test_out_of_range_time_is_rejected(): void
    {
        $_GET['start'] = '2026-10-11T00:00:00';
        $_GET['end']   = '2026-10-11T25:00:00';

        $captured = array();
        Functions\when('wp_send_json_error')->alias(static function ($data, $status = null) use (&$captured) {
            $captured = array('data' => $data, 'status' => $status);
        });

        proud_meeting_calendar_feed();

        $this->assertSame(400, $captured['status']);
    }

    // -----------------------------------------------------------------------
    // Query shape
    // -----------------------------------------------------------------------

    /**
     * FullCalendar's own `end` is exclusive (the day after the visible
     * range). A BETWEEN ... 23:59:59 upper bound included a whole extra day
     * of meetings the calendar never asked for. Two meta_query clauses --
     * >= start 00:00:00 AND < end 00:00:00 -- match FullCalendar's own
     * semantics exactly.
     */
    public function test_meta_query_end_date_is_exclusive(): void
    {
        $_GET['start'] = '2026-02-01';
        $_GET['end']   = '2026-02-15';

        $capturedArgs = array();
        \Patchwork\redefine('WP_Query::__construct', function (array $args = array()) use (&$capturedArgs) {
            $capturedArgs = $args;
            $this->posts = array();
        });
        Functions\when('wp_send_json')->justReturn(null);

        proud_meeting_calendar_feed();

        $this->assertSame('AND', $capturedArgs['meta_query']['relation']);

        $lower = $capturedArgs['meta_query'][0];
        $this->assertSame('datetime', $lower['key']);
        $this->assertSame('DATETIME', $lower['type']);
        $this->assertSame('>=', $lower['compare']);
        $this->assertSame('2026-02-01 00:00:00', $lower['value']);

        $upper = $capturedArgs['meta_query'][1];
        $this->assertSame('datetime', $upper['key']);
        $this->assertSame('DATETIME', $upper['type']);
        $this->assertSame('<', $upper['compare']);
        $this->assertSame('2026-02-15 00:00:00', $upper['value'], 'end is exclusive -- no trailing 23:59:59 upper bound.');

        \Patchwork\restoreAll();
    }

    /**
     * month/basicWeek/basicDay send bare Y-m-d; agendaWeek/agendaDay send a
     * full Y-m-d\TH:i:s at midnight when no minTime/maxTime is configured
     * (reproduced live: start=2026-09-27T00:00:00&end=2026-11-08T00:00:00
     * returned a 400 before this fix).
     */
    public function test_t_suffixed_midnight_range_is_accepted_and_builds_meta_query(): void
    {
        $_GET['start'] = '2026-02-01T00:00:00';
        $_GET['end']   = '2026-02-15T00:00:00';

        $capturedArgs = array();
        \Patchwork\redefine('WP_Query::__construct', function (array $args = array()) use (&$capturedArgs) {
            $capturedArgs = $args;
            $this->posts = array();
        });
        $captured = null;
        Functions\when('wp_send_json')->alias(static function ($data) use (&$captured) {
            $captured = $data;
        });

        proud_meeting_calendar_feed();

        $this->assertSame(array(), $captured, 'A valid range must reach wp_send_json(), not wp_send_json_error().');
        $this->assertSame('meeting', $capturedArgs['post_type']);
        $this->assertSame('2026-02-01 00:00:00', $capturedArgs['meta_query'][0]['value']);
        $this->assertSame('2026-02-15 00:00:00', $capturedArgs['meta_query'][1]['value']);

        \Patchwork\restoreAll();
    }

    /**
     * A configured minTime (e.g. 06:00) makes FullCalendar send a
     * non-midnight start -- that time of day must reach the meta_query
     * value, not get floored to 00:00:00.
     */
    public function test_t_suffixed_non_midnight_start_is_honoured_in_meta_value(): void
    {
        $_GET['start'] = '2026-02-01T06:00:00';
        $_GET['end']   = '2026-02-15T00:00:00';

        $capturedArgs = array();
        \Patchwork\redefine('WP_Query::__construct', function (array $args = array()) use (&$capturedArgs) {
            $capturedArgs = $args;
            $this->posts = array();
        });
        Functions\when('wp_send_json')->justReturn(null);

        proud_meeting_calendar_feed();

        $this->assertSame('2026-02-01 06:00:00', $capturedArgs['meta_query'][0]['value']);

        \Patchwork\restoreAll();
    }

    public function test_query_args_are_scoped_and_bounded(): void
    {
        $_GET['start'] = '2026-02-01';
        $_GET['end']   = '2026-02-15';

        $capturedArgs = array();
        \Patchwork\redefine('WP_Query::__construct', function (array $args = array()) use (&$capturedArgs) {
            $capturedArgs = $args;
            $this->posts = array();
        });
        Functions\when('wp_send_json')->justReturn(null);

        proud_meeting_calendar_feed();

        $this->assertSame('meeting', $capturedArgs['post_type']);
        $this->assertSame(500, $capturedArgs['posts_per_page']);
        $this->assertTrue($capturedArgs['no_found_rows']);
        $this->assertSame('datetime', $capturedArgs['meta_key']);
        $this->assertSame('DATETIME', $capturedArgs['meta_type']);
        $this->assertArrayNotHasKey('tax_query', $capturedArgs, 'No categories requested, so no tax_query should be built.');

        // meta_query's exact shape (exclusive end, two bounded clauses) is
        // covered by test_meta_query_end_date_is_exclusive().

        \Patchwork\restoreAll();
    }

    public function test_categories_build_tax_query_and_are_capped(): void
    {
        $_GET['start']      = '2026-02-01';
        $_GET['end']        = '2026-02-15';
        $_GET['categories'] = implode(',', range(1, 150));

        $capturedArgs = array();
        \Patchwork\redefine('WP_Query::__construct', function (array $args = array()) use (&$capturedArgs) {
            $capturedArgs = $args;
            $this->posts = array();
        });
        Functions\when('wp_send_json')->justReturn(null);

        proud_meeting_calendar_feed();

        $this->assertArrayHasKey('tax_query', $capturedArgs);
        $this->assertSame('meeting-taxonomy', $capturedArgs['tax_query'][0]['taxonomy']);
        $this->assertLessThanOrEqual(100, count($capturedArgs['tax_query'][0]['terms']));

        \Patchwork\restoreAll();
    }

    public function test_non_numeric_categories_are_dropped(): void
    {
        $_GET['start']      = '2026-02-01';
        $_GET['end']        = '2026-02-15';
        $_GET['categories'] = 'abc,<script>,0';

        $capturedArgs = array();
        \Patchwork\redefine('WP_Query::__construct', function (array $args = array()) use (&$capturedArgs) {
            $capturedArgs = $args;
            $this->posts = array();
        });
        Functions\when('wp_send_json')->justReturn(null);

        proud_meeting_calendar_feed();

        $this->assertArrayNotHasKey('tax_query', $capturedArgs, 'Junk input must sanitize down to no valid category ids.');

        \Patchwork\restoreAll();
    }

    // -----------------------------------------------------------------------
    // Item mapping
    // -----------------------------------------------------------------------

    /**
     * The 'datetime' meta value is a site-local wall-clock time with no
     * offset. If it's parsed against wp_timezone() and the site observes
     * DST, a wall time that falls in the spring-forward gap (America/New_York
     * loses 02:00-03:00 on 2026-03-08) gets silently shifted forward an hour
     * by DateTimeImmutable::createFromFormat() instead of coming back as
     * stored. Parsing against UTC instead -- which never has a DST gap --
     * avoids it. (The start/end range itself still parses against
     * wp_timezone(), unaffected here since 2026-02-01/2026-02-15 aren't near
     * a transition.)
     */
    public function test_meeting_datetime_is_not_shifted_by_dst_gap(): void
    {
        $_GET['start'] = '2026-03-01';
        $_GET['end']   = '2026-03-15';

        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('America/New_York'));

        $post = (object) array('ID' => 7, 'post_title' => 'Early Meeting', 'post_password' => '');

        \Patchwork\redefine('WP_Query::__construct', function (array $args = array()) use ($post) {
            $this->posts = array($post);
        });
        Functions\when('get_post_meta')->justReturn('2026-03-08 02:30');
        Functions\when('get_the_title')->justReturn('Early Meeting');
        Functions\when('get_permalink')->justReturn('https://example.com/meetings/early');

        $captured = null;
        Functions\when('wp_send_json')->alias(static function ($data) use (&$captured) {
            $captured = $data;
        });

        proud_meeting_calendar_feed();

        $this->assertCount(1, $captured);
        $this->assertSame('2026-03-08T02:30:00', $captured[0]['start'], 'The stored wall time must survive a DST spring-forward gap unshifted.');

        \Patchwork\restoreAll();
    }

    public function test_items_are_mapped_with_title_start_and_url(): void
    {
        $_GET['start'] = '2026-02-01';
        $_GET['end']   = '2026-02-15';

        $post = (object) array('ID' => 42, 'post_title' => 'Budget Meeting', 'post_password' => '');

        \Patchwork\redefine('WP_Query::__construct', function (array $args = array()) use ($post) {
            $this->posts = array($post);
        });
        Functions\when('get_post_meta')->justReturn('2026-02-05 18:00');
        Functions\when('get_the_title')->justReturn('Budget Meeting');
        Functions\when('get_permalink')->justReturn('https://example.com/meetings/budget');

        $captured = null;
        Functions\when('wp_send_json')->alias(static function ($data) use (&$captured) {
            $captured = $data;
        });

        proud_meeting_calendar_feed();

        $this->assertCount(1, $captured);
        $this->assertSame('Budget Meeting', $captured[0]['title']);
        $this->assertSame('2026-02-05T18:00:00', $captured[0]['start']);
        $this->assertSame('https://example.com/meetings/budget', $captured[0]['url']);

        \Patchwork\restoreAll();
    }

    /**
     * Local San Rafael data had 1 of 791 published meetings with an empty
     * datetime meta value (#2768). Skip rather than fatal or emit a bogus
     * start date.
     */
    public function test_meeting_with_empty_datetime_is_skipped(): void
    {
        $_GET['start'] = '2026-02-01';
        $_GET['end']   = '2026-02-15';

        $post = (object) array('ID' => 1, 'post_title' => 'No Date', 'post_password' => '');

        \Patchwork\redefine('WP_Query::__construct', function (array $args = array()) use ($post) {
            $this->posts = array($post);
        });
        Functions\when('get_post_meta')->justReturn('');

        $captured = null;
        Functions\when('wp_send_json')->alias(static function ($data) use (&$captured) {
            $captured = $data;
        });

        proud_meeting_calendar_feed();

        $this->assertSame(array(), $captured);

        \Patchwork\restoreAll();
    }

    public function test_meeting_with_unparseable_datetime_is_skipped(): void
    {
        $_GET['start'] = '2026-02-01';
        $_GET['end']   = '2026-02-15';

        $post = (object) array('ID' => 2, 'post_title' => 'Bad Date', 'post_password' => '');

        \Patchwork\redefine('WP_Query::__construct', function (array $args = array()) use ($post) {
            $this->posts = array($post);
        });
        Functions\when('get_post_meta')->justReturn('not-a-date');

        $captured = null;
        Functions\when('wp_send_json')->alias(static function ($data) use (&$captured) {
            $captured = $data;
        });

        proud_meeting_calendar_feed();

        $this->assertSame(array(), $captured);

        \Patchwork\restoreAll();
    }

    /**
     * Password-protected meetings are included with title/time/url only --
     * the password gate still applies when the visitor follows the link.
     */
    public function test_password_protected_meeting_is_included(): void
    {
        $_GET['start'] = '2026-02-01';
        $_GET['end']   = '2026-02-15';

        $post = (object) array('ID' => 3, 'post_title' => 'Executive Session', 'post_password' => 'secret');

        \Patchwork\redefine('WP_Query::__construct', function (array $args = array()) use ($post) {
            $this->posts = array($post);
        });
        Functions\when('get_post_meta')->justReturn('2026-02-05 18:00');
        Functions\when('get_the_title')->justReturn('Executive Session');
        Functions\when('get_permalink')->justReturn('https://example.com/meetings/executive-session');

        $captured = null;
        Functions\when('wp_send_json')->alias(static function ($data) use (&$captured) {
            $captured = $data;
        });

        proud_meeting_calendar_feed();

        $this->assertCount(1, $captured);
        $this->assertSame('Executive Session', $captured[0]['title']);

        \Patchwork\restoreAll();
    }
}
