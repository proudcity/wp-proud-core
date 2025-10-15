<?php
/**
 * Renders the text field for forms
 */
?>
<input
    id="<?php echo $id ?>"
    name="<?php echo $name ?>" type="text"
    value="<?php echo esc_attr($value); ?>"
    <?php if (isset($maxlength) && !empty($maxlength)){ ?>
        maxlength="<?php echo absint($maxlength); ?>"
    <?php } ?>
    <?php foreach ($args as $key=>$value): ?>
        <?php echo $key ?>="<?php echo esc_attr($value); ?>"
    <?php endforeach ?>
/>
<?php echo $after ? $after : ''; ?>
