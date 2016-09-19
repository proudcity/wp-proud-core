<?php 

/**
 * Enqueue JS
 */
function proud_pagetitle_admin_script(){
    $path = plugins_url( 'assets/js/',__FILE__ );
    wp_enqueue_script( 'proud-pagetitle', $path . '/proud-pagetitle.js', array('jquery') );
    // Set Nonce, URL
    wp_localize_script( 'proud-pagetitle', 'proud_title',
        array( 
            'ajax_url' => admin_url( 'admin-ajax.php' ), 
            '_wpnonce' => wp_create_nonce( 'proud_pagetitle_check' )
        ) 
    );
}
add_action('admin_enqueue_scripts', 'proud_pagetitle_admin_script');   

/**
 * Helper function checks if post title already exists
 */
function proud_pagetitle_get_duplicates($title, $id) {
   global $wpdb;
   $title_exists = $wpdb->get_results( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = '" . sanitize_text_field($title) . "' AND post_status = 'publish'") );
   $id = sanitize_text_field($id);
   foreach( $title_exists as $key => $post ) {
        if( $id !== $post->ID ){
            return TRUE;
        }
   }
   return FALSE;
}

/**
 * Ajax callback
 */
function proud_pagetitle_callback() {
    // Check nonce
    if( isset( $_POST['_wpnonce'] ) && wp_verify_nonce($_POST['_wpnonce'], 'proud_pagetitle_check') ) {
        if( proud_pagetitle_get_duplicates( $_POST['title'], $_POST['post_id'] ) ) {
            wp_send_json(array(
                'duplicate_exists' => 1,
                'duplicate_message' => __('There is already a post with this title, please choose a new one or make sure the permalink below does not already exist!', 'wp-proud-core')
            ));
        }
    }
    wp_die();
}
add_action( 'wp_ajax_my_action', 'proud_pagetitle_callback' );