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
        switch ($field['#type']) {
          case 'html':
            echo $field['#html'];
            break;
          case 'fa-icon':
            ?>
            <script>
              jQuery(document).ready(function() {
                jQuery('#<?php echo $field['#id'];?>').once('icon-picker', function() { 
                  jQuery(this).iconpicker(); 
                });
              });
            </script>
            <?php
            $this->printFormTextLabel($field['#id'], $field['#title'], $this->form_id);
            $this->printTextInput($field['#id'], $field['#name'], $field['#value'], $this->form_id);
            $this->printDescription($field['#description']);
            break;

          case 'select_media':
            $this->printFormTextLabel($field['#id'], $field['#title'], $this->form_id);
            $this->printTextInput($field['#id'], $field['#name'], $field['#value'], $this->form_id);
            $this->printImageUpload($field['#value'], $this->form_id);
            $this->printDescription($field['#description']);
            break;

          case 'text':
          case 'email':
            $this->printFormTextLabel($field['#id'], $field['#title'], $this->form_id);
            $this->printTextInput($field['#id'], $field['#name'], $field['#value'], $this->form_id, !empty($field['#args']) ? $field['#args'] : array() );
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
            $this->printDescription($field['#description']);
            break;

          case 'checkboxes':
          case 'radios':
            // Print label
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
            $this->printDescription($field['#description']);
            break;

          case 'checkbox':
            ?>
            <?php if(!empty($field['#label_above'])): ?>
              <?php $this->printFormTextLabel('', $field['#title'], $this->form_id); ?>
            <?php endif; ?> 
            <div class="<?php echo $field['#type'] ?>">
              <?php $this->printOptionBox(
                $field['#type'], 
                $field['#id'], 
                $field['#name'], 
                !empty($field['#replace_title']) ? $field['#replace_title'] : $field['#title'], 
                $field['#return_value'],
                $field['#value'], 
                $this->form_id
              ); ?>
              <?php $this->printDescription($field['#description']) ?>
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

    public function printGroupFields ($id, $field) {
      include $this->template('repeating-fields');
    }

    public function printFields () {
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
            switch( $this->fields[$watch_field]['#type'] ) {
              case 'radios':
              case 'checkbox':
                $watches[] = $group_id . ' input';
                $selector = $group_id . ' input:checked';
                break;

              default:
                $watches[] = $group_id . ' input';
                $selector = $group_id . ' input';
            }
            // Build if criteria
            $criteria = [];
            foreach ( $watch_vals['value'] as $val ) {
              $criteria[] = 'jQuery("' . $selector . '").val()' . $watch_vals['operator'] . '"' . $val . '"'; 
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