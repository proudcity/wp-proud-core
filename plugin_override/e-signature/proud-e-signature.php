<?php 

if (class_exists('WP_E_Digital_Signature')) {
  function proud_esig_remove_styles() {
    global $wp_styles;
    // remove proud items
    foreach ( $wp_styles->queue as $handle ) {
      if( preg_match( '/proud/i', $handle ) ) {
        wp_deregister_style( $handle );
        wp_dequeue_style( $handle );
      }
    }
    // Enqueue
    wp_enqueue_style( 
      'proud-e-signature', 
      plugins_url( 'assets/css/', __FILE__ ) . 'proud-e-signature.css',
      []
    );
  }

  function proud_esig_deal_with_styles() {
    $current_page = get_queried_object_id();
    global $wpdb;
    $table = $wpdb->prefix . 'esign_documents_stand_alone_docs';
    $default_page = array();
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
        $default_page = $wpdb->get_col("SELECT page_id FROM {$table}");
    }
    $setting = new WP_E_Setting();
    $default_normal_page = $setting->get_generic('default_display_page');

    if (is_page($current_page) && in_array($current_page, $default_page)) {
      proud_esig_remove_styles();
    }
    else if (is_page($current_page) && $current_page == $default_normal_page) {
      proud_esig_remove_styles();
    }
  }

  add_action('wp_print_styles', 'proud_esig_deal_with_styles', 101);
}