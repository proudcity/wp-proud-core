<?php 
  $class = ((int) $across === 3) ? 'card-columns-md-3' : 'card-columns-md-2';
?>
<div class="card-columns card-columns-xs-2 card-columns-sm-2 <?php echo $class ?> card-columns-equalize">
  <?php foreach ( $imageset as $image ) : ?>
    <?php if( !empty( $image['link_title'] ) && !empty( $image['link_url'] ) && !empty( $image['image'] ) ): ?>
      <div class="card-wrap"><div class="card">
        <?php if( !empty( $image['image'] ) && is_numeric( $image['image'] ) ): ?>
        <div class="card-img-top text-center">
          <a href="<?php print $image['link_url'] ?>">
            <?php 
              $meta = \Proud\Core\build_responsive_image_meta( $image['image'], 'card-thumb', 'card-thumb' ); 
              \Proud\Core\print_responsive_image( $meta, [], true );
            ?>
          </a>
        </div>
        <?php endif; ?>
        <div class="card-block">
          <h3 class="margin-top-none">
            <a href="<?php print $image['link_url'] ?>">
              <?php print $image['link_title'] ?>
            </a>
          </h3>
          <?php if( !empty( $image['text'] ) ): ?>
          <p class="margin-bottom-none"><?php echo $image['text']; ?></p>
          <?php endif; ?>
        </div>
      </div></div>
    <?php endif; ?>
  <?php endforeach; ?>
</div>