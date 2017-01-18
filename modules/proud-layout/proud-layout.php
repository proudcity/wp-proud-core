<?php
/**
 * @author ProudCity
 */


if ( !class_exists( 'ProudLayout' ) ) {

    class ProudLayout {

        private static $fields = [];
        private $title;
        private $afterHead = false;

        function __construct(){
          // Add option to hide featured image
          add_filter( 'admin_post_thumbnail_html', array( $this, 'hide_featured_image' ) );
          // Add save option
          add_action( 'save_post', array( $this, 'save_featured_image_meta' ), 10, 3 );
        }

        /**
         * Helper function loads site origin meta if available
         */
        public function get_site_origins_meta( ) {
          static $site_origins_meta = null;
          if( $site_origins_meta === null && function_exists( 'siteorigin_panels_is_panel' ) ) {
            $id = get_the_ID();
            $site_origins_meta = get_post_meta( get_the_ID(), 'panels_data', false );
          }
          return $site_origins_meta;
        }

        /**
         * Helper function tests if jumbotron is present on panel data
         * AS first item, AND is full row
         *
         * @returns name of jumbotron style
         */
        public function post_has_full_jumbotron_header( ) {
          static $has_jumbotron = null;
          if( $has_jumbotron === null ) {
            // Set false to avoid another round
            $has_jumbotron = false;
            $site_origins_meta = $this->get_site_origins_meta( );
            if( !empty( $site_origins_meta ) ) {
              $meta = reset( $site_origins_meta );
              if( !empty( $meta['widgets'] ) ) {
                // Check if we're first jumbotron large
                $widget = $meta['widgets'][0];
                // widget active, is jumbotron, is full width
                if(  !empty( $widget['panels_info']['class'] ) 
                  && $widget['panels_info']['class'] === 'JumbotronHeader'
                  && !empty( $widget['headertype'] )
                  && $meta['grids'][0]['cells'] === 1
                  && !empty( $meta['grids'][0]['style']['row_stretch'] )
                  && ( $meta['grids'][0]['style']['row_stretch'] === 'full'
                    || $meta['grids'][0]['style']['row_stretch'] === 'full-stretched' )
                ) {
                  // Set to header type.  Transparent
                  // navbar needs to know
                  $has_jumbotron = $widget['headertype'];
                }
              }
            }
          }
          return $has_jumbotron;
        }

        /**
         * Helper function checks if page is full width
         */
        public function post_is_full_width( ){
          if ( is_page() || get_post_type() == 'agency' ) {
            // @todo: fix this so we dont need to reference post ids
            $id = get_the_ID();
            return !empty( $this->get_site_origins_meta() ) 
                      && ( $id != 6 && $id != 149 && $id != 147 );
          }
        }

        /**
         * Helper function checks if page is full width
         */
        public function title_is_hidden(  ){
          return $this->post_is_full_width(  );
        }

        /**
         * Determine information about Agencies, subpages to displ
         * parent menu item title, sidebar
         */
        function page_parent_info( $req = false ) {

          $display = false;
          
          if ( is_page() ) {
            // $pageInfo is set in wp-proud-core on init
            global $pageInfo;
            if ( !empty( $pageInfo ) ) {
              if ( $req === false ) {
                $display = (bool) ( !empty( $pageInfo['parent_post'] ) 
                               ||   !empty( $pageInfo['parent_link'] ) )
                               && !$this->post_has_full_jumbotron_header();
              }
              // Parent header specific call
              // There must be a parent item
              elseif ( $req === 'title' ) {
                $display = (bool) !empty( $pageInfo['parent_post'] )
                               && !$this->post_has_full_jumbotron_header();
              }
              // Sidebar specific call
              // parent should MUST be agecy
              elseif ( $req === 'agency' ) {
                $display = (bool) !empty( $pageInfo['parent_post'] ) 
                               && !$this->post_has_full_jumbotron_header()
                               && !empty( $pageInfo['parent_post_type'] ) 
                               && $pageInfo['parent_post_type'] === 'agency';
              }
              // Sidebar specific call
              // parent should NOT be agency
              elseif ( $req === 'noagency' ) {
                $display = (bool) isset( $pageInfo['parent_link'] ) 
                               && !empty( $pageInfo['parent_link'] )
                               && !$this->post_has_full_jumbotron_header()
                               && (  empty( $pageInfo['parent_post_type'] ) 
                                  || $pageInfo['parent_post_type'] !== 'agency'
                                  );
              }
            }
          }
          
          if( $display ) {
            return apply_filters( 'proud/display_sidebar', $display, $req );
          }
        }

        /**
         * Adds hide featured image to post meta
         */
        public function hide_featured_image( $content ){
          global $post;
          $add_featured_box = $post->post_type === 'post'
                           || $post->post_type === 'page'
                           || $post->post_type === 'agency';
          if($add_featured_box) {
            $text = __( 'Don\'t display image on individual page.', 'prefix' );
            $id = 'hide_featured_image';
            $value = esc_attr( get_post_meta( $post->ID, $id, true ) );
            $label = '<label for="' . $id . '" class="selectit"><input name="' . $id . '" type="checkbox" id="' . $id . '" value="' . $value . ' "'. checked( $value, 1, false) .'> ' . $text .'</label>';
            $content .= $label;
          }
          return $content;
        }

        /**
         * Save featured image meta data when saved
         *
         * @param int $post_id The ID of the post.
         * @param post $post the post.
         */
        function save_featured_image_meta( $post_id, $post, $update ) {
          $value = 0;
          if ( isset( $_REQUEST['hide_featured_image'] ) ) {
              $value = 1;
          }
          // Set meta value to either 1 or 0
          update_post_meta( $post_id, 'hide_featured_image', $value );
          
        }

    } // ProudLayout

} // !class_exists( 'ProudLayout' )
