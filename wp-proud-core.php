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

// Load Extendibles
// -----------------------
require_once plugin_dir_path(__FILE__) . 'proud-plugin.class.php';

// Load Helpers
// -----------------------
require_once plugin_dir_path(__FILE__) . 'proud-helpers.php';
require_once plugin_dir_path(__FILE__) . 'proud-formhelper.class.php';

// Load Modules
// -----------------------
require_once plugin_dir_path(__FILE__) . '/modules/proud-libraries/libraries.class.php';
require_once plugin_dir_path(__FILE__) . '/modules/proud-widget/proud-widgets.php';
require_once plugin_dir_path(__FILE__) . '/modules/proud-addons/proud-addons.php';
require_once plugin_dir_path(__FILE__) . '/modules/proud-navbar/proud-navbar.php';
require_once plugin_dir_path(__FILE__) . '/modules/proud-layout/proud-layout.php';
require_once plugin_dir_path(__FILE__) . '/modules/proud-teasers/proud-teasers.php';

use Proud\Core\ProudLibraries as ProudLibraries;

class Proudcore extends \ProudPlugin {

  // proud libraries
  public static $libraries;
  // proud layout
  public static $layout;
  // js settings
  public static $jsSettings = [];

  function __construct() {

    parent::__construct( array(
      'textdomain'     => 'wp-proud-core',
      'plugin_path'    => __FILE__,
    ) );

    // Init on plugins loaded
    $this->hook('plugins_loaded', 'init');
    // Load scripts from libraries
    $this->hook('wp_enqueue_scripts', 'loadLibraries');
    // Load admin scripts from libraries
    $this->hook('admin_enqueue_scripts', 'loadAdminLibraries');
    // Add Javascript settings
    $this->hook('proud_settings', 'printJsSettings');

    // -- ReST tweaks
    $this->hook('init',  'restPostSupport');
    $this->hook('init',  'restTaxonomySupport');

    // -- Hacks
    // Hide admin fields
    $this->hook('init', 'removePostAdminFields');

  }

  public function init() {
    $this->addJsSettings(array('global' => array(
      'location' => array(
        'city' => get_option( 'city', 'Huntsville' ),
        'state' => get_option( 'state', 'Alabama' ),
        'lat' => (float) get_option( 'lat', 34.7303688 ),
        'lng' => (float) get_option( 'lng', -86.5861037 ),
      ),
    )));
    self::$libraries = new ProudLibaries;
    self::$layout = new \ProudLayout;
  }

  // Load common libraries
  public function loadLibraries() {
    $path = plugins_url('includes/js/',__FILE__);
    wp_register_script('proud', $path . 'proud.js', ['jquery']);
    wp_enqueue_script('proud');
    self::$libraries->loadLibraries();
  }

    // Load common libraries
  public function loadAdminLibraries($hi) {
    $path = plugins_url('includes/js/',__FILE__);
    wp_register_script('proud', $path . 'proud.js', ['jquery']);
    wp_enqueue_script('proud');
    self::$libraries->loadLibraries('true');
  }

  // Recusive array merging from 
  // https://api.drupal.org/api/drupal/includes%21bootstrap.inc/function/drupal_array_merge_deep_array/7
  public function arrayMergeDeepArray($arrays) {
    $result = array();

    foreach ($arrays as $array) {
      foreach ($array as $key => $value) {
        // Renumber integer keys as array_merge_recursive() does. Note that PHP
        // automatically converts array keys that are integer strings (e.g., '1')
        // to integers.
        if (is_integer($key)) {
          $result[] = $value;
        }
        // Recurse when both values are arrays.
        elseif (isset($result[$key]) && is_array($result[$key]) && is_array($value)) {
          $result[$key] = $this->arrayMergeDeepArray(array($result[$key], $value));
        }
        // Otherwise, use the latter value, overriding any previous value.
        else {
          $result[$key] = $value;
        }
      }
    }

    return $result;
  }

  // Add js settings to Proud js var
  public function addJsSettings($settings) {
    self::$jsSettings = $this->arrayMergeDeepArray([self::$jsSettings, $settings]);
  }

  // Prints out Proud js settings
  public function printJsSettings() {
    ?>
    <script>
      jQuery.extend(Proud.settings, <?php echo json_encode(self::$jsSettings); ?>);
    </script>
    <?php
  }

  // Remove extra fields on the admin pages
  public function removePostAdminFields() {
    remove_post_type_support( 'question', 'author' );
    remove_post_type_support( 'question', 'comments' );
    remove_post_type_support( 'question', 'custom-fields' );
  }

  // Add REST API support to an already registered post types
  public function restPostSupport() {
    global $wp_post_types;
    $types = array('event', 'question', 'job_listing');
    foreach ($types as $post_type_name) {
      if( isset( $wp_post_types[ $post_type_name ] ) ) {
          $wp_post_types[$post_type_name]->show_in_rest = true;
          $wp_post_types[$post_type_name]->rest_base = $post_type_name.'s';
          $wp_post_types[$post_type_name]->rest_controller_class = 'WP_REST_Posts_Controller';
      }
    }
  }

  // Add REST API support to an already registered taxonomy.
  public function restTaxonomySupport() {
    global $wp_taxonomies;
    $taxonomy_names = array('event-categories', 'event-tags', 'faq-topic', 'faq-tags', 'job_listing_type');
    foreach ($taxonomy_names as $taxonomy_name) {
      if ( isset( $wp_taxonomies[ $taxonomy_name ] ) ) {
          $wp_taxonomies[ $taxonomy_name ]->show_in_rest = true;
          $wp_taxonomies[ $taxonomy_name ]->rest_base = $taxonomy_name;
          $wp_taxonomies[ $taxonomy_name ]->rest_controller_class = 'WP_REST_Terms_Controller';
      }
    }
  }

}


global $proudcore;
$proudcore = new Proudcore;
