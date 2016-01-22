<?php

namespace Proud\SOPagebuilder;

class ProudSOPagebuilder {

  /**
   * Constructor
   */
  public function __construct() {
    //add_filter( 'siteorigin_panels_css_object', array($this, 'siteorigin_panels_css_object'), 11, 3);
    add_filter('siteorigin_panels_row_style_attributes', array($this, 'so_row_style_attributes'), 10, 2);
  }


  /**
   * Add async, defer to Google places API code
   * From http://stackoverflow.com/questions/18944027/how-do-i-defer-or-async-this-wordpress-javascript-snippet-to-load-lastly-for-fas
   */
  function siteorigin_panels_css_object($css, $panels_data, $post_id)
  {
    //print_r($post_id);
    //print_r($css);
    //print_r($panels_data);
    return $css;
  }

  // Add container class to rows that aren't full width
  function so_row_style_attributes( $attributes, $args ) {
    if( empty( $args['row_stretch'] ) ) {
        array_push($attributes['class'], 'container');
    }

    return $attributes;
  }


  
}

new ProudSOPagebuilder;
