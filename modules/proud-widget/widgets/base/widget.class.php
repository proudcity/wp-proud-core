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
   * Print out js settings for scripts in footer
   */
  public function addJsSettings($instance = false) {
    if(!empty($instance)) {
      global $proudcore;
      $settings = [];
      foreach ($this->settings as $key => $value) {
        // field to js, and exists?
        if(!empty($value['#to_js_settings']) && isset($instance[$key])) {
          $settings[$key] = $instance[$key];
        }
      }
      $proudcore->addJsSettings([
        $this->id_base => [
          $this->id => $settings
        ]
      ]);
    }
  }

  //
  public function printFormTextLabel($id, $text, $translate = false) {
    ?>
      <label for="<?php echo $id; ?>">
        <?php if($translate) : ?>
          <?php echo __( $text, $translate); ?>
        <?php else: ?>
          <?php echo $text; ?>
        <?php endif; ?>
      </label>
    <?php ;
  }

  // 
  public function printTextInput($id, $name, $value, $translate = false) {
    ?>
     <input class="form-control" id="<?php echo $id ?>" name="<?php echo $name ?>" type="text" value="<?php echo esc_attr( $value ); ?>">
    <?php 
  }

  public function printOptionBox($type, $id, $name, $text, $value, $active, $translate = false) {
    ?>
    <label for="<?php echo $id ?>">
      <input class="form-control" id="<?php echo $id ?>" name="<?php echo $name ?>" type="<?php echo $type ?>"<?php if($active){echo ' checked="checked"'; } ?> value="<?php echo esc_attr( $value ); ?>"> 
      <?php if($translate) : ?>
        <?php echo __( $text, $translate); ?>
      <?php else: ?>
        <?php echo $text; ?>
      <?php endif; ?>
    </label>
    <?php 
  }

  public function printFieldDescription($description) {
    ?>
    <?php if(!empty($field['#description'])): ?>
      <span id="helpBlock" class="help-block">
        <?php echo $field['#description']; ?>
      </span>
    <?php endif; ?>
    <?php
  }

  public function printFormItem($widgetName, $field) {
    ?>
    <div class="form-group">
    <?php
      switch ($field['#type']) {
        // @TODO
        case 'link-with-title':
          // $this->printFormTextLabel($field['#id']['title'], $field['#title']['title'], $widgetName);
          // $this->printTextInput($field['#id']['title'], $field['#name']['title'], $field['#value']['title'], $widgetName);
          // $this->printFieldDescription($field['#description']['title']);
          // $this->printFormTextLabel($field['#id']['url'], $field['#title']['url'], $widgetName);
          // $this->printTextInput($field['#id']['url'], $field['#name']['url'], $field['#value']['url'], $widgetName);
          // $this->printFieldDescription($field['#description']['url']);
          break;

        case 'fa-icon':
          ?>
          <script>
            jQuery(window).load(function(){
              jQuery('#<?php echo $field['#id'];?>').once('icon-picker', function() { 
                jQuery(this).iconpicker(); 
              });
            });
          </script>
          <?php
          $this->printFormTextLabel($field['#id'], $field['#title'], $widgetName);
          $this->printTextInput($field['#id'], $field['#name'], $field['#value'], $widgetName);
          $this->printFieldDescription($field['#description']);
          break;

        case 'text':
        case 'email':
          $this->printFormTextLabel($field['#id'], $field['#title'], $widgetName);
          $this->printTextInput($field['#id'], $field['#name'], $field['#value'], $widgetName);
          $this->printFieldDescription($field['#description']);
          break;

        case 'checkboxes':
        case 'radios':
          // Print label
          $this->printFormTextLabel($field['#id'], $field['#title'], $widgetName); 
          foreach ($field['#options'] as $value => $title) {
            $name = $field['#name'];
            if($field['#type'] == 'checkboxes') {
              $type = 'checkbox';
              // Make name array
              $name .= '[' .  $value . ']';
              // Chekc in active
              $field['#value'] = empty($field['#value']) ? [] : $field['#value'];
              $active = in_array($value, $field['#value']);
            }
            else {
              $type = 'radio';
              $active = $value == $field['#value'];
            }
            ?>
            <div class="<?php echo $type ?>">
              <?php $this->printOptionBox(
                $type, 
                $field['#id'] . '-' . $value, 
                $name, 
                $title, 
                $value,
                $active, 
                $widgetName
              ); ?>
            </div>
            <?php $this->printFieldDescription($field['#description']) ?>
            <?php 
          }
          break;

        case 'checkbox':
          ?>
          <?php if(!empty($field['#label_above'])): ?>
            <?php $this->printFormTextLabel('', $field['#title'], $widgetName); ?>
          <?php endif; ?> 
          <div class="<?php echo $field['#type'] ?>">
            <?php $this->printOptionBox(
              $field['#type'], 
              $field['#id'], 
              $field['#name'], 
              !empty($field['#replace_title']) ? $field['#replace_title'] : $field['#title'], 
              $field['#return_value'],
              $field['#value'], 
              $widgetName
            ); ?>
            <?php $this->printFieldDescription($field['#description']) ?>
          </div>
          <?php
          break;
        
        default:
          ?>
          <div class="alert alert-danger">Form type not handled</div>
          <?php
          break;
      }
    ?>
    </div>
    <?php
  }

  public function printWidgetConfig($instance) {
    foreach ($this->settings as $id => $field) {
      // @TODO
      // Link / title field
      if($field['#type'] == 'link-with-title') {
        // $field['#title'] = !empty($field['#title']) 
        //                  ? $field['#title']
        //                  : ['title' => 'Title', 'url' => 'URL'];
        // $field['#default_value'] = !empty($field['#default_value']) 
        //                          ? $field['#default_value']
        //                          : ['title' => '', 'url' => ''];
        // $field['#description'] = !empty($field['#description']) 
        //                        ? $field['#description']
        //                        : ['title' => 'The link title.', 'url' => 'URL to link to'];
        // $field['#id'] = [
        //   'title' => $this->get_field_id($id . '-title'),
        //   'url' => $this->get_field_id($id . '-url'),
        // ];
        // $field['#name'] = [
        //   'title' => $this->get_field_name($id . '-title'),
        //   'url' => $this->get_field_name($id . '-url'),
        // ];
      }
      // others
      else {
        // Set id
        $field['#id'] = $this->get_field_id($id);
        $field['#name'] = $this->get_field_name($id);
      }

      // Set default value
      $field['#value'] = isset( $instance[$id] ) 
         ? $instance[$id] 
         : $field['#default_value'];

      $field['#description'] = !empty($field['#description']) ? $field['#description'] : false;
      $this->printFormItem($this->id_base, $field);
    }
  }

  public function updateWidgetConfig($new_instance, $old_instance) {
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
    ?>
    <section class="widget <?php echo str_replace('_', '-', $this->option_name) ?> clearfix">
      <?php $this->printWidget($args, $instance); ?>
    </section>
    <?php
  }
}