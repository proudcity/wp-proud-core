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


/**
 * Prints out list of job types for multi / single jobs
 */
function proud_wp_job_manager_print_types($post) {
  if(!$post) {
    global $post;
  }
  if(job_manager_multi_job_type()): ?>
    <?php 
      $types = wp_get_post_terms( $post->ID, 'job_listing_type' );
      foreach ($types as $type) {
        ?>
          <span class="label job-type <?php echo sanitize_title( $type->slug ) ?>">
            <?php echo $type->name ?>
          </span>
        <?php
      }
    ?>
  <?php else: ?>
    <span class="label job-type <?php echo get_the_job_type() ? sanitize_title( get_the_job_type()->slug ) : ''; ?>"><?php the_job_type(); ?></span>
  <?php endif;
}