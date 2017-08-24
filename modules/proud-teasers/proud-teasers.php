<?php

namespace Proud\Core;

/**
 * Prints out a list of teasers
 */
if ( !class_exists( 'TeaserList' ) ) {

  // Prints out a teaser list from the args on creation
  class TeaserList {
    
    const _FORM_ID = 'proud-teaser-filter';
    const _TEMPLATE_PATH = 'templates/teaser-items/';
    private $post_type;
    private $display_type;
    private $query;
    private $filters;
    private $form = []; // FormHelper
    private $form_instance = []; // FormHelper values
    private $pagination;
    private $keyword;
    private $featured; // Including a featured
    private $hide; // Agency switch to hide elements
    private $columns; // Splits media view into columns
    private $options; // Extra options
    private $default_image; // Default image to use
    private $templates = []; // Holder for templates to avoid extra checks

    /** 
     * $post_type: post, event, ect
     * $display_type: list, mini, cards, ect 
     * $args format: 
     * 'posts_per_page' => 5,
     */
    public function __construct( 
      $post_type, 
      $display_type, 
      $args, 
      $filters = false, 
      $terms = false, 
      $pagination = false, 
      $options = []
    ) {

      $this->post_type    = !empty( $post_type ) ? $post_type : 'post';
      $this->display_type = !empty( $display_type ) ? $display_type : 'list';
      $this->featured = !empty( $options['featured'] ) ?  $options['featured'] : [];
      // @todo remove hide option in favor of specific
      $this->hide = !empty( $options['hide'] ) ?  $options['hide'] : [];
      $this->columns = !empty( $options['columns'] ) ?  $options['columns'] : [];
      $this->options = $options;
      // Use specific post ids?
      $this->specific_ids = !empty( $options['use_specific'] ) && !empty( $options['specific_ids'] ) 
                          ? $options['specific_ids'] 
                          : [];

      // Intercept search lists, set keyword
      global $proudsearch;
      if( $post_type == 'search' ) {
        // Collect get parameter
        $this->search_key = $proudsearch::_SEARCH_PARAM;
        // Add key for search
        $args['proud_teaser_search'] = true;
      }
      else {
        $this->search_key = 'filter_keyword';
      }

      // Limit to $terms
      if ( $terms ) {
        $args['tax_query'] = [
          [
            'taxonomy' => $this->get_taxonomy(),
            'field'    => 'term_id',
            'terms'    => $terms,
            'operator' => 'IN',
          ]
        ];

        $this->terms = $terms;
      }

      // Use specific post ids?
      if ( !empty( $this->specific_ids ) ) {
        $args['post__in'] = $this->specific_ids;
        $args[ 'posts_per_page' ] = 100;
      }
      // Add logic for Agency custom exclude checkbox ?
      else if ($this->post_type === 'agency') {
        $args['meta_query'] = array(
          array(
            'relation' => 'OR',
            array(
              'key' => 'list_exclude',
              'compare' => 'NOT EXISTS'
            ),
            array(
              'key' => 'list_exclude',
              'value' => '0',
              'compare' => '='
            ),
          )
        );
      }

      // Attach filters
      if( $filters ) {
        $this->build_filters( $terms );
        $this->process_post( $args );
      }
      // Pager?
      if( $pagination ) {
        $this->process_pagination( $args );
      }
      // Sort posts
      $this->add_sort( $args, $options );
      // Final build on args
      $args = array_merge( [
        'post_type' => $this->post_type == 'search' ? $proudsearch->search_whitelist(): $this->post_type,
        'post_status' => 'publish',
        'update_post_term_cache' => true, // don't retrieve post terms
        'update_post_meta_cache' => true, // don't retrieve post meta
      ] , $args );

      // Do we need to execute a featured (sticky) query?
      // @TODO make custom post types work with sticky
      /*if( $featured ) {
        $this->featured_query = new \WP_Query( array_merge( $args, [
          'post__in' => get_option('sticky_posts'),
          'posts_per_page' => 1
        ] ) );
        // No sticky post to display, so reset
        if( empty( $this->featured_query->posts ) ) {
          $this->featured_query = false;
        }
        else {
          // Get keys and exclude from main query
          $keys = []; 
          foreach ( $this->featured_query->posts as $post ) {
            $keys[] = $post->ID;
          }
          $args['post__not_in'] = $keys;
        }
      } */

      // If posts per page is set to 0, make it show all posts (up to 100)
      $args[ 'posts_per_page' ] = ( (int) $args[ 'posts_per_page' ] ) === 0 ? 100 : $args[ 'posts_per_page' ];
      // Build query
      $this->query = new \WP_Query( apply_filters( 
        'proud_teaser_query_args', 
        $args,
        [
          'type' => $this->post_type,
          'taxonomy' => $this->get_taxonomy(),
          'options' => $this->options,
          'form_id_base' => !empty( $this->form ) ? self::_FORM_ID : null,
          'form_instance' => $this->form_instance,
        ]
      ) );

      // Alter pagination links to deal with issues with documents, ext
      add_filter('get_pagenum_link', [$this, 'alter_pagination_path']);

      // Build default image
      $this->default_image = apply_filters(
        'proud_teaser_default_image',
        plugins_url( '/assets/images/teaser-card-default-image.png',  __FILE__  ),
        $this->post_type
      );
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
        case 'question':
          return 'faq-topic';
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
          '#type' => 'text',
          '#title' => __( 'Search Keywords', 'proud-teaser' ),
          '#args' => array(
            'placeholder' => __( 'Search Keywords', 'proud-teaser' ),
            'after' => '<i aria-hidden="true" class="fa fa-search form-control-search-icon"></i>',
          ),
          '#description' => ''
        ]
      ];

      $taxonomy = $this->get_taxonomy();
      // Grab categories
      if( $taxonomy ) {
        // Get categories from alternate source
        $categories = apply_filters( 
          'proud-teaser-filter-categories', 
          [],
          [
            'type' => $this->post_type, 
            'taxonomy' => $taxonomy, 
            'options' => $this->options 
          ] 
        );
        // Get local categories
        if( empty( $categories ) ) {
          $categories = get_categories( ['type' => $this->post_type, 'taxonomy' => $taxonomy] );
        }
        if( !empty( $categories ) ) {
          $options = [];
          foreach ($categories as $cat) {
            if ( !$terms || in_array( $cat->term_id, $terms ) ) {
              $options[$cat->term_id] = $cat->name;
            }
          };
          $this->filters['filter_categories'] = [
            '#title' => __( 'Category', 'proud-teaser' ),
            '#type' => 'checkboxes',
            '#options' => $options,
            '#description' => '',
            '#default_value' => 0
          ];
        }
      }

      // Post specific options
      switch( $this->post_type ) {
        
        case 'search': 
          // @TODO should this be hidden globally?  Or need a filter for search page only?
          // Hide search form in favor of search page
          $this->filters[$this->search_key]['#args']['class'] = 'hide';
          unset( $this->filters[$this->search_key]['#args']['after'] );
          $this->filters['title'] = [
            '#type' => 'html',
            '#html' => '<h4 class="margin-top-none">' . __('Filters', 'wp-proud-core') . '</h4>',
          ];
          // Add post type filter
          global $proudsearch;
          $options = array_merge(
            ['all' => __('All', 'wp-proud-core')],
            $proudsearch->search_whitelist( true )
          );
          $this->filters['filter_post_type'] = [
            '#title' => __( 'Type', 'proud-teaser' ),
            '#type' => 'radios',
            '#options' => $options,
            '#description' => '',
            '#default_value' => 'all'
          ];
          break;

        case 'job_listing': 
          $this->filters['filter_show_filled'] = [
            '#title' => __( 'Show filled positions?', 'proud-teaser' ),
            '#type' => 'checkbox',
            '#return_value' => '1',
            '#label_above' => true,
            '#replace_title' => __( 'Yes', 'proud-teaser' ),
            '#default_value' => false
          ];
          break;
      }
      // Filter filters
      $this->filters = apply_filters( 
        'proud-teaser-filters', 
        $this->filters, 
        [
          'type' => $this->post_type, 
          'options' => $this->options, 
        ]
      );

      // Init form
      $this->form = new \Proud\Core\FormHelper( 'proud-teaser-filter', $this->filters );
    }

    /**
     * Processes the incoming filters, build arguments
     */
    private function process_post( &$args ) {
      // Grab values
      foreach( $this->filters as $key => $filter ) {
        if(!empty( $_REQUEST[$key] ) ) {
          $req_val = wp_unslash( $_REQUEST[$key] );
          switch( $key ) {
            // taxonomies
            case 'filter_categories':
              $taxonomy = $this->get_taxonomy();
              if($taxonomy) {
                $terms = [];
                foreach( $req_val as $cat_key ) {
                  $terms[] = (int) sanitize_text_field( $cat_key );
                }
                $args['tax_query'] = [
                  [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $terms,
                    'operator' => 'IN',
                  ]
                ];
              }
              break;

            // keyword search
            case $this->search_key:
              $args['s'] = sanitize_text_field( $req_val );
              break;

            // Post type filter
            case 'filter_post_type':
              $type = sanitize_text_field( $req_val );
              if( $type !== 'all' ) {
                $args['post_type'] = $type;
              }
              break;

            // Jobs show filtered positions
            case 'filter_show_filled':
              $args['meta_query'] = array(
                  'relation' => 'OR',
                  array(
                      'key' => '_filled',
                      'compare' => 'NOT EXISTS'
                  ),
                  array(
                      'key' => '_filled',
                      'compare' => 'EXISTS'
                  )
              );

          }
          $this->form_instance[$key] = $req_val;
        }
        else {
          $this->form_instance[$key] = isset( $filter['#default_value'] ) 
                                     ? $filter['#default_value']  
                                     : '';
        }
      }
    }

    /**
     * Alters pager paths from /news/page/2?filter=blah -> /news?pager=2&filter=blah
     */
    public function alter_pagination_path($result) {
      // We have pagination active
      if($this->pagination) {
        // remove our pager param in every situation
        $result = preg_replace("/\?pager\=[0-9]+\&*/", "?", $result);
        // rebuild new pager? 
        $preg_page = "/\/page\/([0-9]*?)\//";
        if(preg_match($preg_page, $result)) {
          // If other params exist
          $result = preg_replace("/\?/", "&", $result);
          // If place in pager
          $result = preg_replace($preg_page, "?pager=$1", $result);
        }
        // remove trailing ? if present
        else {
          $result = preg_replace("/\?$/", "", $result);
        }
      }
      return $result;
    }

    /**
     * Processes pagination if enabled
     * Converts our ?pager=2 into standard WP pager vars
     */
    private function process_pagination(&$args) {
      $this->pagination = true;
      // $paged = ( get_query_var( 'pager' ) ) ? get_query_var( 'pager' ) : 1;
      $pager = !empty($_REQUEST['pager']) ? $_REQUEST['pager'] : 1;
      // Set the global paged var
      global $paged;
      $paged = sanitize_text_field( $pager );
      // Set the paged vars
      set_query_var( 'paged', $paged );
      // Set the paged vars
      $args['paged'] = $paged;
    }

    /**
     * Adds user-defined sort
     */
    private function apply_user_sort( &$args, $options ) {
      // @TODO add processing for post types?  Add jobs, events?
      $args['orderby'] = $options['sort_by'];
      $args['order']   = $options['sort_order'];
    }

    /**
     * Adds sort
     */
    private function add_sort( &$args, $options ) {

      // Have user defined sort?
      if( !empty( $options['sort_by'] ) && !empty( $options['sort_order'] ) ) {
        $this->apply_user_sort( $args, $options );
        return;
      }

      switch( $this->post_type ) {
        case 'post':
          $args['orderby'] = 'date';
          $args['order']   = 'DESC';
          break;

        case 'staff-member':
        case 'question':
        case 'issue':
        case 'document':
        case 'agency':
          $args['orderby'] = 'menu_order';
          $args['order']   = 'ASC';
          break;

        case 'job_listing': 
          // Featured is the "sticky" option for jobs
          $args['orderby'] = ['_featured' => 'DESC', 'date' => 'DESC'];
          $args['meta_key']     = '_featured';
          // If the filter for "Show filled" is NOT active
          if( !isset( $args['meta_query'] ) ) {
            $args['meta_query'] = array(
                'relation' => 'OR',
                array(
                    'key' => '_filled',
                    'compare' => '!=',
                    'value' => '1'
                )
            );
          }
          break;

        // Search +  events need to filter out older content
        case 'event':
        case 'search':
          // http://www.billerickson.net/wp-query-sort-by-meta/
          $query_key =  '_end_ts';
          // @ TODO figure out optimized query that allows 
          // 1. All day
          // 2. ENd time greater than now
          // For now, just does specificity == beginning of day
          $time = current_time( 'timestamp' );
          $day_start = strtotime( date( 'Y-m-d', $time ) );
          // Event
          if( $this->post_type === 'event' ) {
            $args['orderby']    = 'meta_value_num';
            $args['meta_key']   = $query_key;
            $args['order']      = 'ASC';
            $args['meta_query'] = array(
              'relation' => 'AND',
              array(
                  'key' => $query_key,
                  'compare' => 'EXISTS'
              ),
              array(
                  'key' => $query_key,
                  'compare' => '>=',
                  'value' => $day_start
              ),
            );
          }
          // Search
          else {
            $args['meta_query'] = array(
              'relation' => 'OR',
              array(
                  'key' => $query_key,
                  'compare' => 'NOT EXISTS'
              ),
              array(
                  'key' => $query_key,
                  'compare' => '>=',
                  'value' => $day_start
              ),
            );
          }
          
          break;

        default:
          $args['orderby'] = 'title';
          $args['order']   = 'ASC';
          break;
      }
    }

    /** 
     * Process columns if applicable
     */
    private function column_row( &$row_open, &$row_close, &$column_classes, $post_count, $current ) {
      if( $this->columns ) {
        $column_classes = ' col-md-6';
        // Open row?
        if( $current%2 === 1 ) {
          $row_open = '<div class="row">';
        }
        // Close row?
        if( ( $post_count === $current ) || $current%2 === 0 ) {
          $row_close = '</div>';
        }
      }
    }

    /**
     * Wraps teaser list: open
     */
    private function print_wrapper_open() {
      $class = '';
      $attrs = '';
      switch( $this->display_type ) {
        case 'search':
        case 'list':
          $class = 'teaser-list';
          break;

        case 'media':
          $class = 'media-list';
          break;

        case 'mini':
          $class = 'title-list list-unstyled';
          break;

        case 'cards':
          $class = 'card-columns card-columns-xs-1 card-columns-sm-2 card-columns-md-3 card-columns-equalize';
          break;

        case 'icons':
          $class = 'card-columns card-columns-xs-2 card-columns-md-3 card-columns-equalize';
          break;
      }

      // Try for post type
      $template = self::_TEMPLATE_PATH . 'teaser-' . $this->post_type . '-' . $this->display_type . '-header.php';
      $file = "";
      // Try to load template from theme
      if( '' === ( $file = locate_template( $template ) ) ) {
        // Try for generic theme
        $template = self::_TEMPLATE_PATH . 'teaser-' . $this->display_type . '-header.php';
        if( '' === ( $file = locate_template( $template ) ) ) {
          // Try for generic locally
          $file = plugin_dir_path( __FILE__ ) . 'templates/teaser-' . $this->display_type . '-header.php';
          if( !file_exists( $file ) ) {
            // Just load default
            $file = plugin_dir_path( __FILE__ ) . 'templates/teaser-header.php';
          }
        }
      }
      include($file);     
    }

    /**
     * Prints featured content
     */
    private function print_featured() {
      // Don't allow non standard featured
      $allowed = ( $this->display_type === 'list' || $this->display_type === 'mini' );
      if( !$allowed ) {
        return;
      }
      if( empty( $templates['featured'] ) ) {
        // Try for post type
        $template = self::_TEMPLATE_PATH . 'teaser-' . $this->post_type . '-' . $this->display_type . '-featured.php';
        $file = "";
        // Try to load template from theme
        if( '' === ( $file = locate_template( $template ) ) ) {
          // Try for generic theme
          $template = self::_TEMPLATE_PATH . 'teaser-' . $this->display_type . '-featured.php';
          if( '' === ( $file = locate_template( $template ) ) ) {
            // Try for generic locally
            $file = plugin_dir_path( __FILE__ ) . 'templates/teaser-' . $this->display_type . '-featured.php';
            if( !file_exists( $file ) ) {
              // Just load default
              $file = plugin_dir_path( __FILE__ ) . 'templates/teaser-featured.php';
            }
          }
        }
        $templates['featured'] = $file;
      }

      // Init post
      $this->query->the_post();

      // Load Meta info?
      $meta;
      global $post;

      include( $templates['featured'] );
    }

    /**
     * Prints teaser list
     */
    private function print_content( $post_count, $current ) {
      if( empty( $templates['content'] ) ) {
        // Try for post type
        $template = self::_TEMPLATE_PATH . 'teaser-' . $this->post_type . '-' . $this->display_type . '.php';
        $file = "";

        // Try to load template from theme
        if( '' === ( $file = locate_template( $template ) ) ) {
          // Try for generic
          $template = self::_TEMPLATE_PATH . 'teaser-' . $this->display_type . '.php';
          if( '' === ( $file = locate_template( $template ) ) ) {
            // Just load from here
            $file = plugin_dir_path( __FILE__ ) . 'templates/teaser-' . $this->display_type . '.php';
          }
        }
        $templates['content'] = $file;
      }

      // Init post
      $this->query->the_post();
      // Load Meta info?
      $meta = [];
      global $post;
      // Post type
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
          // if (empty($this->terms) || count($this->terms) > 1) {
          //   $terms = wp_get_post_terms( $post->ID, 'document_taxonomy', array("fields" => "all"));    
          // } 
          $terms = wp_get_post_terms( $post->ID, 'document_taxonomy', array("fields" => "all"));
          break;
      }
      $hide = $this->hide;
      // Init column vars
      $row_open = '';
      $row_close = '';
      $column_classes = '';
      // Display type
      switch( $this->display_type ) {
        case 'mini':
          if(in_the_loop()) {
            $header_tag = 'h4';
          }
          else {
            $header_tag = 'h5';
          }
          break;

        case 'media':
          // Add columns if applicable
          $this->column_row(
            $row_open,
            $row_close,
            $column_classes,
            $post_count,
            $current
          );
          break;

        // Build default images
        case 'cards':
          if( !has_post_thumbnail() ) {
            $default_image = $this->default_image; 
          }
          break;
      }
      // Filter post
      $post = apply_filters( 'proud_teaser_teaser_post', $post, $this->post_type, $meta );
      // Print 
      include( $templates['content'] );
    }


    /**
     * Wraps teaser list: open
     */
    private function print_wrapper_close() {
      // Try for post type
      $template = self::_TEMPLATE_PATH . 'teaser-' . $this->post_type . '-' . $this->display_type . '-footer.php';
      $file = "";
      // Try to load template from theme
      if( '' === ( $file = locate_template( $template ) ) ) {
        // Try for generic theme
        $template = self::_TEMPLATE_PATH . 'teaser-' . $this->display_type . '-footer.php';
        if( '' === ( $file = locate_template( $template ) ) ) {
          // Try for generic local
          $file = plugin_dir_path( __FILE__ ) . 'templates/teaser-' . $this->display_type . '-footer.php';
          if( !file_exists( $file ) ) {
            /// Just load default
            $file = plugin_dir_path( __FILE__ ) . 'templates/teaser-footer.php';
          }
        }
      }
      include( $file );     
    }

    /**
     * Prints empty behavior
     */
    private function print_empty() {
      // Try for post type + display
      $template = self::_TEMPLATE_PATH . 'teasers-empty-' . $this->post_type . '-' . $this->display_type . '.php';
      $file = "";
      // Try to load template from theme
      if( !( $file = locate_template( $template ) ) ) {
        // Try for generic post type
        $template = self::_TEMPLATE_PATH .  'teasers-empty-' . $this->post_type . '.php';
        if( !( $file = locate_template( $template ) ) ) {
          // Just load from here
          $file = plugin_dir_path( __FILE__ ) . 'templates/teasers-empty.php';
        }
      }
      include( $file );
    }

    /**
     * Prints content pager
     */
    private function print_pagination() {
      $template = self::_TEMPLATE_PATH . 'pagination-default.php';
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
        if( !empty( $this->featured ) ) {
          $this->print_featured();
        }
        // Get some stats 
        $post_count = count($this->query->posts);
        $current = 1;
        while ( $this->query->have_posts() ) :
          $this->print_content($post_count, $current);
          $current++;
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
      $this->form->printForm( [
        'button_text' => __( $button_text, 'proud-teaser' ),
        'instance' => $this->form_instance,
        'fields' => $this->filters
      ] );
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
      // Call static version
      $values = \Proud\Core\FormHelper::formValues( $_POST, 'proud-teaser-filter' );
      if( empty( $values ) ) {
        return;
      }
      foreach ( $values as $key => $value ) {
        // Sanitize
        $key = sanitize_key( $key );
        global $proudsearch;
        $retain_filter = ( $key === $proudsearch::_SEARCH_PARAM || strpos( $key, 'filter_' ) === 0 ) 
                      && !empty( $value );
        if( $retain_filter ) {
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
        $url = strtok($_SERVER['REQUEST_URI'],'?');
        wp_redirect( sanitize_text_field( $url ) );
      }
      exit();
    }
  }
}

// Search submit
add_action( 'init', __NAMESPACE__ . '\\process_filter_submit' );