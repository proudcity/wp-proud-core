<?php

/**
 * WP Stateless overrides
 *
 * Moves the cache-busting hash from the filename prefix (upstream default)
 * to a suffix so the human-readable portion of the name is visible first.
 *
 * Upstream:  a8oset-report.pdf
 * ProudCity: report-a8oset.pdf
 *
 * The hook point is `stateless_skip_cache_busting` (class-utility.php:71).
 * Returning a non-null string from that filter short-circuits the upstream
 * randomizer entirely.
 *
 * @since 2026.06.23
 */

/**
 * Replace the upstream prefix hash with a suffix hash on file upload.
 *
 * @param string|null $return  Value set by an earlier filter; respected if non-null.
 * @param string      $filename Filename being processed (after sanitize_file_name).
 * @return string|null Suffixed filename, or null to let the upstream handler run.
 */
function proudcity_stateless_suffix_cache_bust( $return, $filename ) {
    // If something else already decided, respect it.
    if ( $return ) {
        return $return;
    }

    $info = pathinfo( $filename );
    $name = isset( $info['filename'] ) ? $info['filename'] : '';
    $ext  = empty( $info['extension'] ) ? '' : '.' . strtolower( $info['extension'] );

    // Idempotency: already suffixed with -[8 hex chars] before the extension.
    if ( preg_match( '/-[a-f0-9]{8}$/', $name ) ) {
        return $filename;
    }

    // Respect files uploaded before this change shipped — they carry the
    // upstream prefix format and should not be double-randomized.
    if ( preg_match( '/^[a-f0-9]{8}-/', $name ) ) {
        return $filename;
    }

    // Mix wp_rand() into the seed so two uploads in the same second get
    // different hashes. md5(time()) alone collides within the same second,
    // which is the latent upstream bug that caused issue #2232.
    $rand = substr( md5( (string) time() . wp_rand() ), 0, 8 );

    // Handle retina @2x-style suffixes: keep the @2x marker in the right
    // position so WordPress srcset handling continues to work correctly.
    if ( false !== strpos( $name, '@' ) ) {
        list( $clean, $retina ) = explode( '@', $name, 2 );
        return strtolower( $clean ) . '-' . $rand . '@' . strtolower( $retina ) . $ext;
    }

    return strtolower( $name ) . '-' . $rand . $ext;
}
add_filter( 'stateless_skip_cache_busting', 'proudcity_stateless_suffix_cache_bust', 10, 2 );
