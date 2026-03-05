<?php
if (!defined('ABSPATH')) exit;

class GG_Reports {

    public static function init() {
        // Nothing to hook for now — handled via REST API
    }

    public static function create_report($data) {
        global $wpdb;

        $result = $wpdb->insert($wpdb->prefix . GG_TABLE_REPORTS, [
            'resource_id' => absint($data['resource_id']),
            'resource_type' => sanitize_text_field($data['resource_type'] ?? 'resource'),
            'reporter_name' => sanitize_text_field($data['reporter_name'] ?? ''),
            'reporter_email' => sanitize_email($data['reporter_email'] ?? ''),
            'issue_type' => sanitize_text_field($data['issue_type']),
            'description' => sanitize_textarea_field($data['description']),
            'status' => 'pending',
        ]);

        if ($result) {
            self::send_notification($wpdb->insert_id, $data);
            return $wpdb->insert_id;
        }

        return false;
    }

    private static function send_notification($report_id, $data) {
        $email = get_option('gg_report_email', get_option('admin_email'));
        if (empty($email)) return;

        $subject = "[Givers' Guide] New Report: " . $data['issue_type'];
        $body = "A new report has been submitted.\n\n";
        $body .= "Report ID: #{$report_id}\n";
        $body .= "Resource ID: {$data['resource_id']}\n";
        $body .= "Issue Type: {$data['issue_type']}\n";
        $body .= "Description: {$data['description']}\n";

        if (!empty($data['reporter_name'])) {
            $body .= "Reporter: {$data['reporter_name']}\n";
        }
        if (!empty($data['reporter_email'])) {
            $body .= "Email: {$data['reporter_email']}\n";
        }

        $body .= "\nView reports in your WordPress admin: " . admin_url('admin.php?page=gg-reports');

        wp_mail($email, $subject, $body);
    }

    public static function get_reports($status = '', $limit = 20, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . GG_TABLE_REPORTS;

        $where = '1=1';
        $params = [];

        if (!empty($status)) {
            $where .= " AND status = %s";
            $params[] = $status;
        }

        $params[] = $limit;
        $params[] = $offset;

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        return $wpdb->get_results($wpdb->prepare($sql, $params));
    }

    public static function update_report($id, $data) {
        global $wpdb;
        $update = [];

        if (isset($data['status'])) $update['status'] = sanitize_text_field($data['status']);
        if (isset($data['admin_notes'])) $update['admin_notes'] = sanitize_textarea_field($data['admin_notes']);
        if (isset($data['status']) && $data['status'] === 'resolved') $update['resolved_at'] = current_time('mysql');

        return $wpdb->update($wpdb->prefix . GG_TABLE_REPORTS, $update, ['id' => absint($id)]);
    }

    public static function count_reports($status = '') {
        global $wpdb;
        $table = $wpdb->prefix . GG_TABLE_REPORTS;

        if (!empty($status)) {
            return (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status
            ));
        }
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
    }
}
