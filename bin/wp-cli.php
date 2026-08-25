<?php
/**
 * WP-CLI commands for ProudCity Core.
 *
 * Loaded only in CLI context — the file returns early when WP_CLI is not
 * defined so frontend / admin requests never pay the parse cost.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * `wp proud ...` commands.
 */
class ProudCore_CLI {

	/**
	 * Notes written onto every redirect rule this command creates, and the marker
	 * used to tell our own rules apart from ones an editor made by hand.
	 */
	private const REDIRECT_NOTE = 'Events Manager recurring slug cleanup — wp-proudcity#2893';

	/**
	 * Repair Events Manager recurring event slugs that accumulated occurrence dates.
	 *
	 * Events Manager mutates `$post_fields['post_name']` inside its occurrence loop
	 * without resetting it between iterations, so occurrence twelve carries all twelve
	 * dates in its URL. `modules/events-manager-recurrence-slug.php` stops new damage;
	 * this command repairs slugs that were already written. See wp-proudcity#2893.
	 *
	 * Rewrites `wp_posts.post_name` and `wp_em_events.event_slug` in place. Post IDs,
	 * meta, comments and bookings are untouched, and no event is created or deleted.
	 *
	 * Every changed URL gets a 301 in the Safe Redirect Manager `redirect_rule` CPT so
	 * editors can see and adjust it at /wp-admin/edit.php?post_type=redirect_rule, plus
	 * a `_wp_old_slug` entry as a fallback for sites where that plugin is inactive.
	 *
	 * Only slugs matching the known accumulation shape — the recurring template's slug
	 * followed by two or more `-YYYY-MM-DD` groups — are touched. Hand-edited slugs and
	 * sites using a non-default `em_event_save_events_format` are left alone.
	 *
	 * Idempotent: a second run reports nothing to fix and creates no duplicate rules.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Report what would change without writing anything.
	 *
	 * [--no-redirects]
	 * : Rewrite slugs without creating redirect rules.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt. Required when running non-interactively,
	 * such as through kube-cmd-all.sh.
	 *
	 * ## EXAMPLES
	 *
	 *     # Preview the repair
	 *     wp proud fix-em-slugs --dry-run
	 *
	 *     # Run it
	 *     wp proud fix-em-slugs
	 *
	 *     # Run it unattended across the fleet
	 *     wp proud fix-em-slugs --yes
	 *
	 * @subcommand fix-em-slugs
	 * @when after_wp_load
	 *
	 * @param array<int,string> $args       Positional arguments (unused).
	 * @param array<string,bool> $assoc_args Flag arguments.
	 */
	public function fix_em_slugs( array $args, array $assoc_args ): void {

		global $wpdb;

		$dry_run      = isset( $assoc_args['dry-run'] );
		$do_redirects = ! isset( $assoc_args['no-redirects'] );

		if ( $do_redirects && ! function_exists( 'srm_create_redirect' ) ) {
			WP_CLI::warning( 'Safe Redirect Manager is not active — falling back to _wp_old_slug only.' );
			$do_redirects = false;
		}

		$rows = $this->get_recurrence_occurrences();

		if ( ! $rows ) {
			WP_CLI::success( 'No recurring event occurrences found — nothing to do.' );
			return;
		}

		$planned = [];

		foreach ( $rows as $row ) {
			$outcome = $this->plan_row( $row );

			if ( 'ok' === $outcome['status'] ) {
				$planned[] = $outcome;
			} elseif ( 'skip' === $outcome['status'] ) {
				WP_CLI::log( sprintf( 'SKIP   %-7d %s', $row->post_id, $outcome['reason'] ) );
			}
		}

		$skipped = count( $rows ) - count( $planned ) - $this->already_correct;

		if ( ! $planned ) {
			WP_CLI::success( sprintf( 'Nothing to fix. %d occurrence(s) already correct, %d skipped.', $this->already_correct, $skipped ) );
			return;
		}

		foreach ( $planned as $item ) {
			WP_CLI::log( sprintf(
				'%s %-7d %s  ->  %s',
				$dry_run ? 'WOULD ' : 'FIX   ',
				$item['row']->post_id,
				$item['row']->event_slug,
				$item['correct']
			) );
		}

		if ( $dry_run ) {
			WP_CLI::success( sprintf( 'Would fix %d occurrence(s), %d skipped. Nothing was written.', count( $planned ), $skipped ) );
			return;
		}

		WP_CLI::confirm( sprintf( 'Rewrite %d published event URL(s)? This is not reversible without a database restore.', count( $planned ) ), $assoc_args );

		$fixed      = 0;
		$redirected = 0;

		foreach ( $planned as $item ) {
			$row     = $item['row'];
			$correct = $item['correct'];

			// Capture the permalink before the rename so the redirect "from" path does
			// not have to assume the event permalink base.
			$old_path = wp_parse_url( (string) get_permalink( $row->post_id ), PHP_URL_PATH );

			add_post_meta( $row->post_id, '_wp_old_slug', $row->event_slug );

			$wpdb->update( $wpdb->posts, [ 'post_name' => $correct ], [ 'ID' => $row->post_id ], [ '%s' ], [ '%d' ] );
			$wpdb->update( "{$wpdb->prefix}em_events", [ 'event_slug' => $correct ], [ 'event_id' => $row->event_id ], [ '%s' ], [ '%d' ] );

			clean_post_cache( (int) $row->post_id );
			$fixed++;

			if ( ! $do_redirects ) {
				continue;
			}

			if ( $this->create_redirect( (int) $row->post_id, (string) $old_path ) ) {
				$redirected++;
			}
		}

		if ( $redirected ) {
			srm_flush_cache();
		}

		WP_CLI::success( sprintf( 'Fixed %d occurrence(s), %d redirect(s) created, %d skipped.', $fixed, $redirected, $skipped ) );
	}

	/**
	 * Count of occurrences that already had the correct slug on this run.
	 *
	 * @var int
	 */
	private int $already_correct = 0;

	/**
	 * Every recurring occurrence joined to the recurring template it belongs to.
	 *
	 * @return array<int,object>
	 */
	private function get_recurrence_occurrences(): array {

		global $wpdb;

		return (array) $wpdb->get_results(
			"SELECT e.event_id, e.post_id, e.event_slug, e.event_start_date, p.post_name AS base
			 FROM {$wpdb->prefix}em_events e
			 JOIN {$wpdb->prefix}em_event_recurrences r ON r.recurrence_set_id = e.recurrence_set_id
			 JOIN {$wpdb->prefix}em_events t ON t.event_id = r.event_id
			 JOIN {$wpdb->posts} p ON p.ID = t.post_id
			 WHERE e.recurrence_set_id IS NOT NULL
			   AND e.post_id > 0
			   AND p.post_name <> ''
			 ORDER BY e.event_id"
		);
	}

	/**
	 * Decide what should happen to a single occurrence.
	 *
	 * @param object $row Occurrence row joined to its template.
	 * @return array{status:string,reason?:string,correct?:string,row?:object}
	 */
	private function plan_row( object $row ): array {

		global $wpdb;

		$correct = $row->base . '-' . $row->event_start_date;

		if ( $row->event_slug === $correct ) {
			$this->already_correct++;
			return [ 'status' => 'correct' ];
		}

		// Only the known accumulation shape: base followed by two or more dates.
		if ( ! preg_match( '/^' . preg_quote( (string) $row->base, '/' ) . '(?:-\d{4}-\d{2}-\d{2}){2,}\z/', (string) $row->event_slug ) ) {
			return [ 'status' => 'skip', 'reason' => 'unrecognised slug: ' . $row->event_slug ];
		}

		// Never hand two posts the same slug.
		$taken = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND ID <> %d LIMIT 1",
			$correct,
			$row->post_id
		) );

		if ( $taken ) {
			return [ 'status' => 'skip', 'reason' => sprintf( 'target slug already held by post %d: %s', $taken, $correct ) ];
		}

		return [ 'status' => 'ok', 'correct' => $correct, 'row' => $row ];
	}

	/**
	 * Create a 301 redirect_rule from the old path to the post's current permalink.
	 *
	 * An existing rule for the same path that this command did not create is left
	 * alone — Safe Redirect Manager updates in place when `_redirect_rule_from`
	 * matches, and clobbering an editor's own redirect would be worse than a 404.
	 *
	 * @param int    $post_id  Occurrence post, already renamed.
	 * @param string $old_path Request path the occurrence used to answer on.
	 * @return bool Whether a redirect was created or refreshed.
	 */
	private function create_redirect( int $post_id, string $old_path ): bool {

		global $wpdb;

		$new_path = wp_parse_url( (string) get_permalink( $post_id ), PHP_URL_PATH );

		if ( ! $old_path || ! $new_path || $old_path === $new_path ) {
			WP_CLI::warning( sprintf( 'Post %d renamed but the redirect path could not be derived — add it by hand.', $post_id ) );
			return false;
		}

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_redirect_rule_from' AND meta_value = %s LIMIT 1",
			$old_path
		) );

		if ( $existing && get_post_meta( $existing, '_redirect_rule_notes', true ) !== self::REDIRECT_NOTE ) {
			WP_CLI::warning( sprintf( 'Redirect for %s already exists (rule %d) and was not created by this command — left alone.', $old_path, $existing ) );
			return false;
		}

		if ( ! $existing && srm_max_redirects_reached() ) {
			WP_CLI::warning( sprintf( 'Safe Redirect Manager is at its %d redirect limit — no redirect created for %s.', srm_get_max_redirects(), $old_path ) );
			return false;
		}

		$result = srm_create_redirect(
			$old_path,
			$new_path,
			301,
			false,
			'publish',
			0,
			self::REDIRECT_NOTE,
			(int) get_post_field( 'post_author', $post_id )
		);

		if ( is_wp_error( $result ) ) {
			WP_CLI::warning( sprintf( 'Redirect failed for %s: %s', $old_path, $result->get_error_message() ) );
			return false;
		}

		return true;
	}
}

WP_CLI::add_command( 'proud', ProudCore_CLI::class );
