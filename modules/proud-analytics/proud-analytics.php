<?php
/**
 * @author ProudCity
 */

function enqueue_proud_analytics_frontend() {
  $path = plugins_url('assets/js/',__FILE__);
  wp_register_script('proud-analytics/js', $path . 'proud-analytics.js', ['jquery', 'proud']);
  wp_enqueue_script('proud-analytics/js');
}

add_action( 'wp_enqueue_scripts', 'enqueue_proud_analytics_frontend' );
