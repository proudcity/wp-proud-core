<?php
/**
 * @author ProudCity
 */

use Proud\Core;

class ShareLinks extends Core\ProudWidget {

  function __construct() {
    parent::__construct(
      'proud_share_links', // Base ID
      __( 'Share dropdown', 'wp-proud-core' ), // Name
      array( 'description' => __( 'Quickly share the current page', 'wp-proud-core' ), ) // Args
    );
  }

  function initialize() {
    // $this->settings = [];
    $this->settings = [
      'classes' => [
        '#title' => 'Link Classes',
        '#type' => 'text',
        '#default_value' => '',
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
    // Get URL
    global $wp;
    $url = add_query_arg( $wp->query_string, '', home_url( $wp->request ) );
    $title = the_title_attribute( [ 'echo'=>0 ] ) . ' from ' . get_bloginfo( 'name' );

    // Get meta information 
    $desc = \Proud\Core\wp_trim_excerpt( '', false, true );
    ?>
    <!--<div class="dropdown share">-->
      <a<?php if( !empty( $instance['classes'] ) ) { echo ' class="' . $instance['classes'] .'"'; } ?> href="#" class="share-dropdown" id="share-<?php echo $args['widget_id'] ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i aria-hidden="true" class="fa fa-fw fa-share-alt"></i>Share</a>
      <ul class="dropdown-menu" aria-labelledby="share-<?php echo $args['widget_id'] ?>">
        <li><a title="Share on Facebook" href="https://www.facebook.com/sharer/sharer.php" target="_blank"><i aria-hidden="true" class="fa fa-facebook-square fa-fw"></i> Facebook</a></li>
        <li><a title="Share on Twitter" href="https://twitter.com/share?url=<?php print urlencode($url); ?>"><i aria-hidden="true" class="fa fa-twitter-square fa-fw"></i> Twitter</a></li>
        <li><a  title="Share by Email" href="mailto:?subject=<?php print urlencode($title); ?>&body=Read more: <?php print urlencode($url); ?>"><i aria-hidden="true" class="fa fa-envelope fa-fw"></i> Email</a>
      </ul>
    <!--</div>-->
    <?php
  }
}

// register Foo_Widget widget
function register_share_links_widget() {
  register_widget( 'ShareLinks' );
}
add_action( 'widgets_init', 'register_share_links_widget' );