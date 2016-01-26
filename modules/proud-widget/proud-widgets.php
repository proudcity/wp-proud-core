<?php
/**
 * @author ProudCity
 */

require_once plugin_dir_path(__FILE__) . 'widget-base.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/icon-link/icon-link-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/teaser-list/teaser-list-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/font-size/font-size-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/share-links/share-links-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/google-translate/google-translate-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/footer-info/footer-info-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/main-menu-list/main-menu-list-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/icon-set/icon-set.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/jumbotron-header/jumbotron-header.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/submenu/submenu-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/page-title/page-title-widget.class.php';



/**
 * Converts shortcode into widget if its a proud widget
 */
function proud_shortcode($atts) {

  global $wp_widget_factory;

  extract(shortcode_atts(array(
      'widget_name' => FALSE
  ), $atts));

  $widget_name = wp_specialchars($widget_name);

  if ( !is_a( $wp_widget_factory->widgets[$widget_name], 'ProudWidget') ) {
    // Try to get instance info
    $instance = !empty($instance) ? str_ireplace("&amp;", '&' ,$instance) : [];
    ob_start();
    // Print widget
    the_widget($widget_name, $instance, array('widget_id'=>'arbitrary-instance-' . strtolower($widget_name) ) );
    $output = ob_get_contents();
    ob_end_clean();
    return $output;
  }
  else {
    return '<p>'.sprintf(__("%s: Widget class not found. Make sure this widget exists and the class name is correct"),'<strong>' . $widget_name . '</strong>').'</p>';
  }
}

// Add shortcode
add_shortcode( 'widget' , 'proud_shortcode' ); 