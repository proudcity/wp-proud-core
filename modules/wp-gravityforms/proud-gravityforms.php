<?php

function gform_force_footer_scripts() {
   return true;
}

add_filter("gform_init_scripts_footer",  'gform_force_footer_scripts');