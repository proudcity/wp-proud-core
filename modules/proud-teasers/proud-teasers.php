<?php

namespace Proud\Core;

/**
 * Sets up options for listing on pages
 */
if ( !class_exists( 'TeaserOptions' ) ) {

  class TeaserOptions {

    private static $fields = [];

    function __construct() {
      // Set Fields
      self::$fields =  [
        'proud_teaser_content' => [
          '#title' => __('Content Type', 'proud-teaser'),
          '#type' => 'select',
          '#options' => [
            'post' => __('News', 'proud-teaser'),
            'event' => __('Events', 'proud-teaser'),
            'agency' => __('Agencies', 'proud-teaser'),
          ]
        ],
        'proud_teaser_display' => [
          '#title' => __('Teaser Display Mode', 'proud-teaser'),
          '#type' => 'select',
          '#options' => [
            'list' => __('List View', 'proud-teaser'),
            'mini' => __('Mini List', 'proud-teaser'),
            'cards' => __('Card View', 'proud-teaser'),
          ]
        ]
      ];
      // Actions
      add_action( 'admin_init', array( $this, 'add_teaser_options' ) );
      add_action( 'save_post', array( $this, 'on_save' ) );
      add_action( 'delete_post', array( $this, 'on_delete' ) );
    }

    public function add_teaser_options()
    {

      $post_id = $_GET['post'] ? $_GET['post'] : $_POST['post_ID'] ;
      $template_file = get_post_meta( $post_id,'_wp_page_template', TRUE );
      // d($template_file);
      // check for a template type
      if ( strpos( $template_file, 'teasers.php' ) > 0 ) {
        add_meta_box( 'proud_teaser', 'Teaser Configuration', array( $this, 'build_box' ), 'page', 'side' );
      }

      add_action('save_post','my_meta_save');
    }

    public function build_box( $post ){

      foreach( self::$fields as $id => $field ) {
        $value = get_post_meta( $post->ID, $id, true );
        wp_nonce_field( $id . '_dononce', $id . '_noncename' );
        ?>

        <label><?php echo $field['#title']; ?></label>
        <select name="<?php echo $id; ?>">
          <?php foreach ( $field['#options'] as $key => $label ): ?>
            <option value="<?php echo $key; ?>"<?php if($key == $value) print ' selected="selected"';?>><?php echo $label; ?></option>
          <?php endforeach; ?>
        </select>
        <?php
      }
    } // build_box()

    public function on_save( $postID ){
      // d('uhhhh');
      foreach( self::$fields as $id => $field ) {
        if ( ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
          || !isset( $_POST[ $id . '_noncename' ] )
          || !wp_verify_nonce( $_POST[ $id . '_noncename' ], $id . '_dononce' ) ) {
          continue;
        }

        $old = get_post_meta( $postID, $id, true );
        print $old;
        $new = $_POST[ $id ] ;
        print $new;
        if( $old ){
          if ( is_null( $new ) ){
            delete_post_meta( $postID, $id );
          } else {
            update_post_meta( $postID, $id, $new, $old );
          }
        } elseif ( !is_null( $new ) ){
          add_post_meta( $postID, $id, $new, true );
        }
      }
      return $postID;
    } // on_save()

    public function on_delete( $postID ){
      foreach( self::$fields as $id => $field ) {
        delete_post_meta( $postID, $id );
      }
      return $postID;
    } // on_delete()
  }

  // init
  $options = new TeaserOptions();
}

/**
 * Prints out a list of teasers
 */
if ( !class_exists( 'TeaserList' ) ) {

  // Prints out a teaser list from the args on creation
  class TeaserList {
    
    private $template_path = 'templates/teaser-items/';
    private $post_type;
    private $display_type;
    private $query;
    private $filters;

    /** $post_type: post, event, ect
     * $display_type: list, mini, cards, ect 
     * $args format: 
     * 'posts_per_page' => 5,
     */
    public function __construct( $post_type, $display_type, $args, $filters = false ) {
      $this->post_type    = !empty( $post_type ) ? $post_type : 'post';
      $this->display_type = !empty( $display_type ) ? $display_type : 'list';

      // Attach filters?
      if($filters) {
        $this->build_filters();
        $this->process_post($args);
      }

      $this->add_sort($args);

      $args = array_merge( [
        'post_type' => $this->post_type, 
        'post_status' => 'publish',
        'update_post_term_cache' => false, // don't retrieve post terms
        'update_post_meta_cache' => false, // don't retrieve post meta
      ] , $args );
      $this->query = new \WP_Query( $args );
    }

    /**
     * Builds out filters if present
     */
    private function build_filters() {
      switch( $this->post_type ) {
        default: 
          $this->filters = [
            'filter_keyword' => [
              '#id' => 'filter_keyword',
              '#type' => 'text',
              '#name' => 'filter_keyword',
              '#title' => __( 'Search Keywords', 'proud-teaser' ),
            ]
          ];
          // Grab categories
          $categories = get_categories( ['type' => $this->post_type] );
          if(!empty($categories)) {
            $options = [];
            foreach ($categories as $cat) {
              $options[$cat->term_id] = $cat->name;
            };
            $this->filters['filter_categories'] = [
              '#id' => 'filter_categories',
              '#title' => __( 'Category', 'proud-teaser' ),
              '#type' => 'checkboxes',
              '#name' => 'filter_categories',
              '#options' => $options
            ];
          }
          break;
      }
    }

    /**
     * Processes the incoming filters, build arguments
     */
    private function process_post(&$args) {
      foreach( $this->filters as $key => $filter ) {
        if(!empty( $_REQUEST[$key] ) ) {
          switch( $key ) {
            // taxonomies
            case 'filter_categories':
              $values = [];
              foreach( $_REQUEST[$key] as $cat_key ) {
                $values[] = (int) sanitize_text_field( $cat_key );
              }
              $args['category__in'] = $values;
              break;

            // keyword search
            case 'filter_keyword':
              $args['s'] = sanitize_text_field( $_REQUEST[$key] );
              break;
          }
          $this->filters[$key]['#value'] = $_REQUEST[$key];
        }
      }
    }


    /**
     * Adds sort
     */
    private function add_sort(&$args) {
      switch( $this->post_type ) {
        case 'post':
          $args['orderby'] = 'date';
          $args['order']   = 'DESC';
          break;

        case 'event':
          // http://www.billerickson.net/wp-query-sort-by-meta/
          $args['orderby'] = 'meta_value_num';
          $args['meta_key']     = '_start_ts';
          $args['order']    = 'ASC';
          $args['meta_query'] = array(
              'relation' => 'AND',
              array(
                  'key' => '_start_ts',
                  'compare' => 'EXISTS'
              ),
              array(
                  'key' => '_start_ts',
                  'compare' => '>=',
                  'value' => time()
              )
          );
          break;

        default:
          $args['orderby'] = 'title';
          $args['order']   = 'ASC';
          break;
      }
    }

    /**
     * Wraps teaser list: open
     */
    private function print_wrapper_open() {
      switch( $this->display_type ) {
        case 'list':
          echo '<div class="teaser-list">';
          break;

        case 'mini':
          echo '<ul class="title-list list-unstyled">';
          break;

        case 'cards':
          echo '<div class="card-columns card-columns-xs-1 card-columns-sm-2 card-columns-md-3 card-columns-equalize">';
      }
    }

    /**
     * Prints teaser list
     */
    private function print_content() {
      // Try for post type
      $template = $this->template_path . 'teaser-' . $this->post_type . '-' . $this->display_type . '.php';
      $file = "";
      // Try to load template from theme
      if( '' === ( $file = locate_template( $template ) ) ) {
        // Try for generic
        $template = $this->template_path . 'teaser-' . $this->display_type . '.php';
        if( '' === ( $file = locate_template( $template ) ) ) {
          // Just load from here
          $file = plugin_dir_path(__FILE__) . 'templates/teaser-' . $this->display_type . '.php';
        }
      }
      // Init post
      $this->query->the_post();
      include($file);
    }

    /**
     * Wraps teaser list: close
     */
    private function print_wrapper_close() {
      switch( $this->display_type ) {
        case 'list':
          echo "</div>";
          break;

        case 'mini':
          echo "</ul>";
          break;

        case 'cards':
          echo "</div>";
          break;
      }
    }

    /**
     * Prints empty behavior
     */
    private function print_empty() {
      // Try for post type + display
      $template = $this->template_path . 'teasers-empty-' . $this->post_type . '-' . $this->display_type . '.php';
      $file = "";
      // Try to load template from theme
      if( !( $file = locate_template( $template ) ) ) {
        // Try for generic post type
        $template = $this->template_path .  'teasers-empty-' . $this->post_type . '.php';
        if( !( $file = locate_template( $template ) ) ) {
          // Just load from here
          $file = plugin_dir_path(__FILE__) . 'templates/teasers-empty.php';
        }
      }
      include($file);
    }

    /**
     * Function runs through, builds entire teaser list
     */
    public function print_list() {
      if( $this->query->have_posts() ) {
        $this->print_wrapper_open();
        while ( $this->query->have_posts() ) :
          $this->print_content();
        endwhile;
        $this->print_wrapper_close();
      }
      else {
        $this->print_empty();
      }
      // Restore original Query
      wp_reset_postdata();
    }

    /**
     * Prints list filters
     */
    public function print_filters() {
      // Grab form helper
      $form = new \Proud\Core\FormHelper( 'proud-teaser-filter', $this->filters );
      $form->printForm( );
    }
  }
}

/**
 * Process potential filter input
 */
function process_filter_submit() {
  // Do we have post?
  if(isset($_POST['_wpnonce'])) {
    // See if its our filter submission
    if( wp_verify_nonce( $_POST['_wpnonce'], 'proud-teaser-filter' ) ) {
      $params = [];
      foreach ( $_POST as $key => $value ) {
        // Sanitize
        $key = sanitize_key( $key );
        if( strpos( $key, 'filter_' ) === 0 && !empty( $value ) ) {
          echo $key;
          if( is_array( $value ) ) {
            $params[] = $key . '[]=' . urlencode( sanitize_text_field( implode('&' . $key . '[]=', $value ) ) );
          }
          else {
            $params[] = $key . '=' . urlencode( sanitize_text_field( $value ) );
          }
        }
      }
      if( !empty( $params ) ) {
        wp_redirect( get_permalink() . '?' . implode( '&', $params ) );
      }
      else {
        wp_redirect( sanitize_text_field( $_REQUEST['q'] ) );
      }
      exit();
    }
  }
}

// Search submit
add_action( 'init', __NAMESPACE__ . '\\process_filter_submit' );