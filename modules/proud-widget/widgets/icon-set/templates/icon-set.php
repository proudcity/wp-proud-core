<div class="card-columns card-columns-xs-2 card-columns-sm-<?php echo ($md_col - 1) > 1 ? $md_col - 1 : 2  ?> card-columns-md-<?php echo $md_col ?>  card-columns-equalize">
  <?php foreach ( $iconset as $icon ) : ?>
    <?php if( !empty( $icon['link_title'] ) && !empty( $icon['link_url'] ) && !empty( $icon['fa_icon'] ) ): ?>
      <div class="card-wrap"><a href="<?php print $icon['link_url'] ?>" class="card text-center card-btn card-block" <?php if( !empty( $icon['external'] ) ): ?>target="_blank"<?php endif;?> >
        <i class="fa <?php print $icon['fa_icon'] ?> fa-3x"></i>
        <h4><?php print $icon['link_title'] ?></h4>
      </a></div><!--seperate-->
    <?php endif; ?>
  <?php endforeach; ?>
</div>