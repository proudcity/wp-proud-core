<div class="card-columns card-columns-xs-1 card-columns-sm-2 card-columns-md-4 card-columns-equalize">
  <?php foreach ( $iconset as $icon ) : ?>
    <?php if( !empty( $icon['link_title'] ) && !empty( $icon['link_url'] ) && !empty( $icon['fa_icon'] ) ): ?>
      <div class="card-wrap"><a href="<?php print $icon['link_url'] ?>" class="card text-center card-btn card-block">
        <i class="fa <?php print $icon['fa_icon'] ?> fa-3x"></i>
        <h4><?php print $icon['link_title'] ?></h4>
      </a></div><!--seperate-->
    <?php endif; ?>
  <?php endforeach; ?>
</div>