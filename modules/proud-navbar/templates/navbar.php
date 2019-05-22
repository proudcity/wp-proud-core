<header role="banner">
  <?php if( $topbar_active ): ?>
    <div id="navbar-topbar" class="navbar navbar-default navbar-topbar">
      <?php print get_topbar_logo_area(); ?>
      <?php print get_nav_topbar_menu(); ?>
      <?php if ( $topbar_has_action_toolbar ): ?>
        <div class="container-fluid menu-box">
            <?php print get_nav_action_toolbar(); ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <div id="navbar-external" class="navbar navbar-default navbar-external navbar-fixed-bottom <?php echo get_proud_logo_wrapper_class(); ?>">
    <ul id="logo-menu" class="nav navbar-nav">
      <li class="nav-logo" style="<?php if( !empty( $custom_width ) ) { echo 'width: ' . $custom_width . 'px;'; } ?>">
        <a title="Home" rel="home" id="logo" href="<?php echo get_logo_link_url(); ?>" class="same-window">
          <?php echo get_navbar_logo() ?>
        </a>
      </li>
      <li class="nav-text site-name">
        <a title="Home" rel="home" href="<?php echo get_site_name_link_url(); ?>"><strong><?php bloginfo('name'); ?></strong></a>
      </li>
    </ul>
    <a title="Toggle mobile menu" id="menu-button" href="#" class="btn navbar-btn menu-button">
      <span class="hamburger">
        <span class="hamburger-bars"></span>
        <span class="sr-only">Toggle mobile menu</span>
      </span>
    </a>
    <?php print get_nav_primary_menu(); ?>
    <div class="container-fluid menu-box<?php if ( $topbar_has_action_toolbar ): ?> topbar-has-toolbar<?php endif; ?>">
      <?php if ( !$topbar_has_action_toolbar ): ?>
        <?php print get_nav_action_toolbar(); ?>
      <?php endif; ?>
    </div>
  </div>
  <div class="navbar navbar-header-region navbar-default <?php echo get_proud_logo_wrapper_class(); ?>">
    <div class="navbar-header"><div class="container">
      <ul class="logo-menu list-unstyled clearfix">
        <li class="h3">
          <a href="<?php echo get_logo_link_url(); ?>" title="Home" rel="home" id="header-logo" class="nav-logo same-window">
            <?php echo get_navbar_logo() ?>
          </a>
        </li>
        <li class="h3">
          <a href="<?php echo get_site_name_link_url(); ?>" title="Home" rel="home" class="navbar-brand nav-text site-name"><strong><?php bloginfo('name'); ?></strong></a>
        </li>
      </ul>
    </div></div>
  </div>
 </header>