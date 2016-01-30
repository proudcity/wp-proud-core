<?php
/**
 * @author ProudCity
 */

use Proud\Core;

class TeaserFilterTracker {
  static $teaser_class;

  function __construct($teaser_class = null) {
    if($teaser_class) {
      self::$teaser_class = $teaser_class;
    }
  }

  public function print_filters() {
    if( self::$teaser_class ) {
      self::$teaser_class->print_filters();
    }
  }
}

class TeaserListWidget extends Core\ProudWidget {

  function __construct( $base_id = false, $name = false, $description = false ) {
    parent::__construct(
      $base_id ? $base_id : 'proud_teaser_list', // Base ID
      $name ? $name : __( 'Content list', 'wp-proud-core' ), // Name
      $description ? $description : array( 'description' => __( 'List of content with a customizable display style', 'wp-proud-core' ), ) // Args
    );

    $this->post_type = false;
  }

  function postTypes() {
    return [
      'post' => __('News', 'proud-teaser'),
      'event' => __('Events', 'proud-teaser'),
      'agency' => __('Agencies', 'proud-teaser'),
      'staff-member' => __('Staff Members', 'proud-teaser'),
      'document' => __('Documents', 'proud-teaser'),
      'job_listing' => __('Jobs', 'proud-teaser'),
    ];
  }

  function displayModes() {
    return [
      'list' => __('List View', 'proud-teaser'),
      'mini' => __('Mini List', 'proud-teaser'),
      'cards' => __('Card View', 'proud-teaser'),
      'table' => __('Table View', 'proud-teaser'),
    ];
  }

  function initialize() {
    if (!$this->post_type) {
      $this->settings['proud_teaser_content'] = [
        '#title' => __('Content type', 'proud-teaser'),
        '#type' => 'select',
        '#options' => $this->postTypes(),
        '#default_value' => 'post',
      ];
    }
    else {
      // @todo: this should be called from proud-teasers.php
      $taxonomy = $this->get_taxonomy($this->post_type);
      if( $taxonomy ) {
        $categories = get_categories( ['type' => $this->post_type, 'taxonomy' => $taxonomy] );
        $options = [];
        foreach ($categories as $cat) {
          $options[$cat->term_id] = $cat->name;
        };
        $this->settings['proud_teaser_terms'] = [
          '#title' => __( 'Limit to category', 'proud-teaser' ),
          '#type' => 'checkboxes',
          '#options' => $options,
          '#default_value' => array_values($options),
          '#description' => ''
        ];
      }
    }
    $this->settings += [
      'proud_teaser_display' => [
        '#title' => __('Display style', 'proud-teaser'),
        '#type' => 'radios',
        '#default_value' => 'list',
        '#options' => $this->displayModes(),
      ],
      'post_count' => [
        '#type' => 'text',
        '#title' => 'Number of posts to show',
        '#description' => 'How many posts to show?',
        '#default_value' => 3
      ],
      'pager' => [
        '#type' => 'checkbox',
        '#title' => 'Pager',
        '#return_value' => '1',
        '#label_above' => true,
        '#replace_title' => 'Add pager @todo: this needs to be implemented',
        '#default_value' => false
      ],
      'more_link' => [
        '#type' => 'checkbox',
        '#title' => 'More',
        '#return_value' => '1',
        '#label_above' => true,
        '#replace_title' => 'Inlcude a more link',
        '#default_value' => false
      ],
      'link_title' => [
        '#title' => 'More Link title',
        '#type' => 'text',
        '#default_value' => 'More',
        '#description' => 'Text for the link',
        '#to_js_settings' => false,
        '#states' => [
          'hidden' => [
            'more_link' => [
              'operator' => '==',
              'value' => ['0'],
              'glue' => '||'
            ],
          ],
        ],
      ],
      'link_url' => [
        '#title' => 'More Link url',
        '#type' => 'text',
        '#default_value' => '',
        '#description' => 'Url for the link',
        '#to_js_settings' => false,
        '#states' => [
          'hidden' => [
            'more_link' => [
              'operator' => '==',
              'value' => ['0'],
              'glue' => '||'
            ],
          ],
        ],
      ],
      'show_filters' => [
        '#type' => 'checkbox',
        '#title' => 'Filters',
        '#return_value' => '1',
        '#description' => 'You must place the "Content list filters" widget somewhere on the page, and only one filter + list combination is allowed',
        '#replace_title' => __( 'Filters will be included on this page', 'wp-proud-core' ), 
        '#default_value' => false
      ],
    ];
  }

  /**
   * Gets taxonomy for post type
   * @todo: this should come from proud-teasers.php
   */
  public function get_taxonomy( $post_type = false ) {

    switch( $post_type ? $post_type : $this->post_type ) {
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
   * Determines if content empty, show widget, title ect?  
   *
   * @see self::widget()
   *
   * @param array $args     Widget arguments.
   * @param array $instance Saved values from database.
   */
  public function hasContent($args, &$instance) {
    $terms = $instance['proud_teaser_terms'];
    unset($terms[0]);
    $terms = array_keys($terms);
    $terms = count($terms) ? $terms : false;

    $instance['teaser_list'] = new Core\TeaserList(
      $this->post_type ? $this->post_type : $instance['proud_teaser_content'], 
      $instance['proud_teaser_display'], 
      array(
        'posts_per_page' => $instance[ 'post_count' ],
      ),
      ($instance['show_filters']),
      $terms
    );
    if($instance['show_filters']) {
      $teaser_filter_class = new TeaserFilterTracker($instance['teaser_list']);
    }
    return $true;
  }

  /**
   * Front-end display of widget.
   *
   * @see WP_Widget::widget()
   *
   * @param array $args     Widget arguments.
   * @param array $instance Saved values from database.
   */
  public function printWidget( $args, $instance ) {
    extract($instance);

    $file = plugin_dir_path( __FILE__ ) . 'templates/teaser-list.php';
    // Include the template file
    include( $file );
  }
}

class TeaserFilterWidget extends Core\ProudWidget {
  function __construct() {
    parent::__construct(
      'proud_teaser_filters', // Base ID
      __( 'Content list filters', 'wp-proud-core' ), // Name
      array( 'description' => __( 'The filters for a specific content list', 'wp-proud-core' ), ) // Args
    );
  }

  function initialize() { 
    $this->settings = [];
  }

  /**
   * Determines if content empty, show widget, title ect?  
   *
   * @see self::widget()
   *
   * @param array $args     Widget arguments.
   * @param array $instance Saved values from database.
   */
  public function hasContent($args, &$instance) {
    $instance['teaser_filter_class'] = new TeaserFilterTracker();
    return $instance['teaser_filter_class']::$teaser_class;
  }

  /**
   * Front-end display of widget.
   *
   * @see WP_Widget::widget()
   *
   * @param array $args     Widget arguments.
   * @param array $instance Saved values from database.
   */
  public function printWidget( $args, $instance ) {
    extract($instance);
    $file = plugin_dir_path( __FILE__ ) . 'templates/teaser-filters.php';
    // Include the template file
    include( $file );
  }
}

// Posts
class PostTeaserListWidget extends TeaserListWidget {
  function __construct(  ) {
    parent::__construct(
      'proud_post_teaser_list', // Base ID
      __( 'News Posts list', 'wp-proud-core' ), // Name
      array( 'description' => __( 'List of News Posts in a category with a display style', 'wp-proud-core' ), ) // Args
    );

    $this->post_type = 'post';
  }
}

// Events
class EventTeaserListWidget extends TeaserListWidget {
  function __construct(  ) {
    parent::__construct(
      'proud_event_teaser_list', // Base ID
      __( 'Events list', 'wp-proud-core' ), // Name
      array( 'description' => __( 'List of Events in a category with a display style', 'wp-proud-core' ), ) // Args
    );

    $this->post_type = 'event';
  }
}

// Documents
class DocumentTeaserListWidget extends TeaserListWidget {
  function __construct(  ) {
    parent::__construct(
      'proud_document_teaser_list', // Base ID
      __( 'Documents list', 'wp-proud-core' ), // Name
      array( 'description' => __( 'List of Documents in a category with a display style', 'wp-proud-core' ), ) // Args
    );

    $this->post_type = 'document';
  }
}

// Jobs
class JobTeaserListWidget extends TeaserListWidget {
  function __construct(  ) {
    parent::__construct(
      'proud_job_teaser_list', // Base ID
      __( 'Jobs list', 'wp-proud-core' ), // Name
      array( 'description' => __( 'List of Job Listings in a category with a display style', 'wp-proud-core' ), ) // Args
    );

    $this->post_type = 'job_listing';
  }
}


// register widgets
function register_teaser_list_widget() {
  register_widget( 'TeaserListWidget' );
  register_widget( 'TeaserFilterWidget' );

  // Post-type specific widgets
  register_widget( 'PostTeaserListWidget' );
  register_widget( 'EventTeaserListWidget' );
  register_widget( 'DocumentTeaserListWidget' );
  register_widget( 'JobTeaserListWidget' );
}
add_action( 'widgets_init', 'register_teaser_list_widget' );
