<?php
/**
 * @author ProudCity
 */

require_once plugin_dir_path(__FILE__) . 'widget-base.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/icon-link/icon-link-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/teaser-list/teaser-list-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/font-size/font-size-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/share-links/share-links-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/social-links/social-links-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/google-translate/google-translate-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/logo/logo-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/main-menu-list/main-menu-list-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/icon-set/icon-set.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/image-set/image-set.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/jumbotron-header/jumbotron-header.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/submenu/submenu-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/page-title/page-title-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/contact-submenu/contact-submenu-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/contact-block/contact-block-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/gravity-form/gravity-form-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/proudscore-widget/proudscore-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/powered-by/powered-by.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/document/document-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/fullcalendar/fullcalendar-widget.class.php';
require_once plugin_dir_path(__FILE__) . 'widgets/embed/embed-widget.class.php';

/**
 * Converts shortcode into widget if its a proud widget
 */
function proud_shortcode($atts) {

  global $wp_widget_factory;

  extract(shortcode_atts(array(
      'widget_name' => FALSE
  ), $atts));

  $widget_name = esc_html($widget_name);

  if ( !is_a( $wp_widget_factory->widgets[$widget_name], 'ProudWidget') ) {
    // Try to get instance info
    $instance = !empty($atts) ? str_ireplace("&amp;", '&' ,$atts) : [];
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