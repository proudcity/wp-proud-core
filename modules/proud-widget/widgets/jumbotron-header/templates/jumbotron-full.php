<div class="<?php print implode( ' ', $classes ) ?>" id="<?php print $random_id ?>" style="<?php print implode( '', $arr_styles ) ?>">
  <div class="container"><div class="full-container">
    <div class="<?php print implode( ' ', $boxclasses ) ?>"><div class="row"><div class="col-lg-7 col-md-8 col-sm-9">
      <div class="jumbotron-bg"><?php print $content; ?></div>
    </div></div></div>
  </div></div>
  <?php if ($caption): ?><div class="media-byline text-left"><span><?php echo $caption ?></span></div><?php endif; ?>
</div>