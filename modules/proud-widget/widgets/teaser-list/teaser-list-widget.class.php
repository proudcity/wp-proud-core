<?php
/**
 * @author ProudCity
 */

use Proud\Core;

class TeaserListWidget extends Core\ProudWidget {

  function __construct() {
    parent::__construct(
      'proud_teaser_list', // Base ID
      __( 'Content list', 'wp-proud-core' ), // Name
      array( 'description' => __( 'List of content with a customizable display mode', 'wp-proud-core' ), ) // Args
    );
  }

  function initialize() {
    $this->settings = [
      'proud_teaser_content' => [
        '#title' => __('Content Type', 'proud-teaser'),
        '#type' => 'select',
        '#options' => [
          'post' => __('News', 'proud-teaser'),
          'event' => __('Events', 'proud-teaser'),
          'agency' => __('Agencies', 'proud-teaser'),
        ],
        '#default_value' => 'post',
      ],
      'proud_teaser_display' => [
        '#title' => __('Teaser Display Mode', 'proud-teaser'),
        '#type' => 'select',
        '#default_value' => 'list',
        '#options' => [
          'list' => __('List View', 'proud-teaser'),
          'mini' => __('Mini List', 'proud-teaser'),
          'cards' => __('Card View', 'proud-teaser'),
        ]
      ],
      'post_count' => [
        '#type' => 'text',
        '#title' => 'Number of posts to show',
        '#description' => 'How many posts to show?',
        '#default_value' => 3
      ],
      'more_link' => [
        '#type' => 'checkbox',
        '#title' => 'Inlcude a more link?',
        '#description' => 'Inlcude a more link?',
        '#return_value' => '1',
        '#label_above' => true,
        '#replace_title' => 'Yes',
        '#default_value' => false
      ],
      'link_title' => [
        '#title' => 'More Link title',
        '#type' => 'text',
        '#default_value' => 'More',
        '#description' => 'Text for the link',
        '#to_js_settings' => false,
        '#states' => [
          'hidden' => [
            'more_link' => [
              'operator' => '==',
              'value' => ['0'],
              'glue' => '||'
            ],
          ],
        ],
      ],
      'link_url' => [
        '#title' => 'More Link url',
        '#type' => 'text',
        '#default_value' => '',
        '#description' => 'Url for the link',
        '#to_js_settings' => false,
        '#states' => [
          'hidden' => [
            'more_link' => [
              'operator' => '==',
              'value' => ['0'],
              'glue' => '||'
            ],
          ],
        ],
      ],
    ];
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
    // d($instance);
    // Call Teaser list
    $teaser_list = new Core\TeaserList(
      $instance['proud_teaser_content'], 
      $instance['proud_teaser_display'], 
      array(
        'posts_per_page' => $instance[ 'post_count' ],
      )
    );
    $teaser_list->print_list();

    if( $instance['more_link'] ):
    ?>
      <a href="<?php echo $instance['link_url']; ?>"><?php echo $instance['link_title']; ?></a>
    <?php
    endif;
    
  }
}

// register Foo_Widget widget
function register_teaser_list_widget() {
  register_widget( 'TeaserListWidget' );
}
add_action( 'widgets_init', 'register_teaser_list_widget' );