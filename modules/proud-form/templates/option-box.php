<label for="<?php echo $id ?>">
  <input id="<?php echo $id ?>" name="<?php echo $name ?>" type="<?php echo $type ?>"<?php if($active){echo ' checked="checked"'; } ?> value="<?php echo esc_attr( $value ); ?>"> 
  <?php if($translate) : ?>
    <?php echo __( $text, $translate); ?>
  <?php else: ?>
    <?php echo $text; ?>
  <?php endif; ?>
  <?php echo !empty( $after ) ? $after : ''; ?>
</label>