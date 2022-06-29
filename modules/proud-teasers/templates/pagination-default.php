<?php if ($this->query->max_num_pages > 1) { ?>

  <?php if ( 'document' == get_post_type() ){ ?>
    <!-- @todo use a var so we can see what type of navigation this is -->
    <nav aria-label="Page Navigation" class="paged-navigation">
      <?php
        $big = 9999999999;
        $args = array(
            'base' => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
            'format' => '?paged=%#%',
            'current' => max( 1, get_query_var( 'paged' ) ),
            'total' => $this->query->max_num_pages,
            'type' => 'array',
        );

        $page_array = paginate_links( $args );

        // looping through the array of links so that we can customize the HTML to our existing styles
        echo '<ul class="pagination">';
        foreach( $page_array as $page ){
          echo '<li class="page-item">' . wp_kses_post( $page ) . '</li>';
        }
        echo '</ul>';

        //print_r( paginate_links( $args ) );

        /*
        echo '<pre>';
        print_r( $this->query );
        echo '</pre>';
        */
      ?>
    </nav>

  <?php
  } else { /* default if no custom settings */ ?>
    <nav>
      <ul class="pager prev-next-posts">

        <?php if( !empty( $prev ) ): ?>
          <li class="previous"><?php echo wp_kses_post( $prev ); ?></li>
        <?php else: ?>
          <li class="previous disabled"><a href='#'><?php echo wp_kses_post( $prev_text ); ?></a></li>
        <?php endif; ?>

        <?php if( !empty( $next ) ): ?>
          <li class="next"><?php echo wp_kses_post( $next ); ?></li>
        <?php else: ?>
          <li class="next disabled"><a href='#'><?php echo wp_kses_post( $next_text ); ?></a></li>
        <?php endif; ?>

      </ul>
    </nav>
  <?php } // if get_post_type ?>

<?php } // if query->max_num_pages ?>