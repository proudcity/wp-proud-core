<div class="menu-slider level-count-<?php echo count($menus) ?> level-<?php echo $active ?>-active" data-level-active="<?php echo $active ?>">
  <div class="inner list-group">
    <?php echo implode('', $menus) ?> 
  </div>
</div>