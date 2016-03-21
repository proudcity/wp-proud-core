<?php
/*
Plugin Name:        Proud Core
Plugin URI:         http://getproudcity.com
Description:        ProudCity distribution
Version:            1.0
Author:             ProudCity
Author URI:         http://getproudcity.com

License:            Affero GPL v3
*/


namespace Proud\Core;

// Load Extendibles
// -----------------------
require_once plugin_dir_path(__FILE__) . 'proud-plugin.class.php';

// Load Helpers
// -----------------------
require_once plugin_dir_path(__FILE__) . 'proud-helpers.php';

// Load Modules
// -----------------------
require_once plugin_dir_path(__FILE__) . 'modules/proud-libraries/libraries.class.php';
require_once plugin_dir_path(__FILE__) . 'modules/proud-form/proud-form.php';
require_once plugin_dir_path(__FILE__) . 'modules/proud-widget/proud-widgets.php';
require_once plugin_dir_path(__FILE__) . 'modules/proud-navbar/proud-navbar.php';
require_once plugin_dir_path(__FILE__) . 'modules/proud-layout/proud-layout.php';
require_once plugin_dir_path(__FILE__) . 'modules/proud-teasers/proud-teasers.php';
//require_once plugin_dir_path(__FILE__) . 'modules/wr-pagebuilder/proud-addons.php';
require_once plugin_dir_path(__FILE__) . 'modules/proud-bar/proud-bar.php';
require_once plugin_dir_path(__FILE__) . 'modules/proud-analytics/proud-analytics.php';

// Override plugins
//-------------------------
require_once plugin_dir_path(__FILE__) . 'plugin_override/so-pagebuilder/proud-so-pagebuilder.php';
require_once plugin_dir_path(__FILE__) . 'plugin_override/wp-job-manager/proud-wp-job-manager.php';
require_once plugin_dir_path(__FILE__) . 'plugin_override/gravityforms/proud-gravityforms.php';
require_once plugin_dir_path(__FILE__) . 'plugin_override/events-manager/proud-events-manager.php';

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
    $this->hook('admin_footer', 'printJsSettings');
    // Get the $pageInfo global var for submenu logic
    $this->hook('template_redirect',  'getPageInfo');
    // Set up image styles
    $this->hook( 'after_setup_theme', 'addImageSizes' );

    // Shortcodes
    add_shortcode( 'sitename', array($this, 'shortcode_sitename') );
    add_shortcode( 'slogan', array($this, 'shortcode_slogan') );
    add_shortcode( 'title', array($this, 'shortcode_title') );
    add_shortcode( 'featured-image', array($this, 'shortcode_featured_image') );

    // -- ReST tweaks
    $this->hook('init',  'restPostSupport');
    $this->hook('init',  'restTaxonomySupport');
  }

  public function init() {
    $this->addJsSettings(array('global' => array(
      'location' => array(
        'city' => get_option( 'city', 'Huntsville' ),
        'state' => get_option( 'state', 'Alabama' ),
        'lat' => (float) get_option( 'lat', 34.7303688 ),
        'lng' => (float) get_option( 'lng', -86.5861037 ),
      ),
      'external_link_window' => get_option( 'external_link_window', 1 ) == 1,
      'mapbox' => array(
        'token' => get_option( 'mapbox_token', 'pk.eyJ1IjoiYWxiYXRyb3NzZGlnaXRhbCIsImEiOiI1cVUxbUxVIn0.SqKOVeohLfY0vfShalVDUw' ),
        'map' => get_option( 'mapbox_map', 'albatrossdigital.lpkdpcjb' )
      )
    )));
    self::$libraries = new ProudLibaries;
    self::$layout = new \ProudLayout;
  }

  // Load common libraries
  public function loadLibraries() {
    $path = plugins_url('assets/js/',__FILE__);
    wp_register_script('proud', $path . 'proud.js', ['jquery']);
    wp_enqueue_script('proud');
    self::$libraries->loadLibraries();
  }
 
  // Load common libraries
  public function loadAdminLibraries($hi) {
    $path = plugins_url('assets/js/',__FILE__);
    wp_register_script('proud', $path . 'proud.js', ['jquery']);
    wp_enqueue_script('proud');
    self::$libraries->loadLibraries('true');
  }


  // Recusive array merging from 
  // https://api.drupal.org/api/drupal/assets%21bootstrap.inc/function/drupal_array_merge_deep_array/7
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

  function addImageSizes() {
    add_image_size( 'card-thumb', 300, 170, true );
  }

  // If this post is a page, get the menu information
  public function getPageInfo() {
    if ( is_page( ) ) {
      global $pageInfo;
      global $wpdb;
      if ( empty( $pageInfo ) ) {
        // @todo: make this more elegant / cached
        // @todo: this should be in proud core (in some kind of hook_init)
        $row = $wpdb->get_row( $wpdb->prepare( '
          SELECT post_id, slug FROM wp_postmeta pm
          LEFT JOIN wp_term_relationships r ON pm.post_id = r.object_id
          LEFT JOIN wp_terms t ON r.term_taxonomy_id = t.term_id
          WHERE pm.meta_key = %s
          AND pm.meta_value = %d;', 
        '_menu_item_object_id', get_the_ID() ) );
        
        if( !empty($row) ) {
          $pageInfo['menu'] = $row->slug;

          if ( 'primary-links' === $row->slug ) {
            $pageInfo['parent_link'] = get_post_meta ( $row->post_id, '_menu_item_menu_item_parent', true );
            if (!empty( $pageInfo['parent_link'] ) && $pageInfo['parent_link'] ) {
              $pageInfo['parent_post'] = get_post_meta ( $pageInfo['parent_link'], '_menu_item_object_id', true );
            }
          }
          else {
            $pageInfo['parent_post'] = $wpdb->get_var( $wpdb->prepare( '
              SELECT post_id FROM wp_postmeta WHERE meta_key = %s AND meta_value = %s',
            'post_menu', $pageInfo['menu'] ) );
            if (!empty($pageInfo['parent_post'])) {
              $pageInfo['parent_post_type'] = 'agency';
            }
          }
        }
      }
    }
  }


  // Add shortcodees
  // [sitename]
  function shortcode_sitename( ){
    return get_bloginfo('title');
  }
  // [slogan]
  function shortcode_slogan( ){
    return get_bloginfo('description');
  }
  // [title] (page title)
  function shortcode_title( ){
    return get_the_title();
  }
  // [featured-image] (page title)
  function shortcode_featured_image( ){
    return get_the_post_thumbnail_url(get_the_ID());
  }

}


global $proudcore;
$proudcore = new Proudcore;