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

if ( ! class_exists( 'JumbotronHeader' ) ) :

/**
 * Create Jumbotron element
 *
 * @package  ProudCore
 * @since    1.0.0
 */
class JumbotronHeader extends Core\ProudWidget {
  /**
   * Constructor
   *
   * @return  void
   */
  public function __construct() {
    parent::__construct(
      'proud_jumbotron_header', // Base ID
      __( 'Hero unit (page header)', 'wp-proud-core' ), // Name
      array( 'description' => __( 'Image / slideshow / title unit for the header of the page', 'wp-proud-core' ), ) // Args
    );
  }

  function initialize() {

    $this->settings = [
      'headertype' => [
        '#title' => __( 'Header Type', 'wp-proud-core' ),
        '#type'    => 'radios',
        '#default_value'     => 'header',
         '#options' => [
          'header'    => __( 'Header (good for important pages)', 'wp-proud-core' ),
          'slideshow' => __( 'Slideshow', 'wp-proud-core' ),
          'random' => __( 'Random image', 'wp-proud-core' ),
          'full'      => __( 'Full-height (good for landing pages)', 'wp-proud-core' ),
          'simple'    => __( 'Simple heading (good for landing pages)', 'wp-proud-core' )
        ],
      ],
      'text'=> [
        '#title' => __( 'Jumbtron text', 'wp-proud-core' ),
        '#description' => __( 'Enter some content for this textblock', 'wp-proud-core' ),
        '#type' => 'textarea',
        '#default_value'  => '',
        'rows' => 15,
        '#states' => [
          'visible' => [
            'headertype' => [
              'operator' => '!=',
              'value' => ['slideshow'],
              'glue' => '&&'
            ],
          ],
        ],
      ],
      'background' => [
        '#title' => __( 'Container Background', 'wp-proud-core' ),
        '#type' => 'radios',
        '#default_value'  => 'none',
        '#options' => [
          'none' => __( 'Default', 'wp-proud-core' ),
          // 'proud'    => __( 'ProudCity Image from setup', 'wp-proud-core' ),
          // @todo: 'solid'  => __( 'Choose color', 'wp-proud-core' ),
          'pattern' => __( 'Pattern', 'wp-proud-core' ),
          'image' => __( 'Image', 'wp-proud-core' ),
        ],
        '#states' => [
          'visible' => [
            'headertype' => [
              'operator' => '!=',
              'value' => ['simple', 'slideshow', 'random'],
              'glue' => '&&'
            ],
          ],
        ],
      ],
      // @todo
      //'solid_color_value' => [
      //   '#title' => __( 'Solid Color', 'wp-proud-core' ),
      //   '#type' => 'text_field',
      //   '#default_value'  => '#FFFFFF',
      // ],
      // 'solid_color_color' => [
      //   '#type' => 'color_picker',
      //   '#default_value'  => '#ffffff',
      // ],
      'pattern' => [
        '#title' => __( 'Pattern', 'wp-proud-core' ),
        '#type' => 'select_media',
        '#default_value'  => '',
        '#states' => [
          'visible' => [
            'background' => [
              'operator' => '==',
              'value' => ['pattern'],
              'glue' => '||'
            ],
            'headertype' => [
              'operator' => '!=',
              'value' => ['simple', 'slideshow'],
              'glue' => '&&'
            ],
          ],
        ],
      ],
      'repeat' => [
        '#title' => __( 'Repeat', 'wp-proud-core' ),
        '#type'  => 'radios',
        '#default_value'  => 'full',
         '#options' => [
          'full' => __( 'Full', 'wp-proud-core' ),
          'vertical'   => __( 'Vertical', 'wp-proud-core' ),
          'horizontal' => __( 'Horizontal', 'wp-proud-core' ),
        ],
        '#states' => [
          'visible' => [
            'background' => [
              'operator' => '==',
              'value' => ['pattern'],
              'glue' => '||'
            ],
            'headertype' => [
              'operator' => '!=',
              'value' => ['simple', 'slideshow'],
              'glue' => '&&'
            ],
          ],
        ],
      ],
      'featured_image' => [
        '#title' => __( 'Image: Use featured image from post?', 'wp-proud-core' ),
        '#type' => 'radios',
        '#default_value'  => 'no',
        '#options' => [ 
          'yes' => __( 'Yes', 'wp-proud-core' ), 
          'no' => __( 'No', 'wp-proud-core' ) 
        ],
        '#description' => __('If yes, the image used will be from the "Featured Image" field', 'wp-proud-core' ),
        '#states' => [
          'visible' => [
            'background' => [
              'operator' => '==',
              'value' => ['image'],
              'glue' => '&&'
            ],
            'headertype' => [
              'operator' => '!=',
              'value' => ['simple', 'slideshow', 'random'],
              'glue' => '&&'
            ],
          ]
        ]
      ],
      'image' => [
        '#title' => __( 'Image', 'wp-proud-core' ),
        '#type' => 'select_media',
        '#default_value'  => '',
        '#states' => [
          'visible' => [
            'background' => [
              'operator' => '==',
              'value' => ['image'],
              'glue' => '&&'
            ],
            'headertype' => [
              'operator' => '!=',
              'value' => ['simple', 'slideshow', 'random'],
              'glue' => '&&'
            ],
            'featured_image' => [
              'operator' => '!=',
              'value' => ['yes'],
              'glue' => '&&'
            ],
          ],
        ],
      ],
      'slideshow' => [
        '#title' => __( 'Slideshow', 'wp-proud-core' ),
        '#type' => 'group',
        '#group_title_field' => 'slide_title',
        '#sub_items_template' => [
          'slide_title' => [
            '#title' => 'Slide title',
            '#type' => 'text',
            '#default_value' => '',
            '#description' => 'Title for the slide',
            '#to_js_settings' => false
          ],
          'description' => [
            '#title' => 'Text description',
            '#type' => 'text',
            '#default_value' => '',
            '#description' => 'Brief text to be displayed below title.  This should not contain any html',
            '#to_js_settings' => false
          ],
          'link_title' => [
            '#title' => 'Link text',
            '#type' => 'text',
            '#default_value' => '',
            '#description' => 'Text for the link displayed as a button',
            '#to_js_settings' => false
          ],
          'link_url' => [
            '#title' => 'Link url',
            '#type' => 'text',
            '#default_value' => '',
            '#description' => 'Url for the link',
            '#to_js_settings' => false
          ],
          'slide_image' => [
            '#title' => __( 'Image', 'wp-proud-core' ),
            '#type' => 'select_media',
            '#default_value'  => '',
          ]
        ],
        '#states' => [
          'visible' => [
            'headertype' => [
              'operator' => '==',
              'value' => ['slideshow'],
              'glue' => '&&'
            ],
          ],
        ],
      ],
      'random' => [
        '#title' => __( 'Image', 'wp-proud-core' ),
        '#type' => 'group',
        '#group_title_field' => 'slide_title',
        '#sub_items_template' => [
          'random_image' => [
            '#title' => __( 'Image', 'wp-proud-core' ),
            '#type' => 'select_media',
            '#default_value'  => '',
          ]
        ],
        '#states' => [
          'visible' => [
            'headertype' => [
              'operator' => '==',
              'value' => ['random'],
              'glue' => '&&'
            ],
          ],
        ],
      ],
      'image_vertical' => [
        '#title' => __( 'Image Vertical Alignment', 'wp-proud-core' ),
        '#type' => 'radios',
        '#default_value'  => 'middle',
        '#options' => [ 
          'top' => __( 'Align top of image with top of header', 'wp-proud-core' ), 
          'middle' => __( 'Center image vertically', 'wp-proud-core' ),
          'bottom' => __( 'Align bottom of image with bottom of header', 'wp-proud-core' ),
        ],
        '#description' => __( 'Position of image within header', 'wp-proud-core' ),
        '#states' => [
          'visible' => [
            'headertype' => [
              'operator' => '==',
              'value' => ['full', 'header'],
              'glue' => '||'
            ]
          ]
        ]
      ],
      'box_position' => [
        '#title' => __( 'Text box position', 'wp-proud-core' ),
        '#type' => 'radios',
        '#default_value'  => 'middle_left',
        '#options' => [ 
          'middle_left' => __( 'Middle Left', 'wp-proud-core' ), 
          'middle_right' => __( 'Middle Right', 'wp-proud-core' ),
          'middle_center' => __( 'Middle Center', 'wp-proud-core' ),
          'top_left' => __( 'Top Left', 'wp-proud-core' ), 
          'top_right' => __( 'Top Right', 'wp-proud-core' ),
          'bottom_left' => __( 'Bottom Left', 'wp-proud-core' ), 
          'bottom_right' => __( 'Bottom Right', 'wp-proud-core' ) 
        ],
        '#description' => __( 'Position of the header text', 'wp-proud-core' ),
        '#states' => [
          'visible' => [
            'headertype' => [
              'operator' => '==',
              'value' => ['full'],
              'glue' => '||'
            ]
          ]
        ]
      ],
      'make_inverse' => [
        '#title' => __( 'Style', 'wp-proud-core' ),
        '#type' => 'radios',
        '#default_value'  => 'no',
        '#options' => [ 
          'yes' => __( 'White text on dark background', 'wp-proud-core' ), 
          'no' => __( 'Black text on light background', 'wp-proud-core' ) 
        ],
      ]
    ];
  }

  /**
   * OVERRIDE: Back-end widget form.
   *
   * @see WP_Widget::form()
   *
   * @param array $instance Previously saved values from database.
   */
  public function form( $instance ) {
    // $instance['image'] should be a media['ID'], but due to 
    // https://github.com/proudcity/wp-proudcity/issues/436
    // Old values may be [featured-image]
    // Set new value 'featured_image' if that is the case
    if( !empty( $instance['image'] ) && $instance['image'] === '[featured-image]' ) {
      $instance['featured_image'] = 'yes';
    }
    parent::form($instance);
  }


  /**
   * Return CSS for background-repeat
   *
   * @param string $bg_repeat
   * @return string
   */
  static function background_repeat( $bg_repeat ) {
    $background_repeat = '';

    switch ( $bg_repeat ) {
      case 'none':
        $background_repeat = 'no-repeat';
        break;
      case 'full':
        $background_repeat = 'repeat';
        break;
      case 'vertical':
        $background_repeat = 'repeat-y';
        break;
      case 'horizontal':
        $background_repeat = 'repeat-x';
        break;
    }

    return $background_repeat;
  }

  // Image should be a media['ID'], but due to 
  // https://github.com/proudcity/wp-proudcity/issues/436
  // Some values may be a url
  function getResponsiveImage( $image ) {
    $media_id = '';
    // $image is ID
    if( is_numeric ( $image ) ) {
      $media_id = $image;
    }
    // $image is a URL
    else {//if( false !== filter_var( $image, FILTER_VALIDATE_URL ) ) {
      $url = do_shortcode($image);
      global $wpdb;
      $media_id = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE guid='%s';", $url ));
    }
    
    // Build image attrs 
    return !empty( $media_id ) ? Core\build_responsive_image_meta( $media_id ) : [];
  }

  /**
   * Generate HTML code from shortcode content.
   *
   * @param   array   $atts     Shortcode attributes.
   * @param   string  $content  Current content.
   *
   * @return  string
   */
  function printWidget( $args, $instance ) {
    // Container Styles
    $arr_styles = [];
    // init classes
    $classes = [];

    if(!empty( $instance['background']) && $instance['headertype'] !== 'slideshow' ) {
      
      // For random do a little processing
      if ( $instance['headertype'] === 'random' ) {
        $rand = array_rand( $instance['random'] );
        $instance['image'] = $instance['random'][$rand]['random_image'];
        // Fudge as image
        $instance['background'] = 'image';
        // Fudge as full
        $instance['headertype'] = 'full';
        // Remove featured option
        $instance['featured_image'] = false;
        // Force middle vertical
        $instance['image_vertical'] = 'middle';
      }

      switch ( $instance['background'] ) {
        case 'solid':
          $solid_color = $instance['solid_color_value'];
          $background_style  = "background-color: $solid_color;";
          break;

        case 'pattern':
          $pattern_img = wp_get_attachment_image_url( $instance['pattern'], 'full' );
          $background_style = "background-image:url('$pattern_img');";
          $background_style .= "background-size:initial;";
          $background_repeat = self::background_repeat( $instance['repeat'] );
          if ( ! empty( $background_repeat ) ) {
            $background_style .= "background-repeat:$background_repeat;";
          }
          break;

        case 'image':
          // Use featured image?
          if( $instance['featured_image'] === 'yes' || $instance['image'] === '[featured-image]' ) {
            global $post;
            $instance['image'] = get_post_thumbnail_id( $post->ID );
          }
          $resp_img = $this->getResponsiveImage($instance['image']);
          break;
      }

      if( !empty( $background_style ) ) {
        $arr_styles[] = $background_style;
      }
    }
    else if ( $instance['headertype'] === 'slideshow' ) {
      // d($instance);
      foreach ($instance['slideshow'] as $key => $value) {
        $instance['slideshow'][$key]['resp_img'] = $this->getResponsiveImage($value['slide_image']);
      }
    }


    // Init a random id for the element
    $random_id = 'proud-header-' . rand();
    // init file location
    $file = plugin_dir_path( __FILE__ ) . 'templates/';

    $content = Core\sanitize_input_text_output($instance['text']);
  
    // normal header type
    if( $instance['headertype'] == 'header' ) {
      $jumbotron_col_classes = apply_filters('proud_jumbotron_col_classes', 'col-lg-5 col-md-8 col-sm-8', 'header');

      // Classes
      $classes[] = 'jumbotron';
      if ( $instance['background'] == 'pattern' || $instance['background'] == 'image' ) {
        $classes[] = 'jumbotron-image';
      }
      // Inverse?
      if ( $instance['make_inverse'] == 'yes' ) {
        $classes[] = 'jumbotron-inverse';
      }
      // Resp image classes
      $resp_img_classes = ['jumbo-image-container'];
      if( !empty( $instance['image_vertical'] ) && 'middle' !== $instance['image_vertical'] ) {
        $resp_img_classes[] = 'image-vertical-' . $instance['image_vertical'];
      }
      $file .= 'jumbotron-header.php';
    }
    // We're doing a "full" style jumbotron
    else if( $instance['headertype'] == 'full' ) {
      $jumbotron_col_classes = apply_filters('proud_jumbotron_col_classes', 'col-lg-7 col-md-8 col-sm-9', 'full');

      // Classes
      $classes[] = 'full-image jumbotron-header-container';
      // Box styles
      // Init classes
      $boxclasses = ['jumbotron', 'jumbotron-image', 'full'];
      // Inverse?
      if ( $instance['make_inverse'] == 'yes' ) {
        $boxclasses[] = 'jumbotron-inverse';
      }
      // Resp image classes
      $resp_img_classes = ['jumbo-image-container'];
      if( !empty( $instance['image_vertical'] ) && 'middle' !== $instance['image_vertical'] ) {
        $resp_img_classes[] = 'image-vertical-' . $instance['image_vertical'];
      }
      // Vertical horizontal positions
      $pos_options = !empty( $instance['box_position'] )
                   ? explode( '_', $instance['box_position'] )
                   : ['middle', 'left'];
      $boxclasses[] = 'full-v-align-' . $pos_options[0];
      $boxclasses[] = 'full-h-align-' . $pos_options[1];
      $file .= 'jumbotron-full.php';
    }
    else if( $instance['headertype'] === 'slideshow' ) {
      $classes[] = 'jumbotron';
      // Inverse?
      if ( $instance['make_inverse'] == 'yes' ) {
        $classes[] = 'jumbotron-inverse';
      }
      $file .= 'jumbotron-slideshow.php';
    }
    else {
      // Classes
      $classes[] = 'jumbotron';
      // Inverse?
      if ( $instance['make_inverse'] == 'yes' ) {
        $classes[] = 'jumbotron-inverse';
      }
      $file .= 'jumbotron-simple.php';
    }
    // Include the template file
    include( $file );
  }
}

// register Foo_Widget widget
function register_jumbotron_header_widget() {
  register_widget( 'JumbotronHeader' );
}
add_action( 'widgets_init', __NAMESPACE__ . '\\register_jumbotron_header_widget' );

endif;