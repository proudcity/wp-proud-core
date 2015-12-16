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
require_once plugin_dir_path(__FILE__) . '/modules/proud-navbar/proud-navbar.php';

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

    // -- ReST tweaks
    add_action( 'init', array($this, 'restPostSupport') );
    add_action( 'init', array($this, 'restTaxonomySupport') );

    // -- Hacks
    // Hide admin fields
    add_action( 'init', array($this,'removePostAdminFields'));

  }

  public function init() {
    self::$libraries = new ProudLibaries;
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
