<?php

namespace Proud\WP_Job_Manager;

/** 
 * @TODO find out how to remove CSS from pagebuilder
 */
function proud_wp_job_manger_css_dequeue() {
  wp_deregister_style('wp-job-manager-frontend');
  wp_dequeue_style('wp-job-manager-frontend');
}
add_action( 'wp_print_styles', __NAMESPACE__ . '\\proud_wp_job_manger_css_dequeue', 100 );