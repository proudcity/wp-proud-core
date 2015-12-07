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


namespace Proud\Core;

require_once __DIR__ . '/modules/proud-libraries/libraries.php';

use Proud\Core\Libraries;

class Proudcore {

  // proud libraries
  public static $libaries;

  function __construct() {
    // Init on plugins loaded
    add_action('plugins_loaded', array($this, 'init'));
    // Load scripts from libraries
    add_action('wp_enqueue_scripts', array($this,'loadLibraries'));
  }

  public function init() {
    self::$libaries = new Libraries\ProudLibaries;
  }

  public function loadLibraries() {
    $path = plugins_url('includes/js/',__FILE__);
    wp_register_script('proud', $path . 'proud.js', ['jquery']);
    self::$libaries->loadLibraries();
  }
}

global $proudcore;
$proudcore = new Proudcore;
