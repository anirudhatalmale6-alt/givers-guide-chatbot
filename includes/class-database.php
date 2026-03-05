<?php
if (!defined('ABSPATH')) exit;

class GG_Database {

    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = [];

        // Categories table
        $table_cat = $wpdb->prefix . GG_TABLE_CATEGORIES;
        $sql[] = "CREATE TABLE {$table_cat} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            region VARCHAR(50) NOT NULL DEFAULT 'usa',
            parent_id BIGINT UNSIGNED DEFAULT 0,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_region (region),
            KEY idx_slug (slug)
        ) {$charset};";

        // Resources table
        $table_res = $wpdb->prefix . GG_TABLE_RESOURCES;
        $sql[] = "CREATE TABLE {$table_res} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(500) NOT NULL,
            type VARCHAR(500) DEFAULT '',
            category_id BIGINT UNSIGNED DEFAULT 0,
            region VARCHAR(50) NOT NULL DEFAULT 'usa',
            location VARCHAR(500) DEFAULT '',
            location_served VARCHAR(500) DEFAULT '',
            phone VARCHAR(255) DEFAULT '',
            alt_phone VARCHAR(255) DEFAULT '',
            fax VARCHAR(255) DEFAULT '',
            director VARCHAR(255) DEFAULT '',
            email VARCHAR(255) DEFAULT '',
            description TEXT,
            insurance_info TEXT,
            website VARCHAR(500) DEFAULT '',
            facebook VARCHAR(500) DEFAULT '',
            instagram VARCHAR(500) DEFAULT '',
            twitter VARCHAR(500) DEFAULT '',
            linkedin VARCHAR(500) DEFAULT '',
            notes TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_category (category_id),
            KEY idx_region (region),
            KEY idx_name (name(191)),
            KEY idx_active (is_active),
            FULLTEXT idx_search (name, type, description, location, location_served, notes)
        ) {$charset};";

        // Mental Health Apps table
        $table_apps = $wpdb->prefix . GG_TABLE_APPS;
        $sql[] = "CREATE TABLE {$table_apps} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(500) NOT NULL,
            category_id BIGINT UNSIGNED DEFAULT 0,
            description TEXT,
            cost VARCHAR(100) DEFAULT '',
            platform VARCHAR(100) DEFAULT '',
            notes TEXT,
            website VARCHAR(500) DEFAULT '',
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_category (category_id),
            FULLTEXT idx_search (title, description, notes)
        ) {$charset};";

        // Reports table
        $table_rep = $wpdb->prefix . GG_TABLE_REPORTS;
        $sql[] = "CREATE TABLE {$table_rep} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            resource_id BIGINT UNSIGNED NOT NULL,
            resource_type VARCHAR(20) DEFAULT 'resource',
            reporter_name VARCHAR(255) DEFAULT '',
            reporter_email VARCHAR(255) DEFAULT '',
            issue_type VARCHAR(100) NOT NULL,
            description TEXT NOT NULL,
            status VARCHAR(20) DEFAULT 'pending',
            admin_notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_resource (resource_id),
            KEY idx_status (status)
        ) {$charset};";

        // Conversations table (chat history)
        $table_conv = $wpdb->prefix . GG_TABLE_CONVERSATIONS;
        $sql[] = "CREATE TABLE {$table_conv} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            session_id VARCHAR(64) NOT NULL,
            role VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_session (session_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($sql as $query) {
            dbDelta($query);
        }

        update_option('gg_db_version', GG_VERSION);
    }

    public static function seed_default_options() {
        $defaults = [
            'gg_chatbot_enabled' => '1',
            'gg_openai_api_key' => '',
            'gg_openai_model' => 'gpt-4o-mini',
            'gg_bot_name' => "Givers' Guide Assistant",
            'gg_welcome_message' => "Hi! I'm the Givers' Guide Assistant. I can help you find resources and services. What are you looking for today?",
            'gg_primary_color' => '#9355ff',
            'gg_accent_color' => '#4bfada',
            'gg_report_email' => get_option('admin_email'),
            'gg_results_per_page' => '12',
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }

    public static function search_resources($query, $region = '', $category = '', $limit = 10, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . GG_TABLE_RESOURCES;
        $cat_table = $wpdb->prefix . GG_TABLE_CATEGORIES;

        $where = ['r.is_active = 1'];
        $params = [];

        if (!empty($query)) {
            $where[] = "MATCH(r.name, r.type, r.description, r.location, r.location_served, r.notes) AGAINST(%s IN NATURAL LANGUAGE MODE)";
            $params[] = $query;
        }

        if (!empty($region)) {
            $where[] = "r.region = %s";
            $params[] = $region;
        }

        if (!empty($category)) {
            $where[] = "(c.name = %s OR c.slug = %s)";
            $params[] = $category;
            $params[] = sanitize_title($category);
        }

        $where_sql = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;

        $sql = "SELECT r.*, c.name as category_name, c.region as category_region
                FROM {$table} r
                LEFT JOIN {$cat_table} c ON r.category_id = c.id
                WHERE {$where_sql}
                ORDER BY r.name ASC
                LIMIT %d OFFSET %d";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $results = $wpdb->get_results($sql);

        // Fallback to LIKE search if FULLTEXT returns nothing and we have a query
        if (empty($results) && !empty($query) && empty($category)) {
            $like = '%' . $wpdb->esc_like($query) . '%';
            $fb_where = ['r.is_active = 1'];
            $fb_params = [];

            $fb_where[] = "(r.name LIKE %s OR r.type LIKE %s OR r.description LIKE %s OR r.location LIKE %s OR r.location_served LIKE %s OR c.name LIKE %s)";
            $fb_params = [$like, $like, $like, $like, $like, $like];

            if (!empty($region)) {
                $fb_where[] = "r.region = %s";
                $fb_params[] = $region;
            }

            $fb_params[] = $limit;
            $fb_params[] = $offset;

            $fb_sql = "SELECT r.*, c.name as category_name, c.region as category_region
                       FROM {$table} r
                       LEFT JOIN {$cat_table} c ON r.category_id = c.id
                       WHERE " . implode(' AND ', $fb_where) . "
                       ORDER BY r.name ASC
                       LIMIT %d OFFSET %d";

            $results = $wpdb->get_results($wpdb->prepare($fb_sql, $fb_params));
        }

        return $results;
    }

    public static function count_resources($query = '', $region = '', $category = '') {
        global $wpdb;
        $table = $wpdb->prefix . GG_TABLE_RESOURCES;
        $cat_table = $wpdb->prefix . GG_TABLE_CATEGORIES;

        $where = ['r.is_active = 1'];
        $params = [];

        if (!empty($query)) {
            $where[] = "MATCH(r.name, r.type, r.description, r.location, r.location_served, r.notes) AGAINST(%s IN NATURAL LANGUAGE MODE)";
            $params[] = $query;
        }

        if (!empty($region)) {
            $where[] = "r.region = %s";
            $params[] = $region;
        }

        if (!empty($category)) {
            $where[] = "(c.name = %s OR c.slug = %s)";
            $params[] = $category;
            $params[] = sanitize_title($category);
        }

        $where_sql = implode(' AND ', $where);

        $sql = "SELECT COUNT(*) FROM {$table} r
                LEFT JOIN {$cat_table} c ON r.category_id = c.id
                WHERE {$where_sql}";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return (int) $wpdb->get_var($sql);
    }

    public static function get_categories($region = '') {
        global $wpdb;
        $table = $wpdb->prefix . GG_TABLE_CATEGORIES;

        if (!empty($region)) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE region = %s ORDER BY name ASC",
                $region
            ));
        }

        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY region ASC, name ASC");
    }

    public static function get_regions() {
        global $wpdb;
        $table = $wpdb->prefix . GG_TABLE_RESOURCES;
        return $wpdb->get_col("SELECT DISTINCT region FROM {$table} WHERE is_active = 1 ORDER BY region ASC");
    }

    public static function get_resource($id) {
        global $wpdb;
        $table = $wpdb->prefix . GG_TABLE_RESOURCES;
        $cat_table = $wpdb->prefix . GG_TABLE_CATEGORIES;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, c.name as category_name FROM {$table} r
             LEFT JOIN {$cat_table} c ON r.category_id = c.id
             WHERE r.id = %d", $id
        ));
    }

    public static function search_apps($query, $category = '', $limit = 10, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . GG_TABLE_APPS;
        $cat_table = $wpdb->prefix . GG_TABLE_CATEGORIES;

        $where = ['a.is_active = 1'];
        $params = [];

        if (!empty($query)) {
            $where[] = "MATCH(a.title, a.description, a.notes) AGAINST(%s IN NATURAL LANGUAGE MODE)";
            $params[] = $query;
        }

        if (!empty($category)) {
            $where[] = "(c.name = %s OR c.slug = %s)";
            $params[] = $category;
            $params[] = sanitize_title($category);
        }

        $where_sql = implode(' AND ', $where);
        $params[] = $limit;
        $params[] = $offset;

        $sql = "SELECT a.*, c.name as category_name
                FROM {$table} a
                LEFT JOIN {$cat_table} c ON a.category_id = c.id
                WHERE {$where_sql}
                ORDER BY a.title ASC
                LIMIT %d OFFSET %d";

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return $wpdb->get_results($sql);
    }
}
