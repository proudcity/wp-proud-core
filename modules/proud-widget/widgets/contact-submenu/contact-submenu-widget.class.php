<?php
use Proud\Core;

class ContactSubmenu extends Core\ProudWidget {

  function __construct() {
    parent::__construct(
      'submenu', // Base ID
      __( 'Contact submenu', 'wp-agency' ), // Name
      array( 'description' => __( "Lists Agencies and Contact categories", 'wp-agency' ), ) // Args
    );
  }

  
  function initialize() {
    $this->settings += [
      'format' => [
        '#type' => 'radios',
        '#title' => 'Format',
        '#description' => 'Menu format',
        '#options' => array(
          'stacked' => 'Stacked',
          'pills' => 'Pills',
        ),
        '#default_value' => 'stacked',
      ],
      'agency_page' => [
        '#type' => 'text',
        '#title' => 'Agency contact page',
        '#description' => 'Page that lists the contact information for Agencies',
        '#default_value' => 'contact'
      ],
      'contact_page' => [
        '#type' => 'text',
        '#title' => 'People contact page',
        '#description' => 'Page that lists Contacts, including a filter for categories',
        '#default_value' => 'people',
      ],
    ];
  }

  /**
   * Outputs the content of the widget
   *
   * @param array $args
   * @param array $instance
   */
  public function printWidget( $args, $instance ) {
    $categories = get_categories( ['type' => 'staff-member', 'taxonomy' => 'staff-member-group'] );
    $post = get_post(get_the_ID());
    ?>
      <ul class="nav nav-pills <?php if ($instance['format'] == 'stacked'): ?>nav-stacked submenu<?php endif; ?>">
        <?php if ( !empty($instance['agency_page']) ): ?><li <?php if($post->post_name == $instance['agency_page']): ?>class="active"<?php endif; ?>>
          <a href="<?php echo esc_url('/' . $instance['agency_page']) ?>">
            <?php echo _x( 'Agencies', 'post name', 'wp-agency' ) ?>
          </a>
          </li><?php endif; ?>
        <?php foreach ($categories as $cat): ?>
          <li <?php if(!empty( $_GET['filter_categories'] ) && $_GET['filter_categories'][0] == $cat->term_id): ?>class="active"<?php endif; ?>>
            <a href="<?php echo esc_url('/' . $instance['contact_page'] . '?filter_categories[]=' . $cat->term_id) ?>"><?php echo $cat->name; ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php
  }
}

// register Foo_Widget widget
function register_contact_submenu_widget() {
  register_widget( 'ContactSubmenu' );
}
add_action( 'widgets_init', 'register_contact_submenu_widget' );