<div class="btn-toolbar pull-left" role="toolbar">
  <?php do_action( 'proud_nav_toolbar_pre_buttons' ); ?>
  <?php if( !get_option('proud_hide_toolbar_nav') ): ?>
    <?php foreach ( $action_buttons as $button ): ?>
      <a title="<?php echo $button['title'] ?>" <?php if( $button['data_key'] ) : ?>data-proud-navbar="<?php echo $button['data_key'] ?>"<?php endif; ?><?php echo $button['data_attrs'] ?> href="<?php echo $button['href'] ?>" class="<?php echo $button['classes'] ?>"><i aria-hidden="true" class="fa <?php echo $button['icon'] ?>"></i> <?php echo $button['title'] ?></a>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<div class="btn-toolbar pull-right" role="toolbar">
  <a id="menu-button" href="#" class="btn navbar-btn menu-button"><span class="hamburger">
    <span>toggle menu</span>
  </span></a>
  <a title="<?php echo $search_button['title'] ?>"<?php if( $search_button['data_key'] ) : ?> data-proud-navbar="<?php echo $search_button['data_key'] ?>"<?php endif; ?><?php echo $search_button['data_attrs'] ?> href="<?php echo $search_button['href'] ?>" class="<?php echo $search_button['classes'] ?>"><i aria-hidden="true" class="fa <?php echo $search_button['icon'] ?>"></i> <?php echo $search_button['title'] ?></a>
</div>