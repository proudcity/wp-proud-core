<div id="<?php echo $this->form_id . '-' . $field['#id'] ?>" class="repeating-group">
  <div id="<?php echo $field['#id']; ?>-draggable" data-draggable="true" class="panel-group" id="<?php echo $field['#id']; ?>-accordion" role="tablist" aria-multiselectable="true">
  <?php 
    // Print children
    foreach($field['#items'] as $key => $group) {
      // Try to get group title
      $group_title = !empty( $field['#group_titles'][$key] )
                   ? $field['#group_titles'][$key]
                   : __($field['#title'], $this->form_id) . ' ' . ( $key + 1 );
      include($field['#template']);
    }
  ?>
  </div>
  <div data-group-field-template style="display:none;">
    <script>
      // Save json template
      jQuery('#<?php echo $field['#id']; ?>-draggable').data('json_template', <?php echo $json ?>);
      // init Draggable
      jQuery(document).ready(function($) {

        $('#<?php echo $field['#id']; ?>-draggable').once('panelsopen', function() { 
          var that = this;
          dragula([that], {
            moves: function (el, container, handle) {
              console.log(handle.className);
              return handle.className.indexOf('handle') > 0;
            }
          }).on('drop', function (el) {
            Proud.behaviors.groups.recalulateWeight(jQuery(that));
          });
        });
      });
    </script>
    ?>
  </div>
  <a href="#" id="<?php echo $field['#id']; ?>-add" class="btn btn-primary group-add-row">Add Another</a>
</div>
