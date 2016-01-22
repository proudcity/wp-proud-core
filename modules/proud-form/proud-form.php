<?php
/**
 * @author ProudCity
 */

namespace Proud\Core;

if ( ! class_exists( 'FormHelper' ) ) {

  class FormHelper {

    private $form_id;

    function __construct($form_id, $fields) {
      $this->form_id = $form_id;
      $this->fields = $fields;
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

    public function printSelectList($id, $name, $value, $options, $translate = false) {
      ?>
      <select class="form-control" id="<?php echo $id ?>" name="<?php echo $name; ?>">
        <?php foreach ( $options as $key => $label ): ?>
          <option value="<?php echo $key; ?>"<?php if($key == $value) print ' selected="selected"';?>>
            <?php if($translate) : ?>
              <?php echo __( $label, $translate); ?>
            <?php else: ?>
              <?php echo $label; ?>
            <?php endif; ?>
          </option>
        <?php endforeach; ?>
      </select>
      <?php
    }

    public function printImageUpload($value, $translate) {
      ?>
      <img class="custom_media_image" src="<?php if(!empty($value)){echo $value;} ?>" style="margin:0;padding:0;max-width:100px;float:left;display:inline-block" />
      <input class="upload_image_button" type="button" value="<?php if(!empty($value)){ echo __('Change Image', $translate); } else {echo __( 'Upload Image', $translate); }?>" />
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

    public function printFormItem($field) {
      ?>
      <div id="<?php echo $this->form_id . '-' . $field['#id'] ?>" class="form-group">
      <?php
        switch ($field['#type']) {
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
            $this->printFormTextLabel($field['#id'], $field['#title'], $this->form_id);
            $this->printTextInput($field['#id'], $field['#name'], $field['#value'], $this->form_id);
            $this->printFieldDescription($field['#description']);
            break;

          case 'select_media':
            $this->printFormTextLabel($field['#id'], $field['#title'], $this->form_id);
            $this->printTextInput($field['#id'], $field['#name'], $field['#value'], $this->form_id);
            $this->printImageUpload($field['#value'], $this->form_id);
            $this->printFieldDescription($field['#description']);
            break;

          case 'text':
          case 'email':
            $this->printFormTextLabel($field['#id'], $field['#title'], $this->form_id);
            $this->printTextInput($field['#id'], $field['#name'], $field['#value'], $this->form_id);
            $this->printFieldDescription($field['#description']);
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
            $this->printFieldDescription($field['#description']);
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
            $this->printFieldDescription($field['#description']);
            break;

          case 'checkboxes':
          case 'radios':
            // Print label
            $this->printFormTextLabel($field['#id'], $field['#title'], $this->form_id); 
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
              <?php $this->printFieldDescription($field['#description']) ?>
              <?php 
            }
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

    public function printGroupFields ($id, $field) {
      ?>
      <div id="<?php echo $field['#id']; ?>" class="repeating-group">
        <div class="repeating">
        <?php foreach($field['#items'] as $key => $group): ?>
          <fieldset id="<?php echo $field['#id']; ?>-<?php echo $key; ?>">
            <legend><?php echo __($field['#title'], $this->form_id); ?></legend>
            <div>
              <?php foreach($group as $sub_field): ?>
                <?php $this->printFormItem( $sub_field ); ?>
              <?php endforeach; ?>
            </div>            
          </fieldset>
        <?php endforeach; ?>
        </div>
        <button id="<?php echo $field['#id']; ?>-add" class="add-row">Add Set</button>
      </div>
      <?php
    }

    public function printFields () {
      // Javascript states for hiding / showing fields
      $states = [];

      foreach ( $this->fields as $id => $field ) {
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
        <button type="submit" class="btn btn-default"><?php print $args['button_text']; ?></button>
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
      ?>
      <script>
        jQuery(document).ready(function() {
          var fieldFunctions = [];
        <?php
        $field_count = 0;
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
          $if = implode( $field_glue,  $field_if );
          $field_selector = $this->form_id . '-' . $field_id;
          ?>
          fieldFunctions.push(function () {
            if(<?php echo $if ?>) {
              jQuery("#<?php echo $field_selector ?>").show();
            }
            else {
              jQuery("#<?php echo $field_selector ?>").hide();
            }
          });
          jQuery("<?php echo implode(',',$watches) ?>").change(function() {
            fieldFunctions[<?php echo $field_count; ?>]();
          });
          fieldFunctions[<?php echo $field_count; ?>]();
        <?php 
          // Next field to watch
          $field_count++; 
          endforeach; 
        ?>
        });
      </script>
      <?php
    }
  }
}