<?php

/**
 * @author ProudCity
 */

use Proud\Core;

class SocialLinksWidget extends Core\ProudWidget {

  function __construct() {
    parent::__construct(
      'proud_social_links', // Base ID
      __( 'Social Links', 'wp-proud-core' ), // Name
      array( 'description' => __( 'Quick access links to social networks', 'wp-proud-core' ), ) // Args
    );
  }

  function initialize() {
    // @todo: this should be called from proud-teasers.php
    $social = $this->getSocialData();
    if( !empty( $social ) ) {
      $this->settings += [
        'restrict_accounts' => [
          '#type' => 'checkbox',
          '#title' => 'Limit visible social accounts?',
          '#description' => 'Limit visible social accounts?',
          '#return_value' => '1',
          '#label_above' => true,
          '#replace_title' => 'Yes',
          '#default_value' => false
        ]
      ];
      foreach ($social as $value) {
        $account = $this->extractSocialData($value);
        $options[$value] = $account['account'] . sprintf( ' (<a href="%s" target="_blank">%s</a>)', 
          $account['url'],  
          $account['service']
        );
      }
      $this->settings += [
        'social_accounts' => [
          '#title' => __( 'Limit to social account', 'proud-teaser' ),
          '#type' => 'checkboxes',
          '#options' => $options,
          '#default_value' => array_keys($options),
          '#description' => 'Choose the social acounts that should display',
          '#states' => [
            'visible' => [
              'restrict_accounts' => [
                'operator' => '!=',
                'value' => [false],
                'glue' => '||'
              ],
            ],
          ]
        ]
      ];
    }
  }

  /**
   * Helper function returns useful data for account
   * $string: [service]:[account] eg: 'twitter:proudcity'
   */
  function extractSocialData($string) {
    $account = explode( ':', $string );
    $url = $this->accountUrl( $account[0], $account[1] );
    return [
      'service' => ucfirst( $account[0] ),
      'account' => $account[1],
      'url'     => $url
    ];
  }

  /**
   * Helper function gets social accounts from options
   */
  function getSocialData() {
    $social = get_option('social_feeds');
    if( !empty( $social ) ) {
      $social = explode( PHP_EOL, $social );
      // Empty? Trim whitespace
      return !empty( $social ) ? array_filter( array_map('trim', $social) ) : [];
    }
    return [];
  }

  /**
   * Helper function returns url to social acount
   */
  function accountUrl($service, $account) {
    switch ($service) {
      case 'facebook':
      case 'instagram':
      case 'twitter':
        return sprintf( 'https://%s.com/%s', $service, $account);
        break;
    }
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
    $social = [];
    if( !empty($instance['restrict_accounts'] ) && !empty( $instance['social_accounts'] ) ) {
      $social = $instance['social_accounts'];
    }
    else {
      $social = $this->getSocialData();
      // needs array( MEANINGFUL => ) 
      $social = array_combine($social, $social);
    }
    // We have values, go ahead
    if( !empty( $social ) ) {
      foreach ($social as $key => $value) {
        if( $value ) {
          $instance['social_accounts'][$key] = $this->extractSocialData($value);
        }
      }
      return true;
    }
    return false;
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
    $file = plugin_dir_path( __FILE__ ) . 'templates/social-links.php';
    // Include the template file
    include( $file );
  }
}

// register Foo_Widget widget
function register_social_links_widget() {
  register_widget( 'SocialLinksWidget' );
}
add_action( 'widgets_init', 'register_social_links_widget' );