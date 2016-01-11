<?php


add_action( 'wr_pb_addon', 'wp_proud_addons_init');

/**
* The function to init the story
**/
function wp_proud_addons_init() {

    /**
    * Entry Class
    * 
    */
    class ProudAddons extends WR_Pb_Addon {

      public function __construct() {

          /*
          * Define Your Information
          */
          $this->set_provider(
              array(
                  // The name will be displayed as a group when user
                  // chooses the elements.
                  'name' => 'ProudCity',

                  // The main file of this plugin, which is used to
                  // identify itself when removing IG PageBuilder
                  'file' => __FILE__,

                  // Shortcodes directory, where the Elements
                  // of this bundle are stored.
                  'shortcode_dir' => 'elements',
              )
          );

          // call parent construct
          parent::__construct();
      }

    }

    // Init the Plugin Settings
    $this_ = new ProudAddons();
}