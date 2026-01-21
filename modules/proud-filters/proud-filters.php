<?php

if (! defined('ABSPATH')) exit; // Exit if accessed directly
/**
 * Our blueprint class for all filters going forward
 *
 * @since  2026.01.21
 * @author Curtis <curtis@proudcity.com>
 */
interface Proud_Filter_Provider
{
    public function get_key(): string; // unique provider id, e.g. 'service_list'

    public function get_state(array $request): array;
    public function get_available_options(array $config = []): array;

    public function build_query_args(array $config, array $state): array;

    public function render(array $config, array $state): void;
}

class Proud_Filter_Registry
{
    protected $contexts = [];
    protected $default_context_id = '';

    public function register(string $context_id, Proud_Filter_Provider $provider, array $config): void
    {
        $this->contexts[$context_id] = [
            'provider' => $provider,
            'config'   => $config,
        ];
    }

    public function set_default_context_id(string $context_id): void
    {
        $this->default_context_id = $context_id;
    }

    public function get_default_context_id(): string
    {
        return $this->default_context_id;
    }

    public function get(string $context_id): ?array
    {
        return $this->contexts[$context_id] ?? null;
    }

    public function all_context_ids(): array
    {
        return array_keys($this->contexts);
    }

    public function first_context_id(): string
    {
        $ids = $this->all_context_ids();
        return $ids[0] ?? '';
    }
}


final class Proud_Filters
{

    private static $instance;

    protected $registry;
    protected $providers = [];

    private function __construct()
    {
        $this->registry = new Proud_Filter_Registry();
    }

    public static function instance(): Proud_Filters
    {
        if (! self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function registry(): Proud_Filter_Registry
    {
        return $this->registry;
    }

    public function register_provider(Proud_Filter_Provider $provider): void
    {
        $this->providers[$provider->get_key()] = $provider;
    }

    public function provider(string $key): ?Proud_Filter_Provider
    {
        return $this->providers[$key] ?? null;
    }
}

/**
 * Defining the main function so we can use it anywhere
 *
 * @since  2026.01.21
 * @author Curtis <curtis@proudcity.com>
 */
function proud_filters(): Proud_Filters
{
    return Proud_Filters::instance();
}
add_filter('siteorigin_panels_before_content', function ($content, $panels_data) {
    if (!function_exists('proud_filters')) {
        return $content;
    }

    static $did = false;
    if ($did) {
        return $content;
    }
    $did = true;

    if (empty($panels_data['widgets']) || !is_array($panels_data['widgets'])) {
        return $content;
    }

    // Ask other plugins to describe a filterable context for each widget.
    foreach ($panels_data['widgets'] as $widget_instance) {
        $context = apply_filters('proud_filters_detect_context', null, $widget_instance, $panels_data);

        if (
            is_array($context)
            && !empty($context['provider_key'])
            && !empty($context['config'])
        ) {
            $provider = proud_filters()->provider($context['provider_key']);
            if (!$provider) {
                continue;
            }

            // One context per page, deterministic id.
            $context_id = 'default:' . (int) get_queried_object_id();

            proud_filters()->registry()->register($context_id, $provider, $context['config']);

            // Optionally store "default context id" so widgets don't guess.
            proud_filters()->registry()->set_default_context_id($context_id);

            break; // stop after first match (your constraint)
        }
    }

    return $content;
}, 5, 2);
