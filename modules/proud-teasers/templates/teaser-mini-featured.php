<li <?php post_class( "featured teaser-mini" ); ?>>
  <?php if( has_post_thumbnail() ): ?>
  <div class="image image-aspect ratio-2-1">
    <a href="<?php echo esc_url( get_permalink() ); ?>"><?php the_post_thumbnail('featured-teaser'); ?></a>
  </div>
  <?php endif; ?>
  <?php the_title( sprintf( '<h4 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h4>' ); ?>
  <p class="text-muted margin-bottom-none"><?php echo __('Posted on', 'wp-proud-core') ?> <?php echo get_the_date(); ?></p>
  <p class="featured-caption"><?php echo \Proud\Core\wp_trim_excerpt( '', true, false, 15 ); ?></p>
</li>