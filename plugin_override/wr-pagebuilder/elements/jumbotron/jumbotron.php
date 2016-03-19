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


if ( ! class_exists( 'WR_Jumbotron' ) ) :

/**
 * Create Jumbotron element
 *
 * @package  WR PageBuilder Shortcodes
 * @since    1.0.0
 */
class WR_Jumbotron extends WR_Pb_Shortcode_Element {
  /**
   * Constructor
   *
   * @return  void
   */
  public function __construct() {
    parent::__construct();
  }

  /**
   * Configure shortcode.
   *
   * @return  void
   */
  function element_config() {
    $this->config['shortcode']   = strtolower( __CLASS__ );
    $this->config['name']        = __( 'Jumbotron', WR_PBL );
    $this->config['cat']         = __( 'Typography', WR_PBL );
    $this->config['icon']        = 'wr-icon-text';
    $this->config['description'] = __( 'Highlight box: can be inserted anywhere', WR_PBL );

    // Define exception for this shortcode
    $this->config['exception'] = array(
      'default_content' => __( 'Jumbotron', WR_PBL ),

      'admin_assets' => array(
    // Shortcode initialization
        'wr-colorpicker.js',
    ),
    );

    // Use Ajax to speed up element settings modal loading speed
    $this->config['edit_using_ajax'] = true;
  }

  /**
   * Define shortcode settings.
   *
   * @return  void
   */
  function element_items() {
    $this->items = array(
      'content' => array(

        array(
          'name' => __( 'Parent Element Jumbotron', WR_PBL ),
          'desc' => __( 'Enter some content for this textblock', WR_PBL ),
          'id'   => 'text',
          'type' => 'tiny_mce',
          'role' => 'content',
          'std'  => WR_Pb_Helper_Type::lorem_text(),
          'rows' => 15,
        ),
        array(
          'type' => 'preview',
        ),
        array(
          'name'       => __( 'Inverse?', WR_PBL ),
          'id'         => 'make_inverse',
          'type'       => 'radio',
          'std'        => 'no',
          'options'    => array( 'yes' => __( 'Yes', WR_PBL ), 'no' => __( 'No', WR_PBL ) ),
          'tooltip'    => __( 'Inverse colors', WR_PBL ),
        ),
        array(
          'name'       => __( 'Background', WR_PBL ),
          'id'         => 'background',
          'type'       => 'select',
          'std'        => 'none',
          'class'    => 'input-sm',
          'options'    => array(
                'none'     => __( 'None', WR_PBL ),
                'solid'    => __( 'Solid Color', WR_PBL ),
                // 'gradient' => __( 'Gradient Color', WR_PBL ),
                'pattern'  => __( 'Pattern', WR_PBL ),
                'image'    => __( 'Image', WR_PBL ),
          ),
          'has_depend' => '1',
        ),
        array(
              'name' => __( 'Solid Color', WR_PBL ),
              'type' => array(
                    array(
                        'id'           => 'solid_color_value',
                        'type'         => 'text_field',
                        'class'        => 'input-small',
                        'std'          => '#FFFFFF',
                        'parent_class' => 'combo-item',
                    ),
                    array(
                        'id'           => 'solid_color_color',
                        'type'         => 'color_picker',
                        'std'          => '#ffffff',
                        'parent_class' => 'combo-item',
                    ),
              ),
              'container_class' => 'combo-group',
              'dependency'      => array( 'background', '=', 'solid' ),
        ),
        array(
              'name'       => __( 'Pattern', WR_PBL ),
              'id'         => 'pattern',
              'type'       => 'select_media',
              'std'        => '',
              'class'      => 'jsn-input-large-fluid',
              'dependency' => array( 'background', '=', 'pattern' ),
        ),
        array(
              'name'    => __( 'Repeat', WR_PBL ),
              'id'      => 'repeat',
              'type'    => 'radio_button_group',
              'std'     => 'full',
              'options' => array(
                    'full'       => __( 'Full', WR_PBL ),
                    'vertical'   => __( 'Vertical', WR_PBL ),
                    'horizontal' => __( 'Horizontal', WR_PBL ),
              ),
              'dependency' => array( 'background', '=', 'pattern' ),
        ),
        array(
              'name'       => __( 'Image', WR_PBL ),
              'id'         => 'image',
              'type'       => 'select_media',
              'std'        => '',
              'class'      => 'jsn-input-large-fluid',
              'dependency' => array( 'background', '=', 'image' ),
        ),
      )
    );
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

  /**
   * Generate HTML code from shortcode content.
   *
   * @param   array   $atts     Shortcode attributes.
   * @param   string  $content  Current content.
   *
   * @return  string
   */
  function element_shortcode_full( $atts = null, $content = null ) {
    $arr_params = shortcode_atts( $this->config['params'], $atts );
    extract( $arr_params );
    // Init classes
    $classes = ['jumbotron'];
    // Inverse?
    if ( isset( $make_inverse ) && $make_inverse == 'yes' ) {
      $classes[] = 'jumbotron-inverse';
    }
    // Styles
    $arr_styles = [];
    if(!empty($background)) {
      switch ( $background ) {

        case 'solid':
          $solid_color = $arr_params['solid_color_value'];
          $background_style  = "background-color: $solid_color;";
          break;

        case 'pattern':
          $pattern_img     = $arr_params['pattern'];
          $background_style = "background-image:url('$pattern_img');";

          $background_repeat = self::background_repeat( $arr_params['repeat'] );
          if ( ! empty( $background_repeat ) ) {
            $background_style .= "background-repeat:$background_repeat;";
          }
          break;

        case 'image':
          $classes[] = 'jumbotron-image';
          $image = $arr_params['image'];
          $background_style = "background-image:url('$image');";
          break;
      }

      $arr_styles[] = $background_style;
    }

    $random_id = WR_Pb_Utils_Common::random_string();
    // Compile styles
    $style = $arr_styles ? sprintf( 'style="%s"', implode( '', $arr_styles ) ) : '';
    $html  = sprintf( '<div class="container-fluid"><div class="%s" id="%s" %s>', 
      implode( ' ', $classes ), 
      $random_id,
      $data_attr . ' ' . $style 
    );
    if( $image ) {
      $html .= '<div class="row"><div class="col-lg-5 col-md-8 col-sm-8"><div class="jumbotron-bg">';
      $html .= $content;   
      $html .= '</div></div></div>';
      
    } 
    else {
      $html .= $content;
    }
    $html .= '</div></div>';

    return $this->element_wrapper( $html, $arr_params );
  }
}

endif;