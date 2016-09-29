<div <?php post_class( "featured" ); ?>>
  <?php if( has_post_thumbnail() ): ?>
  <div class="image">
    <a href="<?php echo esc_url( get_permalink() ); ?>"><?php the_post_thumbnail('medium'); ?></a>
  </div>
  <?php endif; ?>
  <?php the_title( sprintf( '<h4 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h4>' ); ?>
  <p class="featured-caption"><?php echo \Proud\Core\wp_trim_excerpt( '', true, false, 15 ); ?></p>
</div>