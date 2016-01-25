<script>
  jQuery(document).ready(function() {
    var fieldFunctions = [];
    <?php $field_count = 0; foreach ( $fields as $field_selector => $rules ): ?>
      fieldFunctions.push(function () {
        if(<?php echo $rules['if'] ?>) {
          jQuery("#<?php echo $field_selector ?>").show();
        }
        else {
          jQuery("#<?php echo $field_selector ?>").hide();
        }
      });
      jQuery("<?php echo $rules['watches'] ?>").change(function() {
        fieldFunctions[<?php echo $field_count; ?>]();
      });
      fieldFunctions[<?php echo $field_count; ?>]();
    <?php $field_count++; endforeach; ?>
  });
</script>