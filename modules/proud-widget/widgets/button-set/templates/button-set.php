<div class="card-columns card-columns-xs-2 card-columns-sm-<?php echo ($md_col - 1) > 1 ? $md_col - 1 : 2  ?> card-columns-md-<?php echo $md_col ?>  card-columns-equalize">
  <?php foreach ( $buttonset as $button ) : ?>
    <?php if( !empty( $button['link_title'] ) ): ?>
      <div class="card-wrap">
      <?php if ( !empty( $button['link_url'] ) ): ?><a href="<?php print $button['link_url'] ?>"<?php else: ?><div<?php endif; ?> class="card card-inverse card-link text-center card-btn card-block" <?php if( !empty( $button['external'] ) ): ?>target="_blank"<?php endif;?> >
          <div class="h4"><?php print $button['link_title'] ?></div>
        <?php if ( !empty( $button['link_url'] ) ): ?></a><?php else: ?></div><?php endif; ?>
      </div><!--seperate-->
    <?php endif; ?>
  <?php endforeach; ?>
</div>