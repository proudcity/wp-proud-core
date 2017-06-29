<div <?php post_class( "teaser" ); ?>>
  <div class="row">
    <?php if( has_post_thumbnail() ): ?>
    <div class="col-md-3 pull-right">
      <?php the_post_thumbnail(); ?>
    </div>
    <?php endif; ?>
    <div class="col-md-9 pull-left">
      <?php the_title( sprintf( '<h4 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h4>' ); ?>
      <p class="text-muted"><?php echo __('Posted on', 'wp-proud-core') ?> <?php echo get_the_date(); ?></p>
      <?php do_action( 'teaser_search_matching', $post ); ?>
      <?php echo \Proud\Core\wp_trim_excerpt(); ?>
    </div>
  </div>
</div>