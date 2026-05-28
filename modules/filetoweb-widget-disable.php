<?php
/**
 * Disable the bundled FileToWeb Integration widget.
 *
 * ProudCity surfaces FileToWeb content through Proud Document and the link
 * rewriter, so the standalone widget is not needed.
 *
 * @since 2026.05.28
 */

add_filter( 'filetoweb_integration_enable_widget', '__return_false' );
