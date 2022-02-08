<?php
/**
 * To hold general overrides that span plugins
 */

/**
 * Removes the visual display of custom_fields from the WP admin panels
 *
 * @since 2022.02.08
 * @author Curtis
 *
 * @param   string    $post_type              The post type being passed
 * @param   string    $context                The context that the field is being displayed in (side, normal, advanced)
 * @param   string    $post                   Post object or string that is being operated on
 * @uses    remove_meta_box()                 Removes the metabox given arguments to target it
 */
function proud_remove_default_custom_fields_meta_box( $post_type, $context, $post ) {
    remove_meta_box( 'postcustom', $post_type, $context );
}
add_action( 'do_meta_boxes', 'proud_remove_default_custom_fields_meta_box', 1, 3 );
