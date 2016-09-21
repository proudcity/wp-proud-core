<?php $teaser_list->print_list(); ?>

<?php if( !empty( $instance['more_link'] ) ): ?>
  <a href="<?php echo $instance['link_url']; ?>"><?php echo $instance['link_title']; ?></a>
<?php endif; ?>