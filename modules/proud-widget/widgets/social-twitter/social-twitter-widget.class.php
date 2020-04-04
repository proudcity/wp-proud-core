<?php
/**
 * @author ProudCity
 */

use Proud\Core;

class SocialTwitter extends Core\ProudWidget {

  function __construct() {
    parent::__construct(
      'social_twitter', // Base ID
      __( 'Twitter Page Embed', 'wp-proud-core' ), // Name
      array( 'description' => __( 'Embed your Twitter timeline and events', 'wp-proud-core' ), ) // Args
    );
  }

  function initialize() {
    // $this->settings = [];
    $this->settings = [
      'title' => [
        '#title' => 'Title',
        '#type' => 'text',
        '#default_value' => 'Twitter',
        '#to_js_settings' => false
        ],
      'handle' => [
        '#title' => 'Handle',
        '#type' => 'text',
        '#default_value' => '@getproudcity',
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
    $handle = str_replace('@', '', $instance['handle']);
    ?>
    <div style="height:<?php echo $instance['height'] ?>px; border: 1px solid #eee; margin-bottom: 1em;overflow-y:scroll;">
      <a class="twitter-timeline" href="https://twitter.com/<?php echo $handle ?>"></a> <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
    </div>
    <?php
  }
}

// register Foo_Widget widget
function register_social_twitter_widget() {
  register_widget( 'SocialTwitter' );
}
add_action( 'widgets_init', 'register_social_twitter_widget' );