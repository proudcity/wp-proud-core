<?php
/**
 * UniqueID is passed through a bunch of files so that accordions can have a unique
 * parent id and then target the expected parent.
 *
 * Find the original definition of uniqueID in teaser-list-widget.class.php
 */
?>
<div class="panel-group" id="accordion<?php echo esc_attr( $uniqueID ); ?>"><!-- template-file: wp-proud-core/modules/proud-teasers/templates/teaser-accordian-header.php -->
