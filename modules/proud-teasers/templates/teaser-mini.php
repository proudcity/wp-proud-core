<li <?php post_class( "teaser-mini" ); ?>><!-- template-file: wp-proud-core/modules/proud-teasers/templates/teaser-mini.php -->
  <<?php echo $header_tag; ?> class="<?php echo $header_class; ?> entry-title"><?php the_title( sprintf( '<a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a>' ); ?></<?php echo $header_tag; ?>>
  <p class="text-muted"><?php echo __('Posted on', 'wp-proud-core') ?> <?php echo get_the_date(); ?></p>
</li>
