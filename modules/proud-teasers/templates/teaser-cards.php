<div <?php post_class( "card-wrap" ); ?>>
  <div class="card">
    <?php if( has_post_thumbnail() ): ?>
    <div class="card-img-top text-center">
      <?php the_post_thumbnail(); ?>
    </div>
    <?php endif; ?>
    <div class="card-block">
      <?php the_title( sprintf( '<h3 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h3>' ); ?>
    </div>
  </div>
</div>