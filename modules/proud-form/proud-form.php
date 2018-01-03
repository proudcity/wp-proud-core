<?php
/**
 * @author ProudCity
 */

namespace Proud\Core;

if ( class_exists( 'GFForms' ) ) {
	include_once( ABSPATH . 'wp-content/plugins/gravityforms/includes/api.php' );
}

if ( ! class_exists( 'FormHelper' ) ) {

  class FormHelper {

    private $form_id;
    private $form_id_base;
    private $number;
    private $template_path;
    private $fields;

    /**
     * Set up
     *
     * @param string $form_id_base
     * @param array $fields
     * @param string $number is form number : can be left out
     * @param string $field_base is form base : can be left out
     * 
     * For site origin panels, we don't know $number yet, so it can be null
     */
    function __construct($form_id_base, $fields, $number = null, $field_base = null) {
      $this->form_id_base = strtolower($form_id_base);
      $this->fields = $fields;
      $this->template_path = plugin_dir_path( __FILE__ ) . 'templates/';
      // Add proud admin scripts
      $this->registerAdminLibraries();
      // If included, register
      if( $number && $field_base ) {
        $this->registerIds( $number, $field_base );
      }
    }

    /**
     * Before printing any fields, first we must construct the basis for 
     * field ids, names
     */
    public function registerIds( $number = 1, $field_base = 'form' ) {
      $this->number = $number;
      $this->field_base = $field_base;
      $this->form_id = $this->form_id_base . '-' . $this->number;
    }

    /**
     * Constructs name attributes for use in form() fields
     *
     * This function should be used in form() methods to create name attributes for fields
     * to be saved by update()
     *
     * @since 2.8.0
     * @since 4.4.0 Array format field names are now accepted.
     * @access public
     *
     * @param string $field_name Field name
     * @return string Name attribute for $field_name
     */
    public function get_field_name($field_name) {
      if ( false === $pos = strpos( $field_name, '[' ) ) {
        return $this->field_base . '-' . $this->form_id_base . '[' . $this->number . '][' . $field_name . ']';
      } else {
        return $this->field_base . '-' . $this->form_id_base . '[' . $this->number . '][' . substr_replace( $field_name, '][', $pos, strlen( '[' ) );
      }
    }

    /**
     * Constructs id attributes for use in WP_Widget::form() fields.
     *
     * This function should be used in form() methods to create id attributes
     * for fields to be saved by WP_Widget::update().
     *
     * @since 2.8.0
     * @since 4.4.0 Array format field IDs are now accepted.
     * @access public
     *
     * @param string $field_name Field name.
     * @return string ID attribute for `$field_name`.
     */
    public function get_field_id( $field_name ) {
      return $this->field_base . '-' . $this->form_id_base . '-' . $this->number . '-' . trim( str_replace( array( '[]', '[', ']' ), array( '', '-', '' ), $field_name ), '-' );
    }

    /**
     * Helper functions checks an array for arrays
     */
    public static function contains_array( $array ){
      foreach( $array as $value ) {
          if( is_array( $value ) ) {
            return true;
          }
      }
      return false;
    }

    /**
     * Takes instance setting on submit and deals with draggable weights
     */
    public static function updateGroupsWeight( $new_instance, $fields = [] ) {
      $instance = [];
      foreach ( $new_instance as $key => $value ) {
        // Array based value
        if( is_array( $value ) && self::contains_array( $value ) ) {
          // Try to substitute values #key'd array new values from numeric to key
          if( !empty( $fields[$key]['#keyed'] ) ) {
            $keyed_value = [];
            foreach ( $value as $inner_key => $inner_value ) {
              // we have a keyed value
              if( !empty( $inner_value[$fields[$key]['#keyed']] ) ) {
                $keyed_value[$inner_value[$fields[$key]['#keyed']]] = $inner_value;
              }
              else {
                $keyed_value[$inner_key] = $inner_value;
              }
            }
            $instance[$key] = $keyed_value;
          }
          // Repeating (0-indexed array)
          else if( count( array_filter( array_keys( $value ), 'is_string' ) ) === 0 ) {
            usort($value, function($a, $b) {
                if(!isset( $a['weight'] ) || !isset( $b['weight'] ) ) {
                  return 0;
                }
                return intval( $a['weight'] ) - intval( $b['weight'] );
            });
            $instance[$key] = $value;
          }
        }
        else {
          $instance[$key] = $value;
        }
      }
      return $instance;
    }

    /**
     * Extracts values from submit array using static FormHelper::formValues
     * @param array $values
     * @param string $form_id_base, if null, uses class values
     * @param string $field_base, if null, uses default: 'form'
     * @param string $number, if null uses default: 1
     */
    public static function formValues( $values, $form_id_base = null, $field_base = 'form', $number = 1, $fields = [] ) {
      return ( ! empty( $values[$field_base . '-' . $form_id_base][$number] ) ) 
           ? self::updateGroupsWeight( $values[$field_base . '-' . $form_id_base][$number], $fields )
           : [];
    }

    /**
     * Extracts values from submit array using $myform->getFormValues
     * @param array $values
     */
    public function getFormValues( $values ) {
      return self::formValues( $values, $this->form_id_base, $this->field_base, $this->number, $this->fields );
    }

    /**
     * Register admin libraries from Proud\Core\Libraries
     */
    public function registerAdminLibraries( $fields = [] ) {
      global $proudcore;
      // form has no fields, exit
      if( empty( $this->fields ) ) {
        return;
      }
      // If nothing passed (as in NOT recursing), load fields from global
      else if( empty( $fields ) ) {
        $fields = $this->fields;
      }
      foreach ( $fields as $key => $field ) {
        if( $field['#type'] === 'group' || !empty( $field['#draggable'] ) ) {
          $proudcore->addJsSettings([
            'proud_form' => [
              'draggable' => [
                $key => $key
              ]
            ]
          ]);
          $proudcore::$libraries->addBundleToLoad('dragula', true);

          // Recurse with template children
          if( !empty( $field['#sub_items_template'] ) ) {
            $this->registerAdminLibraries( $field['#sub_items_template'] );
          }
        }
        else if( $field['#type'] === 'fa-icon' ) {
          // Give a chance for modules / themes to pass additional icons
          $options = apply_filters( 'proud_form_icon_picker_options', [
            $key => $key
          ] );
          $proudcore->addJsSettings([
            'proud_form' => [
              'iconpicker' => $options
            ]
          ]);
          $proudcore::$libraries->addBundleToLoad('fontawesome-iconpicker', true);
        }
        // Media upload
        else if( $field['#type'] === 'select_media' ) {
          // Make sure WP media is present
          add_action( 'admin_enqueue_scripts', 'wp_enqueue_media' );
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

    public function printImageUpload($media_id, $url, $translate) {
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
      // @todo: Should we set #name to #id if it isn't set?
      // Extra class for field group
      $extra_group_class = !empty( $field['#extra_group_class'] ) 
                         ? ' ' . $field['#extra_group_class']
                         : '';
      ob_start();
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

        case 'gravityform':
	        if ( class_exists( 'GFAPI' ) ) {
            $options = ['' => __('-- Select form --')];
            $forms = \GFAPI::get_forms();
            foreach ($forms as $key => $form) {
              $options[  $form['id'] ] = $form['title'];
            }
            $this->printFormTextLabel($field['#id'], $field['#title'], $this->form_id);
            $this->printSelectList($field['#id'], $field['#name'], $field['#value'], $options);
            if( !empty( $field['#description'] ) ) 
              $this->printDescription($field['#description']);
          }           
          break;

        case 'select_media':
          // add extra class
          $extra_group_class .= ' clearfix';
          $this->printFormTextLabel($field['#id'], $field['#title'], $this->form_id);
          // Image should be a media['ID'], but due to 
          // https://github.com/proudcity/wp-proudcity/issues/436
          // old values could be a URL
          $media_id = '';
          $url = '';
          if( !empty( $field['#value'] ) ) {
            // Already have media value
            if( is_numeric ( $field['#value'] ) ) {
              $media_id = $field['#value'];
            }
            // featured image on post... would be nice to convert this
            // but global $post is empty on site origins form.
            else if( '[featured-image]' === $field['#value'] ) {
              $media_id = '[featured-image]';
            }
            // URL... this option should be slowly phased out
            else {
              global $wpdb;
              $media_id = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid='%s';", $field['#value'] ) );
              // Don't set the URL unless we have a value
              if( !empty( $media_id ) ) {
                $url = $field['#value'];
              } 
            }
            // Have media ID but not URL, so query
            if( !empty( $media_id ) && is_numeric ( $media_id ) && empty( $url ) ) {
              $url = wp_get_attachment_image_url($media_id, 'thumbnail');
            }
          }
          $this->printTextInput($field['#id'], $field['#name'], $media_id, $this->form_id, array('class' => 'visible-print-block'));
          $this->printImageUpload($media_id, $url, $this->form_id);
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
          $this->printFormTextLabel( $field['#id'], $field['#title'], $this->form_id );
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
          $this->printFormTextLabel( $field['#id'], $field['#title'], $this->form_id );
          $this->printTextArea(
            $field['#id'], 
            $field['#name'], 
            $field['#value'], 
            !empty( $field['#rows'] ) ? $field['#rows'] : 3, 
            $this->form_id
          );
          if( !empty( $field['#description'] ) ) 
            $this->printDescription( $field['#description'] );
          break;

        case 'editor':
          $this->printFormTextLabel( $field['#id'], $field['#title'], $this->form_id );
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
          $this->printFormTextLabel( 
            $field['#id'], 
            $field['#title'], 
            $this->form_id, 
            array( 'class' => 'option-box-label' ) 
          ); 
          if( !empty( $field['#draggable'] ) ) {
            echo '<div data-draggable-checkboxes="true">';
          }
          foreach ( $field['#options'] as $value => $title ) {
            $name = $field['#name'];
            if( $field['#type'] == 'checkboxes' ) {
              $type = 'checkbox';
              // Make name array
              $name .= '[' .  $value . ']';
              // Chekc in active
              $field['#value'] = empty( $field['#value'] ) ? [] : $field['#value'];
              $active = in_array( $value, $field['#value'] );
            }
            else {
              $type = 'radio';
              $active = $value == $field['#value'];
            }
            ?>
            <div class="<?php echo $type ?>">
              <?php if( !empty( $field['#draggable'] ) ): ?>
                <div class="pull-left" style="cursor: move;cursor: grab;cursor: -moz-grab;cursor: -webkit-grab;"><i class="fa fa-arrows handle"></i></div>
              <?php endif; ?>
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
          if( !empty( $field['#draggable'] ) ) {
            echo '</div>';
          }
          if( !empty( $field['#description'] ) ) 
            $this->printDescription($field['#description']);
          break;

        case 'checkbox':
          if( !empty( $field['#label_above'] ) )
            $this->printFormTextLabel(
              ' ', 
              $field['#title'], 
              $this->form_id, 
              array( 'class' => 'option-box-label' ) 
            );
          ?>
          <div class="<?php echo $field['#type'] ?>">
            <input value="0" name="<?php echo $field['#name'];?>" type="hidden">
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
      $field_markup = ob_get_contents();
      ob_end_clean();
      ?>
      <div id="<?php echo $this->form_id . '-' . $field['#id'] ?>" class="form-group<?php echo $extra_group_class ?>">
        <?php echo $field_markup; ?>
      </div>
      <?php
    }
    
    /**
     * Attaches group sub fields from template values
     */
    public function buildGroupSubFieldConfig( &$field, $id, $i, $instance = [] ) {
      $sub_fields = [];
      foreach( $field['#sub_items_template'] as $sub_id => $sub_item ) {
        // build sub children id
        $local_id = $id . '[' . $i . '][' . $sub_id . ']';
        // get field settings
        $sub_item['#id'] = $this->get_field_id( $local_id );
        $sub_item['#name'] = $this->get_field_name( $local_id );
        $sub_item['#description'] = !empty( $sub_item['#description'] ) ? $sub_item['#description'] : false;
        // Set default value
        $sub_item['#value'] = isset( $instance[$id][$i][$sub_id] ) 
          ? $instance[$id][$i][$sub_id]
          : $sub_item['#default_value'];
        
        if($field['#group_title_field'] === $sub_id) {
          $field['#group_titles'][$i] = $sub_item['#value'];
        }

        // Attach to return
        $sub_fields[$sub_id] = $sub_item;
      }
      return $sub_fields;
    }

    /**
     * Attaches field values / defaults before printing
     */
    public function buildFieldConfig( $instance, $fields ) {
      $filled_fields = [];
      foreach (  $fields as $id => $field ) {
        // Set id
        $field['#id'] = $this->get_field_id($id);
        $field['#name'] = $this->get_field_name($id);
        $field['#description'] = !empty( $field['#description'] ) ? $field['#description'] : false;

        // Repeating Group fields
        if( $field['#type'] == 'group') {
          // Init field collection
          $field['#items'] = [];
          // Init group titles
          $field['#group_titles'] = []; 
          // How many of these do we have saved ?
          if( empty( $instance[$id] ) ) {
            $instance[$id][] = [];
          } 
          $count =  count( $instance[$id] ); 
          // Run through any saved field items
          $i = 1;
          foreach( $instance[$id] as $key => $value ) {
            $field['#items'][$key] = $this->buildGroupSubFieldConfig( $field, $id, $key, $instance );
            // Now attach a json template default
            if( ( $i ) === $count ) {
              $field['#json_field_template'] = $this->buildGroupSubFieldConfig( $field, $id, 'GROUP_REPLACE_KEY', $instance );
            }
            $i++;
          }
        }
        // Normal field, so get value
        else {
          // Set default value
          if( isset( $instance[$id] ) ) {
            $field['#value'] = $instance[$id];
          } 
          else if( isset( $field['#default_value'] ) ) {
            $field['#value'] = $field['#default_value'];
          }
          else {
            $field['#value'] = '';
          }
          // Draggable checkboxes
          if( !empty( $field['#draggable'] ) && is_array( $field['#value'] ) ) {
            $options = [];
            foreach ( $field['#value'] as $key => $value ) {
              // Set options in order of draggable
              $options[$key] = $field['#options'][$key];
              unset( $field['#options'][$key] );
            }
            $field['#options'] = array_merge( $options, $field['#options'] );
          }
        }
        $filled_fields[$id] = $field;
      }
      return $filled_fields;
    }

    /**
     * Prints out group fields
     */
    public function printGroupFields( $id, $field ) {
      // Build json template
      $key = 'GROUP_REPLACE_KEY';
      $group_title = __( $field['#title'], $this->form_id ) . ' GROUP_REPLACE_TITLE';
      $group = $field['#json_field_template'];
      ob_start(); // turn on output buffering
      include($this->template( 'repeating-fields-template' ));
      $json = json_encode(ob_get_contents()); // get the contents of the output buffer
      ob_end_clean(); //  clean (erase) the output buffer and turn off output buffering 
      // Include path
      $field['#template'] = 'repeating-fields-template.php';
      include $this->template( 'repeating-fields' );
    }

    /**
     * Prints out form fields
     */
    public function printFields ( $instance, $fields = null, $number, $field_base ) {
      if( ! isset( $this->field_id ) || ! isset( $this->number ) ) {
        $this->registerIds( $number, $field_base );
      }
      // Field override?
      if( empty($fields) ) {
         $fields = $this->fields;
      }
      // Attach values, filter field values id
      $filled_fields = apply_filters( 
        'proud-form-filled-fields',
        $this->buildFieldConfig( $instance, $fields ),
        $instance,
        $this->form_id_base
      );
      // Javascript states for hiding / showing fields
      $states = [];
      foreach ( $filled_fields as $id => &$field ) {
        $field['#id'] = empty( $field['#id'] ) ? $id : $field['#id'];
        // $this->fields[$id]['#id'] = $field['#id'];
        if($field['#type'] == 'group') {
          $this->printGroupFields( $id, $field );
        }
        else {
          $this->printFormItem( $field );
        }
        if(!empty($field['#states'])) {
          $states[$field['#id']] = $field['#states'];
        }
      }
      // Set global to filled
      $this->fields = $filled_fields;

      if( !empty( $states ) ) {
        $this->attachConfigStateJs( $states );
      }
    }

    /**
     * Prints out form
     */
    public function printForm ( $args = [] ) {
      // Merge with defaults
      $args = array_merge( [
        'button_text' => __( 'Submit', 'proud-form' ),
        'method' => 'post',
        'action' => ''
      ], $args);

      // Build fields
      ob_start(); // turn on output buffering
      $this->printFields( 
        !empty( $args['instance'] ) ? $args['instance'] : [],
        !empty( $args['fields'] ) ? $args['fields'] : [],
        !empty( $args['number'] ) ? $args['number'] : 1,
        !empty( $args['field_base'] ) ? $args['field_base'] : 'form'
      );
      $field_output = ob_get_contents(); // get the contents of the output buffer
      ob_end_clean(); 
      ?>
      <form class="proud-settings" id="<?php echo $this->form_id ?>" name="<?php echo $this->form_id ?>" method="<?php echo $args['method']; ?>" action="<?php echo $args['action']; ?>">
        <?php wp_nonce_field( $this->form_id_base ); ?>
        <?php echo $field_output ?>
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
    public function attachConfigStateJs( $states, $fields_override = [], $print = true ) {
      $fields = [];
      if( empty( $fields_override ) ) {
        $fields_override = $this->fields;
      }
      // Build field rules
      foreach ( $states as $field_id => $rules ) {
        // Field level if statement
        $field_if = [];
        // connect the field options
        $field_glue = !empty($rules['glue']) ? $rules['glue'] : '&&';
        // Fields to watch for changes
        $watches = [];
        foreach( $rules as $type => $values ) {
          // Just the statement glue
          if($type == 'glue') {
            continue;
          }
          // connect the visible / invisible state
          $rules_glue = !empty($values['glue']) ? $values['glue'] : '&&';
          // Rule level if statement
          $rule_if = [];
          // Run through fields that make it visible or invisible
          foreach( $values as $watch_field => $watch_vals ) {
            $watch_field = str_replace($this->form_id . '-', '', $watch_field);
            // Just the statement glue
            if($watch_field == 'glue') {
              continue;
            }
            // Needs different selectors per type
            $group_id = '#' . $this->form_id . '-' . $fields_override[$watch_field]['#id'];
            // Init value criteria
            $value_criteria = '.val()';
            switch( $fields_override[$watch_field]['#type'] ) {
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

              case 'checkboxes':
                // @todo: Add support for multiple values
                $watches[] = $group_id . ' input[value=\\"'. $watch_vals['value'][0] .'\\"]';
                $selector = $group_id . ' input[value=\\"'. $watch_vals['value'][0] .'\\"]:checked';
                // Check length
                $value_criteria = '.length';
                $watch_vals['value'][0] = 1;
                break;
 
              case 'select':
                $watches[] = $group_id . ' select';
                $selector = $group_id . ' select';
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
          }
          // connect visible + invisible
          $field_if[] = '('  . implode( $rules_glue,  $rule_if ) . ')';
        }
        // connect entire field
        $fields[$this->form_id . '-' . $field_id] = [
          'watches' => implode( ',', $watches ),
          'if' => implode( $field_glue,  $field_if )
        ];
      }

      // Include ?
      if( $print ) {
        include $this->template('form-state-js');
      }
      // Just return fields
      else {
        return $fields;
      }
    }
  }
}


// register Foo_Widget widget
function proud_form_load_js() {
  wp_enqueue_script( 'proud-form', plugins_url( 'assets/js/',__FILE__) . 'proud-form.js' , ['proud'], false, true );
}
    // Load admin scripts from libraries
add_action('admin_enqueue_scripts',  __NAMESPACE__ . '\\proud_form_load_js');