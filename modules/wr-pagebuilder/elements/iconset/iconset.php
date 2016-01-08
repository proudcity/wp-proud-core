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

if ( ! class_exists( 'WR_Iconset' ) ) :

/**
 * Create Sample Helloworld 2 element
 *
 * @package  WR PageBuilder Shortcodes
 * @since    1.0.0
 */
class WR_Iconset extends WR_Pb_Shortcode_Parent {
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
    $this->config['name']        = __( 'Iconset', WR_PBL );
    $this->config['icon']        = 'wr-icon-text';
    $this->config['has_subshortcode'] = 'WR_Item_Iconset';
    $this->config['description'] = __( 'Collection of icon-links', WR_PBL );

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
          'name' => __( 'Icons', WR_PBL ),
          'desc' => __( 'The icons to be displayed', WR_PBL ),
          'id'   => 'iconset_content',
          'type' => 'group',
          'shortcode'     => ucfirst( __CLASS__ ),
          'sub_item_type' => $this->config['has_subshortcode'],
          'sub_items'     => array(
            array( 'std' => '' ),
          ),
        ),
      ),
      'styling' => array(
        array(
          'type' => 'preview',
        )
      )
    );
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

    $random_id = WR_Pb_Utils_Common::random_string();
    $script = $html_element = '';
    if ( ! empty( $content ) ) {
      $content = WR_Pb_Helper_Shortcode::remove_autop( $content );
    }

    $html_element .= $content;
    $html  = sprintf( '<div class="card-columns card-columns-xs-1 card-columns-sm-2 card-columns-md-4 card-columns-equalize" id="%s">', $random_id );
    $html .= $script;
    $html .= $html_element;
    $html .= '</div>';

    return $this->element_wrapper( $html, $arr_params );
  }
}

endif;