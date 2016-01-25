<div id="<?php echo $field['#id']; ?>" class="repeating-group">
  <div class="panel-group" id="<?php echo $field['#id']; ?>-accordion" role="tablist" aria-multiselectable="true">
  <?php foreach($field['#items'] as $key => $group): ?>
    <div class="panel panel-default">
      <div class="panel-heading" role="tab" id="<?php echo $field['#id']; ?>-heading-<?php echo $key; ?>">
        <h4 class="panel-title">
          <a role="button" data-toggle="collapse" data-parent="#<?php echo $field['#id']; ?>-accordion" href="#<?php echo $field['#id']; ?>-<?php echo $key; ?>" aria-expanded="true" aria-controls="<?php echo $field['#id']; ?>-<?php echo $key; ?>">
            <?php echo __($field['#title'], $this->form_id) . ' ' . ( $key + 1 ); ?>
          </a>
        </h4>
      </div>
      <div id="<?php echo $field['#id']; ?>-<?php echo $key; ?>" class="panel-collapse collapse<?php if( $key == 0 ) echo ' in'; ?> " role="tabpanel" aria-labelledby="<?php echo $field['#id']; ?>-heading-<?php echo $key; ?>">
        <div class="panel-body">
          <?php foreach($group as $sub_field): ?>
            <?php $this->printFormItem( $sub_field ); ?>
          <?php endforeach; ?>
        </div>
      </div>            
    </div>
  <?php endforeach; ?>
  </div>
  <button id="<?php echo $field['#id']; ?>-add" class="add-row">Add Set</button>
</div>