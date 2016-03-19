<?php


function events_manager_script_dequeue() {
  global $wp_scripts;
  // d($wp_scripts);

  // Unset the jqueryui code from events manager
  if( !empty( $wp_scripts->registered['events-manager']->extra['data'] ) ) {
    $css_url = json_encode( plugins_url() . '/events-manager/includes/css/jquery-ui.css' );
    $wp_scripts->registered['events-manager']->extra['data'] = str_replace($css_url, '""', $wp_scripts->registered['events-manager']->extra['data']);
  }
}
add_action( 'wp_print_scripts', __NAMESPACE__ . '\\events_manager_script_dequeue', 100 );