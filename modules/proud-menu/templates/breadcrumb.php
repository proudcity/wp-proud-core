<nav aria-label="Breadcrumb">
	<ol class="breadcrumb"><!-- wp-proud-core/modules/proud-menu/templates/breadcrumb.php -->
	<?php foreach ($active_trail as $item): ?>
		<?php if(!empty($item['active'])): ?>
		<li class="active">
			<a href="" aria-current="page"><?php echo esc_attr($item['title']); ?></a>
		</li>
		<?php else: ?>
			<li>
				<a href="<?php echo esc_url($item['url']); ?>" title="<?php echo esc_attr($item['title']) ?>"><?php echo esc_attr($item['title']); ?></a>
			</li>
		<?php endif; ?>
	<?php endforeach; ?>
	</ol>
</nav>
