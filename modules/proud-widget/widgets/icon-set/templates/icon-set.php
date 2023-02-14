<div class="card-columns card-columns-xs-2 card-columns-sm-<?php echo ($md_col - 1) > 1 ? $md_col - 1 : 2  ?> card-columns-md-<?php echo $md_col ?>  card-columns-equalize">
	<?php if ( isset( $iconset ) && ! empty( $iconset ) ) {?>

		<?php foreach ( $iconset as $icon ) : ?>
			<?php if( !empty( $icon['link_title'] ) ): ?>
				<div class="card-wrap">
					<?php if ( !empty( $icon['link_url'] ) ): ?><a href="<?php print $icon['link_url'] ?>"<?php else: ?><div<?php endif; ?> class="card text-center card-btn card-block <?php echo $classname?>" <?php if( !empty( $icon['external'] ) ): ?>target="_blank"<?php endif;?> >
					<?php if (!empty( $icon['fa_icon'] )): ?><i aria-hidden="true" class="fa <?php print $icon['fa_icon'] ?> fa-3x"></i><?php endif; ?>
					<div class="h4"><?php print $icon['link_title'] ?></div>
					<?php if ( !empty( $icon['link_url'] ) ): ?></a><?php else: ?></div><?php endif; ?>
				</div><!--seperate-->
			<?php endif; ?>
		<?php endforeach; ?>

	<?php } // isset( $iconset ) ! empty( $iconset ) ?>
</div>
