<li>
  <a href="<?php echo $item['url'] ?>" title="<?php echo $item['title'] ?>" class="<?php echo ( !empty( $item['active'] ) || !empty( $item['active_trail'] ) ? ' active' : '') ?>"<?php echo ( !empty( $item['active_click_level'] ) ? ' data-active-click="' . $item['active_click_level'] . '"' : '') ?>>
    <?php echo $item['title'] ?>
  </a>
</li>