<div class="jumbo-header jumbotron-header-container"><div class="<?php print implode( ' ', $classes ) ?>" id="<?php print $random_id ?>" style="<?php print implode( '', $arr_styles ) ?>">
  <?php Proud\Core\print_responsive_image( $resp_img, ['jumbo-image-container'] ); ?>
  <div class="container"><div class="row"><div class="col-lg-5 col-md-8 col-sm-8">
    <div class="jumbotron-bg"><?php print $content; ?></div>
  </div></div></div>
  <?php if ( !empty( $caption ) ): ?><div class="media-byline text-left"><span><?php echo $caption ?></span></div><?php endif; ?>
</div></div>