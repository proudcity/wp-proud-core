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

/**
 * Priority for the folder-upload guard.
 *
 * WP Media Folder registers its own handler at the default priority of 10
 * (class/class-main.php:147), so ours has to run first.
 */
const PROUDCITY_WPMF_BLOCK_UPLOAD_PRIORITY = 1;

/**
 * Refuse WP Media Folder's "Upload folder" bulk upload request.
 *
 * WPMF 6.2.6 ships a chunked uploader — wp_ajax_wpmf_upload_folder ->
 * WpmfMediaFolder::uploadFolder() — that reimplements the WordPress upload
 * pipeline instead of delegating to wp_handle_upload(). Two consequences make
 * it unusable on ProudCity:
 *
 * 1. createFileFromChunks() (class-main.php:3910) writes $_FILES['name']
 *    verbatim, with no sanitize_file_name() and no wp_unique_filename(). Names
 *    keep their spaces, our stateless_skip_cache_busting suffix never gets
 *    applied, and re-uploading a name silently overwrites the first file while
 *    creating a second attachment row pointing at it.
 *
 * 2. It calls wp_update_attachment_metadata() only for image, video and audio
 *    (class-main.php:3769-3781). WordPress core calls it for every attachment
 *    type (wp-admin/includes/media.php:446), and WP Stateless offloads solely
 *    on that filter (wp-stateless/lib/classes/class-bootstrap.php:379). So
 *    documents uploaded this way never reach the bucket, live only on the
 *    pod's local disk, and are destroyed on the next container restart.
 *
 * Duchesne County lost 338 meeting documents to this on 2026-08-18. Blocking
 * the endpoint is the fix until JoomUnited ships one; the standard "Add New"
 * uploader is unaffected and remains the supported path.
 *
 * Answers in WPMF's own response shape (status => false) because that is what
 * its uploader expects. Note that WPMF discards the message — its fileError
 * handler is an empty function (assets/js/script.js:1668) and its `complete`
 * handler shows a success snackbar regardless (script.js:1698). So the refusal
 * is deliberately logged server-side as well; the button is hidden, so nobody
 * should reach this, and if they do we want a record rather than a silent no-op.
 *
 * @see https://github.com/proudcity/wp-proudcity/issues/2887
 */
function proudcity_wpmf_block_folder_upload() {
    error_log(
        sprintf(
            'ProudCity: blocked WP Media Folder folder upload (user %d). See issue #2887.',
            function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0
        )
    );

    wp_send_json(
        array(
            'status' => false,
            'msg'    => __( 'Folder upload is disabled on ProudCity because it does not save files to cloud storage. Use the Add New button to upload media.', 'wp-proud-core' ),
        ),
        403
    );
}

/**
 * Take sole ownership of the folder-upload action before admin-ajax dispatches.
 *
 * Registering at priority 1 only wins while WPMF keeps registering at 10. A
 * future release that moves to priority 0, or adds a second registration, would
 * silently restore the data-loss bug with no error and no failing test.
 *
 * admin-ajax.php fires admin_init (line 45) well before it dispatches
 * wp_ajax_{$action} (line 192), so clearing the hook here is deterministic
 * regardless of what priority WPMF used. Re-adding immediately matters: core
 * wp_die()s with a 400 when has_action() is false for the requested action
 * (admin-ajax.php:180).
 *
 * `wpmf_upload_folder` is not a core Ajax action, so remove_all_actions() here
 * cannot clobber a WordPress handler.
 *
 * @see https://github.com/proudcity/wp-proudcity/issues/2887
 */
function proudcity_wpmf_seize_folder_upload_action() {
    remove_all_actions( 'wp_ajax_wpmf_upload_folder' );
    add_action( 'wp_ajax_wpmf_upload_folder', 'proudcity_wpmf_block_folder_upload' );
}

/**
 * Wire up the folder-upload block.
 *
 * Kept in its own function so the registrations are assertable in tests rather
 * than being a bare side effect of loading this file.
 */
function proudcity_wpmf_register_folder_upload_block() {
    // Belt: beats WPMF's priority 10 on a normal request.
    add_action( 'wp_ajax_wpmf_upload_folder', 'proudcity_wpmf_block_folder_upload', PROUDCITY_WPMF_BLOCK_UPLOAD_PRIORITY );
    // Braces: survives WPMF changing its own priority.
    add_action( 'admin_init', 'proudcity_wpmf_seize_folder_upload_action', PHP_INT_MAX );
}
proudcity_wpmf_register_folder_upload_block();

/**
 * Hide the "Upload folder" button that WP Media Folder injects into the admin.
 *
 * The button is added by WPMF's JavaScript after page load (assets/js/script.js
 * lines 184-190), so there is no server-side markup to filter and no setting to
 * switch it off. CSS is the reliable removal point, and it applies whenever the
 * script inserts the element.
 *
 * proudcity_wpmf_block_folder_upload() is the actual enforcement; this only
 * keeps editors from reaching for a control that now refuses.
 *
 * @see https://github.com/proudcity/wp-proudcity/issues/2887
 */
function proudcity_wpmf_hide_folder_upload_button() {
    echo '<style id="proudcity-wpmf-hide-folder-upload">.wpmf_btn_upload_folder{display:none !important;}</style>' . "\n";
}
add_action( 'admin_head', 'proudcity_wpmf_hide_folder_upload_button' );
