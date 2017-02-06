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

if ( ! class_exists( 'ImageSet' ) ) :

/**
 * Proud Widget
 *
 * @package  ProudCore
 * @since    1.0.0
 */
class ImageSet extends Core\ProudWidget {
  /**
   * Constructor
   *
   * @return  void
   */
  public function __construct() {
    parent::__construct(
      'proud_image_set', // Base ID
      __( 'Image set', 'wp-proud-core' ), // Name
      array( 'description' => __( 'A collection of images and links', 'wp-proud-core' ), ) // Args
    );
  }

  /**
   * Define shortcode settings.
   *
   * @return  void
   */
  function initialize() {
    $this->settings += [
      'display' => [
        '#title' => __( 'Display type', 'wp-proud-core' ),
        '#type' => 'radios',
        '#default_value'  => 'cards',
        '#options' => [ 
          'cards' => __( 'Image cards', 'wp-proud-core' ),
          'list' => __( 'Media list', 'wp-proud-core' ), 
        ],
        '#description' => __( 'How to display the set', 'wp-proud-core' )
      ],
      'across' => [
        '#title' => __( 'Columns across', 'wp-proud-core' ),
        '#type' => 'radios',
        '#default_value'  => '3',
        '#options' => [ 
          '2' => __( 'Two', 'wp-proud-core' ),
          '3' => __( 'Three', 'wp-proud-core' ), 
        ],
        '#description' => __( 'How many columns to display', 'wp-proud-core' )
      ],
      'imageset' => [
        '#title' => __( 'Image items', 'wp-proud-core' ),
        '#type' => 'group',
        '#group_title_field' => 'link_title',
        '#sub_items_template' => [
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
          'image' => [
            '#title' => __( 'Image', 'wp-proud-core' ),
            '#type' => 'select_media',
            '#default_value'  => '',
          ],
          'text' => [
            '#title' => 'Description (optional)',
            '#type' => 'text',
            '#default_value' => '',
            '#description' => 'Text to display.  Best if ALL or NONE have text, and are of similar length',
          ]
        ],
      ]
    ];
  }

  /**
   * Opens list
   */
  public static function row_open( $current, $columns  ){
     return $current%$columns === 0
          ? '<div class="row">' 
          : '';
  }

  /**
   * Closes list
   */
  public static function row_close( $current, $post_count, $columns ) {
    return ( ( $post_count - 1 ) === $current ) || ( $current%$columns === ( $columns - 1 ) )
         ? '</div>'
         : '';
  }

  /**
   * Generate HTML code from shortcode content.
   */
  function printWidget( $args, $instance ) {
    extract($instance);
    if( $display === 'cards' ) {
      $file = plugin_dir_path( __FILE__ ) . 'templates/image-cards.php';
    }
    else {
      $file = plugin_dir_path( __FILE__ ) . 'templates/media-list.php';
    }
    include( $file );
  }
}

// register Foo_Widget widget
function register_image_set_widget() {
  register_widget( 'ImageSet' );
}
add_action( 'widgets_init', __NAMESPACE__ . '\\register_image_set_widget' );

endif;