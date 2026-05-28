<?php

/**
 * AJAX handlers for the Embed Document widget picker.
 *
 * Both endpoints are admin-only (no nopriv variants). Both share the same
 * nonce action so a single wp_create_nonce() call in the widget form covers
 * both requests.
 */

/**
 * Search for Documents by title.
 *
 * GET params: q (search query), _wpnonce
 *
 * Returns JSON: { items: [ { id, title, filename, filetype, icon, edit_url } ] }
 */
function proud_document_search_callback() {
	check_ajax_referer( 'proud_document_search', '_wpnonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( 'Unauthorized', 403 );
		wp_die();
		return;
	}

	$query_string = isset( $_GET['q'] ) ? sanitize_text_field( $_GET['q'] ) : '';

	$query = new WP_Query( array(
		'post_type'      => 'document',
		'post_status'    => 'publish',
		'posts_per_page' => 10,
		's'              => $query_string,
	) );

	$items = array();
	foreach ( $query->posts as $post ) {
		$filename = get_post_meta( $post->ID, 'document_filename', true );
		$filetype = \Proud\Document\get_document_type( $post->ID );
		$icon     = \Proud\Document\get_document_icon( $post->ID );
		$items[]  = array(
			'id'       => $post->ID,
			'title'    => esc_html( $post->post_title ),
			'filename' => esc_html( $filename ),
			'filetype' => esc_attr( $filetype ),
			'icon'     => esc_attr( $icon ),
			'edit_url' => esc_url( get_admin_url( null, 'post.php?post=' . $post->ID . '&action=edit' ) ),
		);
	}

	wp_send_json( array( 'items' => $items ) );
	wp_die();
}
add_action( 'wp_ajax_proud_document_search', 'proud_document_search_callback' );

/**
 * Render a preview of a Document for the widget admin form.
 *
 * GET params: post_id, _wpnonce
 *
 * Returns JSON: { html: '<rendered template>' }
 */
function proud_document_preview_callback() {
	check_ajax_referer( 'proud_document_search', '_wpnonce' );

	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( 'Unauthorized', 403 );
		wp_die();
		return;
	}

	$id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;

	if ( $id <= 0 ) {
		wp_send_json_error( 'Invalid document ID.' );
		wp_die();
		return;
	}

	$template = plugin_dir_path( __FILE__ ) . 'templates/content-embed-document.php';

	ob_start();
	include $template;
	$html = ob_get_clean();

	wp_send_json( array( 'html' => $html ) );
	wp_die();
}
add_action( 'wp_ajax_proud_document_preview', 'proud_document_preview_callback' );
