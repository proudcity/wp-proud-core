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
      array( 'description' => __( 'Quickly share the page or node. Uses Sharethis if module is available', 'wp-proud-core' ), ) // Args
    );
  }

  function initialize() {
    // $this->settings = [];
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

    // @ TODO finish this .directive('shareBlock' in getproudcity
    // Get meta information 
    global $post;
    $desc = get_post_meta($post->ID, '_yoast_wpseo_metadesc', true);
    if (empty($desc)) {
      $desc = \Proud\Core\wp_trim_excerpt();
    }

    ?>
    <!--<div class="dropdown share">-->
      <a href="#" id="share-dropdown" data-toggle="dropdown"><i class="fa fa-share-alt"></i> Share <!--<span class="caret"></span>--></a>
      <ul class="dropdown-menu" aria-labelledby="share-dropdown">
        <li><a href="https://www.facebook.com/sharer/sharer.php" target="_blank"><i class="fa fa-facebook-square fa-fw"></i> Facebook</a></li>
        <li><a href="http://twitter.com/share"><i class="fa fa-twitter-square fa-fw"></i> Twitter</a></li>
        <li><a href="mailto:?subject=<?php print urlencode($title); ?>&body=Read more: <?php print urlencode($url); ?>"><i class="fa fa-envelope fa-fw"></i> Email</a>
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