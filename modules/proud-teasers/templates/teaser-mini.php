<li <?php post_class( "teaser-mini" ); ?>>
  <<?php echo $header_tag; ?> class="entry-title"><?php the_title( sprintf( '<a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a>' ); ?></<?php echo $header_tag; ?>>
  <p class="text-muted"><?php echo __('Posted on', 'wp-proud-core') ?> <?php echo get_the_date(); ?></p>
</li>