<?php
/**
 * @author ProudCity
 */

namespace Proud\Core;

abstract class ProudWidget extends \WP_Widget {

  // Widget settings
  public $settings = [];
  // proud libraries
  public static $libaries;
  // proud form
  public $form;
  // Simple version of class name
  public $shortcode_name;

  function __construct($id, $name, $args) {
    parent::__construct($id, $name, $args);
    // Add title if not present
    if(empty($settings['title'])) {
      $this->settings = array_merge([
        'title' => [
          '#title' => 'Widget Title',
          '#type' => 'text',
          '#default_value' => '',
          '#to_js_settings' => false
        ]
      ], $this->settings);
    }
    // Save class name
    $this->shortcode_name = get_class($this);
    // Init settings
    add_action( 'init', [$this, 'initialize'] );
    // Init proud library on plugins loaded
    add_action( 'init', [$this,'registerLibraries'] );
    // Add proud admin scripts
    add_action( 'init', [$this,'attachAdminForm'], 101 );
  }

  /**
   * Initialize widget settings
   */
  function initialize() {

  }

  /**
   * Register libraries from Proud\Core\Libraries
   */
  public function registerLibraries() {

  }

  /**
   * Enqueue scripts and styles
   */
  public function enqueueFrontend() {
    
  }


  /**
   * Attach form, and load admin libraries
   */
  public function attachAdminForm() {
    // Are we admin?
    if( is_admin( ) ) {
      // Attach form
      $this->form = new FormHelper( $this->id_base, $this->settings );
    }
  }

  /**
   * Register admin libraries from Proud\Core\Libraries
   */
  public function addSettingDefaults($instance) {
    $return = [];
    foreach ( $this->settings as $key => $value ) {
      if( isset( $instance[$key] ) ) {
        $return[$key] = $instance[$key];
      }
      else if ( isset( $value['#default_value'] ) ) {
        // Invert default values array for checkboxes
        if($value['#type'] == 'checkboxes') {
          $value['#default_value'] = array_combine( $value['#default_value'], $value['#default_value'] );
        }
        $return[$key] = $value['#default_value'];
      }
    }
    return $return;
  }


  /**
   * Print out js settings for scripts in footer
   * if $app_wide is passed, its a setting for the whole app,
   * otherwise, its a per-instance setting
   */
  public function addJsSettings($instance = false, $app_wide = false) {
    global $proudcore;

    // We are setting app-wide 
    if( $app_wide ) {
      if( !empty( $instance ) ) {
        $proudcore->addJsSettings([
          $this->id_base => [
            'global' => $instance
          ]
        ]);
      } 
    }
    // instance specific
    else {
      // Empty or un-initialized
      $instance = $this->addSettingDefaults( $instance );
      // Actually have settings
      if(!empty($instance)) {
        $settings = [];
        foreach ($this->settings as $key => $value) {
          // field to js, and exists?
          if( !empty( $value['#to_js_settings'] ) && isset( $instance[$key] ) ) {
            $settings[$key] = $instance[$key];
          }
        }
        $proudcore->addJsSettings([
          $this->id_base => [
            'instances' => [
              $this->id => $settings
            ]
          ]
        ]);
      }
    }
  }

  /**
   * Back-end widget form.
   *
   * @see WP_Widget::form()
   *
   * @param array $instance Previously saved values from database.
   */
  public function form( $instance ) {
    $this->form->printFields( $instance, $this->settings, $this->number, 'widget' );
  }

  /**
   * Sanitize widget form values as they are saved.
   *
   * @see WP_Widget::update()
   *
   * @param array $new_instance Values just sent to be saved.
   * @param array $old_instance Previously saved values from database.
   *
   * @return array Updated safe values to be saved.
   */
  public function update( $new_instance, $old_instance ) {
    return $this->form->updateGroupsWeight( $new_instance, $this->settings );
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

  }

  /**
   * Determines if content empty, show widget, title ect?  
   *
   * @see self::widget()
   *
   * @param array $args     Widget arguments.
   * @param array $instance Saved values from database.
   */
  public function hasContent( $args, &$instance ) {
    // empty plugin
    if( empty( $instance ) || ( isset( $instance['title'] ) && count( $instance ) === 1 ) ) {
      return false;
    }
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
  public function widget( $args, $instance ) {
    // Add JS Settings
    $this->addJsSettings( $instance );
    if( !is_admin() ) {
      $this->enqueueFrontend();
    }

    $instance = $this->addSettingDefaults($instance);
    // Widget placed in theme, so replace default values
    if( empty( $args['name'] ) ) {
      // SO widget
      if(preg_match('/class=\".*?so-panel\ .*?\"/', $args['before_widget'])) {
        $args['before_widget'] = str_replace( 'so-panel ', str_replace( '_', '-', $this->option_name ) . ' so-panel ', $args['before_widget'] );
      }
      else {
        $args['before_widget'] = sprintf( '<section class="widget %s clearfix">', str_replace( '_', '-', $this->option_name ) );
        $args['after_widget']  = '</section>';
      }
      $args['before_title']  = '<h2>';
      $args['after_title']   = '</h2>';
    }

    // do we print??
    $has_content = $this->hasContent( $args, $instance );
    ?>
    <?php if( $has_content ): ?>
      <?php echo $args['before_widget'] ?>
        <?php if( !empty( $instance['title'] ) ): ?>
          <?php echo $args['before_title'] ?><?php echo $instance['title']; ?><?php echo $args['after_title'] ?>
        <?php endif; ?>
        <?php $this->printWidget($args, $instance); ?>
      <?php echo $args['after_widget'] ?>
    <?php endif; ?>
    <?php
  }
}
