<?php

/**
 * Duplicate Post support for Bold Timeline.
 *
 * Lets editors copy a timeline so a styled "template" timeline can seed new
 * ones. Three things are needed, none of which Duplicate Post or Bold Timeline
 * provide on their own:
 *
 * 1. Duplicate Post only copies post types in `duplicate_post_enabled_post_types`
 *    (default post and page). We add `bold-timeline` through the filter instead
 *    of writing the per-site option.
 * 2. The Bold Timelines list is a plain WP_List_Table with no row-action filter,
 *    so Duplicate Post's stock Clone / New Draft links never appear. PHP builds
 *    the links with duplicate_post_get_clone_post_link() (so the nonce and the
 *    copy_posts check are Duplicate Post's own) and a small admin script adds
 *    them to each row.
 * 3. New Draft always redirects to post.php, which dies with a 403 for a post
 *    type without show_ui. We send that one redirect to the builder instead.
 *
 * Rewrite & Republish is deliberately not offered; it needs the post.php editor.
 *
 * Tracked in wp-proudcity#3014.
 *
 * @since 2026.11.10
 */

add_filter( 'duplicate_post_enabled_post_types', 'proudcity_duplicate_post_enable_bold_timeline' );
add_action( 'admin_enqueue_scripts', 'proudcity_bold_timeline_clone_enqueue' );
add_action( 'admin_notices', 'proudcity_bold_timeline_clone_notice' );
add_filter( 'wp_redirect', 'proudcity_bold_timeline_new_draft_redirect', 10, 2 );

/**
 * Adds the Bold Timeline post type to the types Duplicate Post may copy.
 *
 * @param mixed $post_types Enabled post types.
 * @return array
 */
function proudcity_duplicate_post_enable_bold_timeline( $post_types ) {
    $post_types = is_array( $post_types ) ? $post_types : array_filter( (array) $post_types );

    if ( ! in_array( 'bold-timeline', $post_types, true ) ) {
        $post_types[] = 'bold-timeline';
    }

    return $post_types;
}

/**
 * Builds the post ID => links map handed to the list page script.
 *
 * Honors Duplicate Post's "show links in" row setting and its per-link
 * settings. duplicate_post_get_clone_post_link() returns '' when the current
 * user can't copy, so IDs with no usable link are left out.
 *
 * @param array $statuses       Post status keyed by timeline ID.
 * @param mixed $show_link_in   duplicate_post_show_link_in option value.
 * @param mixed $show_link      duplicate_post_show_link option value.
 * @return array<int, array{clone: string, newDraft: string, isDraft: bool}>
 */
function proudcity_bold_timeline_clone_link_map( array $statuses, $show_link_in, $show_link ) {
    $show_link_in = is_array( $show_link_in ) ? $show_link_in : [];
    $show_link    = is_array( $show_link ) ? $show_link : [];

    if ( empty( $show_link_in['row'] ) ) {
        return [];
    }

    $want_clone     = ! empty( $show_link['clone'] );
    $want_new_draft = ! empty( $show_link['new_draft'] );

    $map = [];

    foreach ( $statuses as $id => $status ) {
        $id = absint( $id );
        if ( ! $id ) {
            continue;
        }

        $clone     = $want_clone ? proudcity_bold_timeline_raw_link( $id, false ) : '';
        $new_draft = $want_new_draft ? proudcity_bold_timeline_raw_link( $id, true ) : '';

        if ( '' === $clone && '' === $new_draft ) {
            continue;
        }

        $map[ $id ] = [
            'clone'    => $clone,
            'newDraft' => $new_draft,
            'isDraft'  => 'draft' === $status,
        ];
    }

    return $map;
}

/**
 * Fetches one Duplicate Post link in the 'raw' context.
 *
 * wp_nonce_url() runs its result through esc_html(), so even the raw context
 * carries `&amp;`. The script sets the value as an href attribute, where that
 * would be read literally, so decode it and validate with esc_url_raw().
 *
 * @param int  $id    Timeline ID.
 * @param bool $draft True for New Draft, false for Clone.
 * @return string
 */
function proudcity_bold_timeline_raw_link( $id, $draft ) {
    $url = duplicate_post_get_clone_post_link( $id, 'raw', $draft );

    if ( ! is_string( $url ) || '' === $url ) {
        return '';
    }

    return esc_url_raw( str_replace( '&amp;', '&', $url ) );
}

/**
 * Loads the row link script on the Bold Timelines list page only.
 *
 * @param string $hook_suffix Current admin page.
 */
function proudcity_bold_timeline_clone_enqueue( $hook_suffix ) {
    if ( 'toplevel_page_bold-timeline' !== $hook_suffix || ! function_exists( 'duplicate_post_get_clone_post_link' ) ) {
        return;
    }

    $timelines = get_posts( [
        'post_type'              => 'bold-timeline',
        'post_status'            => 'any',
        'posts_per_page'         => 500,
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ] );

    $statuses = [];
    foreach ( $timelines as $timeline ) {
        $statuses[ (int) $timeline->ID ] = $timeline->post_status;
    }

    $links = proudcity_bold_timeline_clone_link_map(
        $statuses,
        get_option( 'duplicate_post_show_link_in' ),
        get_option( 'duplicate_post_show_link' )
    );

    if ( empty( $links ) ) {
        return;
    }

    $file = __DIR__ . '/assets/js/proud-bold-timeline-clone.js';

    wp_enqueue_script(
        'proud-bold-timeline-clone',
        plugins_url( 'assets/js/proud-bold-timeline-clone.js', __FILE__ ),
        [],
        file_exists( $file ) ? (string) filemtime( $file ) : false,
        true
    );

    wp_localize_script( 'proud-bold-timeline-clone', 'proudBoldTimelineClone', [
        'links'  => $links,
        'labels' => [
            'clone'           => __( 'Clone', 'wp-proud-core' ),
            'newDraft'        => __( 'New Draft', 'wp-proud-core' ),
            'draft'           => __( 'Draft', 'wp-proud-core' ),
            /* translators: %s: timeline title. */
            'cloneAria'       => __( 'Clone “%s”', 'wp-proud-core' ),
            /* translators: %s: timeline title. */
            'newDraftAria'    => __( 'New draft of “%s”', 'wp-proud-core' ),
        ],
    ] );
}

/**
 * Confirms a copy on the Bold Timelines list.
 *
 * Duplicate Post's own notice only fires in the classic editor, and Clone
 * redirects back here with `cloned=N`.
 */
function proudcity_bold_timeline_clone_notice() {
    if ( ! function_exists( 'get_current_screen' ) ) {
        return;
    }

    $screen = get_current_screen();
    if ( ! $screen || 'toplevel_page_bold-timeline' !== $screen->id ) {
        return;
    }

    if ( ! isset( $_GET['cloned'] ) || ! absint( wp_unslash( $_GET['cloned'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
        return;
    }

    printf(
        '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
        esc_html__( 'Timeline copied.', 'wp-proud-core' )
    );
}

/**
 * Sends Duplicate Post's New Draft redirect for a timeline to the builder.
 *
 * Scoped to the duplicate_post_new_draft action and a post.php?action=edit
 * target whose post is a bold-timeline. Every other redirect passes through.
 * The destination is built from admin_url() and an integer ID only.
 *
 * @param string $location Redirect target.
 * @param int    $status   HTTP status.
 * @return string
 */
function proudcity_bold_timeline_new_draft_redirect( $location, $status = 302 ) {
    if ( ! is_admin() || ! isset( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only narrows a redirect; Duplicate Post verified the nonce.
        return $location;
    }

    if ( 'duplicate_post_new_draft' !== sanitize_key( wp_unslash( $_REQUEST['action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        return $location;
    }

    $path = wp_parse_url( (string) $location, PHP_URL_PATH );
    if ( ! is_string( $path ) || 'post.php' !== basename( $path ) ) {
        return $location;
    }

    $query = [];
    parse_str( (string) wp_parse_url( (string) $location, PHP_URL_QUERY ), $query );

    if ( ! isset( $query['action'], $query['post'] ) || 'edit' !== $query['action'] || ! is_scalar( $query['post'] ) ) {
        return $location;
    }

    $post_id = absint( $query['post'] );
    if ( ! $post_id || 'bold-timeline' !== get_post_type( $post_id ) ) {
        return $location;
    }

    return admin_url( 'admin.php?page=bold-timeline-edit&post=' . $post_id );
}
