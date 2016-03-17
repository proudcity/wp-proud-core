<?php

function gform_force_footer_scripts() {
   return true;
}

add_filter("gform_init_scripts_footer",  'gform_force_footer_scripts');


function gform_css_dequeue() {
  wp_deregister_style('gforms_datepicker_css');
  wp_dequeue_style('gforms_datepicker_css');
}
add_action( 'wp_footer', __NAMESPACE__ . '\\gform_css_dequeue', 5 );
add_action( 'wp_print_styles', __NAMESPACE__ . '\\gform_css_dequeue', 5 );