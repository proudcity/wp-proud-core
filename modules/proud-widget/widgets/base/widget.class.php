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

  function __construct($id, $name, $args) {
    parent::__construct($id, $name, $args);
    $this->initialize();
    // Add title if not present
    if(empty($settings['title'])) {
      $this->settings = array_merge([
        'title' => [
          '#title' => 'Widget Title',
          '#type' => 'text',
          '#default_value' => '',
          '#description' => 'Title',
          '#to_js_settings' => false
        ]
      ], $this->settings);
    }
    // Init proud library on plugins loaded
    add_action( 'init', [$this,'registerLibraries'] );
    // Add admin scripts
    add_action( 'init', [$this,'registerAdminLibraries']);
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
   * Register admin libraries from Proud\Core\Libraries
   */
  public function registerAdminLibraries() {
    global $proudcore;
    foreach ($this->settings as $key => $value) {
      if(!empty($value['#admin_libraries'])) {
        foreach ($value['#admin_libraries'] as $library) {
          $proudcore::$libraries->addBundleToLoad($library, true);
        }
      }
    }
  }

  /**
   * Register admin libraries from Proud\Core\Libraries
   */
  public function addSettingDefaults($instance) {
    $return = [];
    foreach ($this->settings as $key => $value) {
      if(isset($instance[$key])) {
        $return[$key] = $instance[$key];
      }
      else if (isset($value['#default_value'])) {
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
    if($app_wide) {
      if(!empty($instance)) {
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
      $instance = $this->addSettingDefaults($instance);
      // Actually have settings
      if(!empty($instance)) {
        $settings = [];
        foreach ($this->settings as $key => $value) {
          // field to js, and exists?
          if(!empty($value['#to_js_settings']) && isset($instance[$key])) {
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

  public function printWidgetConfig( $instance ) {

    $fields = $this->settings;
    foreach ( $fields as $id => &$field ) {
      // Set id
      $field['#id'] = $this->get_field_id($id);
      $field['#name'] = $this->get_field_name($id);

      // Set default value
      $field['#value'] = isset( $instance[$id] ) 
         ? $instance[$id] 
         : $field['#default_value'];

      $field['#description'] = !empty( $field['#description'] ) ? $field['#description'] : false;
    }

    $form = new FormHelper($this->id_base, $fields);
    $form->printFields( );
  }

  public function updateWidgetConfig( $new_instance, $old_instance ) {
    $instance = [];
    foreach ($new_instance as $key => $value) {
      $instance[$key] = $value;
    }
    return $instance;
  }

  /**
   * Back-end widget form.
   *
   * @see WP_Widget::form()
   *
   * @param array $instance Previously saved values from database.
   */
  public function form( $instance ) {

    $this->printWidgetConfig($instance);
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
    return $this->updateWidgetConfig($new_instance, $old_instance);
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
   * Front-end display of widget.
   *
   * @see WP_Widget::widget()
   *
   * @param array $args     Widget arguments.
   * @param array $instance Saved values from database.
   */
  public function widget( $args, $instance ) {
    // Add JS Settings
    $this->addJsSettings($instance);
    $this->enqueueFrontend();
    $instance = $this->addSettingDefaults($instance);
    ?>
    <section class="widget <?php echo str_replace('_', '-', $this->option_name) ?> clearfix">
      <?php if( !empty( $instance['title'] ) ): ?>
        <h2><?php echo $instance['title']; ?></h2>
      <?php endif; ?>
      <?php $this->printWidget($args, $instance); ?>
    </section>
    <?php
  }
}