<?php if ($this->query->max_num_pages > 1) { ?>

  <?php if ( 'document' == get_post_type() ){

    $big = 9999999999;

    $args = array(
        'base' => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
        'format' => '?paged=%#%',
        'current' => max( 1, get_query_var( 'paged' ) ),
        'total' => $this->query->max_num_pages,
    );

    echo paginate_links( $args );

    /*
    echo '<pre>';
    print_r( $this->query );
    echo '</pre>';
    */

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