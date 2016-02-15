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

// Contacts
class ContactTeaserListWidget extends TeaserListWidget {
  function __construct(  ) {
    parent::__construct(
      'proud_contact_teaser_list', // Base ID
      __( 'Contacts list', 'wp-proud-core' ), // Name
      array( 'description' => __( 'List of staff Contacts in a category with a display style', 'wp-proud-core' ), ) // Args
    );

    $this->post_type = 'staff-member';
  }
}