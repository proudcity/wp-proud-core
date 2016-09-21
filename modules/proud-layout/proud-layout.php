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
         * Helper function checks if page is full width
         */
        public function post_is_full_width(  ){
          if ( function_exists( 'siteorigin_panels_is_panel' ) && ( is_page() || get_post_type() == 'agency' ) ) {
            $id = get_the_ID();
            // @todo: fix this so we dont need to reference post ids
            return !empty( get_post_meta( get_the_ID(), 'panels_data', false ) ) 
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
