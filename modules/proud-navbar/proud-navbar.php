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
 *  Helper function checks if navbar is transparent
 */
function proud_navbar_transparent() {
  static $transparent = null;
  if( $transparent === null ) {
    global $proudcore;
    $transparent = get_option( 'proud_navbar_transparent', '0' )
                && $proudcore::$layout->post_is_full_width() 
                && $proudcore::$layout->post_has_full_jumbotron_header();
  }
  return $transparent;
}

/**
 *  Active navbar, so edit body class
 */
function proud_navbar_body_class( $classes ) {
  $classes[] = 'proud-navbar-active';
  // Do we have the navbar transparent?
  if( proud_navbar_transparent() ) {
    $classes[] = 'proud-navbar-transparent';
  }
  return $classes;
}
add_filter( 'proud_body_class', 'proud_navbar_body_class' );

/**
 *  Helper prints logo
 */
function get_proud_logo_wrapper_class() {
  $hide = get_theme_mod( 'proud_logo_includes_title' );
  return $hide ? 'hide-site-name' : '';
}

/**
 *  Helper gets URL for navbar logo
 */
function get_logo_link_url() {
  static $logo_url = null;
  if( null === $logo_url ) {
    $logo_url = apply_filters( 'proud_navbar_logo_url', esc_url( home_url('/') ) );
  } 
  return $logo_url;
}

/**
 *  Helper gets URL for navbar site name
 */
function get_site_name_link_url() {
  static $site_name = null;
  if( null === $site_name ) {
    $site_name = apply_filters( 'proud_navbar_site_name_url', esc_url( home_url('/') ) );
  } 
  return $site_name;
}

/**
 *  Returns logo html
 */
function get_navbar_logo() {
  static $logo_markup  = null;
  if( null === $logo_markup ) {
    // Grab logo
    $logo =  Core\get_proud_logo();
    $image_meta = [];
    $custom_width = false;
    if($logo) {
      global $wpdb;
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
    }
    // Build user uploaded logo
    if( !empty( $image_meta ) ) {
      ob_start();
      Proud\Core\print_retina_image( $image_meta, false, true );
      $logo_markup = ob_get_contents();
      ob_end_clean();
    }
    // Build proud logo
    else {
      ob_start();
      Proud\Core\print_proud_logo( 'icon-white', [
          'class' => 'logo',
          'title' => 'Home',
          'alt' => 'Home'
      ] );
      $logo_markup = ob_get_contents();
      ob_end_clean();
    }
  }

  return $logo_markup;
}

/**
 * Prints primary menu
 */
function get_nav_action_toolbar() {
  $toolbar = '';
  $toolbar = apply_filters( 'proud_nav_action_toolbar', $toolbar );

  // No plugin overtaking, print template
  if( !$toolbar ) {
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/nav-toolbar.php';
    $toolbar = ob_get_contents();
    ob_end_clean();
  }

  return $toolbar;
}

/**
 * Prints primary menu
 */
function get_nav_primary_menu() {
  $menu = '';
  $menu = apply_filters( 'proud_nav_primary_menu', $menu );

  // No plugin overtaking, try primary
  if( !$menu && has_nav_menu( 'primary_navigation' ) ) {
    $menu_args = [ 
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
    ];
    // Dropdown menu?
    if( get_option( 'proud_navbar_dropdown', false ) ) {
      // Load Extendible
      // -----------------------
      if ( ! class_exists( 'wp_bootstrap_navwalker' ) ) {
        require_once( plugin_dir_path(__FILE__) . 'lib/wp_bootstrap_navwalker/wp_bootstrap_navwalker.php' );
      }
      $menu_args['walker'] = new wp_bootstrap_navwalker();
      $menu_args['menu_class'] .= ' navbar-depth';
      $menu_args['depth'] = '2';
    }
    ob_start();
    wp_nav_menu( $menu_args );
    $menu = ob_get_contents();
    ob_end_clean();
  }

  return $menu;  
}

/**
 *  Prints the proud navbar
 */
function print_proud_navbar() {
  $navbar = '';
  $navbar = apply_filters( 'proud_nav_navbar', $navbar );

  // No plugin overtaking, print template
  if( !$navbar ) {
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/navbar.php';
    $navbar = ob_get_contents();
    ob_end_clean();
  }
  // Should we add transparent mask?
  if( proud_navbar_transparent() ) {
    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/navbar-transparent.php';
    $navbar .= ob_get_contents();
    ob_end_clean();
  }
  echo $navbar;
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