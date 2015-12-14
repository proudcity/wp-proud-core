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

require_once plugin_dir_path(__FILE__) . '/modules/proud-libraries/libraries.class.php';
require_once plugin_dir_path(__FILE__) . '/modules/proud-widget/proud-widgets.php';

use Proud\Core\ProudLibraries as ProudLibraries;

class Proudcore {

  // proud libraries
  public static $libraries;
  // js settings
  public static $jsSettings = [];

  function __construct() {
    // Init on plugins loaded
    add_action('plugins_loaded', array($this, 'init'));
    // Load scripts from libraries
    add_action('wp_enqueue_scripts', array($this,'loadLibraries'));
    // Load admin scripts from libraries
    add_action('admin_enqueue_scripts', array($this,'loadAdminLibraries'));
    // Add Javascript settings
    add_action('proud_settings', array($this,'printJsSettings'));

  }

  public function init() {
    self::$libraries = new ProudLibaries;
  }

  // Load common libraries
  public function loadLibraries() {
    $path = plugins_url('includes/js/',__FILE__);
    wp_register_script('proud', $path . 'proud.js', ['jquery']);
    self::$libraries->loadLibraries();
  }

    // Load common libraries
  public function loadAdminLibraries() {
    $path = plugins_url('includes/js/',__FILE__);
    wp_register_script('proud', $path . 'proud.js', ['jquery']);
    self::$libraries->loadLibraries('true');
  }

  // Add js settings to Proud js var
  public function addJsSettings($settings) {
    self::$jsSettings += $settings;
  }

  // Prints out Proud js settings
  public function printJsSettings() {
    ?>
    <script>
      jQuery.extend(Proud.settings, <?php echo json_encode(self::$jsSettings); ?>);
    </script>
    <?php
  }
}

global $proudcore;
$proudcore = new Proudcore;
