<?php
/**
 * @version    $Id$
 * @package    ProudCore
 * @author     ProudCity
 * @copyright  Copyright (C) 2015 http://getproudcity.com. All Rights Reserved.
 * @license    GNU/GPL v2 or later http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Websites: http://getproudcity.com
 * Technical Support:  Feedback - http://getproudcity.com
 */

use Proud\Core;

if ( ! class_exists( 'IconSet' ) ) :

/**
 * Proud Icon Widget
 *
 * @package  ProudCore
 * @since    1.0.0
 */
class IconSet extends Core\ProudWidget {
  /**
   * Constructor
   *
   * @return  void
   */
  public function __construct() {
    parent::__construct(
      'proud_icon_set', // Base ID
      __( 'Icon set', 'wp-proud-core' ), // Name
      array( 'description' => __( 'A collection of icons and links', 'wp-proud-core' ), ) // Args
    );
  }

  /**
   * Define shortcode settings.
   *
   * @return  void
   */
  function initialize() {
    $this->settings += array(
      'iconset' => array(
        '#title' => __( 'Icons', 'wp-proud-core' ),
        '#type' => 'group',
        '#group_title_field' => 'link_title',
        '#sub_items_template' => array(
          'link_title' => [
            '#title' => 'Link title',
            '#type' => 'text',
            '#default_value' => '',
            '#description' => 'Text for the link',
            '#to_js_settings' => false
          ],
          'link_url' => [
            '#title' => 'Link url',
            '#type' => 'text',
            '#default_value' => '',
            '#description' => 'Url for the link',
            '#to_js_settings' => false
          ],
          'fa_icon' => [
            '#title' => 'Icon',
            '#type' => 'fa-icon',
            '#default_value' => '',
            '#description' => 'The icon to use for the icon box.',
            '#to_js_settings' => false,
          ],
          'external' => [
            '#type' => 'checkbox',
            '#title' => 'Open in new tab',
            '#return_value' => '1',
            '#default_value' => false
          ]
        ),
      )
    );
  }

  /**
   * Generate HTML code from shortcode content.
   */
  function printWidget( $args, $instance ) {
    extract($instance);
    $md_col = 4;
    $col_count = count( $iconset );
    if( $col_count < 4 || ( $col_count === 6 ) ) {
      $md_col = 3;
    }
    $file = plugin_dir_path( __FILE__ ) . 'templates/icon-set.php';
    include( $file );
  }
}

// register Foo_Widget widget
function register_icon_set_widget() {
  register_widget( 'IconSet' );
}
add_action( 'widgets_init', __NAMESPACE__ . '\\register_icon_set_widget' );

endif;