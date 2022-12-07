<?php echo $row_open; ?>
<div <?php post_class("teaser media" . $column_classes); ?>><!-- template-file: wp-proud-core/modules/proud-teasers/templates/teaser-media.php -->
    <div class="media-left">
        <?php if (\Proud\Core\TeaserList::has_thumbnail()) : ?>
            <a href="<?php echo esc_url(get_permalink()); ?>">
                <?php \Proud\Core\TeaserList::print_teaser_thumbnail(array(64, 64)); ?>
            </a>
        <?php endif; ?>
    </div>
    <div class="media-body changed">
        <?php the_title(sprintf('<h3 class="entry-title media-heading"><a href="%s" rel="bookmark">', esc_url(get_permalink())), '</a></h3>'); ?>
        <?php
			$yoast_meta = get_post_meta( get_the_ID(), '_yoast_wpseo_metadesc', true );

			if( isset( $yoast_meta ) && ! empty( $yoast_meta ) ){
				echo apply_filters( 'the_content', $yoast_meta );
			} else {
				the_excerpt();
			}
		?>
    </div>
</div>
<?php echo $row_close; ?>
