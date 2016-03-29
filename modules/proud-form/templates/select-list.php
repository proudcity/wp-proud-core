<select class="form-control" id="<?php echo $id ?>" name="<?php echo $name; ?>">
  <?php foreach ( $options as $key => $label ): ?>
    <option value="<?php echo $key; ?>"<?php if($key == $value && '' !== $value) print ' selected="selected"';?>>
      <?php if($translate) : ?>
        <?php echo __( $label, $translate); ?>
      <?php else: ?>
        <?php echo $label; ?>
      <?php endif; ?>
    </option>
  <?php endforeach; ?>
</select>