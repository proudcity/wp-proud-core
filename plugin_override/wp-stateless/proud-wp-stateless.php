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
 * Returns true when a Gravity Forms file upload is currently in progress.
 *
 * Two signals are checked so both of our GF integration paths are covered:
 *
 * 1. Explicit flag ($GLOBALS['proudcity_gform_upload_context']) — set by
 *    gform_get_gcloud_file() in proud-gravityforms.php around the direct
 *    Utility::randomize_filename() call. Deterministic for our active path.
 *
 * 2. doing_filter('gform_save_field_value') fallback — covers the WP-Stateless
 *    GF add-on path, where randomize_filename() is called via sanitize_file_name
 *    while the gform_save_field_value hook is on the stack.
 *
 * @return bool
 */
function proudcity_stateless_is_gform_upload() {
    if ( ! empty( $GLOBALS['proudcity_gform_upload_context'] ) ) {
        return true;
    }
    if ( function_exists( 'doing_filter' ) && doing_filter( 'gform_save_field_value' ) ) {
        return true;
    }
    return false;
}

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

    // In a GF-upload context every submission is a genuinely new file, so we
    // bypass both idempotency guards and always mint a fresh unique suffix.
    // This prevents multiple applicants uploading the same pre-named form from
    // colliding onto the same GCS object (issue #2876).
    if ( proudcity_stateless_is_gform_upload() ) {
        $rand = substr( md5( (string) time() . wp_rand() ), 0, 8 );

        if ( false !== strpos( $name, '@' ) ) {
            list( $clean, $retina ) = explode( '@', $name, 2 );
            return strtolower( $clean ) . '-' . $rand . '@' . strtolower( $retina ) . $ext;
        }

        return strtolower( $name ) . '-' . $rand . $ext;
    }

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
