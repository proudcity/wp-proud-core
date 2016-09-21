<?php
use Proud\Theme\Wrapper,
  Proud\Document;

$src = get_post_meta( $id, 'document', true );
$filename = get_post_meta( $id, 'document_filename', true );
$meta = json_decode(get_post_meta( $id, 'document_meta', true ));
$terms = wp_get_post_terms( $id, 'document_taxonomy', array("fields" => "all"));
$filetype = Document\get_document_type( $id );

$show_preview = in_array($filetype, array('pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx') ) && ( strpos(strtoupper($meta->size), 'KB') !== FALSE || ( strpos($meta->size, 'MB') !== FALSE && (int)str_replace(' MB', '', $meta->size) < 10 ) );

// @todo: currently not showing gravity forms
/*
$form_id = get_post_meta( $id, 'form', true );
if ( !empty($form_id) ) {

  // Docs: https://www.gravityhelp.com/documentation/article/embedding-a-form/#usage-examples
  // gravity_form( $id_or_title, $display_title = true, $display_description = true, $display_inactive = false, $field_values = null, $ajax = false, $tabindex, $echo = true );
  $form = gravity_form( $form_id, false, true, false, null, false, 0, false );
  $show_preview = 2;
}*/

?>

<h2 style="max-width:600px;">
  <i class="fa fa-fw <?php echo Document\get_document_icon( $id ) ?>"></i>
  <a href="<?php echo $src; ?>" class="btn btn-primary btn-sm pull-right" download="<?php echo $filename; ?>"><i class="fa fa-download"></i> Download</a>
  <a href="<?php echo get_permalink( $id ); ?>"><?php echo get_the_title( $id ); ?></a>
</h2>

<?php if ($show_preview): ?>
  <iframe class="clearfix" src="//docs.google.com/gview?url=<?php echo $src; ?>&embedded=true" id="doc-preview" style="width:100%; height:400px; max-width:600px;<?php if($show_preview === 2): ?>display:none<?php endif; ?>;" frameborder="0" ></iframe>
<?php endif; ?>
<?php if( !empty($form_id) ): ?>
  <?php print $form; ?>
<?php endif; ?>