<?php
/**
 * Keep Media Gallery widget images that aren't attached to the current page.
 *
 * The core Media Gallery widget (WP_Widget_Media_Gallery) passes its selected
 * images to gallery_shortcode() as an array of IDs, and gallery_shortcode()
 * copies that array into 'include'. Core accepts an array there.
 *
 * WP Media Folder 6.3.0 replaces the gallery output through its post_gallery
 * filter. Its new sanitizeGalleryAttributes() (class/class-display-gallery.php)
 * does `explode( ',', (string) $attrs['include'] )`. Casting an array gives
 * "Array", so 'include' ends up empty and WPMF falls back to the images attached
 * to the current post. Images uploaded to any other post disappear from the
 * gallery (Palmview's City Council page showed 2 of its 5 photos).
 *
 * WPMF's gallery() runs shortcode_atts( ..., 'gallery' ) right before that
 * sanitizing step, so shortcode_atts_gallery lets us hand it the comma-separated
 * string it expects. Core's own shortcode_atts() call fires the same filter, and
 * core accepts the string too. Once WPMF handles arrays this filter is a no-op.
 *
 * Fixed here rather than in our wp-media-folder fork so the next WPMF update
 * doesn't undo it. Tracked in wp-proudcity#3033.
 *
 * @since 2026.11.20
 */

add_filter( 'shortcode_atts_gallery', 'proudcity_gallery_ids_to_string' );

/**
 * Join array 'include' / 'exclude' gallery attributes into comma-separated IDs.
 *
 * @param array $out Gallery attributes after shortcode_atts().
 * @return array
 */
function proudcity_gallery_ids_to_string( $out ) {
	if ( ! is_array( $out ) ) {
		return $out;
	}

	foreach ( array( 'include', 'exclude' ) as $key ) {
		if ( isset( $out[ $key ] ) && is_array( $out[ $key ] ) ) {
			$out[ $key ] = implode( ',', array_filter( array_map( 'absint', $out[ $key ] ) ) );
		}
	}

	return $out;
}
