<?php if ($this->query->max_num_pages > 1) : ?>
<nav>
  <ul class="pager prev-next-posts">
    <?php if( !empty( $prev ) ): ?>
    <li class="previous"><?php echo $prev ?></li>
    <?php else: ?>
    <li class="previous disabled"><a href='#'><?php echo $prev_text ?></a></li>
    <?php endif; ?>
    <?php if( !empty( $next ) ): ?>
    <li class="next"><?php echo $next ?></li>
    <?php else: ?>
    <li class="next disabled"><a href='#'><?php echo $next_text ?></a></li>
    <?php endif; ?>
  </ul>
</nav>
<?php endif; ?>