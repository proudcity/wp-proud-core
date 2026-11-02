<?php
/**
 * Keep the Events Manager iCal feed to recent and upcoming events.
 *
 * em_ical_item() (events-manager/em-ical.php) handles every `?ical=1` request and
 * starts its arguments with 'scope' => 'all'. Only the single-archetype branch
 * unsets it, so a feed requested from the events page, a location or a category
 * ignores the site's own "iCal Scope" setting (dbem_ical_scope, 'future' by
 * default) and exports every event the site has ever published.
 *
 * The template pages through 50 events at a time, but each EM_Event it loads stays
 * in memory, so a site with years of history runs out of PHP memory. holyokema has
 * 3,580 published events back to 2015: the full feed grows memory by ~132MB and
 * every request died at the 128M limit inside the object-cache drop-in.
 *
 * em_calendar_template_args runs in templates/ical.php after the request's
 * arguments are merged, so it sees the final scope. We replace 'all' with the
 * last 12 months plus everything upcoming. Subscribed calendars drop events that
 * leave the feed, so a year of history keeps recent past events visible to
 * subscribers (holyokema: 496 events, ~10MB).
 *
 * Single-event downloads (the per-event "Add to calendar" link) pass an event ID
 * and are left alone. A scope other than 'all' is the site's own choice and is
 * also left alone.
 *
 * The scope alone isn't enough for busy sites. The memory is held by the object
 * cache's in-process copy of every post, meta row and location the feed touches,
 * not by the events themselves. santaanaca has 1,711 events in the 12-month
 * window and still ran out of memory. So on iCal requests we also clear the
 * runtime cache after each page of events (em_events_get fires once per page).
 * That only drops the in-memory copy; Redis is untouched. Memory stays flat
 * across santaanaca's full 2,926 events.
 *
 * Tracked in wp-proudcity#3008.
 *
 * @since 2026.11.02
 */

add_filter( 'em_calendar_template_args', 'proudcity_em_bound_ical_feed_scope' );
add_filter( 'em_events_get', 'proudcity_em_flush_runtime_cache_between_ical_pages' );

/**
 * Replace the iCal feed's 'all' scope with "12 months ago, through 10 years ahead".
 *
 * Events Manager's date-range scope needs both ends, so "everything upcoming" is
 * a far-future end date rather than an open range.
 *
 * @param array    $args Events Manager query arguments for the iCal template.
 * @param int|null $now  Timestamp to measure from. Only passed by tests.
 * @return array
 */
function proudcity_em_bound_ical_feed_scope( $args, $now = null ) {
	if ( ! is_array( $args ) || ! get_query_var( 'ical' ) ) {
		return $args;
	}

	if ( ! empty( $args['event'] ) ) {
		return $args;
	}

	if ( ! isset( $args['scope'] ) || 'all' !== $args['scope'] ) {
		return $args;
	}

	$now = null === $now ? time() : (int) $now;

	$args['scope'] = wp_date( 'Y-m-d', strtotime( '-12 months', $now ) )
		. ','
		. wp_date( 'Y-m-d', strtotime( '+10 years', $now ) );

	return $args;
}

/**
 * Clear the in-process object cache after each page of iCal events.
 *
 * The events on the current page are already built when em_events_get fires, so
 * clearing here only drops cached copies of rows from earlier pages and the ones
 * just loaded. Anything the template reads again comes back from Redis.
 *
 * @param array $events Events Manager events for the current page.
 * @return array The same events, unchanged.
 */
function proudcity_em_flush_runtime_cache_between_ical_pages( $events ) {
	if ( get_query_var( 'ical' ) && function_exists( 'wp_cache_flush_runtime' ) ) {
		wp_cache_flush_runtime();
	}

	return $events;
}
