<?php $teaser_list->print_list(); ?>

<?php if( !empty( $instance['more_link'] ) ): ?>
  <p><a href="<?php echo $instance['link_url']; ?>"><?php echo $instance['link_title']; ?></a></p>
<?php endif; ?>