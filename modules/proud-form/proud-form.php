<?php
/**
 * @author ProudCity
 */

namespace Proud\Core;

if ( ! class_exists( 'FormHelper' ) ) {

  class FormHelper {

    private $form_id;
    private $template_path;

    function __construct($form_id, $fields) {
      $this->form_id = $form_id;
      $this->fields = $fields;
      $this->template_path = plugin_dir_path( __FILE__ ) . 'templates/';
      // Add proud admin scripts
      $this->registerAdminLibraries();
      // 
    }

    /**
     * Register admin libraries from Proud\Core\Libraries
     */
    public function registerAdminLibraries() {
      global $proudcore;
      foreach ( $this->fields as $key => $value ) {
        if( $value['#type'] === 'group' ){
          $proudcore->addJsSettings([
            'proud_form' => [
              'draggable' => [
                $key => $key
              ]
            ]
          ]);
          $proudcore::$libraries->addBundleToLoad('dragula', true);
        }
        else if( $value['#type'] === 'fa-icon' ) {
          $proudcore->addJsSettings([
            'proud_form' => [
              'iconpicker' => [
                $key => $key
              ]
            ]
          ]);
          $proudcore::$libraries->addBundleToLoad('fontawesome-iconpicker', true);
        }
        // Media upload
        else if( $value['#type'] === 'select_media' ) {
          $proudcore::$libraries->addBundleToLoad('upload-media', true);
        }
      }
    }

    private function template($file) {
      return $this->template_path . $file . '.php';
    }

    public function printFormTextLabel($id, $text, $translate = false, $args = array() ) {
      $after = !empty($args['after']) ? $args['after'] : false;
      unset($args['after']);
      include $this->template('form-label');
    }

    public function printTextInput($id, $name, $value, $translate = false, $args = array() ) {
      $args['class'] = !empty($args['class']) ? $args['class'] . ' form-control' : 'form-control';
      $after = !empty($args['after']) ? $args['after'] : false;
      unset($args['after']);
      include $this->template('text-input');
    }

    public function printSelectList($id, $name, $value, $options, $translate = false) {
      include $this->template('select-list');
    }

    public function printImageUpload($value, $translate) {
      include $this->template('image-upload');
    }

    public function printTextArea($id, $name, $value, $rows, $translate = false) {
      include $this->template('textarea');
    }

    public function printEditor($id, $name, $value, $rows, $translate = false) {
      include $this->template('editor');
    }

    public function printOptionBox($type, $id, $name, $text, $value, $active, $translate = false) {
      include $this->template('option-box');
    }

    public function printDescription($description) {
      include $this->template('description');
    }

    public function printFormItem($field) {
      ?>
      <div id="<?php echo $this->form_id . '-' . $field['#id'] ?>" class="form-group">
      <?php
        // @todo: Should we set #name to #id if it isn't set?
        switch ($field['#type']) {
          case 'html':
            echo $field['#html'];
            break;
          case 'fa-icon':
            $this->printFormTextLabel($field['#id'], $field['#title'], $this->form_id);
            $this->printTextInput($field['#id'], $field['#name'], $field['#value'], $this->form_id, ['class' => 'iconpicker']);
            if( !empty( $field['#description'] ) ) 
              $this->printDescription($field['#description']);
            break;

          case 'select_media':
            $this->printFormTextLabel($field['#id'], $field['#title'], $this->form_id);
            $this->printTextInput($field['#id'], $field['#name'], $field['#value'], $this->form_id);
            $this->printImageUpload($field['#value'], $this->form_id);
            if( !empty( $field['#description'] ) ) 
              $this->printDescription($field['#description']);
            break;

          case 'text':
          case 'email':
            // Placeholder ?
            $label_args = !empty( $field['#args']['placeholder'] ) ? array('class' => 'sr-only') : array();
            $this->printFormTextLabel($field['#id'], $field['#title'], $this->form_id, $label_args );
            // Input args, placeholder, after
            $input_args = !empty( $field['#args'] ) ? $field['#args'] : array();
            $this->printTextInput($field['#id'], $field['#name'], $field['#value'], $this->form_id, $input_args );
            if( !empty( $field['#description'] ) ) 
              $this->printDescription($field['#description']);
            break;

          case 'select':
            $this->printFormTextLabel($field['#id'], $field['#title'], $this->form_id);
            $this->printSelectList(
              $field['#id'], 
              $field['#name'], 
              $field['#value'], 
              $field['#options'], 
              $this->form_id
            );
            if( !empty( $field['#description'] ) ) 
              $this->printDescription($field['#description']);
            break;

          case 'textarea':
            $this->printFormTextLabel($field['#id'], $field['#title'], $this->form_id);
            $this->printTextArea(
              $field['#id'], 
              $field['#name'], 
              $field['#value'], 
              !empty($field['#rows']) ? $field['#rows'] : 3, 
              $this->form_id
            );
            if( !empty( $field['#description'] ) ) 
              $this->printDescription($field['#description']);
            break;

          case 'editor':
            $this->printFormTextLabel($field['#id'], $field['#title'], $this->form_id);
            $this->printEditor(
              $field['#id'],
              $field['#name'],
              $field['#value'],
              !empty($field['#rows']) ? $field['#rows'] : 10, 
              $this->form_id
            );
            if( !empty( $field['#description'] ) ) 
              $this->printDescription($field['#description']);
            break;

          case 'checkboxes':
          case 'radios':
            // Print label, add class
            $this->printFormTextLabel($field['#id'], $field['#title'], $this->form_id, array('class' => 'option-box-label') ); 
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
                  $this->form_id
                ); ?>
              </div>
              <?php 
            }
            if( !empty( $field['#description'] ) ) 
              $this->printDescription($field['#description']);
            break;

          case 'checkbox':
            if( !empty( $field['#label_above'] ) )
              $this->printFormTextLabel('', $field['#title'], $this->form_id, array('class' => 'option-box-label'));
            ?>
            <div class="<?php echo $field['#type'] ?>">
            <?php
            $this->printOptionBox(
              $field['#type'], 
              $field['#id'], 
              $field['#name'], 
              !empty($field['#replace_title']) ? $field['#replace_title'] : $field['#title'], 
              $field['#return_value'],
              $field['#value'], 
              $this->form_id
            );
            if( !empty( $field['#description'] ) ) 
              $this->printDescription($field['#description']);
            ?>
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

    public function printGroupFields( $id, $field ) {
      // Build json template
      $key = 'GROUP_REPLACE_KEY';
      $group_title = __($field['#title'], $this->form_id) . ' GROUP_REPLACE_TITLE';
      $group = $field['#json_field_template'];
      ob_start(); // turn on output buffering
      include($this->template( 'repeating-fields-template' ));
      $json = json_encode(ob_get_contents()); // get the contents of the output buffer
      ob_end_clean(); //  clean (erase) the output buffer and turn off output buffering 
      // Include path
      $field['#template'] = 'repeating-fields-template.php';
      include $this->template( 'repeating-fields' );
    }

    public function printFields ( $fields = null ) {
      // Field override?
      if( $fields ) {
        $this->fields = $fields;
      }
      // Javascript states for hiding / showing fields
      $states = [];
      foreach ( $this->fields as $id => $field ) {
        $field['#id'] = empty($field['#id']) ? $id : $field['#id'];
        $this->fields[$id]['#id'] = $field['#id'];
        if($field['#type'] == 'group') {
          $this->printGroupFields( $id, $field );
        }
        else {
          $this->printFormItem( $field );
          if(!empty($field['#states'])) {
            $states[$field['#id']] = $field['#states'];
          }
        }
      }

      if( !empty( $states ) ) {
        $this->attachConfigStateJs($states);
      }
    }

    public function printForm ($args = []) {
      // Merge with defaults
      $args = array_merge( [
        'button_text' => __( 'Submit', 'proud-form' ),
        'method' => 'post',
        'action' => '',
        'name' => $this->form_id,
        'id' => $this->form_id
      ], $args);
      ?>
      <form id="<?php echo $args['id']; ?>" name="<?php echo $args['name']; ?>" method="<?php echo $args['method']; ?>" action="<?php echo $args['action']; ?>">
        <?php wp_nonce_field( $args['id'] ); ?>
        <?php $this->printFields(); ?>
        <button type="submit" class="btn btn-primary"><?php print $args['button_text']; ?></button>
      </form>
      <?php
    }

    /**
     * Prints out show / hide javascript for the form
      * '#states' => [
      *  'glue' => '&&',
      *  'visible' => [
      *    'background' => [
      *      'operator' => '==',
      *      'value' => ['image'],
      *      'glue' => '&&'
      *    ],
      *  ],
      *  'invisible' => [
      *    'glue' => '&&',
      *    'headertype' => [
      *      'operator' => '==',
      *      'value' => ['simple'],
      *      'glue' => '&&'
      *    ],
      *  ],
      *]
     */
    public function attachConfigStateJs( $states ) {
      $fields = [];
      // Build field rules
      foreach ( $states as $field_id => $rules ):
        // Field level if statement
        $field_if = [];
        // connect the field options
        $field_glue = !empty($rules['glue']) ? $rules['glue'] : '&&';
        // Fields to watch for changes
        $watches = [];
        foreach( $rules as $type => $values ):
          // Just the statement glue
          if($type == 'glue') {
            continue;
          }
          // connect the visible / invisible state
          $rules_glue = !empty($values['glue']) ? $values['glue'] : '&&';
          // Rule level if statement
          $rule_if = [];
          // Run through fields that make it visible or invisible
          foreach( $values as $watch_field => $watch_vals ):
            $watch_field = str_replace($this->form_id . '-', '', $watch_field);
            // Just the statement glue
            if($watch_field == 'glue') {
              continue;
            }
            // Needs different selectors per type
            $group_id = '#' . $this->form_id . '-' . $this->fields[$watch_field]['#id'];
            // Init value criteria
            $value_criteria = '.val()';
            switch( $this->fields[$watch_field]['#type'] ) {
              case 'radios':
                $watches[] = $group_id . ' input';
                $selector = $group_id . ' input:checked';
                break;

              case 'checkbox':
                $watches[] = $group_id . ' input';
                $selector = $group_id . ' input:checked';
                // Check length
                $value_criteria = '.length';
                break;

              default:
                $watches[] = $group_id . ' input';
                $selector = $group_id . ' input';
            }
            // Build if criteria
            $criteria = [];
            foreach ( $watch_vals['value'] as $val ) {
              $criteria[] = 'jQuery("' . $selector . '")' . $value_criteria . $watch_vals['operator'] . '"' . $val . '"'; 
            }
            $rule_if[] = $type == 'visible' 
                       ? '('  . implode( $watch_vals['glue'], $criteria ) . ')'
                       : '!(' . implode( $watch_vals['glue'], $criteria ) . ')';
          endforeach;
          // connect visible + invisible
          $field_if[] = '('  . implode( $rules_glue,  $rule_if ) . ')';
        endforeach;
        // connect entire field
        $fields[$this->form_id . '-' . $field_id] = [
          'watches' => implode( ',', $watches ),
          'if' => implode( $field_glue,  $field_if )
        ];
      endforeach;

      // Include JS
      include $this->template('form-state-js');
    }
  }
}


// register Foo_Widget widget
function proud_form_load_js() {
  wp_enqueue_script( 'proud-form', plugins_url( 'assets/js/',__FILE__) . 'proud-form.js' , ['proud'], false, true );
}
    // Load admin scripts from libraries
add_action('admin_enqueue_scripts',  __NAMESPACE__ . '\\proud_form_load_js');