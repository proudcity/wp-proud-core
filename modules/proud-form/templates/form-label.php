<label for="<?php echo $id; ?>">
  <?php if($translate) : ?>
    <?php echo __( $text, $translate); ?>
  <?php else: ?>
    <?php echo $text; ?>
  <?php endif; ?>
</label>