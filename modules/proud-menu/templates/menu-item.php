<a href="<?php echo $item['url'] ?>" title="<?php echo $item['title'] ?>" class="list-group-item<?php echo ( !empty( $item['active'] ) || !empty( $item['active_trail'] ) ? ' active' : '') ?>"<?php echo ( !empty( $item['active_click_level'] ) ? ' data-active-click="' . $item['active_click_level'] . '"' : '') ?>><!-- wp-proud-core/modules/proud-menu/templates/menu-item.php -->
  <?php echo $item['title'] ?>
</a>
