<?php
if (!defined('ABSPATH')) exit;

class GG_Importer {

    /**
     * Import resources from uploaded CSV file
     */
    public static function import_resources_csv($file_path, $region = 'usa') {
        global $wpdb;

        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'CSV file not found.');
        }

        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return new WP_Error('file_read_error', 'Cannot read CSV file.');
        }

        $cat_table = $wpdb->prefix . GG_TABLE_CATEGORIES;
        $res_table = $wpdb->prefix . GG_TABLE_RESOURCES;

        $imported = 0;
        $categories_created = 0;
        $current_category_id = 0;
        $row_num = 0;
        $header_found = false;

        while (($row = fgetcsv($handle)) !== false) {
            $row_num++;

            // Skip completely empty rows
            $non_empty = array_filter($row, function($v) { return trim($v) !== '' && $v !== 'None'; });
            if (empty($non_empty)) continue;

            $first_col = isset($row[0]) ? trim($row[0]) : '';
            $second_col = isset($row[1]) ? trim($row[1]) : '';

            // Skip the header/intro rows
            if (stripos($first_col, "Givers' Guide") !== false) continue;
            if (stripos($first_col, 'INSTRUCTIONS ON') !== false) continue;

            // Detect header row (supports different column formats)
            if (strtoupper($first_col) === 'NAME' && ($second_col !== '' || $row_num <= 5)) {
                $header_found = true;
                continue;
            }

            if (!$header_found) {
                // Check if this is a Table of Contents-style file (just categories)
                if ($first_col && !$second_col && $first_col === strtoupper($first_col) && strlen($first_col) > 2) {
                    $slug = sanitize_title($first_col);
                    $existing = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$cat_table} WHERE slug = %s AND region = %s", $slug, $region
                    ));
                    if (!$existing) {
                        $wpdb->insert($cat_table, [
                            'name' => self::title_case($first_col),
                            'slug' => $slug,
                            'region' => $region,
                            'sort_order' => $categories_created,
                        ]);
                        $categories_created++;
                    }
                }
                continue;
            }

            // After header: detect category rows (ALL CAPS, no type)
            if ($first_col && !$second_col && $first_col === strtoupper($first_col) && strlen($first_col) > 2) {
                $slug = sanitize_title($first_col);
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$cat_table} WHERE slug = %s AND region = %s", $slug, $region
                ));
                if ($existing) {
                    $current_category_id = $existing;
                } else {
                    $wpdb->insert($cat_table, [
                        'name' => self::title_case($first_col),
                        'slug' => $slug,
                        'region' => $region,
                        'sort_order' => $categories_created,
                    ]);
                    $current_category_id = $wpdb->insert_id;
                    $categories_created++;
                }
                continue;
            }

            // Skip rows without a name
            if (empty($first_col) || $first_col === 'None') continue;

            // This is a resource row
            $data = [
                'name' => self::clean($first_col),
                'type' => self::clean($second_col),
                'category_id' => $current_category_id,
                'region' => $region,
                'location' => self::clean(isset($row[2]) ? $row[2] : ''),
                'location_served' => self::clean(isset($row[3]) ? $row[3] : ''),
                'phone' => self::clean(isset($row[4]) ? $row[4] : ''),
                'alt_phone' => self::clean(isset($row[5]) ? $row[5] : ''),
                'fax' => self::clean(isset($row[6]) ? $row[6] : ''),
                'director' => self::clean(isset($row[7]) ? $row[7] : ''),
                'email' => self::clean(isset($row[8]) ? $row[8] : ''),
                'description' => self::clean(isset($row[9]) ? $row[9] : ''),
                'insurance_info' => self::clean(isset($row[10]) ? $row[10] : ''),
                'website' => self::clean(isset($row[11]) ? $row[11] : ''),
                'facebook' => self::clean(isset($row[12]) ? $row[12] : ''),
                'instagram' => self::clean(isset($row[13]) ? $row[13] : ''),
                'twitter' => self::clean(isset($row[14]) ? $row[14] : ''),
                'linkedin' => self::clean(isset($row[15]) ? $row[15] : ''),
                'notes' => self::clean(isset($row[16]) ? $row[16] : ''),
                'is_active' => 1,
            ];

            $wpdb->insert($res_table, $data);
            $imported++;
        }

        fclose($handle);

        return [
            'imported' => $imported,
            'categories' => $categories_created,
        ];
    }

    /**
     * Import mental health apps from CSV
     */
    public static function import_apps_csv($file_path) {
        global $wpdb;

        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'CSV file not found.');
        }

        $handle = fopen($file_path, 'r');
        if (!$handle) {
            return new WP_Error('file_read_error', 'Cannot read CSV file.');
        }

        $cat_table = $wpdb->prefix . GG_TABLE_CATEGORIES;
        $apps_table = $wpdb->prefix . GG_TABLE_APPS;

        $imported = 0;
        $current_category_id = 0;
        $header_found = false;

        while (($row = fgetcsv($handle)) !== false) {
            $non_empty = array_filter($row, function($v) { return trim($v) !== '' && $v !== 'None'; });
            if (empty($non_empty)) continue;

            $first_col = isset($row[0]) ? trim($row[0]) : '';
            $second_col = isset($row[1]) ? trim($row[1]) : '';

            if (stripos($first_col, "Givers' Guide") !== false) continue;
            if (stripos($first_col, 'INSTRUCTIONS ON') !== false) continue;

            // Header row
            if (strtoupper($first_col) === 'TITLE') {
                $header_found = true;
                continue;
            }

            if (!$header_found) continue;

            // Category row (name only, no description)
            if ($first_col && !$second_col && strlen($first_col) > 1) {
                $slug = sanitize_title($first_col);
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$cat_table} WHERE slug = %s AND region = %s", $slug, 'apps'
                ));
                if ($existing) {
                    $current_category_id = $existing;
                } else {
                    $wpdb->insert($cat_table, [
                        'name' => self::title_case($first_col),
                        'slug' => $slug,
                        'region' => 'apps',
                    ]);
                    $current_category_id = $wpdb->insert_id;
                }
                continue;
            }

            if (empty($first_col) || $first_col === 'None') continue;

            $wpdb->insert($apps_table, [
                'title' => self::clean($first_col),
                'category_id' => $current_category_id,
                'description' => self::clean($second_col),
                'cost' => self::clean(isset($row[2]) ? $row[2] : ''),
                'platform' => self::clean(isset($row[3]) ? $row[3] : ''),
                'notes' => self::clean(isset($row[4]) ? $row[4] : ''),
                'is_active' => 1,
            ]);
            $imported++;
        }

        fclose($handle);
        return ['imported' => $imported];
    }

    /**
     * Import from uploaded spreadsheet (XLSX)
     */
    public static function import_xlsx($file_path) {
        // We'll handle this through CSV conversion in the admin
        return new WP_Error('not_implemented', 'Please convert to CSV first.');
    }

    private static function clean($val) {
        if ($val === null || $val === 'None' || $val === 'none' || $val === 'n/a') return '';
        return trim(sanitize_text_field($val));
    }

    private static function title_case($str) {
        return ucwords(strtolower(trim($str)));
    }

    /**
     * Clear all data for a fresh import
     */
    public static function clear_all($region = '') {
        global $wpdb;

        if (!empty($region)) {
            $wpdb->delete($wpdb->prefix . GG_TABLE_RESOURCES, ['region' => $region]);
            $wpdb->delete($wpdb->prefix . GG_TABLE_CATEGORIES, ['region' => $region]);
        } else {
            $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . GG_TABLE_RESOURCES);
            $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . GG_TABLE_CATEGORIES);
            $wpdb->query("TRUNCATE TABLE " . $wpdb->prefix . GG_TABLE_APPS);
        }

        return true;
    }
}
