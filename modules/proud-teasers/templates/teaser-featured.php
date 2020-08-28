<div <?php post_class( "featured" ); ?>>
  <?php if( has_post_thumbnail() ): ?>
  <div class="image image image-aspect ratio-2-1">
    <a href="<?php echo esc_url( get_permalink() ); ?>"><?php \Proud\Core\TeaserList::print_teaser_thumbnail('large'); ?></a>
  </div>
  <?php endif; ?>
  <?php the_title( sprintf( '<h3 class="h4 entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h3>' ); ?>
  <p class="text-muted margin-bottom-none"><?php echo get_the_date(); ?></p>
  <p class="featured-caption"><?php echo \Proud\Core\wp_trim_excerpt( '', true, false, 15 ); ?></p>
</div>

