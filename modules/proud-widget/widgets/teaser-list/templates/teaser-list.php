<?php $teaser_list->print_list(); ?>

<?php if( !empty( $instance['more_link'] ) ): ?>
  <p><a href="<?php echo esc_url( $instance['link_url'] ); ?>"><?php echo esc_attr( $instance['link_title'] ); ?></a></p>
<?php endif; ?>