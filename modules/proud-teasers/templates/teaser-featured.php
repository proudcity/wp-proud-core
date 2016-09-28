<div <?php post_class( "featured" ); ?>>
  <?php if( has_post_thumbnail() ): ?>
  <div class="image">
    <a href="<?php echo esc_url( get_permalink() ); ?>"><?php the_post_thumbnail('medium'); ?></a>
  </div>
  <?php endif; ?>
  <?php the_title( sprintf( '<h3 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h3>' ); ?>
</div>