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
	 * Only slugs matching the known accumulation shape — a base followed by two or more
	 * `-YYYY-MM-DD` groups — are touched. Hand-edited slugs and sites using a non-default
	 * `em_event_save_events_format` are left alone.
	 *
	 * Occurrences detached from their recurrence set are covered too. Editing a single
	 * occurrence in wp-admin sets `recurrence_set_id` to NULL and flips `event_type` to
	 * `single`, but leaves the accumulated slug in place. For those the base is recovered
	 * from the slug itself and only trusted when a recurring template still carries it or
	 * more than one event post was built from it.
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

		$rows = $this->get_candidates();

		if ( ! $rows ) {
			WP_CLI::success( 'No event slugs carry accumulated dates — nothing to do.' );
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

			// Drafts and pending posts have no public URL — get_permalink() returns a
			// ?p= query string for them, so there is nothing to redirect from.
			if ( 'publish' !== get_post_status( $row->post_id ) ) {
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
	 * Memoised count of event posts sharing a given derived base slug.
	 *
	 * @var array<string,int>
	 */
	private array $base_usage = [];

	/**
	 * Every event post whose slug carries two or more trailing dates.
	 *
	 * Deliberately does NOT require `recurrence_set_id` to be set. Editing a single
	 * occurrence in wp-admin detaches it from its recurrence set — `recurrence_set_id`
	 * goes NULL and `event_type` flips to `single` — but the accumulated slug stays.
	 * Joining through the recurrence set made those rows invisible to both this command
	 * and the audit query, which is how 17 of them survived the first run on
	 * williamscountynd. See wp-proudcity#2893.
	 *
	 * `base` is the recurring template's own slug where the row is still attached, and
	 * NULL where it is not — `plan_row()` derives the base from the slug in that case.
	 *
	 * @return array<int,object>
	 */
	private function get_candidates(): array {

		global $wpdb;

		return (array) $wpdb->get_results(
			"SELECT e.event_id, e.post_id, e.event_slug, e.event_start_date, tpl.post_name AS base
			 FROM {$wpdb->posts} po
			 JOIN {$wpdb->prefix}em_events e ON e.post_id = po.ID
			 LEFT JOIN {$wpdb->prefix}em_event_recurrences r ON r.recurrence_set_id = e.recurrence_set_id
			 LEFT JOIN {$wpdb->prefix}em_events t ON t.event_id = r.event_id
			 LEFT JOIN {$wpdb->posts} tpl ON tpl.ID = t.post_id AND tpl.post_name <> ''
			 WHERE po.post_type = 'event'
			   AND po.post_name REGEXP '(-[0-9]{4}-[0-9]{2}-[0-9]{2}){2,}$'
			 ORDER BY e.event_id"
		);
	}

	/**
	 * Decide what should happen to a single occurrence.
	 *
	 * @param object $row Occurrence row, with `base` set only when still attached.
	 * @return array{status:string,reason?:string,correct?:string,row?:object}
	 */
	private function plan_row( object $row ): array {

		global $wpdb;

		$slug = (string) $row->event_slug;

		// Strip every trailing date group to recover the base the slug was built from.
		$derived = preg_replace( '/(?:-\d{4}-\d{2}-\d{2}){2,}\z/', '', $slug );

		if ( null === $derived || $derived === $slug || '' === $derived ) {
			return [ 'status' => 'skip', 'reason' => 'unrecognised slug: ' . $slug ];
		}

		if ( ! empty( $row->base ) ) {
			// Still attached to its recurrence set — trust the template's own slug.
			if ( $derived !== $row->base ) {
				return [ 'status' => 'skip', 'reason' => sprintf( 'slug base %s does not match template %s', $derived, $row->base ) ];
			}
			$base = (string) $row->base;
		} else {
			// Detached. Corroborate the derived base before trusting it, so a single
			// event legitimately titled with two dates never gets rewritten.
			if ( ! $this->base_is_generated( $derived ) ) {
				return [
					'status' => 'skip',
					'reason' => sprintf( 'detached, and base %s matches no recurring template and no sibling occurrence', $derived ),
				];
			}
			$base = $derived;
		}

		$correct = $base . '-' . $row->event_start_date;

		if ( $slug === $correct ) {
			$this->already_correct++;
			return [ 'status' => 'correct' ];
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
	 * Whether a base slug looks machine-generated rather than hand-titled.
	 *
	 * True when a recurring template still carries that slug, or when more than one
	 * event post was built from it. Either is strong evidence Events Manager produced
	 * the series. A one-off event someone happened to title with two ISO dates matches
	 * neither, so it is left alone.
	 *
	 * @param string $base Base slug recovered by stripping trailing dates.
	 */
	private function base_is_generated( string $base ): bool {

		global $wpdb;

		if ( isset( $this->base_usage[ $base ] ) ) {
			return $this->base_usage[ $base ] > 0;
		}

		$template = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'event-recurring' AND post_name = %s LIMIT 1",
			$base
		) );

		if ( $template ) {
			$this->base_usage[ $base ] = 1;
			return true;
		}

		// No template — it may have been deleted. Fall back to sibling occurrences:
		// more than one event post built from the same base means a generated series.
		$siblings = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = 'event' AND ( post_name = %s OR post_name LIKE %s )",
			$base,
			$wpdb->esc_like( $base . '-' ) . '%'
		) );

		$this->base_usage[ $base ] = $siblings > 1 ? 1 : 0;

		return $siblings > 1;
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
