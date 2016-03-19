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
    $social = Core\getSocialData();
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
        $account = Core\extractSocialData($value);
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
      $social = Core\getSocialData();
      // needs array( MEANINGFUL => ) 
      $social = array_combine($social, $social);
    }
    // We have values, go ahead
    if( !empty( $social ) ) {
      foreach ($social as $key => $value) {
        if( $value ) {
          $instance['social_accounts'][$key] = Core\extractSocialData($value);
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