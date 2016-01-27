<?php
/**
 * @author ProudCity
 */


if ( !class_exists( 'ProudLayout' ) ) {

    class ProudLayout {

        private static $fields = [];
        private $title;
        private $afterHead = false;

        /**
        * PHP 5 Constructor
        */
        function __construct(){
          // Set Fields
          //self::$fields =  [
          //  'proud_hide_title' => __('Hide the title on singular page views.', 'proud-layout'), 
          //  'proud_full_width' => __('Make page full width.', 'proud-layout')
          //];
          //add_action( 'add_meta_boxes', array( $this, 'add_box' ) );
          //add_action( 'save_post', array( $this, 'on_save' ) );
          //add_action( 'delete_post', array( $this, 'on_delete' ) );
          add_action( 'wp_head', array( $this, 'head_insert' ), 3000 );
          // add_action( 'the_title', array( $this, 'wrap_title' ) );
          add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );

        } // __construct()

        public function post_is_full_width(  ){

           /*if( is_singular() ){

            global $post;

            $toggle = get_post_meta( $post->ID, 'proud_full_width', true );

            if( (bool) $toggle ){
              return true;
            } else {
              return false;
            }

          } else {
            return false;
          }*/
          if ( function_exists('siteorigin_panels_is_panel') && is_page() ) {
            $id = get_the_ID();
            return siteorigin_panels_is_panel() && ($id != 6 && $id != 149 && $id != 147);
          }
        }

        public function title_is_hidden(  ){

          /*f( is_singular() ){

            global $post;

            $toggle = get_post_meta( $post->ID, 'proud_hide_title', true );

            if( (bool) $toggle ){
              return true;
            } else {
              return false;
            }

          } else {
            return false;
          }*/
          return $this->post_is_full_width(  );

        } // title_is_hidden()


        public function head_insert(){

          // indicate that the header has run so we can hopefully prevent adding span tags to the meta attributes, etc.
          $this->afterHead = true;

        } // head_insert()

        public function add_box(){

          $posttypes = array( 'page', 'agency' );

          foreach ( $posttypes as $posttype ){
            add_meta_box( 'proud_layout', 'Layout Options', array( $this, 'build_box' ), $posttype, 'side' );
          }

        } // add_box()


        public function build_box( $post ){

          foreach(self::$fields as $field => $label) {
            $value = get_post_meta( $post->ID, $field, true );
            $checked = (bool) $value ? ' checked="checked"' : '';
            wp_nonce_field( $field . '_dononce', $field . '_noncename' );

            ?>
            <label><input type="checkbox" name="<?php echo $field; ?>" <?php echo $checked; ?> /> <?php echo $label; ?> </label>
            <?php
          }
        } // build_box()


        public function wrap_title( $content ){

          if(!$this->afterHead || !$this->title) { return; }

          if( $this->title_is_hidden() && $content == $this->title ){
            $content = '<span class="' . 'proud_hide_title' . '">' . $content . '</span>';
          }

          return $content;

        } // wrap_title()

        public function load_scripts(){
          // Grab the title early in case it's overridden later by extra loops.
          global $post;
          $this->title = $post->post_title;
        }


        public function on_save( $postID ){
          foreach(self::$fields as $field => $label) {
            if ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
              || !isset( $_POST[ $field . '_noncename' ] )
              || !wp_verify_nonce( $_POST[ $field . '_noncename' ], $field . '_dononce' ) ) {
              continue;
            }

            $old = get_post_meta( $postID, $field, true );
            $new = $_POST[ $field ] ;

            if( !is_null( $old ) ){
              if ( is_null( $new ) ){
                delete_post_meta( $postID, $field );
              } else {
                update_post_meta( $postID, $field, $new, $old );
              }
            } elseif ( !is_null( $new ) ){
              add_post_meta( $postID, $field, $new, true );
            }
          }
          return $postID;
        } // on_save()


        public function on_delete( $postID ){
          foreach(self::$fields as $field => $label) {
            delete_post_meta( $postID, $field );
          }
          return $postID;
        } // on_delete()


        // public function set_selector( $selector ){

        //   if( isset( $selector ) && is_string( $selector ) ){
        //     $this->selector = $selector;
        //   }

        // } // set_selector()


    } // ProudLayout

} // !class_exists( 'ProudLayout' )
