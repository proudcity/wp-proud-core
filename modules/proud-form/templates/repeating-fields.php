<fieldset id="<?php echo $this->form_id . '-' . $field['#id'] ?>" class="repeating-group">
  <?php if( empty( $field['#hide_title'] ) ): ?>
  <legend class="form-label">
    <?php if( !empty( $translate ) ) : ?>
      <?php echo __( $field['#title'], $translate); ?>
    <?php else: ?>
      <?php echo $field['#title']; ?>
    <?php endif; ?>
  </legend>
  <?php endif; ?>
  <div id="<?php echo $field['#id']; ?>-draggable" data-draggable-group="true" class="panel-group" id="<?php echo $field['#id']; ?>-accordion" role="tablist" aria-multiselectable="true">
  <?php 
    // Print children
    $i = 1; 
    foreach( $field['#items'] as $key => $group ) {
      // Try to get group title
      $group_title = !empty( $field['#group_titles'][$key] )
                   ? $field['#group_titles'][$key]
                   : __( $field['#title'], $this->form_id ) . ' ' . ( $i );
      include($field['#template']);
    }
  ?>
  </div>
  <div data-group-field-template style="display:none;">
    <script>
      // Save json template
      jQuery('#<?php echo $field['#id']; ?>-draggable').data('json_template', <?php echo $json ?>);
    </script>
  </div>
  <a href="#" id="<?php echo $field['#id']; ?>-add" class="btn btn-primary group-add-row">Add Another</a>
  <p><br></p>
</fieldset>
