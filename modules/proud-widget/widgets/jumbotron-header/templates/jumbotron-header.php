<div class="jumbo-header jumbotron-header-container"><div class="<?php print implode( ' ', $classes ) ?>" id="<?php print $random_id ?>" style="<?php print implode( '', $arr_styles ) ?>">
  <?php if( !empty( $resp_img ) ) { Proud\Core\print_responsive_image( $resp_img, $resp_img_classes ); } ?>
  <div class="container"><div class="row"><div class="<?php echo $jumbotron_col_classes;?>">
    <div class="jumbotron-bg"><div class="jumbotron-bg-mask"></div><?php print $content; ?></div>
  </div></div></div>
  <?php if ( !empty( $caption ) ): ?><div class="media-byline text-left"><span><?php echo $caption ?></span></div><?php endif; ?>
</div></div>