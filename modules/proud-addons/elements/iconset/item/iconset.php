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

if ( ! class_exists( 'WR_Item_IconSet' ) ) {

  class WR_Item_IconSet extends WR_Pb_Shortcode_Child {

    public function __construct() {
      parent::__construct();
    }

    /**
     * DEFINE configuration information of shortcode
     */
    public function element_config() {
      $this->config['shortcode'] = strtolower( __CLASS__ );
      
      // Inline edit for sub item
      $this->config['edit_inline'] = true;
    }

    /**
     * DEFINE setting options of shortcode
     */
    public function element_items() {
      $this->items = array(
        'Notab' => array(
      array(
        'name' => __( 'Link title', WR_PBL ),
        'id'   => 'title',
        'role'    => 'title',
        'type' => 'text_field',
        'class'   => 'jsn-input-xxlarge-fluid',
        'std'  => '',
        'desc' => __( 'Text for the link', WR_PBL ),

      ),
      array(
        'name' => __( 'Link url', WR_PBL ),
        'id'   => 'url',
        'type' => 'text_field',
        'role'    => 'url',
        'class'   => 'jsn-input-xxlarge-fluid',
        'desc' => __( 'Url for the link', WR_PBL ),
        'std'  => '',

      ),
      array(
        'name' => __( 'Icon', WR_PBL ),
        'id'   => 'icon',
        'type' => 'text_field',
        'role'    => 'icon',
        'class'   => 'jsn-input-xxlarge-fluid fa-icon-picker',
        'desc' => __( 'The icon to use for the icon box', WR_PBL ),
        'std'  => '',
      ),
      )
      );
    }

    /**
     * DEFINE shortcode content
     *
     * @param type $atts
     * @param type $contentz
     */
    public function element_shortcode_full( $atts = null, $content = null ) {
      extract( shortcode_atts( $this->config['params'], $atts ) );
      return "<div class=\"card-wrap\"><a href=\"$url\" class=\"card text-center card-btn card-block\">
        <i class=\"fa $icon fa-3x\"></i>
        <h3>$title</h3>
      </a></div><!--seperate-->";
    }

  }

}
