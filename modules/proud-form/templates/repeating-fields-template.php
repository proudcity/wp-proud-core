<div data-count="<?php echo $key ?>" class="panel panel-default">
  <div class="panel-heading" role="tab" id="<?php echo $field['#id']; ?>-heading-<?php echo $key; ?>">
    <h4 class="panel-title">
      <a role="button" data-toggle="collapse" data-parent="#<?php echo $field['#id']; ?>-accordion" href="#<?php echo $field['#id']; ?>-<?php echo $key; ?>" aria-expanded="true" aria-controls="<?php echo $field['#id']; ?>-<?php echo $key; ?>">
        <?php echo $group_title ?>
      </a>
      <div class="pull-right" style="cursor: move;cursor: grab;cursor: -moz-grab;cursor: -webkit-grab;"><i class="fa fa-arrows handle"></i></div>
    </h4>
  </div>
  <div id="<?php echo $field['#id']; ?>-<?php echo $key; ?>" class="panel-collapse collapse<?php if( $key == 0 ) echo ' in'; ?> " role="tabpanel" aria-labelledby="<?php echo $field['#id']; ?>-heading-<?php echo $key; ?>">
    <div class="panel-body">
      <?php foreach($group as $sub_field): ?>
        <?php $this->printFormItem( $sub_field ); ?>
      <?php endforeach; ?>
      <input type="hidden" class="group-weight" name="<?php echo $field['#name']; ?>[<?php echo $key; ?>][weight]" value="<?php echo $key; ?>">
      <a href="#" class="pull-right label label-warning group-delete-row">Remove</a>
    </div>
  </div>
</div>