<div data-count="<?php echo $key ?>" class="panel panel-default">
  <div class="panel-heading" role="tab" id="<?php echo $field['#id']; ?>-heading-<?php echo $key; ?>">
    <h4 class="panel-title">
      <a role="button" data-toggle="collapse" data-parent="#<?php echo $field['#id']; ?>-accordion" href="#<?php echo $field['#id']; ?>-<?php echo $key; ?>" aria-expanded="true" aria-controls="<?php echo $field['#id']; ?>-<?php echo $key; ?>">
        <?php echo $group_title ?>
      </a>
      <?php
        // Are we a weight-based or key based repeating field? 
        if( empty( $field['#keyed'] ) ): 
      ?>
      <div class="pull-right" style="cursor: move;cursor: grab;cursor: -moz-grab;cursor: -webkit-grab;"><i aria-hidden="true" class="fa fa-arrows handle"></i></div>
      <?php endif; ?>
    </h4>
  </div>
  <div id="<?php echo $field['#id']; ?>-<?php echo $key; ?>" class="panel-collapse collapse<?php if( $key == 0 ) echo ' in'; ?> " role="tabpanel" aria-labelledby="<?php echo $field['#id']; ?>-heading-<?php echo $key; ?>">
    <div class="panel-body">
      <?php 
        // Try to build states, print fields
        $states = [];
        foreach( $group as $sub_field ) {
          if( $sub_field ) {
            if( !empty( $sub_field['#states'] ) ) {
              $states[$sub_field['#id']] = $sub_field['#states'];
            }
          }
          $this->printFormItem( $sub_field ); 
        }
        if( !empty( $states ) ) {
          $this->attachConfigStateJs( $states, $group );
        }
      ?>
      <input type="hidden" class="group-weight" name="<?php echo $field['#name']; ?>[<?php echo $key; ?>][weight]" value="<?php echo $key; ?>">
      <a href="#" class="pull-right label label-warning group-delete-row">Remove</a>
    </div>
  </div>
</div>