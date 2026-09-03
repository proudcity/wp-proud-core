<?php
  use Proud\Core;

  if( !empty($imageset) ):
  // Init column vars
  $post_count = count( $imageset );
  $columns = (int) $across;
  $class = $columns === 3 ? 'col-sm-4' : 'col-sm-6';
?>

<div class="media-list"><!-- template-file: wp-proud-core/proud-widget/widgets/image-set/templates/media-list.php -->
  <?php for ( $i = 0; $i < $post_count; $i++ ) : ?>
  <?php echo ImageSet::row_open( $i, $columns ); $image = $imageset[$i]; ?>
    <div class="media <?php echo $class ?>">
    <?php if( !empty( $image['link_title'] ) && !empty( $image['link_url'] ) && !empty( $image['image'] ) ): ?>
      <div class="media-left">
        <?php if( !empty( $image['image'] ) && is_numeric( $image['image'] ) ): ?>
          <a href="<?php print Core\esc_link_url( $image['link_url'] ) ?>"<?php if ( !empty( $image['external'] ) ): ?> target="_blank" rel="noopener"<?php endif; ?>>
            <?php
              $meta = Core\build_responsive_image_meta( $image['image'], array( 64, 64 ), array( 64, 64 ) );
              Core\print_responsive_image( $meta, [], true );
            ?>
          </a>
        <?php endif; ?>
      </div>
      <div class="media-body">
        <div class="h3 media-heading">
          <a href="<?php print Core\esc_link_url( $image['link_url'] ) ?>"<?php if ( !empty( $image['external'] ) ): ?> target="_blank" rel="noopener"<?php endif; ?>>
            <?php print Core\esc_widget_title( $image['link_title'] ) ?>
          </a>
        </div>
        <p><?php echo ( !empty( $image['text'] ) ? esc_html( $image['text'] ) : '&nbsp' ) ?></p>
      </div>
    <?php endif; ?>
    </div>
  <?php echo ImageSet::row_close( $i, $post_count, $columns ); ?>
  <?php endfor; ?>
</div>
<?php endif; ?>
