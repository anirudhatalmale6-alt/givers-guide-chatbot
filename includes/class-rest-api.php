<?php
if (!defined('ABSPATH')) exit;

class GG_REST_API {

    public static function init() {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes() {
        $namespace = 'givers-guide/v1';

        // Chat endpoint
        register_rest_route($namespace, '/chat', [
            'methods' => 'POST',
            'callback' => [self::class, 'handle_chat'],
            'permission_callback' => '__return_true',
        ]);

        // Search resources
        register_rest_route($namespace, '/search', [
            'methods' => 'GET',
            'callback' => [self::class, 'handle_search'],
            'permission_callback' => '__return_true',
        ]);

        // Get categories
        register_rest_route($namespace, '/categories', [
            'methods' => 'GET',
            'callback' => [self::class, 'handle_categories'],
            'permission_callback' => '__return_true',
        ]);

        // Get regions
        register_rest_route($namespace, '/regions', [
            'methods' => 'GET',
            'callback' => [self::class, 'handle_regions'],
            'permission_callback' => '__return_true',
        ]);

        // Get single resource
        register_rest_route($namespace, '/resource/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [self::class, 'handle_get_resource'],
            'permission_callback' => '__return_true',
        ]);

        // Submit report
        register_rest_route($namespace, '/report', [
            'methods' => 'POST',
            'callback' => [self::class, 'handle_report'],
            'permission_callback' => '__return_true',
        ]);

        // Search apps
        register_rest_route($namespace, '/apps', [
            'methods' => 'GET',
            'callback' => [self::class, 'handle_search_apps'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * POST /chat — AI chatbot interaction
     */
    public static function handle_chat($request) {
        $message = sanitize_text_field($request->get_param('message'));
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $history = $request->get_param('history') ?? [];

        if (empty($message)) {
            return new WP_REST_Response(['error' => 'Message is required.'], 400);
        }

        if (empty($session_id)) {
            $session_id = wp_generate_uuid4();
        }

        // Rate limiting: max 20 messages per session per minute
        $rate_key = 'gg_rate_' . md5($session_id);
        $rate = get_transient($rate_key);
        if ($rate && $rate > 20) {
            return new WP_REST_Response(['error' => 'Too many requests. Please wait a moment.'], 429);
        }
        set_transient($rate_key, ($rate ? $rate + 1 : 1), 60);

        // Sanitize history
        $clean_history = [];
        if (is_array($history)) {
            foreach ($history as $h) {
                if (isset($h['role'], $h['content'])) {
                    $clean_history[] = [
                        'role' => in_array($h['role'], ['user', 'assistant']) ? $h['role'] : 'user',
                        'content' => sanitize_text_field($h['content']),
                    ];
                }
            }
        }

        $result = GG_Chatbot::process_message($message, $session_id, $clean_history);

        return new WP_REST_Response([
            'session_id' => $session_id,
            'message' => $result['message'],
            'resources' => $result['resources'] ?? [],
            'apps' => $result['apps'] ?? [],
        ]);
    }

    /**
     * GET /search — Search resources
     */
    public static function handle_search($request) {
        $query = sanitize_text_field($request->get_param('q') ?? '');
        $region = sanitize_text_field($request->get_param('region') ?? '');
        $category = sanitize_text_field($request->get_param('category') ?? '');
        $page = max(1, absint($request->get_param('page') ?? 1));
        $per_page = min(50, max(1, absint($request->get_param('per_page') ?? 12)));
        $offset = ($page - 1) * $per_page;

        $resources = GG_Database::search_resources($query, $region, $category, $per_page, $offset);
        $total = GG_Database::count_resources($query, $region, $category);

        $formatted = [];
        foreach ($resources as $r) {
            $formatted[] = [
                'id' => (int) $r->id,
                'name' => $r->name,
                'type' => $r->type,
                'category' => $r->category_name ?? '',
                'region' => $r->region,
                'location' => $r->location,
                'location_served' => $r->location_served,
                'phone' => $r->phone,
                'alt_phone' => $r->alt_phone,
                'email' => $r->email,
                'website' => $r->website,
                'description' => $r->description,
                'insurance_info' => $r->insurance_info,
                'director' => $r->director,
            ];
        }

        return new WP_REST_Response([
            'resources' => $formatted,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page),
        ]);
    }

    /**
     * GET /categories
     */
    public static function handle_categories($request) {
        $region = sanitize_text_field($request->get_param('region') ?? '');
        $categories = GG_Database::get_categories($region);

        $formatted = [];
        foreach ($categories as $c) {
            $formatted[] = [
                'id' => (int) $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'region' => $c->region,
            ];
        }

        return new WP_REST_Response(['categories' => $formatted]);
    }

    /**
     * GET /regions
     */
    public static function handle_regions($request) {
        $regions = GG_Database::get_regions();
        $labels = [
            'usa' => 'United States',
            'israel' => 'Israel',
            'england' => 'England',
            'apps' => 'Mental Health Apps',
        ];

        $formatted = [];
        foreach ($regions as $r) {
            $formatted[] = [
                'slug' => $r,
                'name' => $labels[$r] ?? ucfirst($r),
            ];
        }

        return new WP_REST_Response(['regions' => $formatted]);
    }

    /**
     * GET /resource/{id}
     */
    public static function handle_get_resource($request) {
        $id = absint($request['id']);
        $resource = GG_Database::get_resource($id);

        if (!$resource) {
            return new WP_REST_Response(['error' => 'Resource not found.'], 404);
        }

        return new WP_REST_Response(['resource' => $resource]);
    }

    /**
     * POST /report — Submit a report
     */
    public static function handle_report($request) {
        $data = [
            'resource_id' => absint($request->get_param('resource_id')),
            'resource_type' => sanitize_text_field($request->get_param('resource_type') ?? 'resource'),
            'reporter_name' => sanitize_text_field($request->get_param('reporter_name') ?? ''),
            'reporter_email' => sanitize_email($request->get_param('reporter_email') ?? ''),
            'issue_type' => sanitize_text_field($request->get_param('issue_type')),
            'description' => sanitize_textarea_field($request->get_param('description')),
        ];

        if (empty($data['resource_id']) || empty($data['issue_type']) || empty($data['description'])) {
            return new WP_REST_Response(['error' => 'Resource ID, issue type, and description are required.'], 400);
        }

        $report_id = GG_Reports::create_report($data);

        if ($report_id) {
            return new WP_REST_Response(['success' => true, 'report_id' => $report_id]);
        }

        return new WP_REST_Response(['error' => 'Failed to submit report.'], 500);
    }

    /**
     * GET /apps — Search mental health apps
     */
    public static function handle_search_apps($request) {
        $query = sanitize_text_field($request->get_param('q') ?? '');
        $category = sanitize_text_field($request->get_param('category') ?? '');
        $page = max(1, absint($request->get_param('page') ?? 1));
        $per_page = min(50, max(1, absint($request->get_param('per_page') ?? 12)));
        $offset = ($page - 1) * $per_page;

        $apps = GG_Database::search_apps($query, $category, $per_page, $offset);

        $formatted = [];
        foreach ($apps as $a) {
            $formatted[] = [
                'id' => (int) $a->id,
                'title' => $a->title,
                'category' => $a->category_name ?? '',
                'description' => $a->description,
                'cost' => $a->cost,
                'platform' => $a->platform,
                'notes' => $a->notes,
            ];
        }

        return new WP_REST_Response([
            'apps' => $formatted,
            'page' => $page,
            'per_page' => $per_page,
        ]);
    }
}
