<?php
	$phone = get_post_meta( absint( get_the_ID() ), '_job_contact_phone', true ) ? get_post_meta( absint( get_the_ID() ), '_job_contact_phone', true ) : '';
	$job_type = get_terms(
		array(
			'object_ids' => get_the_ID(),
			'taxonomy' => 'job_listing_type',
		)
	);

	$class = $job_type['0']->slug;
	$name = $job_type['0']->name;

//echo '<pre>';
//print_r( $job_type );
//echo '</pre>';
?>
<tr><!-- template-file: wp-proud-core/modules/proud-teasers/templates/teaser-table.php -->
	<td><?php the_title( sprintf( '<a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a>' ); ?></td>
	<td><?php echo '<span class="label job-type '. sanitize_html_class( $class ) .'">'.esc_attr( $name ) .'</span>'; ?></td>
	<td><?php echo esc_attr( $phone ); ?></td>
</tr>
