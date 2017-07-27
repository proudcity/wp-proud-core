<?php 
  use Proud\Core;

  if( !empty($imageset) ):
  // Init column vars
  $post_count = count( $imageset );
  $columns = (int) $across;
  $class = $columns === 3 ? 'col-sm-4' : 'col-sm-6';
?>

<div class="media-list">
  <?php for ( $i = 0; $i < $post_count; $i++ ) : ?>
  <?php echo ImageSet::row_open( $i, $columns ); $image = $imageset[$i]; ?>
    <div class="media <?php echo $class ?>">
    <?php if( !empty( $image['link_title'] ) && !empty( $image['link_url'] ) && !empty( $image['image'] ) ): ?>
      <div class="media-left">
        <?php if( !empty( $image['image'] ) && is_numeric( $image['image'] ) ): ?>
          <a href="<?php print $image['link_url'] ?>">
            <?php 
              $meta = Core\build_responsive_image_meta( $image['image'], array( 64, 64 ), array( 64, 64 ) ); 
              Core\print_responsive_image( $meta, [], true );
            ?>
          </a>
        <?php endif; ?>
      </div>
      <div class="media-body">
        <div class="h3 media-heading">
          <a href="<?php print $image['link_url'] ?>">
            <?php print $image['link_title'] ?>
          </a>
        </div>
        <p><?php echo ( !empty( $image['text'] ) ? $image['text'] : '&nbsp' ) ?></p>
      </div>
    <?php endif; ?>
    </div>
  <?php echo ImageSet::row_close( $i, $post_count, $columns ); ?>
  <?php endfor; ?>
</div>
<?php endif; ?>