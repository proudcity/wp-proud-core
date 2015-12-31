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

  // 
  public function printTextArea($id, $name, $value, $rows, $translate = false) {
    ?>
     <textarea class="form-control" rows="<?php echo $rows ?>" id="<?php echo $id ?>" name="<?php echo $name ?>"><?php echo esc_attr( $value ); ?></textarea>
    <?php 
  }

  public function printOptionBox($type, $id, $name, $text, $value, $active, $translate = false) {
    ?>
    <label for="<?php echo $id ?>">
      <input id="<?php echo $id ?>" name="<?php echo $name ?>" type="<?php echo $type ?>"<?php if($active){echo ' checked="checked"'; } ?> value="<?php echo esc_attr( $value ); ?>"> 
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
    <div id="<?php echo $widgetName . '-' . $field['#id'] ?>" class="form-group">
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

        case 'textarea':
          $this->printFormTextLabel($field['#id'], $field['#title'], $widgetName);
          $this->printTextArea(
            $field['#id'], 
            $field['#name'], 
            $field['#value'], 
            !empty($field['#rows']) ? $field['#rows'] : 3, 
            $widgetName
          );
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

  public function attachConfigStateJs($widgetName, $states) {
    ?>
    <script>
      jQuery(document).ready(function() {
        <?php foreach ($states as $field_id => $rules): ?>
          <?php foreach($rules as $type => $values): ?>
            // init visiblility
            jQuery("#<?php echo $widgetName . '-' . $field_id ?>").<?php echo $type == 'visible' ? 'hide' : 'show' ?>();
            <?php foreach($values as $watch_field => $watch_vals): ?>
              <?php 
                // Needs different selectors per type
                $group_id = '#' . $widgetName . '-' . $this->get_field_id($watch_field);
                switch($this->settings[$watch_field]['#type']) {
                  case 'radios':
                  case 'checkbox':
                    $watch = $group_id . ' input';
                    $selector = $group_id . ' input:checked';
                    break;

                  default:
                    $watch = $group_id . ' input';
                    $selector = $group_id . ' input';
                }
                // Build if criteria
                $criteria = [];
                foreach ($watch_vals['value'] as $val) {
                  $criteria[] = 'jQuery("' . $selector . '").val()' . $watch_vals['operator'] . '"' . $val . '"'; 
                }
              ?>
              jQuery("<?php echo $watch ?>").change(function() {
                if(<?php echo implode($watch_vals['glue'], $criteria) ?>) {
                  jQuery("#<?php echo $widgetName . '-' . $field_id ?>").<?php echo $type == 'visible' ? 'show' : 'hide' ?>();
                }
                else {
                  jQuery("#<?php echo $widgetName . '-' . $field_id ?>").<?php echo $type == 'visible' ? 'hide' : 'show' ?>();
                }
              });
            <?php endforeach; ?>
          <?php endforeach; ?>
        <?php endforeach; ?>
      });
    </script>
    <?php
  }

  public function printWidgetConfig($instance) {
    // Javascript states for hiding / showing fields
    $states = [];
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
      $this->printFormItem($this->id_base, $field, $states);
      if(!empty($field['#states'])) {
        $states[$field['#id']] = $field['#states'];
      }
    }
    if(!empty($states)) {
      $this->attachConfigStateJs($this->id_base, $states);
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
    $instance = $this->addSettingDefaults($instance);
    ?>
    <section class="widget <?php echo str_replace('_', '-', $this->option_name) ?> clearfix">
      <?php $this->printWidget($args, $instance); ?>
    </section>
    <?php
  }
}