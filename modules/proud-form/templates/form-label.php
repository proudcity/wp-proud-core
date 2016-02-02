
  <label 
    for="<?php echo $id; ?>"
    <?php foreach ($args as $key=>$value): ?>
      <?php echo $key ?>="<?php echo $value ?>"
    <?php endforeach ?>
  >
    <?php if($translate) : ?>
      <?php echo __( $text, $translate); ?>
    <?php else: ?>
      <?php echo $text; ?>
    <?php endif; ?>
    <?php echo $after ? $after : ''; ?>
  </label>