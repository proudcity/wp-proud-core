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


if ( ! class_exists( 'WR_JumbotronHeader' ) ) :

/**
 * Create Jumbotron element
 *
 * @package  WR PageBuilder Shortcodes
 * @since    1.0.0
 */
class WR_JumbotronHeader extends WR_Pb_Shortcode_Element {
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
    $this->config['name']        = __( 'Jumbotron Header', WR_PBL );
    $this->config['cat']         = __( 'Typography', WR_PBL );
    $this->config['icon']        = 'wr-icon-text';
    $this->config['description'] = __( 'Jumbotron header layout: for landing pages', WR_PBL );

    // Define exception for this shortcode
    $this->config['exception'] = array(
      'default_content' => __( 'Jumbotron Header', WR_PBL ),

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
      ),
      'styling' => array(
        array(
          'type' => 'preview',
        ),
        array(
          'name'       => __( 'Include Page Title', WR_PBL ),
          'id'         => 'include_title',
          'type'       => 'radio',
          'std'        => 'no',
          'desc' => __( 'If yes, print page title will be placed inside the box', WR_PBL ),
          'options'    => array( 'yes' => __( 'Yes', WR_PBL ), 'no' => __( 'No', WR_PBL ) ),
        ),
        array(
          'name'       => __( 'Container Background', WR_PBL ),
          'id'         => 'background',
          'type'       => 'select',
          'std'        => 'proud',
          'class'    => 'input-sm',
          'options'    => array(
            'none'     => __( 'None', WR_PBL ),
            'proud'    => __( 'ProudCity Image from setup', WR_PBL ),
            'solid'    => __( 'Solid Color', WR_PBL ),
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
        array(
              'name'    => __( 'Repeat', WR_PBL ),
              'id'      => 'img_repeat',
              'type'    => 'radio_button_group',
              'std'     => 'none',
              'options' => array(
                'none'       => __( 'None', WR_PBL ),
                'full'       => __( 'Full', WR_PBL ),
                'vertical'   => __( 'Vertical', WR_PBL ),
                'horizontal' => __( 'Horizontal', WR_PBL ),
              ),
              'dependency' => array( 'background', '=', 'proud__#__image' ),
        ),
        array(
              'name'       => __( 'Size', WR_PBL ),
              'id'         => 'background_size',
              'type'       => 'radio',
              'std'        => 'normal',
              'options'    => array(
                'normal'      => __( 'Normal', WR_PBL ),
                'cover'       => __( 'Cover', WR_PBL ),
                '200%'        => __( 'Huge (good for paralax)', WR_PBL ),
              ),
              'dependency' => array( 'background', '=', 'proud__#__image' ),
        ),  
        array(
              'name'       => __( 'Enable Paralax', WR_PBL ),
              'id'         => 'paralax',
              'type'       => 'radio',
              'std'        => 'no',
              'options'    => array( 'yes' => __( 'Yes', WR_PBL ), 'no' => __( 'No', WR_PBL ) ),
              'dependency' => array( 'background', '=', 'pattern__#__proud__#__image' ),
        ),
        array(
          'name'       => __( 'Inverse text in box?', WR_PBL ),
          'id'         => 'make_inverse',
          'type'       => 'radio',
          'std'        => 'no',
          'options'    => array( 'yes' => __( 'Yes', WR_PBL ), 'no' => __( 'No', WR_PBL ) ),
          'tooltip'    => __( 'Inverse colors on box', WR_PBL ),
        ),
        array(
          'name'       => __( 'Box Background', WR_PBL ),
          'id'         => 'box_background',
          'type'       => 'select',
          'std'        => 'none',
          'class'    => 'input-sm',
          'options'    => array(
            'none'     => __( 'Normal', WR_PBL ),
            'solid'    => __( 'Solid Color', WR_PBL ),
        ),
              'has_depend' => '1',
        ),
        array(
              'name' => __( 'Solid Color', WR_PBL ),
              'type' => array(
        array(
                  'id'           => 'box_solid_color_value',
                  'type'         => 'text_field',
                  'class'        => 'input-small',
                  'std'          => '#FFFFFF',
                  'parent_class' => 'combo-item',
        ),
        array(
                  'id'           => 'box_solid_color_color',
                  'type'         => 'color_picker',
                  'std'          => '#ffffff',
                  'parent_class' => 'combo-item',
        ),
        ),
              'container_class' => 'combo-group',
              'dependency'      => array( 'box_background', '=', 'solid' ),
        ),
        // WR_Pb_Helper_Type::get_apprearing_animations(),
        // WR_Pb_Helper_Type::get_animation_speeds(),
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
    // Container Styles
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
          $image = $arr_params['image'];
          $background_style = "background-image:url('$image');";
          // Background repeat
          $background_repeat = self::background_repeat( $arr_params['img_repeat'] );
          if ( ! empty( $background_repeat ) ) {
            $background_style .= "background-repeat:$background_repeat;";
          }
          // Backgound size
          if ( ! empty( $background_size ) ) {
            $background_style .= ($background_size != 'normal') ? "background-size:$background_size;"  : '';
          }
          break;

        case 'proud':
          $image = $arr_params['image'];
          $background_style = "background-image:url('$image');";
          // Background repeat
          $background_repeat = self::background_repeat( $arr_params['img_repeat'] );
          if ( ! empty( $background_repeat ) ) {
            $background_style .= "background-repeat:$background_repeat;";
          }
          // Backgound size
          if ( ! empty( $background_size ) ) {
            $background_style .= ($background_size != 'normal') ? "background-size:$background_size;"  : '';
          }
          break;
      }

      $arr_styles[] = $background_style;

      // Paralax background
      if ( isset( $atts['paralax']) && $atts['paralax'] == 'yes' ) {
        $data_attr = ' data-stellar-background-ratio="-.3"';
      }
    }

    // Box styles
    // Init classes
    $boxclasses = ['jumbotron', 'jumbotron-image', 'full'];
    // Inverse?
    if ( isset( $make_inverse ) && $make_inverse == 'yes' ) {
      $boxclasses[] = 'jumbotron-inverse';
    }
    $box_arr_styles = [];
    // if(!empty($box_background) && $box_background != 'none') {
    //   switch ( $background ) {

    //     case 'solid':
    //       $solid_color = $arr_params['box_solid_color_value'];
    //       $background_style  = "background-color: $solid_color;";
    //       break;
    //   }

    //   $box_arr_styles[] = $background_style;
    // }

    $random_id = WR_Pb_Utils_Common::random_string();
    $script = $html_element = '';
    // Compile Container styles
    $style = $arr_styles ? sprintf( 'style="%s"', implode( '', $arr_styles ) ) : '';
    $html  = sprintf( '<div class="full-image" id="%s" %s><div class="container"><div class="full-container">', 
      $random_id,
      $data_attr . ' ' . $style 
    );
    $html .= $script;
    // Compile Box Styles
    $html_element .= sprintf( '<div class="%s" %s><div class="row"><div class="col-lg-7 col-md-8 col-sm-9"><div class="jumbotron-bg">',
      implode( ' ', $boxclasses ),
      implode( '', $box_arr_styles )
    );
    $html .= $html_element;
    // Include title inside box?
    if ( isset( $include_title ) && $include_title == 'yes' ) {
      global $proudcore;
      $html .= $proudcore::$layout->title_is_hidden() 
             ? '<h1>' . get_the_title() . '</h1>'
             : '<h2 class="h1">' . get_the_title() . '</h2>';
        
      }
    $html .= $content;
    $html .= '</div></div></div></div></div></div></div></div>';

    return $this->element_wrapper( $html, $arr_params );
  }
}

endif;