<?php
$across = 2;
$class = ((int) $across === 3) ? 'card-columns-md-3' : 'card-columns-md-2';
?>

<div class="card-columns card-columns-xs-2 card-columns-sm-2 <?php echo sanitize_html_class($class) ?> card-columns-equalize text-card"><!-- template-file: wp-proud-menu/templates/textcard-wrapper.php -->
    <?php echo implode('', $menus); ?>
</div>
