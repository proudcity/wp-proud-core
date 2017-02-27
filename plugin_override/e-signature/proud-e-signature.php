<?php 

if ( class_exists( 'WP_E_Digital_Signature' ) && !class_exists( 'ProudESignature' ) ) {

  class ProudESignature {

    private static $is_page = null;

    /**
     * Constructor
     */
    public function __construct() {
      add_action('wp_print_styles', [ $this, 'styles' ], 101);
      add_action('wp_print_scripts', [ $this, 'scripts' ], 101);
    }

    function is_esig_page() {
      if( self::$is_page === null ) {
        $current_page = get_queried_object_id();
        global $wpdb;
        $table = $wpdb->prefix . 'esign_documents_stand_alone_docs';
        $default_page = array();
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") == $table) {
          $default_page = $wpdb->get_col("SELECT page_id FROM {$table}");
        }
        $setting = new WP_E_Setting();
        $default_normal_page = $setting->get_generic('default_display_page');
        self::$is_page = is_page($current_page) 
                      && ( in_array($current_page, $default_page) || $current_page == $default_normal_page );
      }
      return self::$is_page;
    }

    function styles() {
      if($this->is_esig_page()) {
        global $wp_styles;
        // remove proud items
        foreach ( $wp_styles->queue as $handle ) {
          if( preg_match( '/proud/i', $handle ) ) {
            wp_deregister_style( $handle );
            wp_dequeue_style( $handle );
          }
        }
        // Enqueue some override
        wp_enqueue_style( 
          'proud-e-signature-css', 
          plugins_url( 'assets/css/', __FILE__ ) . 'proud-e-signature.css',
          []
        );
      }
    }

    function scripts() {
      if($this->is_esig_page()) {
        // Enqueue some override
        wp_enqueue_script(
        'proud-e-signature-js',
          plugins_url( 'assets/js/', __FILE__ ) . 'proud-e-signature.js',
          []
        );
      }
    }
  }

  new ProudESignature();
}