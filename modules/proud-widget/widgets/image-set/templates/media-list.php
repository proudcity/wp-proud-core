<?php 
  
  // Init column vars
  $post_count = count( $imageset );
  $columns = (int) $across;
  $class = $columns === 3 ? 'col-sm-4' : 'col-sm-6';

  function row_open( $current, $columns  ){
     return $current%$columns === 0
          ? '<div class="row">' 
          : '';
  }

  function row_close( $current, $post_count, $columns ) {
    return ( ( $post_count - 1 ) === $current ) || ( $current%$columns === ( $columns - 1 ) )
         ? '</div>'
         : '';
  }
?>

<div class="media-list">
  <?php for ( $i = 0; $i < $post_count; $i++ ) : ?>
  <?php echo row_open( $i, $columns ); $image = $imageset[$i]; ?>
    <div class="media <?php echo $class ?>">
    <?php if( !empty( $image['link_title'] ) && !empty( $image['link_url'] ) && !empty( $image['image'] ) ): ?>
      <div class="media-left">
        <?php if( !empty( $image['image'] ) && is_numeric( $image['image'] ) ): ?>
          <a href="<?php print $image['link_url'] ?>">
            <?php 
              $meta = \Proud\Core\build_responsive_image_meta( $image['image'], array( 64, 64 ), array( 64, 64 ) ); 
              \Proud\Core\print_responsive_image( $meta, [], true );
            ?>
          </a>
        <?php endif; ?>
      </div>
      <div class="media-body">
        <h3 class="media-heading">
          <a href="<?php print $image['link_url'] ?>">
            <?php print $image['link_title'] ?>
          </a>
        </h3>
        <p><?php echo ( !empty( $image['text'] ) ? $image['text'] : '&nbsp' ) ?></p>
      </div>
    <?php endif; ?>
    </div>
  <?php echo row_close( $i, $post_count, $columns ); ?>
  <?php endfor; ?>
</div>