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

define('PROUDCITY_API', 'https://rest.proudcity.com/v1');
//define('PROUDCITY_API', 'http://localhost:4000/v1');
define('MY_PROUDCITY', 'https://my.proudcity.com');
define( 'PRODCITYCITY_API_URL', 'https://city-api.proudcity.com/v1' );

// Load Extendibles
// -----------------------
require_once plugin_dir_path(__FILE__) . 'proud-plugin.class.php';

// Load Helpers
// -----------------------
require_once plugin_dir_path(__FILE__) . 'proud-helpers.php';

// Load Modules
// -----------------------
require_once plugin_dir_path(__FILE__) . 'modules/proud-libraries/libraries.class.php';
require_once plugin_dir_path(__FILE__) . 'modules/proud-authentication/proud-authentication.php';
require_once plugin_dir_path(__FILE__) . 'modules/proud-accessibility/proud-accessibility.php';
require_once plugin_dir_path(__FILE__) . 'modules/proud-form/proud-form.php';
require_once plugin_dir_path(__FILE__) . 'modules/proud-menu/proud-menu.php';
require_once plugin_dir_path(__FILE__) . 'modules/proud-widget/proud-widgets.php';
require_once plugin_dir_path(__FILE__) . 'modules/proud-navbar/proud-navbar.php';
require_once plugin_dir_path(__FILE__) . 'modules/proud-layout/proud-layout.php';
require_once plugin_dir_path(__FILE__) . 'modules/proud-teasers/proud-teasers.php';
require_once plugin_dir_path(__FILE__) . 'modules/proud-pagetitle/proud-pagetitle.php';
//require_once plugin_dir_path(__FILE__) . 'modules/wr-pagebuilder/proud-addons.php';
require_once plugin_dir_path(__FILE__) . 'modules/proud-bar/proud-bar.php';
require_once plugin_dir_path(__FILE__) . 'modules/proud-analytics/proud-analytics.php';
require_once plugin_dir_path(__FILE__) . 'modules/proud-sitemap/proud-sitemap.php';


// Override plugins
//-------------------------
require_once plugin_dir_path(__FILE__) . 'plugin_override/so-pagebuilder/proud-so-pagebuilder.php';
require_once plugin_dir_path(__FILE__) . 'plugin_override/wp-job-manager/proud-wp-job-manager.php';
require_once plugin_dir_path(__FILE__) . 'plugin_override/gravityforms/proud-gravityforms.php';
require_once plugin_dir_path(__FILE__) . 'plugin_override/events-manager/proud-events-manager.php';
require_once plugin_dir_path(__FILE__) . 'plugin_override/e-signature/proud-e-signature.php';
require_once plugin_dir_path(__FILE__) . 'plugin_override/gravityformsstripe/proud-gravityformsstripe.php';
require_once plugin_dir_path(__FILE__) . 'plugin_override/buddydrive/proud-buddydrive.php';
require_once plugin_dir_path(__FILE__) . 'plugin_override/wordpress-seo/proud-wordpress-seo.php';

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
    // Add powered by content
    $this->hook( 'proud_footer_after', 'poweredby' );
    // Modify max size for responsive images
    add_filter( 'max_srcset_image_width', array( $this, 'max_srcset_width' ), 10, 2 );
    // Add our responsive options if applicable
    add_filter( 'wp_calculate_image_srcset', array( $this, 'calculate_image_srcset' ), 10, 5 );
    // Add to allowed mimetypes
    add_filter('upload_mimes', array( $this, 'allowed_mimetypes'), 1, 1);

    // Shortcodes
    add_shortcode( 'sitename', array($this, 'shortcode_sitename') );
    add_shortcode( 'slogan', array($this, 'shortcode_slogan') );
    add_shortcode( 'title', array($this, 'shortcode_title') );
    add_shortcode( 'featured-image', array($this, 'shortcode_featured_image') );

    // Set cookie lifetime
    add_filter('auth_cookie_expiration', array($this, 'cookieExpiration'), 99, 3);

    // -- ReST tweaks
    $this->hook('init',  'restPostSupport');
    $this->hook('init',  'restTaxonomySupport');

    // reorder post types
    add_action( 'pre_get_posts', array( $this, 'restPostOrder' ) );
  }

  public function init() {
    $url = get_site_url();
    $this->addJsSettings(array('global' => array(
      'proudcity_api' => PROUDCITY_API,
      'proudcity_dashboard' => MY_PROUDCITY,
      'proudcity_site_id' => APP != 'APP' ? APP : getenv('APP'), // @todo: remove this once we push live
      'url' => $url,
      'location' => array(
        'city' => get_option( 'city', 'Huntsville' ),
        'state' => get_option( 'state', 'Alabama' ),
        'lat' => (float) get_option( 'lat', 34.7303688 ),
        'lng' => (float) get_option( 'lng', -86.5861037 ),
        'bounds' => get_option( 'bounds', null ),
        'code' => str_replace(' ', '_', get_option( 'city', 'Huntsville' ) . ', ' . str_replace(' ', '_', get_option( 'state', 'Alabama' )) ),
      ),
      'external_link_window' => get_option( 'external_link_window', 1 ) == 1,
      'mapbox' => array(
        'token' => get_option( 'mapbox_token', '' ),
        'map' => get_option( 'mapbox_map', '' ),
      ),
      'google_key' => get_option( 'google_api_key', '' ),
    )));
    self::$libraries = new ProudLibaries;
    self::$layout = new \ProudLayout;

    // Remove unecessary actions
    // from http://cubiq.org/clean-up-and-optimize-wordpress-for-your-next-theme
    remove_action( 'wp_head', 'rsd_link' );
    remove_action( 'wp_head', 'wlwmanifest_link' );
    remove_action( 'wp_head', 'wp_shortlink_wp_head' );
    remove_action( 'wp_head', 'wp_generator' );
    add_filter('the_generator', '__return_false');
    remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10 );
    // Emojis
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
  }

  // Load common libraries
  public function loadLibraries() {
    $path = plugins_url('assets/js/',__FILE__);
    wp_register_script('proud', $path . 'proud.js', ['jquery']);
    wp_enqueue_script('proud');
    self::$libraries->loadLibraries();
  }
 
  // Load common libraries
  public function loadAdminLibraries( $hook ) {
    $path = plugins_url('assets/js/',__FILE__);
    wp_register_script('proud', $path . 'proud.js', ['jquery']);
    wp_enqueue_script('proud');
    self::$libraries->loadLibraries('true');
  }

  // Add js settings to Proud js var
  public function addJsSettings($settings) {
    self::$jsSettings = array_merge_deep_array([self::$jsSettings, $settings]);
  }

  // Get js settings from Proud js var
  public function getJsSettings() {
    return self::$jsSettings;
  }

  // Prints out Proud js settings
  public function printJsSettings() {
    ?>
    <script>
      jQuery.extend(Proud.settings, <?php echo json_encode(self::$jsSettings); ?>);
    </script>
    <?php
  }


  // Set expiration time
  // https://stackoverflow.com/a/21356746
  function cookieExpiration($seconds, $user_id, $remember){
    // 14 days
    $expiration = 14*24*60*60;
    //http://en.wikipedia.org/wiki/Year_2038_problem
    if ( PHP_INT_MAX - time() < $expiration ) {
        //Fix to a little bit earlier!
        $expiration =  PHP_INT_MAX - time() - 5;
    }
    return $expiration;
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

  // Add the draggable post order to REST API endpoints
  public function restPostOrder( $wp_query ) {
    global $wp;
    $q = add_query_arg(array(),$wp->request);
    if ( 
      $q === 'wp-json/wp/v2/issues' ||
      $q === 'wp-json/wp/v2/questions' ||
      $q === 'wp-json/wp/v2/payments'
    ) {
      $wp_query->set( 'orderby', 'menu_order' );
      $wp_query->set( 'order', 'ASC' );
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

  public function addImageSizes( $sizes ) {
    // Post sizes
    add_image_size( 'card-thumb', 300, 170, true );
    add_image_size( 'featured-teaser', 500, 250, true );
    add_image_size( 'full-screen', 2000, 1333, true );
  }

  public function poweredby() {
    ?>
      <div class="powered-by-footer">
        <?php the_widget( 'PoweredByWidget', [], [] ) ?>
      </div>
    <?php
  }

  // If we're rendering with full-screen, add our values
  public function max_srcset_width( $max_width, $size_array ) {
    // We're using full-screen
    if( $size_array[0] === 2000 ) {
      return 2001;
    }
    // Otherwise respect the wordpress max
    return $max_width;
  }

  // Add our other responsive stlyes if needed
  public function calculate_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {

    if (empty( $sources )) {
      return $sources;
    }

    // Builds full responsive array
    // @TODO this might be outdated since wp-stateless 2
    // We have only 1 source @ our full-screen size add medium, medium_large, large
    // See:
    // https://developer.wordpress.org/reference/functions/wp_calculate_image_srcset/
    // https://www.developersq.com/add-custom-srcset-values-for-responsive-images-wordpress/
    if (count( $sources ) === 1 ) {
      $sizes = [];
      $source_size = key( $sources );
      if ( $source_size >= 1024 ) {
        $sizes = ['large', 'medium_large', 'medium'];
      }
      else if ( $source_size >= 768 ) {
        $sizes = ['medium_large', 'medium'];
      }
      // No need to process
      if ( empty( $sizes ) ) {
        return;
      }
      // Try to grab stateless meta options
      if ( preg_match('/\/\/storage\.googleapis\.com/i', $image_src ) && isset( $sources[$source_size]['url'] ) ) {
        $sources[$source_size]['url'] = $image_src;
        $media_meta_full = wp_get_attachment_metadata( $attachment_id );
      }
      // image base name  
      $image_basename = wp_basename( $image_meta['file'] );
      // upload directory info array
      $upload_dir_info_arr = wp_get_upload_dir();
      // base url of upload directory
      $baseurl = $upload_dir_info_arr['baseurl'];
      
      // Uploads are (or have been) in year/month sub-directories.
      if ( $image_basename !== $image_meta['file'] ) {
        $dirname = dirname( $image_meta['file'] );
        
        if ( $dirname !== '.' ) {
          $image_baseurl = trailingslashit( trailingslashit( $baseurl ) . $dirname ); 
        }
      }
      // Full meta information
      foreach ( $sizes as $size ) {
        // check whether our custom image size exists in image meta 
        if ( !empty( $image_meta['sizes'][$size] ) ){
          $url = '';
          
          // We have WP stateless option
          if ( isset($media_meta_full) && !empty( $media_meta_full['sizes'][$size]['gs_link'] ) ) {
            $url = $media_meta_full['sizes'][$size]['gs_link'];
          }
          else if ( isset( $image_baseurl ) ) {
            $url = $image_baseurl .  $image_meta['sizes'][$size]['file'];
          }
          // add source value to create srcset
          if ( $url ) {
            $sources[ $image_meta['sizes'][$size]['width'] ] = array(
              'url'        => $url,
              'descriptor' => 'w',
              'value'      => $image_meta['sizes'][$size]['width'],
            );
          }
        }
      }
    }

    return $sources;
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
          WHERE slug IS NOT NULL
          AND pm.meta_key = %s
          AND pm.meta_value = %d;', 
        '_menu_item_object_id', get_the_ID() ) );

        if( !empty($row) ) {
          $pageInfo['menu'] = $row->slug;

          if ( 'primary-links' === $row->slug ) {
            $pageInfo['parent_link'] = get_post_meta ( $row->post_id, '_menu_item_menu_item_parent', true );
            if ( isset( $pageInfo['parent_link'] ) &&  $pageInfo['parent_link'] ) {
              $pageInfo['parent_post'] = get_post_meta ( $pageInfo['parent_link'], '_menu_item_object_id', true );
              $pageInfo['parent_post_type'] = get_post_meta ( $pageInfo['parent_link'], '_menu_item_object', true );
            }
          }
          else {
            $pageInfo['parent_post'] = $wpdb->get_var( $wpdb->prepare( '
              SELECT post_id FROM wp_postmeta WHERE meta_key = %s AND meta_value = %s',
            'post_menu', $pageInfo['menu'] ) );
            if (!empty( $pageInfo['parent_post'] )) {
              $pageInfo['parent_post_type'] = 'agency';
            }
          }
        }
      }
    }
  }

  // Add the to the allowed mimetypes for user file uploads
  function allowed_mimetypes($mime_types){
    // We're still unsure what the mimetype is for json files, so we use both
    $mime_types['json'] = 'text/plain';
    return $mime_types;
  }

  // Add shortcodees
  // [sitename]
  public function shortcode_sitename( ){
    return get_bloginfo('title');
  }
  // [slogan]
  public function shortcode_slogan( ){
    return get_bloginfo('description');
  }
  // [title] (page title)
  public function shortcode_title( ){
    return get_the_title();
  }
  // [featured-image] (page title)
  public function shortcode_featured_image( ){
    return get_the_post_thumbnail_url(get_the_ID());
  }

}


global $proudcore;
$proudcore = new Proudcore;