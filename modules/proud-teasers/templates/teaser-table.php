<?php
	$phone = get_post_meta( absint( get_the_ID() ), '_job_contact_phone', true ) ? get_post_meta( absint( get_the_ID() ), '_job_contact_phone', true ) : '';
	$job_position = get_post_meta( absint( get_the_ID() ), '_job_position_name', true ) ? get_post_meta( absint( get_the_ID() ), '_job_position_name', true ) : '';
?>
<tr><!-- template-file: wp-proud-core/modules/proud-teasers/templates/teaser-table.php -->
	<td><?php the_title( sprintf( '<a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a>' ); ?></td>
	<td><?php echo esc_attr( $job_position ); ?></td>
	<td><?php echo esc_attr( $phone ); ?></td>
</tr>
