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

/**
 * Add Last Date Modified Column
 *
 * @since  2025.11.24
 * @author Curtis <curtis@proudcity.com
 */
function proud_modified_columns($columns) {

    unset($columns['date']);

    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['date_published'] = 'Published';
            $new_columns['date_modified'] = 'Updated';
        }
    }
    return $new_columns;
}
add_filter('manage_edit-page_columns', 'proud_modified_columns');
add_filter('manage_edit-meeting_columns', 'proud_modified_columns');
add_filter('manage_edit-post_columns', 'proud_modified_columns');
add_filter('manage_edit-document_columns', 'proud_modified_columns');
add_filter('manage_edit-agency_columns', 'proud_modified_columns');

/**
 * Display Last Date Modified Value
 *
 * @since  2025.11.24
 * @author Curtis <curtis@proudcity.com
 */
function proud_custom_column_content($column, $post_id) {

    if ($column === 'date_published') {
        $post_modified = get_post_field('post_published', $post_id);
        // Last Modified word, line break, last modified date
        $formatted_date = date_i18n('Y/m/d \a\t g:i A', strtotime($post_modified));

        echo '<strong>Published</strong>:<br>' . $formatted_date;
    }

    if ($column === 'date_modified') {
        $post_modified = get_post_field('post_modified', $post_id);
        // Last Modified word, line break, last modified date
        $formatted_date = date_i18n('Y/m/d \a\t g:i A', strtotime($post_modified));

        echo '<strong>Last Modified</strong>:<br>' . $formatted_date;
    }

}
add_action('manage_page_posts_custom_column', 'proud_custom_column_content', 10, 2);
add_action('manage_meeting_posts_custom_column', 'proud_custom_column_content', 10, 2);
add_action('manage_post_posts_custom_column', 'proud_custom_column_content', 10, 2);
add_action('manage_document_posts_custom_column', 'proud_custom_column_content', 10, 2);
add_action('manage_agency_posts_custom_column', 'proud_custom_column_content', 10, 2);

/**
 * Make Last Date Modified Column Sortable
 *
 * @since  2025.11.24
 * @author Curtis <curtis@proudcity.com
 */
function proud_custom_sortable_columns($columns) {
    $columns['date_published'] = 'post_published';
    $columns['date_modified'] = 'post_modified';
    return $columns;
}
add_filter('manage_edit-page_sortable_columns', 'proud_custom_sortable_columns');
