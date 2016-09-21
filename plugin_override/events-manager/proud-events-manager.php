<?php

// Load Event scripts
function events_manager_script_enqueue() {
  global $post;

  if( !empty($post) && preg_match( '/id\=\"event\-form\"/', $post->post_content ) ) {
    global $proudcore;
    $proudcore::$libraries->addBundleToLoad('select2');
    wp_enqueue_script('proud-events-manager/js', plugins_url( 'assets/js/',__FILE__ ) . 'proud-events-manager.js', ['select2', 'proud'], true);
  }
}
add_action( 'wp_enqueue_scripts', 'events_manager_script_enqueue', 1);

// Unload module scripts
function events_manager_script_dequeue() {
  global $wp_scripts;

  // Unset the jqueryui code from events manager
  if( !empty( $wp_scripts->registered['events-manager']->extra['data'] ) ) {
    $css_url = json_encode( plugins_url() . '/events-manager/includes/css/jquery-ui.css' );
    $wp_scripts->registered['events-manager']->extra['data'] = str_replace($css_url, '""', $wp_scripts->registered['events-manager']->extra['data']);
  }
}
add_action( 'wp_print_scripts', __NAMESPACE__ . '\\events_manager_script_dequeue', 100 );