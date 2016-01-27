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

  function __construct() {
    parent::__construct(
      'proud_teaser_list', // Base ID
      __( 'Content list', 'wp-proud-core' ), // Name
      array( 'description' => __( 'List of content with a customizable display mode', 'wp-proud-core' ), ) // Args
    );
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
    $this->settings += [
      'proud_teaser_content' => [
        '#title' => __('Content Type', 'proud-teaser'),
        '#type' => 'select',
        '#options' => $this->postTypes(),
        '#default_value' => 'post',
      ],
      'proud_teaser_display' => [
        '#title' => __('Teaser Display Mode', 'proud-teaser'),
        '#type' => 'radios',
        '#default_value' => 'list',
        '#options' => $this->displayModes(),
      ],
      'show_filters' => [
        '#type' => 'radios',
        '#title' => 'Including filters on this page?',
        '#description' => 'You must place the "Content list filters" widget somewhere on the page, and only one filter + list combination is allowed',
        '#options' => [
          'yes' => __( 'Yes', 'wp-proud-core' ), 
          'no' => __( 'No', 'wp-proud-core' ) 
        ],
        '#default_value' => 'no'
      ],
      'post_count' => [
        '#type' => 'text',
        '#title' => 'Number of posts to show',
        '#description' => 'How many posts to show?',
        '#default_value' => 3
      ],
      'more_link' => [
        '#type' => 'checkbox',
        '#title' => 'Inlcude a more link?',
        '#description' => 'Inlcude a more link?',
        '#return_value' => '1',
        '#label_above' => true,
        '#replace_title' => 'Yes',
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
    ];
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
    $instance['teaser_list'] = new Core\TeaserList(
      $instance['proud_teaser_content'], 
      $instance['proud_teaser_display'], 
      array(
        'posts_per_page' => $instance[ 'post_count' ],
      ),
      ($instance['show_filters'] == 'yes')
    );
    if($instance['show_filters'] == 'yes') {
      $teaser_filter_class = new TeaserFilterTracker($instance['teaser_list']);
    }
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

// register Foo_Widget widget
function register_teaser_list_widget() {
  register_widget( 'TeaserListWidget' );
  register_widget( 'TeaserFilterWidget' );
}
add_action( 'widgets_init', 'register_teaser_list_widget' );