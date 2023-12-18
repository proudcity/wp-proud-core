<!-- modules/proud-widget/widgets/teaser-list/templates/teaser-list.php -->
<?php
/**
 * Unique ID comes from teaser-list-widget.class.php printWidget().
 * This is included so that on Accordion widgets we can have a unique
 * id for each accordion which lets the individual accordion elements
 * target the proper parent accordion.
 */
$teaser_list->print_list( $uniqueID );
?>

<?php if( !empty( $instance['more_link'] ) ): ?>
  <p><a href="<?php echo esc_url( $instance['link_url'] ); ?>"><?php echo esc_attr( $instance['link_title'] ); ?></a></p>
<?php endif; ?>
