<div class="menu-slider level-count-<?php echo count($menus) ?> level-<?php echo $active ?>-active" data-level-active="<?php echo $active ?>">
  <div class="inner list-group">
    <?php echo implode('', $menus) ?> 
  </div>
  <noscript>
    <style>
        .menu-slider {
            height: auto!important;
            opacity: 1!important;
            overflow: visible!important;
        }
        .menu-slider > .inner {
            float: none!important;
            transition: none!important;
            max-width: 100%;
            margin: 0!important;
        }
        .menu-slider > .inner > * {
            float: none;
            width: 100%!important;
        }
    </style>
  </noscript>
</div>