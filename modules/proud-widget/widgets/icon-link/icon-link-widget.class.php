<?php
/**
 * @author ProudCity
 */

use Proud\Core;

class IconLink extends Core\ProudWidget {

  function __construct() {
    parent::__construct(
      'proud_icon_link', // Base ID
      __( 'Icon link', 'wp-proud-core' ), // Name
      array( 'description' => __( 'Simple icon button, and link', 'wp-proud-core' ), ) // Args
    );
  }

  function initialize() {
    $this->settings = [
      'link_title' => [
        '#title' => 'Link title',
        '#type' => 'text',
        '#default_value' => '',
        '#description' => 'Text for the link',
        '#to_js_settings' => false
      ],
      'link_url' => [
        '#title' => 'Link url',
        '#type' => 'text',
        '#default_value' => '',
        '#description' => 'Url for the link',
        '#to_js_settings' => false
      ],
      'fa_icon' => [
        '#title' => 'Icon',
        '#type' => 'fa-icon',
        '#default_value' => '',
        '#description' => 'The icon to use for the icon box.',
        '#to_js_settings' => false,
        '#admin_libraries' => ['fontawesome-iconpicker']
      ],
      'external' => [
        '#type' => 'checkbox',
        '#title' => 'Open in new tab',
        '#return_value' => '1',
        '#default_value' => false
      ]
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
    ?>
    <div class="card-wrap"><a href="<?php echo $instance['link_url']; ?>" class="card text-center card-btn card-block" <?php if($instance['external']): ?>target="_blank"<?php endif;?> >
      <i aria-hidden="true" class="fa <?php echo $instance['fa_icon']; ?> fa-3x"></i>
      <div class="h4"><?php echo $instance['link_title']; ?></div>
    </a></div>
    <?php
  }
}

// register Foo_Widget widget
function register_icon_link_widget() {
  register_widget( 'IconLink' );
}
add_action( 'widgets_init', 'register_icon_link_widget' );