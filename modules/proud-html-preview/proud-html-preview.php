<?php
/**
 * Provider-neutral durable HTML previews for ProudCity documents.
 *
 * Conversion providers publish a sanitized bundle under WordPress uploads and
 * atomically point `_proud_html_preview` at it. ProudCity owns public serving,
 * so a completed preview does not depend on the provider plugin at request time.
 *
 * @package ProudCity
 */

namespace Proud\Core;

const PROUD_HTML_PREVIEW_META = '_proud_html_preview';
const PROUD_HTML_PREVIEW_PROVIDERS_OPTION = 'proud_html_preview_providers';
const PROUD_HTML_PREVIEW_SCHEMA_VERSION = 1;
const PROUD_HTML_PREVIEW_CLEANUP_OPTION = 'proud_html_preview_cleanup_queue';
const PROUD_HTML_PREVIEW_CLEANUP_HOOK = 'proud_html_preview_cleanup_remote_artifacts';

$GLOBALS['proud_html_preview_resolving_source'] = 0;

add_action('template_redirect', __NAMESPACE__ . '\\proud_html_preview_maybe_serve', -100);
add_action('init', __NAMESPACE__ . '\\proud_html_preview_schedule_cleanup', 30);
add_action(PROUD_HTML_PREVIEW_CLEANUP_HOOK, __NAMESPACE__ . '\\proud_html_preview_run_cleanup');

/**
 * Return the public endpoint URL for a valid durable preview.
 *
 * @param int    $source_post_id Attachment or attachment-less source post ID.
 * @param string $source_url     Current original document URL.
 * @return string
 */
function proud_html_preview_url($source_post_id, $source_url)
{
    $record = proud_html_preview_record($source_post_id, $source_url);

    if (!$record) {
        return '';
    }

    return add_query_arg(
        [
            'proud_html_preview' => absint($source_post_id),
            'preview_token' => $record['token'],
            'preview_source' => proud_html_preview_source_identity($record),
        ],
        home_url('/')
    );
}

/**
 * Validate and normalize a published preview record.
 *
 * @param int    $source_post_id Source post ID.
 * @param string $source_url     Optional current source URL.
 * @return array|null
 */
function proud_html_preview_record($source_post_id, $source_url = '')
{
    $source_post_id = absint($source_post_id);
    $record = $source_post_id ? get_post_meta($source_post_id, PROUD_HTML_PREVIEW_META, true) : null;

    if (!is_array($record)) {
        return null;
    }

    $required = ['version', 'provider', 'source_url', 'source_fingerprint', 'artifact_key', 'artifact_url', 'token', 'published_at'];
    foreach ($required as $key) {
        if (!isset($record[$key]) || '' === (string) $record[$key]) {
            return null;
        }
    }

    if (PROUD_HTML_PREVIEW_SCHEMA_VERSION !== (int) $record['version']) {
        return null;
    }

    $provider = sanitize_key($record['provider']);
    if (!$provider || !proud_html_preview_provider_enabled($provider)) {
        return null;
    }

    $record['provider'] = $provider;
    $record['source_url'] = esc_url_raw($record['source_url']);
    $record['artifact_url'] = esc_url_raw($record['artifact_url']);
    $record['artifact_key'] = proud_html_preview_artifact_key($record['artifact_key']);
    $record['token'] = sanitize_text_field($record['token']);
    $record['source_fingerprint'] = sanitize_text_field($record['source_fingerprint']);
    $record['source_fingerprint_algorithm'] = isset($record['source_fingerprint_algorithm'])
        ? sanitize_key($record['source_fingerprint_algorithm'])
        : '';

    if (!$record['source_url'] || !$record['artifact_url'] || !$record['artifact_key'] || !$record['token'] || !$record['source_fingerprint']) {
        return null;
    }

    if ($source_url && proud_html_preview_normalize_url($source_url) !== proud_html_preview_normalize_url($record['source_url'])) {
        return null;
    }

    if (!proud_html_preview_trusted_artifact_url($record['artifact_url'], $record['artifact_key'])) {
        return null;
    }

    return $record;
}

/**
 * Whether a provider remains enabled for completed public previews.
 *
 * @param string $provider Provider key.
 * @return bool
 */
function proud_html_preview_provider_enabled($provider)
{
    $providers = get_option(PROUD_HTML_PREVIEW_PROVIDERS_OPTION, []);

    return is_array($providers) && !empty($providers[sanitize_key($provider)]);
}

/**
 * Build the non-secret source identity included in endpoint URLs.
 *
 * @param array $record Preview record.
 * @return string
 */
function proud_html_preview_source_identity(array $record)
{
    return hash('sha256', $record['source_url'] . '|' . $record['source_fingerprint']);
}

/**
 * Resolve and, when needed, restore an endpoint request.
 *
 * @param int    $source_post_id Source post ID.
 * @param string $token Preview token.
 * @param string $source_identity Source identity.
 * @return array Result with status ready, redirect, or not_found.
 */
function proud_html_preview_resolve_request($source_post_id, $token, $source_identity)
{
    $record = proud_html_preview_record($source_post_id);

    if (!$record || !$token || !hash_equals((string) $record['token'], (string) $token)) {
        return ['status' => 'not_found'];
    }

    $expected_identity = proud_html_preview_source_identity($record);
    if (!$source_identity || !hash_equals($expected_identity, (string) $source_identity)) {
        return ['status' => 'not_found'];
    }

    $current_source = proud_html_preview_current_source($source_post_id);
    if (!proud_html_preview_current_source_matches($record, $current_source)) {
        return proud_html_preview_fallback_result($record, $current_source['url']);
    }

    $path = proud_html_preview_local_path($record['artifact_key']);
    if (!$path) {
        return proud_html_preview_fallback_result($record);
    }

    if (!is_readable($path) && !proud_html_preview_restore_artifact($record, $path)) {
        return proud_html_preview_fallback_result($record);
    }

    return [
        'status' => 'ready',
        'path' => $path,
        'record' => $record,
    ];
}

/**
 * Serve a valid preview request before ProudCity selects a theme template.
 */
function proud_html_preview_maybe_serve()
{
    $source_post_id = isset($_GET['proud_html_preview']) ? absint(wp_unslash($_GET['proud_html_preview'])) : 0;

    if (!$source_post_id) {
        return;
    }

    $token = isset($_GET['preview_token']) && is_scalar($_GET['preview_token'])
        ? sanitize_text_field(wp_unslash($_GET['preview_token']))
        : '';
    $source_identity = isset($_GET['preview_source']) && is_scalar($_GET['preview_source'])
        ? sanitize_text_field(wp_unslash($_GET['preview_source']))
        : '';
    $result = proud_html_preview_resolve_request($source_post_id, $token, $source_identity);

    if ('redirect' === $result['status']) {
        wp_redirect($result['url'], 302, 'ProudCity HTML Preview');
        exit;
    }

    if ('ready' !== $result['status']) {
        status_header(404);
        exit;
    }

    $record = $result['record'];
    $storage_origin = proud_html_preview_url_origin($record['artifact_url']);
    $asset_sources = "'self' data:" . ($storage_origin ? ' ' . $storage_origin : '');

    nocache_headers();
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: SAMEORIGIN');
    header(
        "Content-Security-Policy: default-src 'none'; "
        . "script-src 'none'; style-src 'self' 'unsafe-inline'" . ($storage_origin ? ' ' . $storage_origin : '') . '; '
        . 'img-src ' . $asset_sources . '; font-src ' . $asset_sources . "; connect-src 'none'; "
        . "object-src 'none'; media-src 'self'" . ($storage_origin ? ' ' . $storage_origin : '') . "; "
        . "frame-src 'none'; frame-ancestors 'self'; base-uri 'none'; form-action 'none'"
    );

    readfile($result['path']);
    exit;
}

/**
 * Restore a missing local artifact from its trusted uploads/GCS URL.
 *
 * @param array  $record Preview record.
 * @param string $path Local path.
 * @return bool
 */
function proud_html_preview_restore_artifact(array $record, $path)
{
    if (!proud_html_preview_trusted_artifact_url($record['artifact_url'], $record['artifact_key'])) {
        return false;
    }

    $args = [
        'timeout' => 15,
        'redirection' => 0,
        'reject_unsafe_urls' => true,
        'limit_response_size' => 20 * 1024 * 1024,
    ];
    $response = function_exists('wp_safe_remote_get')
        ? wp_safe_remote_get($record['artifact_url'], $args)
        : wp_remote_get($record['artifact_url'], $args);

    if (is_wp_error($response)) {
        return false;
    }

    $code = absint(wp_remote_retrieve_response_code($response));
    $body = (string) wp_remote_retrieve_body($response);

    if ($code < 200 || $code >= 300 || '' === $body || strlen($body) > 20 * 1024 * 1024) {
        return false;
    }

    if (false === stripos($body, '<html') && false === stripos($body, '<body')) {
        return false;
    }

    if (!wp_mkdir_p(dirname($path))) {
        return false;
    }

    $temp = $path . '.tmp-' . uniqid('', true);
    if (false === file_put_contents($temp, $body)) {
        return false;
    }

    if (!rename($temp, $path)) {
        unlink($temp);
        return false;
    }

    return true;
}

/**
 * Return a safe uploads-relative artifact key.
 *
 * @param mixed $key Candidate key.
 * @return string
 */
function proud_html_preview_artifact_key($key)
{
    $key = is_scalar($key) ? ltrim(str_replace('\\', '/', (string) $key), '/') : '';

    if (!$key || false !== strpos($key, "\0") || !preg_match('~^[A-Za-z0-9._/-]+\.html$~', $key)) {
        return '';
    }

    foreach (explode('/', $key) as $part) {
        if ('' === $part || '.' === $part || '..' === $part) {
            return '';
        }
    }

    return $key;
}

/**
 * Resolve a safe local path below the WordPress uploads directory.
 *
 * @param string $artifact_key Artifact key.
 * @return string
 */
function proud_html_preview_local_path($artifact_key)
{
    $artifact_key = proud_html_preview_artifact_key($artifact_key);
    $uploads = wp_upload_dir();
    $basedir = isset($uploads['basedir']) ? wp_normalize_path($uploads['basedir']) : '';

    if (!$artifact_key || !$basedir) {
        return '';
    }

    $path = wp_normalize_path(trailingslashit($basedir) . $artifact_key);
    $prefix = trailingslashit($basedir);

    return 0 === strpos($path, $prefix) ? $path : '';
}

/**
 * Validate the remote artifact against trusted upload base URLs and its exact key.
 *
 * @param string $url Artifact URL.
 * @param string $artifact_key Artifact key.
 * @return bool
 */
function proud_html_preview_trusted_artifact_url($url, $artifact_key)
{
    $artifact_key = proud_html_preview_artifact_key($artifact_key);
    $url = esc_url_raw($url);
    $parts = parse_url($url);

    if (!$artifact_key || !is_array($parts) || empty($parts['scheme']) || empty($parts['host']) || !empty($parts['user']) || !empty($parts['pass']) || !empty($parts['fragment'])) {
        return false;
    }

    $uploads = wp_upload_dir();
    $base_urls = !empty($uploads['baseurl']) ? [$uploads['baseurl']] : [];
    $base_urls = apply_filters('proud_html_preview_trusted_storage_base_urls', $base_urls, $url, $artifact_key);

    foreach (is_array($base_urls) ? $base_urls : [] as $base_url) {
        if (proud_html_preview_url_matches_base($parts, $base_url, $artifact_key)) {
            return true;
        }
    }

    return false;
}

/**
 * Match one artifact URL to one exact uploads/storage base URL.
 *
 * @param array  $parts Parsed artifact URL.
 * @param string $base_url Trusted base URL.
 * @param string $artifact_key Artifact key.
 * @return bool
 */
function proud_html_preview_url_matches_base(array $parts, $base_url, $artifact_key)
{
    $base = parse_url(esc_url_raw($base_url));

    if (!is_array($base) || empty($base['scheme']) || empty($base['host'])) {
        return false;
    }

    $scheme = strtolower((string) $parts['scheme']);
    $host = strtolower((string) $parts['host']);
    $base_scheme = strtolower((string) $base['scheme']);
    $base_host = strtolower((string) $base['host']);
    $home_host = strtolower((string) parse_url(home_url('/'), PHP_URL_HOST));
    $local_http = 'http' === $scheme && $host === $home_host;

    if (('https' !== $scheme && !$local_http) || $scheme !== $base_scheme || $host !== $base_host) {
        return false;
    }

    $port = isset($parts['port']) ? (int) $parts['port'] : null;
    $base_port = isset($base['port']) ? (int) $base['port'] : null;
    if ($port !== $base_port) {
        return false;
    }

    $path = rawurldecode(isset($parts['path']) ? (string) $parts['path'] : '');
    $base_path = rtrim(rawurldecode(isset($base['path']) ? (string) $base['path'] : ''), '/');
    $expected_path = $base_path . '/' . $artifact_key;

    return $path === $expected_path;
}

/**
 * Add a verified uploads/storage artifact to the provider-neutral cleanup queue.
 *
 * @param string $provider Provider key.
 * @param string $artifact_key Uploads-relative artifact key.
 * @param string $artifact_url Artifact URL.
 * @return bool
 */
function proud_html_preview_queue_cleanup($provider, $artifact_key, $artifact_url)
{
    $provider = sanitize_key($provider);
    $artifact_key = proud_html_preview_cleanup_artifact_key($artifact_key);
    $artifact_url = esc_url_raw($artifact_url);

    if (!$provider || !$artifact_key || !$artifact_url || !proud_html_preview_cleanup_url_is_trusted($artifact_url, $artifact_key)) {
        return false;
    }

    $queue = get_option(PROUD_HTML_PREVIEW_CLEANUP_OPTION, []);
    $queue = is_array($queue) ? $queue : [];
    $id = hash('sha256', $provider . '|' . $artifact_url);
    $queue[$id] = [
        'provider' => $provider,
        'artifact_key' => $artifact_key,
        'artifact_url' => $artifact_url,
        'attempts' => isset($queue[$id]['attempts']) ? absint($queue[$id]['attempts']) : 0,
    ];
    update_option(PROUD_HTML_PREVIEW_CLEANUP_OPTION, $queue, false);
    proud_html_preview_schedule_cleanup();

    return true;
}

/**
 * Schedule remote cleanup while queue entries remain.
 */
function proud_html_preview_schedule_cleanup()
{
    $queue = get_option(PROUD_HTML_PREVIEW_CLEANUP_OPTION, []);

    if (is_array($queue) && !empty($queue) && !wp_next_scheduled(PROUD_HTML_PREVIEW_CLEANUP_HOOK)) {
        wp_schedule_single_event(time() + 60, PROUD_HTML_PREVIEW_CLEANUP_HOOK);
    }
}

/**
 * Retry and verify a bounded set of remote non-media deletions.
 */
function proud_html_preview_run_cleanup()
{
    $queue = get_option(PROUD_HTML_PREVIEW_CLEANUP_OPTION, []);
    $queue = is_array($queue) ? $queue : [];
    $processed = 0;

    foreach ($queue as $id => $entry) {
        if (++$processed > 25) {
            break;
        }

        $provider = isset($entry['provider']) ? sanitize_key($entry['provider']) : '';
        $artifact_key = isset($entry['artifact_key']) ? proud_html_preview_cleanup_artifact_key($entry['artifact_key']) : '';
        $artifact_url = isset($entry['artifact_url']) ? esc_url_raw($entry['artifact_url']) : '';

        if (!$provider || !$artifact_key || !$artifact_url || !proud_html_preview_cleanup_url_is_trusted($artifact_url, $artifact_key)) {
            unset($queue[$id]);
            continue;
        }

        if (function_exists('has_action') && has_action('sm:sync::deleteFile')) {
            do_action('sm:sync::deleteFile', $artifact_key);
        }

        $response = wp_safe_remote_head(
            $artifact_url,
            [
                'timeout' => 10,
                'redirection' => 0,
                'reject_unsafe_urls' => true,
            ]
        );

        if (!is_wp_error($response)) {
            $code = absint(wp_remote_retrieve_response_code($response));
            if (404 === $code || 410 === $code) {
                unset($queue[$id]);
                continue;
            }
        }

        $queue[$id]['attempts'] = isset($entry['attempts']) ? absint($entry['attempts']) + 1 : 1;
    }

    if (empty($queue)) {
        delete_option(PROUD_HTML_PREVIEW_CLEANUP_OPTION);
        return;
    }

    update_option(PROUD_HTML_PREVIEW_CLEANUP_OPTION, $queue, false);
    wp_schedule_single_event(time() + 300, PROUD_HTML_PREVIEW_CLEANUP_HOOK);
}

/**
 * Validate one cleanup artifact key without allowing traversal or executables.
 *
 * @param mixed $key Candidate key.
 * @return string
 */
function proud_html_preview_cleanup_artifact_key($key)
{
    $key = is_scalar($key) ? ltrim(str_replace('\\', '/', (string) $key), '/') : '';

    if (!$key || false !== strpos($key, "\0") || !preg_match('~^[A-Za-z0-9._/-]+\.(?:html|css|jpe?g|png|gif|webp|avif|woff2?|ttf|otf)$~i', $key)) {
        return '';
    }

    foreach (explode('/', $key) as $part) {
        if ('' === $part || '.' === $part || '..' === $part) {
            return '';
        }
    }

    return $key;
}

/**
 * Validate a cleanup URL against the exact current uploads/storage base URL.
 *
 * @param string $artifact_url Artifact URL.
 * @param string $artifact_key Validated artifact key.
 * @return bool
 */
function proud_html_preview_cleanup_url_is_trusted($artifact_url, $artifact_key)
{
    $parts = parse_url($artifact_url);
    $uploads = wp_upload_dir();
    $base_urls = !empty($uploads['baseurl']) ? [$uploads['baseurl']] : [];
    $base_urls = apply_filters('proud_html_preview_trusted_storage_base_urls', $base_urls, $artifact_url, $artifact_key);

    foreach (is_array($base_urls) ? $base_urls : [] as $base_url) {
        if (is_array($parts) && proud_html_preview_url_matches_base($parts, $base_url, $artifact_key)) {
            return true;
        }
    }

    return false;
}

/**
 * Return the current, provider-neutral WordPress source for a preview owner.
 *
 * @param int $source_post_id Attachment or attachment-less Document ID.
 * @return array Source URL and optional readable local file.
 */
function proud_html_preview_current_source($source_post_id)
{
    $source_post_id = absint($source_post_id);
    $post_type = $source_post_id ? get_post_type($source_post_id) : '';

    if ('attachment' === $post_type) {
        $url = proud_html_preview_original_attachment_url($source_post_id);
        $path = function_exists('get_attached_file') ? get_attached_file($source_post_id, true) : '';

        return [
            'url' => esc_url_raw($url),
            'path' => is_string($path) ? $path : '',
        ];
    }

    if ('document' === $post_type) {
        return [
            'url' => esc_url_raw(proud_html_preview_raw_post_meta($source_post_id, 'document')),
            'path' => '',
        ];
    }

    return ['url' => '', 'path' => ''];
}

/**
 * Read an attachment URL while signaling render-time providers not to replace it.
 *
 * @param int $attachment_id Attachment ID.
 * @return string
 */
function proud_html_preview_original_attachment_url($attachment_id)
{
    ++$GLOBALS['proud_html_preview_resolving_source'];
    $url = wp_get_attachment_url(absint($attachment_id));
    --$GLOBALS['proud_html_preview_resolving_source'];

    return $url ? esc_url_raw($url) : '';
}

/**
 * Whether ProudCity is resolving a source URL for endpoint validation.
 *
 * @return bool
 */
function proud_html_preview_is_resolving_source()
{
    return !empty($GLOBALS['proud_html_preview_resolving_source']);
}

/**
 * Check the stored source URL and, when possible, its local SHA-256 content.
 *
 * @param array $record Preview record.
 * @param array $current_source Current source.
 * @return bool
 */
function proud_html_preview_current_source_matches(array $record, array $current_source)
{
    if (empty($current_source['url']) || proud_html_preview_normalize_url($current_source['url']) !== proud_html_preview_normalize_url($record['source_url'])) {
        return false;
    }

    $algorithm = isset($record['source_fingerprint_algorithm']) ? $record['source_fingerprint_algorithm'] : '';
    $path = isset($current_source['path']) ? $current_source['path'] : '';

    if ('sha256' === $algorithm && $path && is_readable($path)) {
        $fingerprint = hash_file('sha256', $path);

        return is_string($fingerprint) && hash_equals((string) $record['source_fingerprint'], $fingerprint);
    }

    return true;
}

/**
 * Read unfiltered post metadata so active rendering providers cannot rewrite it.
 *
 * @param int    $post_id Post ID.
 * @param string $meta_key Metadata key.
 * @return mixed
 */
function proud_html_preview_raw_post_meta($post_id, $meta_key)
{
    global $wpdb;

    if (isset($wpdb) && isset($wpdb->postmeta) && method_exists($wpdb, 'get_var') && method_exists($wpdb, 'prepare')) {
        $value = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id ASC LIMIT 1",
                absint($post_id),
                $meta_key
            )
        );

        return null === $value ? '' : maybe_unserialize($value);
    }

    return get_post_meta($post_id, $meta_key, true);
}

/**
 * Return a fallback only when the stored source is a safe HTTP(S) URL.
 *
 * @param array $record Preview record.
 * @return array
 */
function proud_html_preview_fallback_result(array $record, $source = null)
{
    $source = esc_url_raw(null === $source ? $record['source_url'] : $source);
    $scheme = strtolower((string) parse_url($source, PHP_URL_SCHEME));

    return $source && in_array($scheme, ['http', 'https'], true)
        ? ['status' => 'redirect', 'url' => $source]
        : ['status' => 'not_found'];
}

/**
 * Normalize URLs for source identity comparisons.
 *
 * @param string $url URL.
 * @return string
 */
function proud_html_preview_normalize_url($url)
{
    $url = esc_url_raw(html_entity_decode(trim((string) $url), ENT_QUOTES, 'UTF-8'));

    return untrailingslashit($url);
}

/**
 * Return a CSP-safe HTTPS origin.
 *
 * @param string $url URL.
 * @return string
 */
function proud_html_preview_url_origin($url)
{
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));

    return 'https' === $scheme && $host ? 'https://' . $host : '';
}
