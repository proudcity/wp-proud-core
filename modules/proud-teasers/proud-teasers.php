<?php

namespace Proud\Core;

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
    private $pagination;
    private $keyword;

    /** $post_type: post, event, ect
     * $display_type: list, mini, cards, ect 
     * $args format: 
     * 'posts_per_page' => 5,
     */
    public function __construct( $post_type, $display_type, $args, $filters = false, $terms = false, $pagination = false ) {

      $this->post_type    = !empty( $post_type ) ? $post_type : 'post';
      $this->display_type = !empty( $display_type ) ? $display_type : 'list';

      // Intercept search lists, set keyword
      if($post_type == 'search') {
        global $proudsearch;
        // Collect get parameter
        $this->search_key = $proudsearch::_SEARCH_PARAM;
      }
      else {
        $this->search_key = 'filter_keyword';
      }

      // Limit to $terms
      if ($terms) {
        $args['tax_query'] = [
          [
            'taxonomy' => $this->get_taxonomy(),
            'field'    => 'term_id',
            'terms'    => $terms,
            'operator' => 'IN',
          ]
        ];
      }

      // Attach filters
      if($filters) {
        $this->build_filters( $terms );
        $this->process_post( $args );
      }
      // Pager?
      if($pagination) {
        $this->process_pagination( $args );
      }

      //print_r($args);

      $this->add_sort($args);

      $args = array_merge( [
        'post_type' => $this->post_type == 'search' ? 'any' : $this->post_type, 
        'post_status' => 'publish',
        'update_post_term_cache' => false, // don't retrieve post terms
        'update_post_meta_cache' => false, // don't retrieve post meta
      ] , $args );
      $this->query = new \WP_Query( $args );
    }

    /**
     * Gets taxonomy for post type
     */
    public function get_taxonomy( $post_type = false ) {

      switch( $post_type ? $post_type : $this->post_type ) {
        case 'event':
          return 'event-categories';
        case 'staff-member':
          return 'staff-member-group';
        case 'post':
          return 'category';
        case 'job_listing':
          return 'job_listing_type';
        case 'document':
          return 'document_taxonomy';
      }
      return false;
    }


    /**
     * Attaches scripts to list
     */
    public function load_resources() {
      switch( $this->post_type ) {
        case 'event': 
          wp_enqueue_style('addtocalendar','//addtocalendar.com/atc/1.5/atc-style-blue.css');
          wp_enqueue_script('addtocalendar','//addtocalendar.com/atc/1.5/atc.min.js', [], false, true);
          break;
      }
    }


    /**
     * Builds out filters if present
     */
    private function build_filters( $terms ) {
      $this->filters = [
        $this->search_key => [
          '#id' => $this->search_key,
          '#type' => 'text',
          '#title' => __( 'Search Keywords', 'proud-teaser' ),
          '#name' => $this->search_key,
          '#args' => array(
            'placeholder' => __( 'Search Keywords', 'proud-teaser' ),
            'after' => '<i class="fa fa-search form-control-search-icon"></i>',
          ),
          '#description' => ''
        ]
      ];

      $taxonomy = $this->get_taxonomy();
      // Grab categories
      if( $taxonomy ) {
        $categories = get_categories( ['type' => $this->post_type, 'taxonomy' => $taxonomy] );
        if(!empty($categories)) {
          $options = [];
          foreach ($categories as $cat) {
            if (!$terms || in_array($cat->term_id, $terms)) {
              $options[$cat->term_id] = $cat->name;
            }
          };
          $this->filters['filter_categories'] = [
            '#id' => 'filter_categories',
            '#title' => __( 'Category', 'proud-teaser' ),
            '#type' => 'checkboxes',
            '#name' => 'filter_categories',
            '#options' => $options,
            '#description' => ''
          ];
        }
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
              $taxonomy = $this->get_taxonomy();
              if($taxonomy) {
                $values = [];
                foreach( $_REQUEST[$key] as $cat_key ) {
                  $values[] = (int) sanitize_text_field( $cat_key );
                }
                $args['tax_query'] = [
                  [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $values,
                    'operator' => 'IN',
                  ]
                ];
              }
              break;

            // keyword search
            case $this->search_key:
              $args['s'] = sanitize_text_field( $_REQUEST[$key] );
              break;
          }
          $this->filters[$key]['#value'] = $_REQUEST[$key];
        }
        else {
          $this->filters[$key]['#value'] = ($key == 'filter_categories') ? 0 : '';
        }
      }
    }

    /**
     * Processes pagination if enabled
     */
    private function process_pagination(&$args) {
      $this->pagination = true;
      $paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
      $args['paged'] = $paged;
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

        case 'staff-member':
          $args['orderby'] = 'menu_order';
          $args['order']   = 'ASC';
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

        // Don't order for search
        case 'search':
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
          break;

        case 'table':
          echo '<div class="table-responsive"><table class="table table-striped">';
          switch( $this->post_type ) {
            case 'agency': 
               echo sprintf( '<thead><tr><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th></tr></thead>',    
                 __( 'Agency', 'proud-agency' ),   
                 __( 'Person', 'proud-agency' ),   
                 __( 'Phone', 'proud-teaser' ),    
                 __( 'Email', 'proud-teaser' ),    
                 __( 'Social', 'proud-teaser' )    
               );    
               break;
            case 'staff-member':
              echo sprintf( '<thead><tr><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th><th>%s</th></tr></thead>',
                __( 'Name', 'proud-teaser' ),
                __( 'Position', 'proud-teaser' ),
                __( 'Agency', 'proud-agency' ),     
                __( 'Phone', 'proud-teaser' ),    
                __( 'Email', 'proud-teaser' ),    
                __( 'Social', 'proud-teaser' )    
              );
              break;
            case 'document':
              echo sprintf( '<thead><tr><th>%s</th><th>%s</th><th>%s</th><th>%s</th></tr></thead>',
                __( 'Name', 'proud-teaser' ),
                __( 'Category', 'proud-teaser' ),
                __( 'Date', 'proud-teaser' ),
                __( 'Download', 'proud-teaser' )
              );
              break;
            case 'job_listing':
              echo sprintf( '<thead><tr><th>%s</th><th>%s</th><th>%s</th></tr></thead>',
                __( 'Name', 'proud-teaser' ),
                __( 'Position', 'proud-teaser' ),
                __( 'Phone', 'proud-teaser' )
              );
              break;
            default:
              break;
          }
          echo '<tbody>';
          break;
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
          $file = plugin_dir_path( __FILE__ ) . 'templates/teaser-' . $this->display_type . '.php';
        }
      }
      // Init post
      $this->query->the_post();
      // Load Meta info?
      $meta;
      global $post;
      switch( $this->post_type ) {
        case 'staff-member':
          $terms = wp_get_post_terms( $post->ID, 'staff-member-group', array("fields" => "all"));
          // Intentionally no break
        case 'agency':
        case 'event':
          $meta = get_post_meta( $post->ID );
          break;
        case 'search':
          global $proudsearch;
          $meta = get_post_meta( $post->ID );
          $search_meta = $proudsearch->post_meta( $post->post_type );
          break;
        case 'document':    
          $src = get_post_meta( $post->ID, 'document', true );    
          $filename = get_post_meta( $post->ID, 'document_filename', true );    
          $meta = json_decode(get_post_meta( $post->ID, 'document_meta', true ));   
          $terms = wp_get_post_terms( $post->ID, 'document_taxonomy', array("fields" => "all"));    
          break;
      }

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

        case 'table':
          echo "</tbody></table></div>";
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
          $file = plugin_dir_path( __FILE__ ) . 'templates/teasers-empty.php';
        }
      }
      include($file);
    }

    /**
     * Prints content pager
     */
    private function print_pagination() {
      $template = $this->template_path . 'pagination-default.php';
      $file = "";
      // Try to load template from theme
      if( !( $file = locate_template( $template ) ) ) {
        // Just load from here
        $file = plugin_dir_path( __FILE__ ) . 'templates/pagination-default.php';
      }
      switch( $this->post_type ) {
        case 'post':
          $prev_text = '&laquo; Older';
          $next_text = 'Newer &raquo;';
          $prev = get_next_posts_link( $prev_text, $this->query->max_num_pages );
          $next = get_previous_posts_link( $next_text );
          break;

        // Switched around since ordering is "ASC"
        case 'event':
        case 'search';
          $prev_text = '&laquo; Previous';
          $next_text = 'More &raquo;';
          $prev = get_previous_posts_link( $prev_text );
          $next = get_next_posts_link( $next_text, $this->query->max_num_pages );
          break;

        default: 
          $prev_text = '&laquo; Previous';
          $next_text = 'Next &raquo;';
          $prev = get_next_posts_link( $prev_text, $this->query->max_num_pages );
          $next = get_previous_posts_link( $next_text );
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
        // Close wrapper
        $this->print_wrapper_close();
        // Print pager?
        if( $this->pagination ) {
          $this->print_pagination();
        }
        // Load Resources
        $this->load_resources();
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
    public function print_filters( $include_filters = null, $button_text = 'Filter' ) {

      // Remove filters that we don't want to show
      if ( !empty($include_filters) ) {
        foreach ( $this->filters as $key => $filter ) {
          if (!in_array($key, $include_filters)) {
            unset($this->filters[$key]);
          }
        }
      }

      // Grab form helper
      $form = new \Proud\Core\FormHelper( 'proud-teaser-filter', $this->filters );
      $form->printForm( ['button_text' => __( $button_text, 'proud-teaser' )] );
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
            foreach ($value as $val) {
              $params[] = $key . '[]=' . urlencode( sanitize_text_field( $val ) );
            }
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