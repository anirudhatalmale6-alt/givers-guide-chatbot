<?php
if (!defined('ABSPATH')) exit;

class GG_Chatbot {

    /**
     * Process a chat message and return AI response
     */
    public static function process_message($message, $session_id, $history = []) {
        $api_key = get_option('gg_openai_api_key', '');

        if (empty($api_key)) {
            return self::fallback_search($message);
        }

        return self::ai_response($message, $session_id, $history, $api_key);
    }

    /**
     * AI-powered response using OpenAI
     */
    private static function ai_response($message, $session_id, $history, $api_key) {
        // First, search the database for relevant resources
        $resources = GG_Database::search_resources($message, '', '', 8);
        $apps = GG_Database::search_apps($message, '', 5);

        // Build context from search results
        $context = self::build_context($resources, $apps);

        $model = get_option('gg_openai_model', 'gpt-4o-mini');

        $system_prompt = "You are the Givers' Guide Assistant, a helpful chatbot for giversguide.org. Your role is to help users find resources and services from the Givers' Guide database.

IMPORTANT RULES:
- Only recommend resources from the database results provided below
- If no relevant resources are found, say so honestly and suggest the user try different search terms or browse the directory
- Always include contact details (phone, email, website) when showing resources
- Be warm, empathetic, and professional
- If the user's query is vague, ask clarifying questions about location, type of service needed, etc.
- Format responses clearly with resource names in bold
- Keep responses concise but informative
- Never make up resources or contact information

AVAILABLE RESOURCES FROM DATABASE:
{$context}

If the database results don't match what the user is looking for, let them know and suggest they browse the full directory or try different search terms.";

        // Build messages array
        $messages = [
            ['role' => 'system', 'content' => $system_prompt],
        ];

        // Add conversation history
        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode([
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => 1000,
                'temperature' => 0.7,
            ]),
        ]);

        if (is_wp_error($response)) {
            return self::fallback_search($message);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['choices'][0]['message']['content'])) {
            $reply = $body['choices'][0]['message']['content'];

            // Save conversation
            self::save_message($session_id, 'user', $message);
            self::save_message($session_id, 'assistant', $reply);

            return [
                'message' => $reply,
                'resources' => self::format_resources($resources),
                'apps' => self::format_apps($apps),
            ];
        }

        return self::fallback_search($message);
    }

    /**
     * Fallback keyword-based search when no API key is set
     */
    private static function fallback_search($message) {
        $resources = GG_Database::search_resources($message, '', '', 8);
        $apps = GG_Database::search_apps($message, '', 5);

        if (empty($resources) && empty($apps)) {
            // Try a broader search with individual keywords
            $words = explode(' ', $message);
            foreach ($words as $word) {
                if (strlen($word) > 3) {
                    $resources = GG_Database::search_resources($word, '', '', 5);
                    if (!empty($resources)) break;
                }
            }
        }

        if (!empty($resources) || !empty($apps)) {
            $count = count($resources) + count($apps);
            $reply = "I found {$count} resource(s) that might help. Here's what I found:";
        } else {
            $reply = "I couldn't find specific resources matching your query. Try browsing our directory for a complete list, or rephrase your question with more specific terms like a service type or location.";
        }

        return [
            'message' => $reply,
            'resources' => self::format_resources($resources),
            'apps' => self::format_apps($apps),
        ];
    }

    private static function build_context($resources, $apps) {
        $context = '';

        if (!empty($resources)) {
            foreach ($resources as $r) {
                $context .= "\n---\n";
                $context .= "Name: {$r->name}\n";
                $context .= "Category: {$r->category_name}\n";
                $context .= "Type: {$r->type}\n";
                $context .= "Region: {$r->region}\n";
                if ($r->location) $context .= "Location: {$r->location}\n";
                if ($r->location_served) $context .= "Serves: {$r->location_served}\n";
                if ($r->phone) $context .= "Phone: {$r->phone}\n";
                if ($r->email) $context .= "Email: {$r->email}\n";
                if ($r->website) $context .= "Website: {$r->website}\n";
                if ($r->description) $context .= "Description: {$r->description}\n";
                if ($r->insurance_info) $context .= "Insurance: {$r->insurance_info}\n";
            }
        }

        if (!empty($apps)) {
            $context .= "\n\nMENTAL HEALTH APPS:\n";
            foreach ($apps as $a) {
                $context .= "\n---\n";
                $context .= "App: {$a->title}\n";
                if ($a->description) $context .= "Description: {$a->description}\n";
                if ($a->cost) $context .= "Cost: {$a->cost}\n";
                if ($a->platform) $context .= "Platform: {$a->platform}\n";
            }
        }

        return $context ?: 'No matching resources found in the database.';
    }

    private static function format_resources($resources) {
        $formatted = [];
        foreach ($resources as $r) {
            $formatted[] = [
                'id' => $r->id,
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
            ];
        }
        return $formatted;
    }

    private static function format_apps($apps) {
        $formatted = [];
        foreach ($apps as $a) {
            $formatted[] = [
                'id' => $a->id,
                'title' => $a->title,
                'category' => $a->category_name ?? '',
                'description' => $a->description,
                'cost' => $a->cost,
                'platform' => $a->platform,
            ];
        }
        return $formatted;
    }

    private static function save_message($session_id, $role, $message) {
        global $wpdb;
        $wpdb->insert($wpdb->prefix . GG_TABLE_CONVERSATIONS, [
            'session_id' => sanitize_text_field($session_id),
            'role' => $role,
            'message' => sanitize_textarea_field($message),
        ]);
    }

    /**
     * Shortcode to embed chatbot inline
     */
    public static function shortcode($atts) {
        ob_start();
        include GG_PLUGIN_DIR . 'templates/chatbot-inline.php';
        return ob_get_clean();
    }
}
