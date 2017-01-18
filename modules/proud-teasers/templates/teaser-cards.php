<div <?php post_class( "card-wrap" ); ?>>
  <div class="card">
    <?php if( has_post_thumbnail() ): ?>
    <div class="card-img-top text-center">
      <a href="<?php echo esc_url( get_permalink() ); ?>"><?php the_post_thumbnail('card-thumb'); ?></a>
    </div>
    <?php elseif( !empty($default_image) ): ?>
    <div class="card-img-top text-center">
      <a href="<?php echo esc_url( get_permalink() ); ?>"><img title="<?php echo $post->post_title ?>" src="<?php echo $default_image ?>"/></a>
    </div>
    <?php endif; ?>
    <div class="card-block">
      <?php the_title( sprintf( '<h3 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h3>' ); ?>
    </div>
  </div>
</div>