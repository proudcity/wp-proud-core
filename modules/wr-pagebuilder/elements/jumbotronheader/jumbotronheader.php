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
    $this->config['name']        = __( 'Page Header', WR_PBL );
    $this->config['cat']         = __( 'Typography', WR_PBL );
    $this->config['icon']        = 'wr-icon-text';
    $this->config['description'] = __( 'Jumbotron header layout: for landing pages', WR_PBL );

    // Define exception for this shortcode
    $this->config['exception'] = array(
      'default_content' => __( 'Page Header', WR_PBL ),

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
          'name'       => __( 'Container Background', WR_PBL ),
          'id'         => 'background',
          'type'       => 'select',
          'std'        => 'image',
          'class'    => 'input-sm',
          'options'    => array(
            'none'     => __( 'None', WR_PBL ),
            // 'proud'    => __( 'ProudCity Image from setup', WR_PBL ),
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
          'name'       => __( 'Inverse text in box?', WR_PBL ),
          'id'         => 'make_inverse',
          'type'       => 'radio',
          'std'        => 'no',
          'options'    => array( 'yes' => __( 'Yes', WR_PBL ), 'no' => __( 'No', WR_PBL ) ),
          'tooltip'    => __( 'Inverse the colors on the box', WR_PBL ),
        )
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
          break;
      }

      $arr_styles[] = $background_style;
    }

    // Box styles
    // Init classes
    $boxclasses = ['jumbotron', 'jumbotron-image', 'full'];
    // Inverse?
    if ( isset( $make_inverse ) && $make_inverse == 'yes' ) {
      $boxclasses[] = 'jumbotron-inverse';
    }
    $box_arr_styles = [];

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
    $html .= $content;
    $html .= '</div></div></div></div></div></div></div>';

    return $this->element_wrapper( $html, $arr_params );
  }
}

endif;