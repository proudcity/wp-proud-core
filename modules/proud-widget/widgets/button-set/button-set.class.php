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

if ( ! class_exists( 'ButtonSet' ) ) :

/**
 * Proud Button Widget
 *
 * @package  ProudCore
 * @since    1.0.0
 */
class ButtonSet extends Core\ProudWidget {
  /**
   * Constructor
   *
   * @return  void
   */
  public function __construct() {
    parent::__construct(
      'proud_button_set', // Base ID
      __( 'Button set', 'wp-proud-core' ), // Name
      array( 'description' => __( 'A collection of buttons linking out to other pages', 'wp-proud-core' ), ) // Args
    );
  }

  /**
   * Define shortcode settings.
   *
   * @return  void
   */
  function initialize() {
    $this->settings += array(
      'buttonset' => array(
        '#title' => __( 'Buttons', 'wp-proud-core' ),
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
    $col_count = count( $buttonset );
    if( $col_count < 4 || ( $col_count === 6 ) ) {
      $md_col = 3;
    }
    $file = plugin_dir_path( __FILE__ ) . 'templates/button-set.php';
    include( $file );
  }
}

// register Foo_Widget widget
function register_button_set_widget() {
  register_widget( 'ButtonSet' );
}
add_action( 'widgets_init', __NAMESPACE__ . '\\register_button_set_widget' );

endif;