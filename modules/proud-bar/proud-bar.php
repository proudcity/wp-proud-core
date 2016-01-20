<?php
/*
Plugin Name:        Proud Core
Plugin URI:         http://getproudcity.com
Description:        ProudCity distribution
Version:            1.0.0
Author:             ProudCity
Author URI:         http://getproudcity.com

License:            MIT License
License URI:        http://opensource.org/licenses/MIT
*/


namespace Proud\Core\ProudBar;

// Load Extendibles
// -----------------------
require_once plugin_dir_path(__FILE__) . '../../proud-plugin.class.php';

class ProudBar extends \ProudPlugin {

  function __construct() {

    parent::__construct( array(
      'textdomain'     => 'wp-proud-core',
      'plugin_path'    => __FILE__,
    ) );

    // Add blue "demo" bar to footer @todo: should this be moved? @todo: make this work
    $this->hook( 'wp_footer',  'proud_bar' );
    $this->hook( 'admin_footer', 'proud_bar' );

    // This is needed for the demo angular app
    if ( 'example' === get_option('proud_stage', '') ) {
      $this->hook('init', 'allow_origin');
    }
  }


  // Add blue "demo" bar to footer @todo: make this work
  function proud_bar() {
    $stage = get_option('proud_stage', '');
    if ('beta' === $stage || 'demo' === $stage || 'example' === $stage) {
      require_once plugin_dir_path(__FILE__) . 'templates/proud-bar.php';
    }
  }

  // This is needed for the demo angular app
  function allow_origin() {
    header("Access-Control-Allow-Origin: *");
  }

}

new ProudBar;