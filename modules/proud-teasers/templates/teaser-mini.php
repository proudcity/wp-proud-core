<li <?php post_class( "teaser-mini" ); ?>>
  <?php the_title( sprintf( '<h5 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h5>' ); ?>
  <p class="muted"><?php echo get_the_date(); ?></p>
</li>