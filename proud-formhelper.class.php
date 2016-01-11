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
          // @TODO
          case 'link-with-title':
            // $this->printFormTextLabel($field['#id']['title'], $field['#title']['title'], $this->form_id);
            // $this->printTextInput($field['#id']['title'], $field['#name']['title'], $field['#value']['title'], $this->form_id);
            // $this->printFieldDescription($field['#description']['title']);
            // $this->printFormTextLabel($field['#id']['url'], $field['#title']['url'], $this->form_id);
            // $this->printTextInput($field['#id']['url'], $field['#name']['url'], $field['#value']['url'], $this->form_id);
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
            $this->printFormTextLabel($field['#id'], $field['#title'], $this->form_id);
            $this->printTextInput($field['#id'], $field['#name'], $field['#value'], $this->form_id);
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

    public function printFields () {
      // Javascript states for hiding / showing fields
      $states = [];

      foreach ( $this->fields as $id => $field ) {

        $this->printFormItem( $field );

        if(!empty($field['#states'])) {
          $states[$field['#id']] = $field['#states'];
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

    public function attachConfigStateJs( $states ) {
      ?>
      <script>
        jQuery(document).ready(function() {
          <?php foreach ( $states as $field_id => $rules ): ?>
            <?php foreach( $rules as $type => $values ): ?>
              // init visiblility
              jQuery("#<?php echo $this->form_id . '-' . $field_id ?>").<?php echo $type == 'visible' ? 'hide' : 'show' ?>();
              <?php foreach( $values as $watch_field => $watch_vals ): ?>
                <?php 
                  // Needs different selectors per type
                  $group_id = '#' . $this->form_id . '-' . $this->fields[$watch_field]['#id'];
                  switch( $this->fields[$watch_field]['#type'] ) {
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
                  foreach ( $watch_vals['value'] as $val ) {
                    $criteria[] = 'jQuery("' . $selector . '").val()' . $watch_vals['operator'] . '"' . $val . '"'; 
                  }
                ?>
                jQuery("<?php echo $watch ?>").change(function() {
                  if(<?php echo implode( $watch_vals['glue'], $criteria ) ?>) {
                    jQuery("#<?php echo $this->form_id . '-' . $field_id ?>").<?php echo $type == 'visible' ? 'show' : 'hide' ?>();
                  }
                  else {
                    jQuery("#<?php echo $this->form_id . '-' . $field_id ?>").<?php echo $type == 'visible' ? 'hide' : 'show' ?>();
                  }
                });
              <?php endforeach; ?>
            <?php endforeach; ?>
          <?php endforeach; ?>
        });
      </script>
      <?php
    }
  }
}