<input 
  id="<?php echo $id ?>" 
  name="<?php echo $name ?>" type="text" 
  value="<?php echo esc_attr( $value ); ?>"
  <?php foreach ($args as $key=>$value): ?>
    <?php echo $key ?>="<?php echo $value ?>"
  <?php endforeach ?>
/>
<?php echo $after ? $after : ''; ?>