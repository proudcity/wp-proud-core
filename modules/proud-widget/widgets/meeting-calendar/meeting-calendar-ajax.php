<?php

/**
 * AJAX feed for the Meeting Calendar widget.
 *
 * Public, nopriv, GET only, no nonce. The data returned (meeting title,
 * start time, permalink) is already public on the site itself -- a nonce
 * would tie the response to a logged-in session and defeat page caching for
 * no security benefit, since nothing here writes or reveals anything the
 * page doesn't already. Every input is strictly validated below instead.
 *
 * GET params:
 *   start      Y-m-d or Y-m-d\TH:i:s, required, inclusive
 *   end        Y-m-d or Y-m-d\TH:i:s, required, exclusive (matches
 *              FullCalendar's own `end` semantics -- the day after the
 *              visible range, not part of it), after start, at most 93 days
 *              after start
 *   categories comma-separated meeting-taxonomy term ids, optional, capped at 100
 *
 * Both date forms are real traffic, not a hypothetical: FullCalendar 3.10.2's
 * month/basicWeek/basicDay views send bare Y-m-d, but its agenda views
 * (agendaWeek/agendaDay) use min/max time -- calendar.msToMoment()'s
 * forceAllDay is false whenever usesMinMaxTime -- and send a full
 * Y-m-d\TH:i:s instead: midnight by default, or whatever minTime/maxTime is
 * configured. Anything else -- a trailing "Z" or numeric offset, or a value
 * createFromFormat() would otherwise silently normalize -- is rejected.
 *
 * Returns JSON: [ { title, start, url } ]
 */

/**
 * Parse a GET start/end date against the two accepted formats.
 *
 * createFromFormat() silently normalizes an out-of-range value (e.g.
 * 2026-13-45 or a 25:00:00 hour) instead of failing, so each candidate is
 * round-tripped back through the same format and compared to the raw input.
 *
 * @param string       $raw      Sanitized GET value.
 * @param DateTimeZone $timezone
 * @return DateTimeImmutable|false
 */
function proud_meeting_calendar_parse_range_date( $raw, DateTimeZone $timezone ) {
	foreach ( array( 'Y-m-d', 'Y-m-d\TH:i:s' ) as $format ) {
		$parsed = DateTimeImmutable::createFromFormat( '!' . $format, $raw, $timezone );
		if ( $parsed && $parsed->format( $format ) === $raw ) {
			return $parsed;
		}
	}
	return false;
}

function proud_meeting_calendar_feed() {
	if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
		wp_send_json_error( 'Invalid request method.', 405 );
		wp_die();
		return;
	}

	if ( ! post_type_exists( 'meeting' ) ) {
		wp_send_json( array() );
		wp_die();
		return;
	}

	$start_raw = isset( $_GET['start'] ) ? sanitize_text_field( wp_unslash( $_GET['start'] ) ) : '';
	$end_raw   = isset( $_GET['end'] ) ? sanitize_text_field( wp_unslash( $_GET['end'] ) ) : '';

	$start = proud_meeting_calendar_parse_range_date( $start_raw, wp_timezone() );
	$end   = proud_meeting_calendar_parse_range_date( $end_raw, wp_timezone() );

	if ( ! $start || ! $end
		|| $end <= $start
		|| $end > $start->modify( '+93 days' )
	) {
		wp_send_json_error( 'Invalid date range.', 400 );
		wp_die();
		return;
	}

	$categories = isset( $_GET['categories'] ) ? wp_parse_id_list( wp_unslash( $_GET['categories'] ) ) : array();
	$categories = array_slice( array_filter( $categories ), 0, 100 );

	$args = array(
		'post_type'      => 'meeting',
		'post_status'    => array( 'publish' ),
		'posts_per_page' => 500,
		'no_found_rows'  => true,
		'meta_key'       => 'datetime',
		'meta_type'      => 'DATETIME',
		'orderby'        => 'meta_value',
		'order'          => 'ASC',
		// end is exclusive -- matches FullCalendar's own range semantics, so
		// two bounded clauses rather than a BETWEEN ... 23:59:59, which would
		// include a whole extra day FullCalendar never asked for.
		'meta_query'     => array(
			'relation' => 'AND',
			array(
				'key'     => 'datetime',
				'type'    => 'DATETIME',
				'compare' => '>=',
				'value'   => $start->format( 'Y-m-d H:i:s' ),
			),
			array(
				'key'     => 'datetime',
				'type'    => 'DATETIME',
				'compare' => '<',
				'value'   => $end->format( 'Y-m-d H:i:s' ),
			),
		),
	);

	if ( ! empty( $categories ) ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'meeting-taxonomy',
				'field'    => 'term_id',
				'terms'    => $categories,
			),
		);
	}

	$query = new WP_Query( $args );

	$items = array();
	foreach ( $query->posts as $post ) {
		// UTC, not wp_timezone(): the 'datetime' meta value is a site-local
		// wall-clock time with no offset, and createFromFormat() silently
		// shifts a wall time that falls in a DST spring-forward gap forward
		// by an hour when given a timezone that observes DST. UTC never
		// does, so the value comes back exactly as stored.
		$datetime_meta = get_post_meta( $post->ID, 'datetime', true );
		$start_time    = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', (string) $datetime_meta, new DateTimeZone( 'UTC' ) );

		// Local San Rafael data had 1 of 791 published meetings with an
		// empty datetime value. Skip it rather than guess a start time.
		if ( ! $start_time ) {
			continue;
		}

		// Password-protected meetings are included (title/time/url only) --
		// WordPress still gates the content itself behind the password when
		// the link is followed.
		$items[] = array(
			'title' => wp_strip_all_tags( html_entity_decode( get_the_title( $post ), ENT_QUOTES ) ),
			'start' => $start_time->format( 'Y-m-d\TH:i:s' ),
			'url'   => esc_url_raw( get_permalink( $post ) ),
		);
	}

	wp_send_json( $items );
	wp_die();
}
add_action( 'wp_ajax_proud_meeting_calendar', 'proud_meeting_calendar_feed' );
add_action( 'wp_ajax_nopriv_proud_meeting_calendar', 'proud_meeting_calendar_feed' );
