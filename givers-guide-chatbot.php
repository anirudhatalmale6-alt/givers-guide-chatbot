<?php
/**
 * Plugin Name: Givers' Guide AI Chatbot & Directory
 * Plugin URI: https://giversguide.org
 * Description: AI-powered chatbot and searchable resource directory for the Givers' Guide.
 * Version: 1.0.0
 * Author: Anirudha Talmale
 * License: GPL v2 or later
 * Text Domain: givers-guide
 */

if (!defined('ABSPATH')) exit;

define('GG_VERSION', '1.0.0');
define('GG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GG_TABLE_RESOURCES', 'gg_resources');
define('GG_TABLE_CATEGORIES', 'gg_categories');
define('GG_TABLE_APPS', 'gg_apps');
define('GG_TABLE_REPORTS', 'gg_reports');
define('GG_TABLE_CONVERSATIONS', 'gg_conversations');

// Includes
require_once GG_PLUGIN_DIR . 'includes/class-database.php';
require_once GG_PLUGIN_DIR . 'includes/class-importer.php';
require_once GG_PLUGIN_DIR . 'includes/class-chatbot.php';
require_once GG_PLUGIN_DIR . 'includes/class-directory.php';
require_once GG_PLUGIN_DIR . 'includes/class-reports.php';
require_once GG_PLUGIN_DIR . 'includes/class-rest-api.php';

if (is_admin()) {
    require_once GG_PLUGIN_DIR . 'admin/class-admin.php';
}

class GiversGuideChatbot {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_public_assets']);
        add_action('wp_footer', [$this, 'render_chatbot_widget']);

        // Initialize components
        GG_REST_API::init();
        GG_Directory::init();
        GG_Reports::init();

        if (is_admin()) {
            GG_Admin::init();
        }
    }

    public function activate() {
        GG_Database::create_tables();
        GG_Database::seed_default_options();

        // Auto-import bundled CSV data if tables are empty
        global $wpdb;
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->prefix . GG_TABLE_RESOURCES);
        if ($count === 0) {
            $data_dir = GG_PLUGIN_DIR . 'data/';
            if (file_exists($data_dir . 'USA_Resources.csv')) {
                GG_Importer::import_resources_csv($data_dir . 'USA_Resources.csv', 'usa');
            }
            if (file_exists($data_dir . 'Israel_Resources.csv')) {
                GG_Importer::import_resources_csv($data_dir . 'Israel_Resources.csv', 'israel');
            }
            if (file_exists($data_dir . 'England_Resources.csv')) {
                GG_Importer::import_resources_csv($data_dir . 'England_Resources.csv', 'england');
            }
            if (file_exists($data_dir . 'USA_Low-Cost_Psycho_Ed_Evals.csv')) {
                GG_Importer::import_resources_csv($data_dir . 'USA_Low-Cost_Psycho_Ed_Evals.csv', 'usa');
            }
            if (file_exists($data_dir . 'Mental_Health_Apps_Resources.csv')) {
                GG_Importer::import_apps_csv($data_dir . 'Mental_Health_Apps_Resources.csv');
            }
        }

        flush_rewrite_rules();
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function init() {
        // Register shortcodes
        add_shortcode('gg_directory', [GG_Directory::class, 'shortcode']);
        add_shortcode('gg_chatbot', [GG_Chatbot::class, 'shortcode']);
    }

    public function enqueue_public_assets() {
        $enabled = get_option('gg_chatbot_enabled', '1');
        if ($enabled !== '1') return;

        wp_enqueue_style(
            'gg-chatbot-style',
            GG_PLUGIN_URL . 'public/css/chatbot.css',
            [],
            GG_VERSION
        );
        wp_enqueue_style(
            'gg-directory-style',
            GG_PLUGIN_URL . 'public/css/directory.css',
            [],
            GG_VERSION
        );
        wp_enqueue_script(
            'gg-chatbot-script',
            GG_PLUGIN_URL . 'public/js/chatbot.js',
            [],
            GG_VERSION,
            true
        );
        wp_enqueue_script(
            'gg-directory-script',
            GG_PLUGIN_URL . 'public/js/directory.js',
            [],
            GG_VERSION,
            true
        );
        wp_localize_script('gg-chatbot-script', 'ggChatbot', [
            'ajaxUrl' => rest_url('givers-guide/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
            'botName' => get_option('gg_bot_name', "Givers' Guide Assistant"),
            'welcomeMessage' => get_option('gg_welcome_message', "Hi! I'm the Givers' Guide Assistant. I can help you find resources and services. What are you looking for?"),
            'primaryColor' => get_option('gg_primary_color', '#9355ff'),
            'accentColor' => get_option('gg_accent_color', '#4bfada'),
        ]);
        wp_localize_script('gg-directory-script', 'ggDirectory', [
            'ajaxUrl' => rest_url('givers-guide/v1/'),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }

    public function render_chatbot_widget() {
        $enabled = get_option('gg_chatbot_enabled', '1');
        if ($enabled !== '1') return;

        include GG_PLUGIN_DIR . 'templates/chatbot-widget.php';
    }
}

GiversGuideChatbot::instance();
