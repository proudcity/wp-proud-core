<?php
/**
 * @author ProudCity
 */

function get_proud_logo() {
  $logo = get_theme_mod( 'proud_logo' );
  return $logo ? $logo : plugins_url( '/assets/images/logo-icon-white.png', __FILE__ );
}

/**
 *  Prints the proud navbar
 */
function print_proud_navbar() {
  ?>
  <div id="navbar-external" class="navbar navbar-default navbar-external navbar-fixed-bottom" role="navigation">
    <ul id="logo-menu" class="nav navbar-nav">
      <li class="nav-logo">
        <a title="Home" rel="home" id="logo" href="<?= esc_url(home_url('/')); ?>">
          <img class="logo" src="<?php echo esc_url( get_proud_logo() ); ?>" alt="Home" title="Home">
        </a>    
      </li>
      <li class="nav-text">
        <a title="Home" rel="home" href="<?= esc_url(home_url('/')); ?>"><strong><?php bloginfo('name'); ?></strong></a>
      </li>
    </ul>
    <div class="container-fluid menu-box">
      <div class="btn-toolbar pull-left" role="toolbar">
        <a data-proud-navbar="answers" href="#" class="btn navbar-btn faq-button"><i class="fa fa-question-circle"></i> Answers</a>
        <a data-proud-navbar="payments" href="#" class="btn navbar-btn payments-button"><i class="fa fa-credit-card"></i> Payments</a>
        <a data-proud-navbar="report" href="#" class="btn navbar-btn issue-button"><i class="fa fa-wrench"></i> Issues</a>
      </div>
      <div class="btn-toolbar pull-right" role="toolbar">
        <a id="menu-button" href="#" class="btn navbar-btn menu-button"><span class="hamburger">
          <span>toggle menu</span>
        </span></a>
        <a data-proud-navbar="search" href="#" class="btn navbar-btn search-btn"><i class="fa fa-search"></i> <span class="text sr-only">Search</span></a>
      </div>
    </div>
    <?php //do_action('get_theme_menu'); 
      if(has_nav_menu('primary_navigation')) {
        wp_nav_menu( [ 
          'theme_location'    => 'primary_navigation',
          'container'         => 'div',
          'container_class'   => 'below',
          'container_id'      => '',
          'menu_class'        => 'nav navbar-nav',
          'menu_id'           => 'main-menu',
          'echo'              => true,
          'fallback_cb'       => 'wp_page_menu',
          'before'            => '',
          'after'             => '',
          'link_before'       => '',
          'link_after'        => '',
          'items_wrap'        => '<ul id="%1$s" class="%2$s">%3$s</ul>',
          'depth'             => 1,
          'walker'            => ''
        ] );
      }
    ?>
  </div>
  <div class="navbar navbar-header-region navbar-default">
    <div class="navbar-header"><div class="container">
      <h3>
        <a href="<?= esc_url(home_url('/')); ?>" title="Home" rel="home" id="logo"><img style="height:38px;" class="logo pull-left" src="<?php echo get_proud_logo() ?>" alt="Home" title="Home"></a>
        <a href="<?= esc_url(home_url('/')); ?>" title="Home" rel="home" class="navbar-brand"><strong><?php bloginfo('name'); ?></strong></a>
      </h3>
    </div></div>
  </div>
  <?php
}

add_action( 'get_header', 'print_proud_navbar' );

function enqueue_proud_navbar_frontend() {
  $path = plugins_url('assets/js/',__FILE__);
  wp_register_script('proud-navbar/js', $path . 'proud-navbar.js', ['jquery', 'proud']);
  wp_enqueue_script('proud-navbar/js');
}

add_action( 'wp_enqueue_scripts', 'enqueue_proud_navbar_frontend' );

/**
 *  Prints the proud navbar
 */
function print_proud_navbar_footer() {
  ?>
  <div id="overlay-311" class="proud-overlay proud-overlay-left">
    <div class="container">
      <?php 
        // Print 311 in overlay? 
        do_action('proud_navbar_overlay_311'); 
      ?>
    </div>
    <a id="overlay-311-close" href="#" class="proud-overlay-close close-311"><i class="fa fa-times fa-2x"></i><span class="sr-only">Close window</span></a>
  </div>
  <div id="overlay-search" class="proud-overlay proud-overlay-right">
    <div class="container">
      <?php 
        // Print search in overlay?
        do_action('proud_navbar_overlay_search');
      ?>
    </div>
    <a id="overlay-search-close" href="#" class="proud-overlay-close close-search"><i class="fa fa-times fa-2x"></i><span class="sr-only">Close window</span></a>
  </div>
  <?php
}

add_action( 'proud_footer', 'print_proud_navbar_footer' );