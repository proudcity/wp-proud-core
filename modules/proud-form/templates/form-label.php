
  <label for="<?php echo $id; ?>"<?php if( !empty( $args['placeholder'] ) ) { echo ' class="sr-only"'; } ?>>
    <?php if($translate) : ?>
      <?php echo __( $text, $translate); ?>
    <?php else: ?>
      <?php echo $text; ?>
    <?php endif; ?>
    <?php echo $after ? $after : ''; ?>
  </label>