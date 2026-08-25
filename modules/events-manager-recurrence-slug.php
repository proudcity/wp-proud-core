<?php
/**
 * Stop Events Manager accumulating occurrence dates into recurring event slugs.
 *
 * Recurrence_Set::save_recurrences() loads the recurring template's post row once,
 * before the occurrence loop, then writes each occurrence's date-suffixed slug back
 * into that same $post_fields array from inside the loop. It is never reset per
 * iteration, so iteration N reads what iteration N-1 wrote and appends another date.
 * Occurrence twelve ends up carrying all twelve dates in its URL. The plugin's own
 * sanitize_recurrence_slug() does not catch this — it only truncates past 200
 * characters, which caps the runaway rather than preventing it.
 *
 * em_event_save_events_slug is the last filter on both the create path
 * (classes/recurrences/recurrence-set.php:1017) and the update path (:1341), so one
 * hook covers both. We rebuild the slug from the recurring template's own post_name
 * plus the trailing date rather than trusting the value passed in.
 *
 * Upstream: https://wordpress.org/support/topic/recurring-event-slugs-accumulate-every-previous-occurrence-date/
 * Tracked in wp-proudcity#2893.
 *
 * @since 2026.08.25
 */

add_filter( 'em_event_save_events_slug', 'proudcity_em_strip_accumulated_recurrence_dates', 10, 5 );

/**
 * Rebuild a recurring occurrence slug as "<template slug>-<occurrence date>".
 *
 * Deliberately conservative: anything that does not match the known accumulation
 * shape is returned untouched, so a site that has filtered em_event_save_events_format
 * to something other than Y-m-d keeps upstream behaviour instead of getting a mangled
 * slug. An already-correct slug matches with zero repeated groups and comes back
 * unchanged, which also makes this a no-op once Events Manager fixes the bug.
 *
 * We do not read $EM_Event->post_name — that property is only populated during the
 * publish flow (events-manager/classes/em-event.php:1898) and is empty here. The
 * template post is the reliable source for the base slug.
 *
 * Known coverage gap: once a template slug is long enough that EM's own
 * sanitize_recurrence_slug() (recurrence-set.php:1678) truncates it to fit the
 * 200-character post_name column, the incoming slug no longer starts with the full
 * template slug, the match fails and the accumulated slug is left alone. Those slugs
 * are still unique, since each ends in its own date. Fail-open by design.
 *
 * The parameters after $slug carry defaults because this hook is marked deprecated
 * upstream — a compatibility shim re-firing it with a shorter signature would
 * otherwise throw ArgumentCountError and 500 the save request.
 *
 * @param string $slug          Slug built by Events Manager, potentially carrying every prior date.
 * @param array  $post_fields   Post row being written. Unused — this is the polluted source.
 * @param int    $timestamp     Occurrence start timestamp. Unused — the date is already in $slug.
 * @param array  $matching_days All occurrence days. Unused.
 * @param object $EM_Event      The recurring event template.
 * @return string
 */
function proudcity_em_strip_accumulated_recurrence_dates( $slug, $post_fields = [], $timestamp = 0, $matching_days = [], $EM_Event = null ) {

	if ( ! is_string( $slug ) || '' === $slug || empty( $EM_Event->post_id ) ) {
		return $slug;
	}

	$template = get_post( $EM_Event->post_id );

	if ( ! $template || ! is_string( $template->post_name ) || '' === $template->post_name ) {
		return $slug;
	}

	// The base slug, followed by one or more -YYYY-MM-DD groups. Keep only the last.
	// \z rather than $ so a trailing newline cannot slip past the anchor.
	$pattern = '/^' . preg_quote( $template->post_name, '/' ) . '(?:-\d{4}-\d{2}-\d{2})*-(\d{4}-\d{2}-\d{2})\z/';

	if ( ! preg_match( $pattern, $slug, $matches ) ) {
		return $slug;
	}

	return $template->post_name . '-' . $matches[1];
}
