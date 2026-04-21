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
          // Add option to hide featured image. Accept $post_id (arg 2) so the
          // function works correctly in the AJAX context used when setting a
          // featured image — global $post is not reliable there.
          add_filter( 'admin_post_thumbnail_html', array( $this, 'hide_featured_image' ), 10, 2 );
          // Add save option
          add_action( 'save_post', array( $this, 'save_featured_image_meta' ), 10, 3 );
        }

        /**
         * Helper function loads site origin meta if available
         */
        public function get_site_origins_meta( ) {
          static $site_origins_meta = null;
          if( $site_origins_meta === null && function_exists( 'siteorigin_panels_is_panel' ) ) {
            $site_origins_meta = get_post_meta( get_the_ID(), 'panels_data', false );
          }
          if ( !empty( $site_origins_meta ) ) {
              return reset( $site_origins_meta );
          }
          return false;
        }

        /**
         * Helper function tests if breadcrumb present with full page-header
         *
         * @return boolean
         */
        public function post_has_full_breadcrumb( ) {
            static $has_breadcrumb = null;
            if ( $has_breadcrumb === null ) {
                // Set false to avoid another round
                $has_breadcrumb = false;
                $meta           = $this->get_site_origins_meta();
                if ( $meta ) {
                    // widget active, is breadcrumb, is page_header
                    if ( ! empty( $meta['widgets'][0]['panels_info']['class'] )
                         && $meta['widgets'][0]['panels_info']['class'] === 'BreadcrumbWidget'
                         && ! empty( $meta['widgets'][0]['page_header'] )
                    ) {
                        $has_breadcrumb = true;
                    }
                }
            }

            return $has_breadcrumb;
        }

        /**
         * Helper function tests if jumbotron is present on panel data
         * AS first item, AND is full row
         *
         * @returns name of jumbotron style
         */
        public function post_has_full_jumbotron_header( ) {
            static $has_jumbotron = null;
            if ( $has_jumbotron === null ) {
                // Set false to avoid another round
                $has_jumbotron = false;
                $meta          = $this->get_site_origins_meta();
                if ( $meta ) {
                    // Check if we're first jumbotron large
                    // widget active, is jumbotron, is full width
                    if ( ! empty( $meta['widgets'][0]['panels_info']['class'] )
                         && $meta['widgets'][0]['panels_info']['class'] === 'JumbotronHeader'
                         && ! empty( $meta['widgets'][0]['headertype'] )
                         && $meta['grids'][0]['cells'] === 1
                         && ! empty( $meta['grids'][0]['style']['row_stretch'] )
                         && ( $meta['grids'][0]['style']['row_stretch'] === 'full'
                              || $meta['grids'][0]['style']['row_stretch'] === 'full-stretched' )
                    ) {
                        // Set to header type.  Transparent
                        // navbar needs to know
                        $has_jumbotron = $meta['widgets'][0]['headertype'];
                    }
                }
            }

            return $has_jumbotron;
        }

        /**
         * Helper function checks if page is full width
         */
        public function post_is_full_width( ){

            $fullwidth = array( 'agency', 'proud-topic');

          if ( is_page() || in_array( get_post_type(), $fullwidth ) ) {
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

          // proud-topic posts are CPTs, not pages — handle them separately
          if ( is_singular( 'proud-topic' ) ) {
            if ( $req === false || $req === 'proud-topic' ) {
              $display = (bool) !$this->post_has_full_breadcrumb()
                             && !$this->post_has_full_jumbotron_header();
            }
          }
          elseif ( is_page() ) {
            // $pageInfo is set in wp-proud-core on init
            global $pageInfo;
            if ( !empty( $pageInfo['parent_post'] ) || !empty( $pageInfo['parent_link'] ) ) {
              if ( $req === false ) {
                $display = (bool) !$this->post_has_full_breadcrumb()
                               && !$this->post_has_full_jumbotron_header();
              }
              // Parent header specific call
              // There must be a parent item
              elseif ( $req === 'title' ) {
                $display = (bool) !empty( $pageInfo['parent_post'] )
                               && !$this->post_has_full_breadcrumb()
                               && !$this->post_has_full_jumbotron_header();
              }
              // Sidebar specific call
              // parent should MUST be agecy
              elseif ( $req === 'agency' ) {
                $display = (bool) !empty( $pageInfo['parent_post'] )
                               && !$this->post_has_full_breadcrumb()
                               && !$this->post_has_full_jumbotron_header()
                               && !empty( $pageInfo['parent_post_type'] ) 
                               && $pageInfo['parent_post_type'] === 'agency';
              }
              // Sidebar specific call
              // parent should be proud-topic
              elseif ( $req === 'proud-topic' ) {
                $display = (bool) !empty( $pageInfo['parent_post'] )
                               && !$this->post_has_full_breadcrumb()
                               && !$this->post_has_full_jumbotron_header()
                               && !empty( $pageInfo['parent_post_type'] )
                               && $pageInfo['parent_post_type'] === 'proud-topic';
              }
              // Sidebar specific call
              // parent should NOT be agency
              elseif ( $req === 'noagency' ) {
                $display = (bool) !empty( $pageInfo['parent_link'] )
                               && !$this->post_has_full_breadcrumb()
                               && !$this->post_has_full_jumbotron_header()
                               && (  empty( $pageInfo['parent_post_type'] ) 
                                  || $pageInfo['parent_post_type'] !== 'agency'
                                  );
              }
            }
          }
          
          if( $display ) {
            // Make sure breadcrumb runs to get proper hierarchy
            \Proud\Core\ProudBreadcrumb::build_breadcrumb();
            // Return true, filtered
            return apply_filters( 'proud/display_sidebar', $display, $req );
          }
        }

        /**
         * Adds hide featured image to post meta
         *
         * @param string   $content The existing featured image box HTML.
         * @param int|null $post_id Post ID passed by the filter (reliable in AJAX context).
         */
        public function hide_featured_image( $content, $post_id = null ){
          $post = get_post( $post_id );
          if ( ! $post ) {
            return $content;
          }
          $add_featured_box = $post->post_type === 'post'
                           || $post->post_type === 'page'
                           || $post->post_type === 'agency';
          if ( $add_featured_box ) {
            $text  = __( 'Don\'t display image on individual page.', 'prefix' );
            $id    = 'hide_featured_image';
            $saved = get_post_meta( $post->ID, $id, true );
            // $echo = false — prevents output being sent directly into the AJAX
            // response when the featured image is set via JavaScript.
            $nonce = wp_nonce_field( 'save_hide_featured_image', 'hide_featured_image_nonce', true, false );
            $label = '<label for="' . $id . '" class="selectit"><input name="' . $id . '" type="checkbox" id="' . $id . '" value="1"' . checked( $saved, 1, false ) . '> ' . esc_html( $text ) . '</label>';
            $content .= $nonce . $label;
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
          // Skip autosaves — they don't carry meta box fields, so running here
          // would reset a checked value to 0 before the real save can record it.
          if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
          }
          // Only process submissions from the edit-screen form.
          if ( ! isset( $_POST['hide_featured_image_nonce'] )
               || ! wp_verify_nonce( $_POST['hide_featured_image_nonce'], 'save_hide_featured_image' ) ) {
            return;
          }
          $value = isset( $_POST['hide_featured_image'] ) ? 1 : 0;
          update_post_meta( $post_id, 'hide_featured_image', $value );
        }

    } // ProudLayout

} // !class_exists( 'ProudLayout' )
