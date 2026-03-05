<?php
if (!defined('ABSPATH')) exit;

class GG_Directory {

    public static function init() {
        add_action('init', [self::class, 'register_rewrite_rules']);
        add_filter('query_vars', [self::class, 'register_query_vars']);
        add_filter('template_include', [self::class, 'load_template']);
    }

    public static function register_rewrite_rules() {
        add_rewrite_rule('^resource/([0-9]+)/?$', 'index.php?gg_resource_id=$matches[1]', 'top');
    }

    public static function register_query_vars($vars) {
        $vars[] = 'gg_resource_id';
        return $vars;
    }

    public static function load_template($template) {
        $resource_id = get_query_var('gg_resource_id');
        if ($resource_id) {
            $custom = GG_PLUGIN_DIR . 'templates/single-resource.php';
            if (file_exists($custom)) return $custom;
        }
        return $template;
    }

    /**
     * Directory shortcode: [gg_directory]
     */
    public static function shortcode($atts) {
        $atts = shortcode_atts([
            'region' => '',
            'category' => '',
            'per_page' => get_option('gg_results_per_page', '12'),
            'show_search' => 'yes',
            'show_filters' => 'yes',
            'type' => 'resources', // resources or apps
        ], $atts);

        ob_start();
        include GG_PLUGIN_DIR . 'templates/directory.php';
        return ob_get_clean();
    }
}
