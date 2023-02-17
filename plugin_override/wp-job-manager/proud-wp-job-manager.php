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
          <span class="label job-type <?php echo sanitize_html_class( $type->slug ); ?>">
            <?php echo esc_attr( $type->name ) ?>
          </span>
        <?php
      }
    ?>
  <?php else: ?>
    <span class="label job-type <?php echo wpjm_get_the_job_types() ? sanitize_html_class( wpjm_get_the_job_types()[0]->slug ) : ''; ?>"><?php wpjm_the_job_types(); ?></span>
  <?php endif;
}

/**
 * Adds extra fields to job listings for contact phone number and contact position
 *
 * @since 2023.02.16
 * @author Curtis McHale
 *
 * @param   int         $post_id            required                The ID of the post we're working with
 *
 * @todo save the data that is input
 * @todo show any data that is saved
 * @todo look at HTML 5 phone field for better keyboards
 */
function add_job_data( $post_id ){
$phone = get_post_meta( absint( $post_id ), '_job_contact_phone', true ) ? get_post_meta( absint( $post_id ), '_job_contact_phone', true ) : '';
$job_position = get_post_meta( absint( $post_id ), '_job_position_name', true ) ? get_post_meta( absint( $post_id ), '_job_position_name', true ) : '';
?>
	<p class="form-field">
		<label for="job_contact_phone">Contact Phone</label>
		<input type="text" name="job_contact_phone" id="job_contact_phone" placeholder="555.555.5555" value="<?php echo esc_attr( $phone ); ?>" />
	</p>

	<p class="form-field">
		<label for="job_position_name">Job Position Name</label>
		<input type="text" name="job_position_name" id="job_position_name" placeholder="" value="<?php echo esc_attr( $job_position ); ?>" />
	</p>
<?php
}
add_action( 'job_manager_job_listing_data_end', __NAMESPACE__ . '\\add_job_data' );

/**
 * Saves our new meta fields
 *
 * @since 2023.02.17
 * @author Curtis McHale
 *
 * @param   int         $post_id        required            ID of the post we're saving
 * @param   object      $post_object    required            Entire post object
 * @uses    update_post_meta()                              Updates meta given post_id, key, value
 */
function save_listing_meta( $post_id, $post_object ){

	if ( isset( $_POST['job_contact_phone'] ) && ! empty( $_POST['job_contact_phone'] ) ){
		update_post_meta( absint( $post_id ), '_job_contact_phone', esc_attr( $_POST['job_contact_phone'] ) );
	}

	if ( isset( $_POST['job_position_name'] ) && ! empty( $_POST['job_position_name'] ) ){
		update_post_meta( absint( $post_id ), '_job_position_name', esc_attr( $_POST['job_position_name'] ) );
	}

}
add_action( 'job_manager_save_job_listing', __NAMESPACE__ . '\\save_listing_meta', 10, 2 );
