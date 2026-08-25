<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for proudcity_em_strip_accumulated_recurrence_dates() in
 * modules/events-manager-recurrence-slug.php.
 *
 * Events Manager builds each recurring occurrence's slug from a $post_fields
 * array it never resets between loop iterations, so occurrence N inherits every
 * previous occurrence's date. Our filter rebuilds the slug from the recurring
 * template's own post_name plus the trailing date.
 *
 * See wp-proudcity#2893.
 */
class EventsManagerRecurrenceSlugTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Stage the recurring template post that get_post() will return.
     */
    private function stage_template( $post_name ): void
    {
        $template = new \stdClass();
        $template->ID        = 23314;
        $template->post_name = $post_name;

        Functions\when('get_post')->justReturn($template);
    }

    /**
     * A stand-in for the EM_Event object passed as the filter's fifth argument.
     */
    private function em_event( $post_id = 23314 )
    {
        $EM_Event = new \stdClass();
        $EM_Event->post_id = $post_id;

        return $EM_Event;
    }

    /**
     * Convenience wrapper — the two unused middle arguments are noise in every test.
     */
    private function filter( $slug, $EM_Event = null )
    {
        return proudcity_em_strip_accumulated_recurrence_dates(
            $slug,
            [ 'post_name' => $slug ],
            0,
            [],
            $EM_Event ?? $this->em_event()
        );
    }

    /**
     * The bug case: a slug carrying every prior occurrence date keeps only the last.
     */
    public function test_accumulated_slug_keeps_only_the_last_date(): void
    {
        $this->stage_template('vector-control-board-meeting-5');

        $slug = 'vector-control-board-meeting-5'
            . '-2026-01-08-2026-02-12-2026-03-12-2026-04-09-2026-05-14-2026-06-11'
            . '-2026-07-09-2026-08-13-2026-09-10-2026-10-08-2026-11-12-2026-12-10';

        $this->assertSame(
            'vector-control-board-meeting-5-2026-12-10',
            $this->filter($slug)
        );
    }

    /**
     * Two dates is the earliest point the bug shows up.
     */
    public function test_two_date_slug_keeps_only_the_last_date(): void
    {
        $this->stage_template('wheelock-township-regular-meeting');

        $this->assertSame(
            'wheelock-township-regular-meeting-2025-04-01',
            $this->filter('wheelock-township-regular-meeting-2025-03-04-2025-04-01')
        );
    }

    /**
     * A correct single-date slug must survive untouched, so the filter stays a
     * no-op once Events Manager is fixed upstream.
     */
    public function test_correct_slug_is_unchanged(): void
    {
        $this->stage_template('vector-control-board-meeting-5');

        $this->assertSame(
            'vector-control-board-meeting-5-2026-01-08',
            $this->filter('vector-control-board-meeting-5-2026-01-08')
        );
    }

    /**
     * Running the filter over its own output changes nothing.
     */
    public function test_filter_is_idempotent(): void
    {
        $this->stage_template('vector-control-board-meeting-5');

        $once  = $this->filter('vector-control-board-meeting-5-2026-01-08-2026-02-12');
        $twice = $this->filter($once);

        $this->assertSame($once, $twice);
        $this->assertSame('vector-control-board-meeting-5-2026-02-12', $twice);
    }

    /**
     * A slug that does not start with the template slug is not ours to rewrite.
     */
    public function test_slug_not_matching_template_is_unchanged(): void
    {
        $this->stage_template('vector-control-board-meeting-5');

        $this->assertSame(
            'some-other-event-2026-01-08-2026-02-12',
            $this->filter('some-other-event-2026-01-08-2026-02-12')
        );
    }

    /**
     * The template slug must match on a segment boundary. A template named
     * "board-meeting" must not claim "board-meeting-annex-2026-01-08".
     */
    public function test_partial_template_match_is_unchanged(): void
    {
        $this->stage_template('board-meeting');

        $this->assertSame(
            'board-meeting-annex-2026-01-08',
            $this->filter('board-meeting-annex-2026-01-08')
        );
    }

    /**
     * A trailing suffix that is not a Y-m-d date means the site has filtered
     * em_event_save_events_format. Fall through rather than mangle the slug.
     */
    public function test_non_date_suffix_is_unchanged(): void
    {
        $this->stage_template('vector-control-board-meeting-5');

        $this->assertSame(
            'vector-control-board-meeting-5-january-8th',
            $this->filter('vector-control-board-meeting-5-january-8th')
        );
    }

    /**
     * A slug with no date suffix at all is left alone.
     */
    public function test_bare_template_slug_is_unchanged(): void
    {
        $this->stage_template('vector-control-board-meeting-5');

        $this->assertSame(
            'vector-control-board-meeting-5',
            $this->filter('vector-control-board-meeting-5')
        );
    }

    /**
     * Regex metacharacters in the template slug must be quoted, not interpreted.
     */
    public function test_template_slug_with_regex_metacharacters(): void
    {
        $this->stage_template('meeting.a+b');

        $this->assertSame(
            'meeting.a+b-2026-02-12',
            $this->filter('meeting.a+b-2026-01-08-2026-02-12')
        );
    }

    /**
     * An unpublished template has an empty post_name. Bail rather than return a
     * slug that is nothing but a date.
     */
    public function test_empty_template_post_name_is_unchanged(): void
    {
        $this->stage_template('');

        $this->assertSame(
            'vector-control-board-meeting-5-2026-01-08-2026-02-12',
            $this->filter('vector-control-board-meeting-5-2026-01-08-2026-02-12')
        );
    }

    /**
     * A missing template post must not fatal.
     */
    public function test_missing_template_post_is_unchanged(): void
    {
        Functions\when('get_post')->justReturn(null);

        $this->assertSame(
            'vector-control-board-meeting-5-2026-01-08-2026-02-12',
            $this->filter('vector-control-board-meeting-5-2026-01-08-2026-02-12')
        );
    }

    /**
     * An EM_Event with no post_id must not fatal or reach get_post().
     */
    public function test_missing_post_id_is_unchanged(): void
    {
        $this->stage_template('vector-control-board-meeting-5');

        $EM_Event = new \stdClass();

        $this->assertSame(
            'vector-control-board-meeting-5-2026-01-08-2026-02-12',
            $this->filter('vector-control-board-meeting-5-2026-01-08-2026-02-12', $EM_Event)
        );
    }

    /**
     * A null post_name must not reach preg_quote() and raise a PHP 8.1+ deprecation.
     */
    public function test_null_template_post_name_is_unchanged(): void
    {
        $template = new \stdClass();
        $template->ID        = 23314;
        $template->post_name = null;

        Functions\when('get_post')->justReturn($template);

        $this->assertSame(
            'vector-control-board-meeting-5-2026-01-08-2026-02-12',
            $this->filter('vector-control-board-meeting-5-2026-01-08-2026-02-12')
        );
    }

    /**
     * Defensive: the filtered value should always be a non-empty string, but a
     * third-party filter could hand us something else first.
     */
    public function test_non_string_slug_is_unchanged(): void
    {
        $this->stage_template('vector-control-board-meeting-5');

        $this->assertNull($this->filter(null));
        $this->assertSame('', $this->filter(''));
    }

    /**
     * The hook is marked deprecated upstream. If anything re-fires it with a
     * shorter signature we must no-op, not throw ArgumentCountError.
     */
    public function test_short_argument_list_does_not_fatal(): void
    {
        $this->stage_template('vector-control-board-meeting-5');

        $this->assertSame(
            'vector-control-board-meeting-5-2026-01-08-2026-02-12',
            proudcity_em_strip_accumulated_recurrence_dates('vector-control-board-meeting-5-2026-01-08-2026-02-12')
        );
    }

    /**
     * \z rather than $ — a trailing newline must not match the date anchor.
     */
    public function test_trailing_newline_is_unchanged(): void
    {
        $this->stage_template('vector-control-board-meeting-5');

        $slug = "vector-control-board-meeting-5-2026-01-08-2026-02-12\n";

        $this->assertSame($slug, $this->filter($slug));
    }

    /**
     * Documented coverage gap: once Events Manager's own sanitize_recurrence_slug()
     * truncates a long template slug to fit the 200-character post_name column, the
     * incoming slug no longer starts with the full template slug and we fall through.
     * Asserted so the gap is a deliberate, visible behaviour rather than a surprise.
     */
    public function test_truncated_long_template_slug_falls_through(): void
    {
        $base = str_repeat('a', 195);

        $this->stage_template($base);

        // What EM hands us after truncating the base to make room for the dates.
        $slug = substr($base, 0, 178) . '-2026-01-08-2026-02-12';

        $this->assertSame($slug, $this->filter($slug));
    }
}
