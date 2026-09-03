<?php

use Proud\Document;

// DocumentWidget::printWidget() assigns $id from preg_replace(), which returns
// a string even when the stored instance value is already numeric, while the
// admin AJAX preview casts with (int). Normalise once here so both callers
// agree and the `proud_document_embed_preview` filter can promise an int.
$id = absint( $id );

// Conversion providers replace `document` meta on the front end with a URL to
// an HTML rendition, on every page except the Document's own single view. Both
// consumers below need the real file: the Download button, and the third-party
// viewer, which fetches the URL server-side and can only render the original.
$src = \Proud\Core\proud_document_original_url( $id );
$filename = get_post_meta( $id, 'document_filename', true );
$meta = json_decode(get_post_meta( $id, 'document_meta', true ));
$terms = wp_get_post_terms( $id, 'document_taxonomy', array("fields" => "all"));
$filetype = Document\get_document_type( $id );
$show_preview = false;

if (in_array($filetype, array('pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx') ) && (
        empty($meta->size)
        || ( strpos(strtoupper($meta->size), 'KB') !== FALSE || ( strpos($meta->size, 'MB') !== FALSE && (int)str_replace(' MB', '', $meta->size) <= 25 ) )
    )) {
    if (in_array($filetype, array('doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx') )) {
        $show_preview = 'office';
    }
    else {
        $show_preview = true;
    }
};

// Google Docs Viewer and the Office viewer both fetch $src themselves and
// render "No preview available" for anything that is not the file type we
// advertise. If the URL does not carry the extension $filetype promises, send
// it nowhere rather than embedding a viewer that will fail.
//
// This has to be a positive match. Every URL a provider substitutes is
// extensionless -- a query-arg endpoint on home_url('/'), or a page permalink
// -- so a check that only fired when the URL had some *other* extension would
// never fire at all.
$src_extension = strtolower( pathinfo( (string) wp_parse_url( $src, PHP_URL_PATH ), PATHINFO_EXTENSION ) );

if ( $filetype && $src_extension !== $filetype ) {
    $show_preview = false;
}

$preview_html = '';

if ($show_preview === 'office') {
    $preview_html = '<iframe src="https://view.officeapps.live.com/op/embed.aspx?src=' . esc_url( $src ) . '" style="width:100%; max-width:600px; height:400px;' . ($show_preview === 2 ? 'display:none' : '') . ';" frameborder="0"></iframe>';
}
elseif ($show_preview) {
    $preview_html = '<iframe src="//docs.google.com/gview?url=' . esc_url( $src ) . '&embedded=true" id="doc-preview" style="width:100%; max-width:600px; height:400px;' . ($show_preview === 2 ? 'display:none' : '') . ';" frameborder="0" ></iframe>';
}

/**
 * Filters the Embed Document widget preview markup.
 *
 * A conversion provider can substitute its own preview here instead of the
 * third-party viewer. Resolving which preview belongs to a Document is the
 * provider's job -- it knows where it published one; core does not.
 *
 * Fires even when $preview_html is '', so a provider can offer a preview for a
 * file type ProudCity does not preview itself.
 *
 * @param string $preview_html Default third-party viewer iframe, or '' when no preview is shown.
 * @param int    $id           Document post ID.
 * @param string $src          Original document URL, provider substitution stood down.
 */
$filtered_preview_html = apply_filters( 'proud_document_embed_preview', $preview_html, $id, $src );

if ( ! is_string( $filtered_preview_html ) ) {
    $filtered_preview_html = $preview_html;
}

// @todo: currently not showing gravity forms
/*
 * $form_id = get_post_meta( $id, 'form', true );

if ( !empty($form_id) ) {

    // Docs: https://www.gravityhelp.com/documentation/article/embedding-a-form/#usage-examples
    // gravity_form( $id_or_title, $display_title = true, $display_description = true, $display_inactive = false, $field_values = null, $ajax = false, $tabindex, $echo = true );
    $form = gravity_form( $form_id, false, true, false, null, false, 0, false );
    $show_preview = 2;
}
*/

?>

<h2 style="max-width:600px;">
    <i aria-hidden="true" class="fa fa-fw <?php echo esc_attr( Document\get_document_icon( $id ) ) ?>"></i>
    <a href="<?php echo esc_url( $src ); ?>" class="btn btn-primary btn-sm pull-right" download="<?php echo esc_attr( $filename ); ?>"><i aria-hidden="true" class="fa fa-download"></i> Download</a>
    <a href="<?php echo esc_url( get_permalink( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a>
</h2>

<?php
if ( '' !== $filtered_preview_html ) {
    // Our own markup is already escaped above and must not be re-escaped -- a
    // kses pass rewrites the query-string entities and normalises the style
    // attribute, so sites with no provider would see their output change for
    // no reason. Only a provider's replacement goes through wp_kses().
    echo '    ' . ( $filtered_preview_html === $preview_html
        ? $preview_html
        : wp_kses( $filtered_preview_html, \Proud\Core\proud_document_preview_allowed_html() ) ) . "\n";
}
?>

<?php if( !empty($form_id) ): ?>
    <?php print $form; ?>
<?php endif; ?>
