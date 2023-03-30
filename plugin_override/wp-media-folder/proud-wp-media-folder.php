<?php

/**
 * Removes stuff from WP Media Folder
 */
function pc_remove_wp_media_folder_filters(){

    /**
     *  this filter searches all content for stuff that may be an external video
     *  by using the `attachment_url_to_postid ` function which is expensive.
     *  This was introduced in WP Media Folder 5.5.6 so we're taking it out
     *  to make sure it doesn't kill our sites
     */
    remove_filter( 'the_content', 'wpmfFindImages' );

}
add_action( 'wp_loaded', 'pc_remove_wp_media_folder_filters' );
