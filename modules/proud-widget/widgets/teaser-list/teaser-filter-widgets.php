<?php
/**
 * @author ProudCity
 */

use Proud\Core;

/**
 * Helper class tracks teaser class options,
 * initiates filter if everything is present
 */
class TeaserFilterTracker {
  static $teaser_class;

  function __construct($teaser_class = null) {
    if($teaser_class) {
      self::$teaser_class = $teaser_class;
    }
  }

  public function print_filters($include_filters = null, $button_text = 'Filter') {

    if( self::$teaser_class ) {
      self::$teaser_class->print_filters($include_filters, $button_text);
    }
  }
}

/**
 * Teaser filter widget
 */
class TeaserFilterWidget extends Core\ProudWidget {
  function __construct() {
    parent::__construct(
      'proud_teaser_filters', // Base ID
      __( 'Content list filters', 'wp-proud-core' ), // Name
      array( 'description' => __( 'Adds a filter box for a specific content list', 'wp-proud-core' ), ) // Args
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

/**
 * Simple teaser filter widget: search only 
 */
class TeaserFilterSearchWidget extends Core\ProudWidget {
  function __construct() {
    parent::__construct(
      'proud_teaser_search', // Base ID
      __( 'Content list search box', 'wp-proud-core' ), // Name
      array( 'description' => __( 'Adds a search box for a specific content list', 'wp-proud-core' ), ) // Args
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
    $file = plugin_dir_path( __FILE__ ) . 'templates/teaser-filter-search.php';
    // Include the template file
    include( $file );
  }
}