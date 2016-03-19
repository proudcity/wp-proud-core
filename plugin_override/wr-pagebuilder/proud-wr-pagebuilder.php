<?php

namespace Proud\Core;
// Load theme assets

/**
 * Simulate js click on visual tab
 */
function proud_pagebuilder_alter() {
  $path = plugins_url('assets/js/',__FILE__);
  wp_enqueue_script('proud_page_builder_alter', $path . 'page-builder-default-tab.js');
}
add_filter( 'admin_enqueue_scripts', __NAMESPACE__ . '\\proud_pagebuilder_alter' );


/** 
 * @TODO find out how to remove CSS from pagebuilder
 */
function proud_pagebuilder_css_dequeue() {
  wp_deregister_style('wr-pb-frontend-responsive');
  wp_dequeue_style('wr-pb-frontend-responsive');
}
add_action( 'wp_print_styles', __NAMESPACE__ . '\\proud_pagebuilder_css_dequeue', 100 );

/**
 * Register theme styles to popup teaser
 */
function pagebuilder_register_theme_assets($assets) {
  // wp_enqueue_script('proud_page_builder_alter', $path . 'page-builder-default-tab.js');
  
  require_once get_template_directory() . '/lib/assets.php';
  if( empty( $assets['proud-theme-vendor-css'] ) )  {
    $assets['proud-theme-vendor-css'] = array(
      'src' => \Proud\Theme\Assets\asset_path('styles/proud-vendor.css')
    );
  }
  return $assets;
}
add_filter( 'wr_pb_assets_register_modal', __NAMESPACE__ . '\\pagebuilder_register_theme_assets' );

/**
 * Register theme styles to popup teaser
 */
function pagebuilder_enqueue_theme_assets() {
  // wp_enqueue_script('proud_page_builder_alter', $path . 'page-builder-default-tab.js');
  // $assets[] = 'proud-theme-vendor-css';
  // return $assets;
  \WR_Pb_Init_Assets::load( array('proud-theme-vendor-css') );
}
add_action( 'wr_modal_init', __NAMESPACE__ . '\\pagebuilder_enqueue_theme_assets' );