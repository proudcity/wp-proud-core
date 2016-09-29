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
  }

  function displayModes() {
    return [
      'icons' => __('Icon Buttons', 'proud-teaser'),
      'cards' => __('Card View (with thumbnails)', 'proud-teaser'),
      'table' => __('Table View', 'proud-teaser'),
    ];
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

    $this->display_modes = [ 'list', 'accordion' ];
    $this->post_type = 'question';
  }

  function initialize() {
    parent::initialize();
    $this->settings['proud_teaser_terms']['#description'] = __( 'Checking a parent category will display all items belonging to the children categories as well', 'wp-proud-core' );
  }

}