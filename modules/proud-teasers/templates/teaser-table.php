<tr>
  <td><?php the_title( sprintf( '<a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a>' ); ?></td>
  <td><?php echo !empty( $meta['_staff_member_title'][0] ) ? $meta['_staff_member_title'][0] : '' ?></td>
  <td><?php echo !empty( $meta['_staff_member_phone'][0] ) ? $meta['_staff_member_phone'][0] : '' ?></td>
</tr>