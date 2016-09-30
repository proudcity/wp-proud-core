<?php echo $row_open; ?>
<div <?php post_class( "teaser media" . $column_classes  ); ?>>
  <div class="media-left">
  <?php if( has_post_thumbnail() ): ?>
    <a href="<?php echo esc_url( get_permalink() ); ?>">
      <?php the_post_thumbnail( array( 64, 64 ) ); ?>
    </a>
  <?php endif; ?>
  </div>
  <div class="media-body">
    <?php the_title( sprintf( '<h3 class="entry-title media-heading"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h3>' ); ?>
    <?php if( !empty( $post->post_excerpt ) ): ?>
      <p><?php echo $post->post_excerpt ?></p>
    <?php endif; ?>
  </div>
</div>
<?php echo $row_close; ?>