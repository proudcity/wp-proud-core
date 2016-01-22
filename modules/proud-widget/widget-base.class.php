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
  // Simple version of class name
  public $shortcode_name;

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
    // Save class name
    $this->shortcode_name = get_class($this);
    // Init proud library on plugins loaded
    add_action( 'init', [$this,'registerLibraries'] );
    // Add proud admin scripts
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
      // Media upload
      if($value['#type'] == 'select_media') {
        $proudcore::$libraries->addBundleToLoad('upload-media', true);
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

  public function printWidgetConfig( $instance ) {
    $fields = [];
    foreach ( $this->settings as $id => $field ) {
      // Set id
      $field['#id'] = $this->get_field_id($id);
      $field['#name'] = $this->get_field_name($id);
      $field['#description'] = !empty( $field['#description'] ) ? $field['#description'] : false;

      // Repeating Group fields
      if( $field['#type'] == 'group') {

        // How many of these do we have saved ?
        // TODO, fix this
        $count = 8;//!empty( $instance[$id] ) ? count($instance[$id]) : 1; 
        // Init field collection
        $field['#items'] = [];
        // Run through any saved field items
        for($i = 0; $i < $count; $i++) {
          foreach($field['#sub_items_template'] as $sub_id => $sub_field) {
            // build sub children id
            $local_id = $id . '[' . $i . '][' . $sub_id . ']';
            // get field settings
            $sub_field['#id'] = $this->get_field_id( $local_id );
            $sub_field['#name'] = $this->get_field_name( $local_id );
            $sub_field['#description'] = !empty( $sub_field['#description'] ) ? $sub_field['#description'] : false;
            // Set default value
            $sub_field['#value'] = isset( $instance[$id][$i][$sub_id] ) 
              ? $instance[$id][$i][$sub_id]
              : $sub_field['#default_value'];
            $field['#items'][$i][$local_id] = $sub_field;
          }
        }
        d($field);
      }
      // Normal field, so get value
      else {
        // Set default value
        $field['#value'] = isset( $instance[$id] ) 
           ? $instance[$id] 
           : $field['#default_value'];
      }
      $fields[$id] = $field;
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
    $this->addJsSettings($instance);
    $this->enqueueFrontend();
    $instance = $this->addSettingDefaults($instance);
    // Widget placed in theme, so replace default values
    if( empty( $args['name'] ) ) {
      $args['before_widget'] = sprintf( '<section class="widget %s clearfix">', str_replace( '_', '-', $this->option_name ) );
      $args['after_widget']  = '</section>';
      $args['before_title']  = '<h2>';
      $args['after_title']   = '</h2>';
    }
    ?>
    <?php echo $args['before_widget'] ?>
      <?php if( $this->hasContent( $args, $instance ) && !empty( $instance['title'] ) ): ?>
        <?php echo $args['before_title'] ?><?php echo $instance['title']; ?><?php echo $args['after_title'] ?>
      <?php endif; ?>
      <?php $this->printWidget($args, $instance); ?>
    <?php echo $args['after_widget'] ?>
    <?php
  }
}