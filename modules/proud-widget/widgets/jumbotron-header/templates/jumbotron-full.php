<div class="<?php print implode( ' ', $classes ) ?>" id="<?php print $random_id ?>" style="<?php print implode( '', $arr_styles ) ?>">
  <?php if( !empty( $resp_img ) ) { Proud\Core\print_responsive_image( $resp_img, $resp_img_classes ); } ?>
  <div class="container"><div class="full-container">
    <div class="<?php print implode( ' ', $boxclasses ) ?>"><div class="row"><div class="col-lg-7 col-md-8 col-sm-9">
      <div class="jumbotron-bg"><div class="jumbotron-bg-mask"></div><?php print $content; ?></div>
    </div></div></div>
  </div></div>
</div>