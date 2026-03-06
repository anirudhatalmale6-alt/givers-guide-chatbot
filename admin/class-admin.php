<?php
if (!defined('ABSPATH')) exit;

class GG_Admin {

    public static function init() {
        add_action('admin_menu', [self::class, 'add_menu']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
        add_action('admin_init', [self::class, 'handle_actions']);
    }

    public static function add_menu() {
        add_menu_page(
            "Givers' Guide",
            "Givers' Guide",
            'manage_options',
            'givers-guide',
            [self::class, 'page_dashboard'],
            'dashicons-format-chat',
            30
        );

        add_submenu_page('givers-guide', 'Dashboard', 'Dashboard', 'manage_options', 'givers-guide', [self::class, 'page_dashboard']);
        add_submenu_page('givers-guide', 'Resources', 'Resources', 'manage_options', 'gg-resources', [self::class, 'page_resources']);
        add_submenu_page('givers-guide', 'Categories', 'Categories', 'manage_options', 'gg-categories', [self::class, 'page_categories']);
        add_submenu_page('givers-guide', 'Import Data', 'Import Data', 'manage_options', 'gg-import', [self::class, 'page_import']);
        add_submenu_page('givers-guide', 'Reports', 'Reports', 'manage_options', 'gg-reports', [self::class, 'page_reports']);
        add_submenu_page('givers-guide', 'Settings', 'Settings', 'manage_options', 'gg-settings', [self::class, 'page_settings']);
    }

    public static function enqueue_admin_assets($hook) {
        if (strpos($hook, 'givers-guide') === false && strpos($hook, 'gg-') === false) return;

        wp_enqueue_style('gg-admin-style', GG_PLUGIN_URL . 'admin/admin.css', [], GG_VERSION);
    }

    public static function handle_actions() {
        if (!current_user_can('manage_options')) return;

        // Handle settings save
        if (isset($_POST['gg_save_settings']) && wp_verify_nonce($_POST['_wpnonce'], 'gg_settings')) {
            $fields = ['gg_chatbot_enabled', 'gg_openai_api_key', 'gg_openai_model', 'gg_bot_name', 'gg_welcome_message', 'gg_primary_color', 'gg_accent_color', 'gg_report_email', 'gg_results_per_page'];

            foreach ($fields as $field) {
                if (isset($_POST[$field])) {
                    update_option($field, sanitize_text_field(wp_unslash($_POST[$field])));
                }
            }

            if (!isset($_POST['gg_chatbot_enabled'])) {
                update_option('gg_chatbot_enabled', '0');
            }

            add_settings_error('gg_settings', 'updated', 'Settings saved successfully.', 'success');
        }

        // Handle CSV import
        if (isset($_POST['gg_import_csv']) && wp_verify_nonce($_POST['_wpnonce'], 'gg_import')) {
            self::process_import();
        }

        // Handle report status update
        if (isset($_POST['gg_update_report']) && wp_verify_nonce($_POST['_wpnonce'], 'gg_report_action')) {
            $report_id = absint($_POST['report_id']);
            $status = sanitize_text_field($_POST['status']);
            $notes = sanitize_textarea_field($_POST['admin_notes'] ?? '');
            GG_Reports::update_report($report_id, ['status' => $status, 'admin_notes' => $notes]);
            add_settings_error('gg_reports', 'updated', 'Report updated.', 'success');
        }

        // Handle resource edit
        if (isset($_POST['gg_save_resource']) && wp_verify_nonce($_POST['_wpnonce'], 'gg_resource_edit')) {
            self::save_resource();
        }

        // Handle resource delete
        if (isset($_GET['action']) && $_GET['action'] === 'delete_resource' && isset($_GET['id']) && wp_verify_nonce($_GET['_wpnonce'], 'gg_delete_resource')) {
            global $wpdb;
            $wpdb->delete($wpdb->prefix . GG_TABLE_RESOURCES, ['id' => absint($_GET['id'])]);
            wp_redirect(admin_url('admin.php?page=gg-resources&deleted=1'));
            exit;
        }

        // Handle clear all data
        if (isset($_POST['gg_clear_data']) && wp_verify_nonce($_POST['_wpnonce'], 'gg_clear_data')) {
            $region = sanitize_text_field($_POST['clear_region'] ?? '');
            GG_Importer::clear_all($region);
            add_settings_error('gg_import', 'cleared', 'Data cleared successfully.', 'success');
        }
    }

    private static function process_import() {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            add_settings_error('gg_import', 'error', 'Please select a CSV file to upload.', 'error');
            return;
        }

        $file = $_FILES['csv_file'];
        $region = sanitize_text_field($_POST['import_region'] ?? 'usa');

        // Validate file type
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            add_settings_error('gg_import', 'error', 'Only CSV files are accepted.', 'error');
            return;
        }

        $upload = wp_handle_upload($file, ['test_form' => false, 'mimes' => ['csv' => 'text/csv']]);

        if (isset($upload['error'])) {
            // Try again with relaxed mime type
            $upload = wp_handle_upload($file, ['test_form' => false, 'test_type' => false]);
        }

        if (isset($upload['error'])) {
            add_settings_error('gg_import', 'error', 'Upload error: ' . $upload['error'], 'error');
            return;
        }

        if ($region === 'apps') {
            $result = GG_Importer::import_apps_csv($upload['file']);
        } else {
            $result = GG_Importer::import_resources_csv($upload['file'], $region);
        }

        if (is_wp_error($result)) {
            add_settings_error('gg_import', 'error', $result->get_error_message(), 'error');
        } else {
            $msg = 'Import complete! ';
            $msg .= isset($result['imported']) ? $result['imported'] . ' resources imported. ' : '';
            $msg .= isset($result['categories']) ? $result['categories'] . ' categories created.' : '';
            add_settings_error('gg_import', 'success', $msg, 'success');
        }

        @unlink($upload['file']); // Clean up
    }

    private static function save_resource() {
        global $wpdb;
        $table = $wpdb->prefix . GG_TABLE_RESOURCES;

        $id = absint($_POST['resource_id'] ?? 0);
        $data = [
            'name' => sanitize_text_field($_POST['name']),
            'type' => sanitize_text_field($_POST['type'] ?? ''),
            'category_id' => absint($_POST['category_id'] ?? 0),
            'region' => sanitize_text_field($_POST['region'] ?? 'usa'),
            'location' => sanitize_text_field($_POST['location'] ?? ''),
            'location_served' => sanitize_text_field($_POST['location_served'] ?? ''),
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'alt_phone' => sanitize_text_field($_POST['alt_phone'] ?? ''),
            'fax' => sanitize_text_field($_POST['fax'] ?? ''),
            'director' => sanitize_text_field($_POST['director'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'insurance_info' => sanitize_textarea_field($_POST['insurance_info'] ?? ''),
            'website' => esc_url_raw($_POST['website'] ?? ''),
            'facebook' => esc_url_raw($_POST['facebook'] ?? ''),
            'instagram' => esc_url_raw($_POST['instagram'] ?? ''),
            'twitter' => esc_url_raw($_POST['twitter'] ?? ''),
            'linkedin' => esc_url_raw($_POST['linkedin'] ?? ''),
            'notes' => sanitize_textarea_field($_POST['notes'] ?? ''),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
        ];

        if ($id) {
            $wpdb->update($table, $data, ['id' => $id]);
            add_settings_error('gg_resources', 'updated', 'Resource updated.', 'success');
        } else {
            $wpdb->insert($table, $data);
            add_settings_error('gg_resources', 'created', 'Resource created.', 'success');
        }
    }

    // ==================== PAGES ====================

    public static function page_dashboard() {
        global $wpdb;

        $res_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->prefix . GG_TABLE_RESOURCES);
        $cat_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->prefix . GG_TABLE_CATEGORIES);
        $app_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . $wpdb->prefix . GG_TABLE_APPS);
        $report_count = GG_Reports::count_reports('pending');
        $conv_count = (int) $wpdb->get_var("SELECT COUNT(DISTINCT session_id) FROM " . $wpdb->prefix . GG_TABLE_CONVERSATIONS);

        include GG_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    public static function page_resources() {
        global $wpdb;
        $table = $wpdb->prefix . GG_TABLE_RESOURCES;
        $cat_table = $wpdb->prefix . GG_TABLE_CATEGORIES;

        // Check if editing
        $editing = false;
        $resource = null;
        if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
            $resource = GG_Database::get_resource(absint($_GET['id']));
            $editing = true;
        }
        if (isset($_GET['action']) && $_GET['action'] === 'add') {
            $editing = true;
        }

        // List resources with pagination
        $page = max(1, absint($_GET['paged'] ?? 1));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        $search = sanitize_text_field($_GET['s'] ?? '');
        $filter_region = sanitize_text_field($_GET['region'] ?? '');

        $where = '1=1';
        $params = [];
        if ($search) {
            $where .= " AND (r.name LIKE %s OR r.type LIKE %s OR r.description LIKE %s)";
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if ($filter_region) {
            $where .= " AND r.region = %s";
            $params[] = $filter_region;
        }

        $count_sql = "SELECT COUNT(*) FROM {$table} r WHERE {$where}";
        $total = $params ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $params)) : (int) $wpdb->get_var($count_sql);

        $params[] = $per_page;
        $params[] = $offset;
        $sql = "SELECT r.*, c.name as category_name FROM {$table} r LEFT JOIN {$cat_table} c ON r.category_id = c.id WHERE {$where} ORDER BY r.name ASC LIMIT %d OFFSET %d";
        $resources = $wpdb->get_results($wpdb->prepare($sql, $params));

        $total_pages = ceil($total / $per_page);
        $categories = GG_Database::get_categories();

        include GG_PLUGIN_DIR . 'admin/views/resources.php';
    }

    public static function page_categories() {
        global $wpdb;
        $categories = GG_Database::get_categories();
        include GG_PLUGIN_DIR . 'admin/views/categories.php';
    }

    public static function page_import() {
        include GG_PLUGIN_DIR . 'admin/views/import.php';
    }

    public static function page_reports() {
        $status = sanitize_text_field($_GET['status'] ?? '');
        $reports = GG_Reports::get_reports($status, 50);
        $pending_count = GG_Reports::count_reports('pending');
        $total_count = GG_Reports::count_reports();

        include GG_PLUGIN_DIR . 'admin/views/reports.php';
    }

    public static function page_settings() {
        include GG_PLUGIN_DIR . 'admin/views/settings.php';
    }
}
