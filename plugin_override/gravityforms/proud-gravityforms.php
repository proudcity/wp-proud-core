<?php

function gform_force_footer_scripts() {
   return true;
}

add_filter("gform_init_scripts_footer",  'gform_force_footer_scripts');


function gform_css_dequeue() {
  wp_deregister_style('gforms_datepicker_css');
  wp_dequeue_style('gforms_datepicker_css');
}
add_action( 'wp_footer', __NAMESPACE__ . '\\gform_css_dequeue', 100 );
add_action( 'wp_print_styles', __NAMESPACE__ . '\\gform_css_dequeue', 100 );

function gform_admin_css_dequeue() {
  wp_deregister_style('gform_font_awesome');
  wp_dequeue_style('gform_font_awesome');
}
add_action( 'admin_print_scripts', __NAMESPACE__ . '\\gform_admin_css_dequeue', 100 );