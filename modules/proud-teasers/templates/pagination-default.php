<?php if ($this->query->max_num_pages > 1) { ?>

  <?php if ( 'document' == get_post_type() ){
    echo 'custom pager';
  } else { /* default if no custom settings */ ?>
    <nav>
      <ul class="pager prev-next-posts">

        <?php if( !empty( $prev ) ): ?>
          <li class="previous"><?php echo esc_attr( $prev ); ?></li>
        <?php else: ?>
          <li class="previous disabled"><a href='#'><?php echo esc_attr( $prev_text ); ?></a></li>
        <?php endif; ?>

        <?php if( !empty( $next ) ): ?>
          <li class="next"><?php echo esc_attr( $next ); ?></li>
        <?php else: ?>
          <li class="next disabled"><a href='#'><?php echo esc_attr( $next_text ); ?></a></li>
        <?php endif; ?>

      </ul>
    </nav>
  <?php } // if get_post_type ?>

<?php } // if query->max_num_pages ?>