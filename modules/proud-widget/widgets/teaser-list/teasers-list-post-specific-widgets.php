<?php
/**
 * @author ProudCity
 */

use Proud\Core;

// Posts
class PostTeaserListWidget extends TeaserListWidget {
  function __construct(  ) {
    parent::__construct(
      'proud_post_teaser_list', // Base ID
      __( 'News Posts list', 'wp-proud-core' ), // Name
      array( 'description' => __( 'List of News Posts in a category with a display style', 'wp-proud-core' ), ), // Args
      get_class($this)
    );

    $this->post_type = 'post';
    $this->display_featured = true;
    $this->display_modes = [ 'list', 'media', 'mini', 'cards' ];

    // Sort options
    $this->display_sort = true;
  }
}

// Events
class EventTeaserListWidget extends TeaserListWidget {
  function __construct(  ) {
    parent::__construct(
      'proud_event_teaser_list', // Base ID
      __( 'Events list', 'wp-proud-core' ), // Name
      array( 'description' => __( 'List of Events in a category with a display style', 'wp-proud-core' ), ), // Args
      get_class($this)
    );

    $this->post_type = 'event';
    $this->display_featured = true;
    $this->display_modes = [ 'list', 'media', 'mini' ];

    // @TODO sort seems like events you wouldn't really want to?
  }
}

// Documents
class DocumentTeaserListWidget extends TeaserListWidget {
  function __construct(  ) {
    parent::__construct(
      'proud_document_teaser_list', // Base ID
      __( 'Documents list', 'wp-proud-core' ), // Name
      array( 'description' => __( 'List of Documents in a category with a display style', 'wp-proud-core' ), ), // Args
      get_class($this)
    );

    $this->post_type = 'document';
    $this->display_modes = [ 'list', 'cards', 'table', 'mini' ];

    // Sort options
    $this->display_sort = true;
    $this->sort_by_options += [
      'menu_order' => 'Menu Order',
    ];
    $this->sort_by_default = 'menu_order'; // Sort by
    $this->sort_order_default = 'ASC'; // Sort direction
  }

  function initialize() {
    parent::initialize();
    $this->settings += [
      'proud_teaser_hide' => [
        '#title' => __('Hide Columns', 'proud-teaser'),
        '#description' => __('Select columns that you would not like to appear in your table', 'proud-teaser'),
        '#type' => 'checkboxes',
        '#default_value' => [],
        '#options' => [
          'category' => __( 'Category', 'proud-teaser' ),
          'date' => __( 'Date', 'proud-teaser' ),
          'download' => __( 'Document type and size download link', 'proud-teaser' ),
        ],
        '#states' => [
          'hidden' => [
            'proud_teaser_display' => [
              'operator' => '!=',
              'value' => ['table'],
              'glue' => '||'
            ],
          ],
        ],
      ],
    ];
  }
}

// Jobs
class JobTeaserListWidget extends TeaserListWidget {
  function __construct(  ) {
    parent::__construct(
      'proud_job_teaser_list', // Base ID
      __( 'Jobs list', 'wp-proud-core' ), // Name
      array( 'description' => __( 'List of Job Listings in a category with a display style', 'wp-proud-core' ), ), // Args
      get_class($this)
    );

    $this->post_type = 'job_listing';
    $this->display_modes = [ 'list', 'mini', 'table' ];

    // @TODO sort?
  }
}

// Contacts
class ContactTeaserListWidget extends TeaserListWidget {
  function __construct(  ) {
    parent::__construct(
      'proud_contact_teaser_list', // Base ID
      __( 'Contacts list', 'wp-proud-core' ), // Name
      array( 'description' => __( 'List of staff Contacts in a category with a display style', 'wp-proud-core' ), ), // Args
      get_class($this)
    );
    $this->post_type = 'staff-member';
    $this->display_modes = [ 'list', 'table' ];

    // @TODO sort... seems like by name would only be useful if we had last name capability
  }

  function initialize() {
    parent::initialize();
    $this->settings += [
      'proud_teaser_hide' => [
        '#title' => __('Hide Columns', 'proud-teaser'),
        '#description' => __('Select columns that you would not like to appear in your table', 'proud-teaser'),
        '#type' => 'checkboxes',
        '#default_value' => [],
        '#options' => [
          'agency' => _x( 'Agency', 'post type singular name', 'wp-agency' ),
          'social' => __( 'Social', 'proud-teaser' ),
        ],
        '#states' => [
          'hidden' => [
            'proud_teaser_display' => [
              'operator' => '!=',
              'value' => ['table'],
              'glue' => '||'
            ],
          ],
        ],
      ],
    ];
  }

}


// Contacts
class AgencyTeaserListWidget extends TeaserListWidget {
  function __construct(  ) {
    parent::__construct(
      'proud_agency_teaser_list', // Base ID
      __( 'Agency list', 'wp-proud-core' ), // Name
      array( 'description' => __( 'List of agencies', 'wp-proud-core' ), ), // Args
      get_class($this)
    );

    $this->post_type = 'agency';
    $this->display_modes = [ 'cards', 'media', 'icons', 'table' ];

    // Sort options
    $this->display_sort = true;
    $this->sort_by_options += [
      'menu_order' => 'Menu Order',
    ];
    $this->sort_by_default = 'menu_order'; // Sort by
    $this->sort_order_default = 'ASC'; // Sort direction

  }

  function initialize() {
    parent::initialize();

    // Add some hiding options
    $hide_if_specific = ['pager', 'post_count'];
    foreach ($hide_if_specific as $value) {
      $rule = [
        'use_specific' => [
          'operator' => '==',
          'value' => ['1'],
          'glue' => '||'
        ],
      ];
      if( empty( $this->settings[$value]['#states'] ) ) {
        $this->settings[$value]['#states'] = ['hidden' => []];
      }
      if( empty( $this->settings[$value]['#states']['hidden']) ) {
        $this->settings[$value]['#states']['hidden'] = $rule;
      }
      else {
        $this->settings[$value]['#states']['hidden'] = array_merge( 
          $this->settings[$value]['#states']['hidden'], $rule 
        );
      }
    }

    // Build list of agencies
    $query = new \WP_Query( [
      'post_type' => 'agency',
      'post_status' => 'publish',
      'posts_per_page' => 100,
    ] );
    $agency_list = [];
    foreach ($query->posts as $key => $agency) {
      $agency_list[$agency->ID] = $agency->post_title; 
    }

    $this->settings += [
      'use_specific' => [
        '#type' => 'checkbox',
        '#title' => 'Specific Agencies',
        '#return_value' => '1',
        '#label_above' => true,
        '#replace_title' => 'Display specific agencies instead of listing them?',
        '#default_value' => false
      ],
      'specific_ids' => [
        '#title' => __('To display', 'proud-teaser'),
        '#description' => __('Select the agencies to display', 'proud-teaser'),
        '#type' => 'checkboxes',
        '#default_value' => array_combine( array_keys( $agency_list ), array_keys( $agency_list ) ),
        '#options' => $agency_list,
        '#states' => [
          'visible' => [
            'use_specific' => [
              'operator' => '==',
              'value' => ['1'],
              'glue' => '||'
            ],
          ],
        ],
      ],
      'proud_teaser_hide' => [
        '#title' => __('Hide Columns', 'proud-teaser'),
        '#description' => __('Select columns that you would not like to appear in your table', 'proud-teaser'),
        '#type' => 'checkboxes',
        '#default_value' => [],
        '#options' => [
          'person' => __( 'Person', 'proud-teaser' ),
          'social' => __( 'Social', 'proud-teaser' ),
        ],
        '#states' => [
          'hidden' => [
            'proud_teaser_display' => [
              'operator' => '!=',
              'value' => ['table'],
              'glue' => '||'
            ],
          ],
        ],
      ],
    ];
  }

}


// Questions
class QuestionTeaserListWidget extends TeaserListWidget {
  function __construct(  ) {
    parent::__construct(
      'proud_question_teaser_list', // Base ID
      __( 'Answers list', 'wp-proud-core' ), // Name
      array( 'description' => __( 'List of Answers in a category with a display style', 'wp-proud-core' ), ), // Args
      get_class($this)
    );
    
    $this->post_type = 'question';
    $this->display_modes = [ 'list', 'accordion' ];

    // Sort options
    $this->display_sort = true;
    $this->sort_by_options += [
      'menu_order' => 'Menu Order',
    ];
    $this->sort_by_default = 'menu_order'; // Sort by
    $this->sort_order_default = 'ASC'; // Sort direction
  }

  function initialize() {
    parent::initialize();
    $this->settings['proud_teaser_terms']['#description'] = __( 'Checking a parent category will display all items belonging to the children categories as well', 'wp-proud-core' );
  }

}