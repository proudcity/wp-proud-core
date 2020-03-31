<?php
/**
 * @author ProudCity
 */

use Proud\Core;

class SocialFacebook extends Core\ProudWidget {

  function __construct() {
    parent::__construct(
      'social_facebook', // Base ID
      __( 'Facebook Page Embed', 'wp-proud-core' ), // Name
      array( 'description' => __( 'Embed your Facebook timeline and events', 'wp-proud-core' ), ) // Args
    );
  }

  function initialize() {
    // $this->settings = [];
    $this->settings = [
      'facebook_page_url' => [
        '#title' => 'Facebook Page URL',
        '#type' => 'text',
        '#default_value' => 'https://www.facebook.com/getproudcity',
        '#to_js_settings' => false
      ],
      'tabs' => [
        '#title' => 'Tabs',
        '#type' => 'checkboxes',
        '#options' => [
            'timeline' => 'Timeline',
            'events' => 'Events',
        ],
        '#default_value' => ['timeline'],
        '#to_js_settings' => false
      ],
      'height' => [
        '#title' => 'Height',
        '#type' => 'text',
        '#default_value' => '2000',
        '#suffix' => 'px',
        '#to_js_settings' => false
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
    return true;
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
    ?>
    <div id="fb-root"></div>
    <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v6.0"></script>
    <div class="fb-page" data-href="<?php echo $instance['facebook_page_url'] ?>" data-tabs="<?php echo implode(', ', array_keys($instance['tabs'])) ?>" data-width="500" data-height="<?php echo $instance['height'] ?>" data-small-header="true" data-adapt-container-width="true" data-hide-cover="true" data-show-facepile="false"><blockquote cite="https://www.facebook.com/facebook" class="fb-xfbml-parse-ignore"><a href="https://www.facebook.com/facebook">Facebook</a></blockquote></div>
    <?php
  }
}

// register Foo_Widget widget
function register_social_facebook_widget() {
  register_widget( 'SocialFacebook' );
}
add_action( 'widgets_init', 'register_social_facebook_widget' );