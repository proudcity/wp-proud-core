<li <?php post_class( "featured teaser-mini" ); ?>>
  <?php if( has_post_thumbnail() ): ?>
  <div class="image">
    <a href="<?php echo esc_url( get_permalink() ); ?>"><?php the_post_thumbnail('large'); ?></a>
  </div>
  <?php endif; ?>
  <p class="featured-caption"><?php echo \Proud\Core\wp_trim_excerpt( '', true, false, 15 ); ?></p>
</li>