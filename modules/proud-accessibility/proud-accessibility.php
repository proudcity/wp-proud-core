<?php

require_once plugin_dir_path(__FILE__) . 'lib/accessibility-statement.class.php';

/**
 * @author ProudCity
 */

function enqueue_accessibility_frontend() {
  $path = plugins_url('assets/js/',__FILE__);
  wp_register_script('proud-accessibility/js', $path . 'proud-accessibility.js', ['jquery', 'proud'], false, true );
  wp_enqueue_script('proud-accessibility/js');
}
add_action( 'wp_enqueue_scripts', 'enqueue_accessibility_frontend' );
add_action( 'admin_enqueue_scripts', 'enqueue_accessibility_frontend' );


/**
 *  Prints the proud navbar
 */
function print_skip_links() {
  ob_start();
  include plugin_dir_path(__FILE__) . 'templates/skip-links.php';
  $links = ob_get_contents();
  ob_end_clean();
  echo $links;
}

add_action( 'get_header', 'print_skip_links', 1, 0 );