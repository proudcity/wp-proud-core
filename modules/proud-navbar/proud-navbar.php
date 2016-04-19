<?php
/**
 * @author ProudCity
 */

use Proud\Core;

// Resgister logo sizes
function proud_navbar_logo_size() {
  add_image_size( 'proud-logo', 140, 64 );
  add_image_size( 'proud-logo-retina', 380, 128 );
  add_image_size( 'proud-logo-wide', 300, 64 );
  add_image_size( 'proud-logo-wide-retina', 600, 128 );
}

add_action( 'init', 'proud_navbar_logo_size' );

/**
 *  Active navbar, so edit body class
 */
function proud_navbar_body_class( $classes ) {
  $classes[] = 'proud-navbar-active';
  return $classes;
}
add_filter( 'proud_body_class', 'proud_navbar_body_class' );

/**
 *  Helper prints logo
 */
function get_proud_logo() {
  $logo = get_theme_mod( 'proud_logo' );
  return $logo ? $logo : plugins_url( '/assets/images/logo-icon-white.png', __FILE__ );
}

/**
 *  Helper prints logo
 */
function get_proud_logo_wrapper_class() {
  $hide = get_theme_mod( 'proud_logo_includes_title' );
  return $hide ? 'hide-site-name' : '';
}

/**
 *  Prints the proud navbar
 */
function print_proud_navbar() {

  // Grab logo
  $logo =  get_proud_logo();
  global $wpdb;
  $image_meta = [];
  $custom_width = false;
  // Try to grab ID
  $media_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid='%s';", $logo ) );
  // Build responsive image, get width
  if( $media_id ) {
    // Build responsive meta
    if( get_theme_mod( 'proud_logo_includes_title' ) ) {
      $image_meta = Core\build_retina_image_meta( $media_id, 'proud-logo-wide', 'proud-logo-wide-retina' );
    }
    else {
      $image_meta = Core\build_retina_image_meta( $media_id, 'proud-logo', 'proud-logo-retina' );
    }
    $image_meta['meta']['image_meta']['alt'] = 'Home';
    $image_meta['meta']['image_meta']['title'] = 'Home';
    $image_meta['meta']['image_meta']['class'] = 'logo';
    if( !get_theme_mod( 'proud_logo_includes_title' ) ) {
      // try to maximize height @ 64px (and not divide by zero)
      // 64 = max height, 32 = current horizontal padding 
      $custom_width = ($image_meta['meta']['height'] > 0) ? $image_meta['meta']['width']/$image_meta['meta']['height'] * 64 + 32 : 140;
      // 140 max width 
      $custom_width = $custom_width < 140 ? $custom_width : 140;
    }
  }
  
  ?>
  <div id="navbar-external" class="navbar navbar-default navbar-external navbar-fixed-bottom <?php echo get_proud_logo_wrapper_class(); ?>" role="navigation">
    <ul id="logo-menu" class="nav navbar-nav">
      <li class="nav-logo" style="<?php if( $custom_width ) { echo 'width: ' . $custom_width . 'px;'; } ?>">
        <a title="Home" rel="home" id="logo" href="<?php echo esc_url(home_url('/')); ?>">
          <?php if( !empty( $image_meta ) ): ?>
            <?php echo Core\print_retina_image( $image_meta, false, true ); ?>
          <?php else: ?>
            <img class="logo" src="<?php echo esc_url( $logo ); ?>" alt="Home" title="Home">
          <?php endif; ?>
        </a>    
      </li>
      <li class="nav-text site-name">
        <a title="Home" rel="home" href="<?php echo esc_url(home_url('/')); ?>"><strong><?php bloginfo('name'); ?></strong></a>
      </li>
    </ul>
    <div class="container-fluid menu-box">
      <div class="btn-toolbar pull-left" role="toolbar">
        <a data-proud-navbar="answers" href="#" class="btn navbar-btn faq-button"><i class="fa fa-question-circle"></i> Answers</a>
        <a data-proud-navbar="payments" href="#" class="btn navbar-btn payments-button"><i class="fa fa-credit-card"></i> Payments</a>
        <a data-proud-navbar="report" <?php if($link = get_option('311_link_create')): ?>data-click-external="true" href="<?php echo $link ?>"<?php else: ?>href="#"<?php endif; ?> class="btn navbar-btn issue-button"><i class="fa fa-wrench"></i> Issues</a>
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
  <div class="navbar navbar-header-region navbar-default <?php echo get_proud_logo_wrapper_class(); ?>">
    <div class="navbar-header"><div class="container">
      <h3 class="clearfix">
        <a href="<?php echo esc_url(home_url('/')); ?>" title="Home" rel="home" id="header-logo" class="nav-logo">
          <?php if( !empty( $image_meta ) ): ?>
            <?php echo Core\print_retina_image( $image_meta, false, true ); ?>
          <?php else: ?>
            <img class="logo" src="<?php echo esc_url( $logo ); ?>" alt="Home" title="Home">
          <?php endif; ?>
        </a>
        <a href="<?php echo esc_url(home_url('/')); ?>" title="Home" rel="home" class="navbar-brand nav-text site-name"><strong><?php bloginfo('name'); ?></strong></a>
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
 *  Prints the proud footer overlay
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