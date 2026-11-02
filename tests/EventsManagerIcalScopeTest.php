<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for proudcity_em_bound_ical_feed_scope() in
 * modules/events-manager-ical-scope.php.
 *
 * Events Manager's ?ical=1 handler hard-codes 'scope' => 'all', so feeds export
 * every event a site has ever published and run out of memory. The filter
 * swaps 'all' for the last 12 months plus everything upcoming.
 *
 * See wp-proudcity#3008.
 */
class EventsManagerIcalScopeTest extends TestCase
{
    /** 2026-11-02 12:00:00 UTC */
    private const NOW = 1793620800;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('wp_date')->alias(function ($format, $timestamp) {
            return gmdate($format, $timestamp);
        });
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function ical_request(bool $is_ical): void
    {
        Functions\when('get_query_var')->justReturn($is_ical ? '1' : '');
    }

    public function test_feed_with_all_scope_is_bounded_to_last_year_and_upcoming(): void
    {
        $this->ical_request(true);

        $args = proudcity_em_bound_ical_feed_scope(
            ['scope' => 'all', 'status' => 1, 'limit' => 50, 'page' => '1'],
            self::NOW
        );

        $this->assertSame('2025-11-02,2036-11-02', $args['scope']);
        // Everything else passes through untouched.
        $this->assertSame(1, $args['status']);
        $this->assertSame(50, $args['limit']);
    }

    public function test_single_event_download_keeps_all_scope(): void
    {
        $this->ical_request(true);

        $args = proudcity_em_bound_ical_feed_scope(
            ['scope' => 'all', 'status' => 1, 'event' => 4242],
            self::NOW
        );

        $this->assertSame('all', $args['scope']);
    }

    public function test_site_chosen_scope_is_left_alone(): void
    {
        $this->ical_request(true);

        $args = proudcity_em_bound_ical_feed_scope(['scope' => 'future'], self::NOW);

        $this->assertSame('future', $args['scope']);
    }

    public function test_non_ical_requests_are_left_alone(): void
    {
        // em_calendar_template_args is also used outside the feed.
        $this->ical_request(false);

        $args = proudcity_em_bound_ical_feed_scope(['scope' => 'all'], self::NOW);

        $this->assertSame('all', $args['scope']);
    }

    public function test_missing_scope_is_left_alone(): void
    {
        $this->ical_request(true);

        $args = proudcity_em_bound_ical_feed_scope(['status' => 1], self::NOW);

        $this->assertArrayNotHasKey('scope', $args);
    }
    public function test_runtime_cache_is_flushed_after_each_ical_page(): void
    {
        $this->ical_request(true);
        Functions\expect('wp_cache_flush_runtime')->once();

        $events = ['a', 'b'];
        $this->assertSame($events, proudcity_em_flush_runtime_cache_between_ical_pages($events));
    }

    public function test_runtime_cache_is_left_alone_outside_ical(): void
    {
        $this->ical_request(false);
        Functions\expect('wp_cache_flush_runtime')->never();

        $events = ['a'];
        $this->assertSame($events, proudcity_em_flush_runtime_cache_between_ical_pages($events));
    }
}
