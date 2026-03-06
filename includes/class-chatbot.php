<?php
if (!defined('ABSPATH')) exit;

class GG_Chatbot {

    // Conversation states
    const STATE_GREETING = 'greeting';
    const STATE_NEED_SERVICE = 'need_service';
    const STATE_NEED_LOCATION = 'need_location';
    const STATE_NEED_AGE = 'need_age';
    const STATE_RESULTS = 'results';

    /**
     * Process a chat message and return AI response
     */
    public static function process_message($message, $session_id, $history = []) {
        $api_key = get_option('gg_openai_api_key', '');

        if (!empty($api_key)) {
            return self::ai_response($message, $session_id, $history, $api_key);
        }

        return self::smart_fallback($message, $session_id, $history);
    }

    /**
     * AI-powered response using OpenAI
     */
    private static function ai_response($message, $session_id, $history, $api_key) {
        // Extract search terms from the full conversation
        $search_terms = self::extract_search_terms($message, $history);

        // Search with multiple strategies
        $resources = self::multi_search($search_terms);
        $apps = GG_Database::search_apps($message, '', 3);

        // Build context from search results
        $context = self::build_context($resources, $apps);

        // Get available categories for context
        $categories = GG_Database::get_categories();
        $cat_list = array_map(function($c) { return $c->name; }, array_slice($categories, 0, 30));

        $model = get_option('gg_openai_model', 'gpt-4o-mini');

        $system_prompt = "You are the Givers' Guide Assistant, a warm and helpful chatbot for giversguide.org — a resource directory connecting individuals, families, and professionals to trusted help across 80+ categories.

YOUR ROLE:
- Help users find the right resources from the Givers' Guide database
- Be conversational, empathetic, and professional
- When a user's request is vague, ask ONE focused follow-up question to narrow results

CONVERSATION FLOW:
1. Understand what the user needs (service type)
2. If not clear from their message, ask about: location, who it's for, any preferences (insurance, in-person vs virtual)
3. Search and present the MOST RELEVANT results (top 3-5, not all)
4. For each resource, include: name, what they do, phone, website
5. Ask if they need more options or different criteria

FORMATTING:
- Use **bold** for resource names
- Keep responses concise — 2-3 sentences intro, then list resources
- Don't dump all results — pick the best matches
- If showing contact info, format it cleanly

AVAILABLE CATEGORIES IN DATABASE:
" . implode(', ', $cat_list) . "

SEARCH RESULTS FROM DATABASE:
{$context}

RULES:
- Only recommend resources from the database results above
- Never make up resources or contact info
- If no good matches, suggest browsing the directory or trying different terms
- Be honest when results don't perfectly match";

        $messages = [
            ['role' => 'system', 'content' => $system_prompt],
        ];

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
                'max_tokens' => 800,
                'temperature' => 0.7,
            ]),
        ]);

        if (is_wp_error($response)) {
            // Log error for debugging
            error_log('GG Chatbot OpenAI WP Error: ' . $response->get_error_message());
            return self::smart_fallback($message, $session_id, $history);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $body = json_decode($body_raw, true);

        // Handle OpenAI API errors
        if ($status_code !== 200 || isset($body['error'])) {
            $err = isset($body['error']['message']) ? $body['error']['message'] : "HTTP {$status_code}";
            error_log('GG Chatbot OpenAI API Error: ' . $err);
            return self::smart_fallback($message, $session_id, $history);
        }

        if (isset($body['choices'][0]['message']['content'])) {
            $reply = $body['choices'][0]['message']['content'];

            self::save_message($session_id, 'user', $message);
            self::save_message($session_id, 'assistant', $reply);

            // Only show top 5 resource cards alongside the AI message
            $top_resources = array_slice($resources, 0, 5);

            return [
                'message' => $reply,
                'resources' => self::format_resources($top_resources),
                'apps' => self::format_apps($apps),
            ];
        }

        error_log('GG Chatbot: Unexpected OpenAI response format');
        return self::smart_fallback($message, $session_id, $history);
    }

    /**
     * Smart conversational fallback — no API key needed
     */
    private static function smart_fallback($message, $session_id, $history = []) {
        $msg_lower = strtolower(trim($message));

        // Detect conversation context from history
        $context = self::analyze_conversation($msg_lower, $history);

        // Handle greetings
        if (self::is_greeting($msg_lower) && empty($history)) {
            return [
                'message' => "Welcome to the Givers' Guide! I can help you find resources and services across our database of 5,000+ organizations.\n\nWhat kind of help are you looking for? For example:\n- Therapy or counseling\n- Addiction support\n- Services for children or teens\n- Support groups\n- Mental health apps\n\nYou can also tell me a specific need like \"I need therapy for my child in Lakewood, NJ\" and I'll find the best matches.",
                'resources' => [],
                'apps' => [],
            ];
        }

        // Extract key info from message
        $parsed = self::parse_user_intent($msg_lower, $history);

        // If we have enough info, search and respond
        if (!empty($parsed['service']) || !empty($parsed['keywords'])) {
            return self::search_and_respond($parsed, $message, $session_id);
        }

        // Ask for more info
        return [
            'message' => "I'd love to help you find the right resource. Could you tell me:\n\n1. **What type of service** are you looking for? (e.g., therapy, counseling, support group, medical, legal)\n2. **Location** — where are you or where do you need services?\n\nThe more details you share, the better I can match you with the right resources.",
            'resources' => [],
            'apps' => [],
        ];
    }

    /**
     * Parse user intent from message and conversation history
     */
    private static function parse_user_intent($msg_lower, $history = []) {
        $parsed = [
            'service' => '',
            'location' => '',
            'age_group' => '',
            'keywords' => [],
            'region' => '',
            'category' => '',
        ];

        // Combine current message with history for full context
        $full_text = $msg_lower;
        foreach ($history as $h) {
            if ($h['role'] === 'user') {
                $full_text .= ' ' . strtolower($h['content']);
            }
        }

        // Detect service types
        $service_map = [
            'therapy' => 'Behavioral Health Clinics And Services',
            'therapist' => 'Behavioral Health Clinics And Services',
            'counseling' => 'Behavioral Health Clinics And Services',
            'counselor' => 'Behavioral Health Clinics And Services',
            'mental health' => 'Behavioral Health Clinics And Services',
            'psychologist' => 'Behavioral Health Clinics And Services',
            'psychiatrist' => 'Behavioral Health Clinics And Services',
            'addiction' => 'Addiction Support',
            'substance' => 'Addiction Support',
            'drug' => 'Addiction Support',
            'alcohol' => 'Addiction Support',
            'rehab' => 'Addiction Support',
            'recovery' => 'Addiction Support',
            'abuse' => 'Abuse Prevention And Services',
            'domestic violence' => 'Abuse Prevention And Services',
            'sexual abuse' => 'Abuse Prevention And Services',
            'autism' => 'Autism Spectrum Disorders',
            'asd' => 'Autism Spectrum Disorders',
            'cancer' => 'Cancer',
            'legal' => 'Legal',
            'lawyer' => 'Legal',
            'attorney' => 'Legal',
            'housing' => 'Housing',
            'rent' => 'Housing',
            'homeless' => 'Housing',
            'job' => 'Employment/career Services',
            'employment' => 'Employment/career Services',
            'career' => 'Employment/career Services',
            'food' => 'Food',
            'kosher' => 'Food',
            'financial' => 'Financial Assistance',
            'money' => 'Financial Assistance',
            'insurance' => 'Insurance',
            'medicaid' => 'Insurance',
            'child' => 'Adolescents/teenagers',
            'children' => 'Adolescents/teenagers',
            'teen' => 'Adolescents/teenagers',
            'teenager' => 'Adolescents/teenagers',
            'adolescent' => 'Adolescents/teenagers',
            'baby' => 'Babies/newborn Support',
            'newborn' => 'Babies/newborn Support',
            'pregnancy' => 'Babies/newborn Support',
            'special needs' => 'Special Needs',
            'disability' => 'Special Needs',
            'senior' => 'Senior Services',
            'elderly' => 'Senior Services',
            'grief' => 'Grief Support',
            'bereavement' => 'Grief Support',
            'shalom bayis' => 'Shalom Bayis',
            'marriage' => 'Shalom Bayis',
            'divorce' => 'Divorce',
            'support group' => 'Support Groups/networks',
            'bikur cholim' => 'Bikur Cholim',
            'hospital' => 'Bikur Cholim',
            'education' => 'Education',
            'school' => 'Education',
            'tutoring' => 'Tutoring',
            'tutor' => 'Tutoring',
            'app' => '',
            'medication' => 'Medical',
            'doctor' => 'Medical',
            'medical' => 'Medical',
        ];

        foreach ($service_map as $keyword => $category) {
            if (strpos($full_text, $keyword) !== false) {
                $parsed['service'] = $keyword;
                if ($category) $parsed['category'] = $category;
                $parsed['keywords'][] = $keyword;
            }
        }

        // Detect locations
        $locations = [
            'lakewood' => 'Lakewood, NJ',
            'brooklyn' => 'Brooklyn, NY',
            'queens' => 'Queens, NY',
            'manhattan' => 'Manhattan, NY',
            'new york' => 'NY',
            'new jersey' => 'NJ',
            'nj' => 'NJ',
            'ny' => 'NY',
            'monsey' => 'Monsey, NY',
            'five towns' => 'Five Towns, NY',
            'baltimore' => 'Baltimore, MD',
            'chicago' => 'Chicago, IL',
            'los angeles' => 'Los Angeles, CA',
            'miami' => 'Miami, FL',
            'florida' => 'FL',
            'israel' => 'Israel',
            'jerusalem' => 'Jerusalem',
            'london' => 'London',
            'england' => 'England',
        ];

        foreach ($locations as $keyword => $loc) {
            if (strpos($full_text, $keyword) !== false) {
                $parsed['location'] = $loc;
                // Set region
                if (in_array($keyword, ['israel', 'jerusalem'])) {
                    $parsed['region'] = 'israel';
                } elseif (in_array($keyword, ['london', 'england'])) {
                    $parsed['region'] = 'england';
                } else {
                    $parsed['region'] = 'usa';
                }
                break;
            }
        }

        // Detect age groups
        if (preg_match('/\b(\d{1,2})\s*(-|to)\s*(\d{1,2})\s*(year|yr)/', $full_text, $m)) {
            $age = (int) $m[1];
            if ($age < 13) $parsed['age_group'] = 'children';
            elseif ($age < 18) $parsed['age_group'] = 'teen';
            else $parsed['age_group'] = 'adult';
        } elseif (preg_match('/(\d{1,2})\s*(year|yr)\s*(old)?/', $full_text, $m)) {
            $age = (int) $m[1];
            if ($age < 13) $parsed['age_group'] = 'children';
            elseif ($age < 18) $parsed['age_group'] = 'teen';
            else $parsed['age_group'] = 'adult';
        }

        // Add raw keywords from message
        $stop_words = ['i', 'a', 'the', 'in', 'for', 'my', 'me', 'to', 'and', 'or', 'is', 'it', 'of', 'need', 'want', 'looking', 'find', 'help', 'can', 'you', 'do', 'have', 'with', 'are', 'there', 'any', 'some', 'please', 'thanks', 'thank', 'hi', 'hello', 'hey'];
        $words = preg_split('/\s+/', $msg_lower);
        foreach ($words as $w) {
            $w = preg_replace('/[^a-z]/', '', $w);
            if (strlen($w) > 2 && !in_array($w, $stop_words)) {
                $parsed['keywords'][] = $w;
            }
        }
        $parsed['keywords'] = array_unique($parsed['keywords']);

        return $parsed;
    }

    /**
     * Search database and build a conversational response
     */
    private static function search_and_respond($parsed, $original_message, $session_id) {
        $resources = self::multi_search_parsed($parsed);
        $apps = [];

        // Also search apps if relevant
        if (in_array($parsed['service'], ['app', 'mental health', 'therapy', 'anxiety', 'depression'])) {
            $apps = GG_Database::search_apps($original_message, '', 3);
        }

        // Filter by location if specified
        if (!empty($parsed['location']) && !empty($resources)) {
            $location_lower = strtolower($parsed['location']);
            $location_filtered = array_filter($resources, function($r) use ($location_lower) {
                return (stripos($r->location, $location_lower) !== false ||
                        stripos($r->location_served, $location_lower) !== false);
            });
            // If we got location-filtered results, prefer those
            if (!empty($location_filtered)) {
                $resources = array_values($location_filtered);
            }
        }

        // Limit to top 5
        $top = array_slice($resources, 0, 5);

        if (empty($top) && empty($apps)) {
            // No results — ask for clarification
            $reply = "I wasn't able to find an exact match for that. Let me help narrow it down:\n\n";
            if (empty($parsed['service'])) {
                $reply .= "- **What type of service** are you looking for? (therapy, counseling, support group, legal, etc.)\n";
            }
            if (empty($parsed['location'])) {
                $reply .= "- **What area or city** are you in?\n";
            }
            $reply .= "\nOr you can browse our categories: Addiction Support, Therapy, Autism, Cancer, Housing, Legal, and 70+ more.";

            return [
                'message' => $reply,
                'resources' => [],
                'apps' => [],
            ];
        }

        // Build a conversational response
        $reply = self::build_conversational_reply($top, $apps, $parsed);

        self::save_message($session_id, 'user', $original_message);
        self::save_message($session_id, 'assistant', $reply);

        return [
            'message' => $reply,
            'resources' => self::format_resources($top),
            'apps' => self::format_apps($apps),
        ];
    }

    /**
     * Build a natural conversational reply
     */
    private static function build_conversational_reply($resources, $apps, $parsed) {
        $count = count($resources);
        $reply = '';

        // Opening based on what we know
        if (!empty($parsed['location']) && !empty($parsed['service'])) {
            $reply = "Here are the best matches for **{$parsed['service']}** services";
            if (!empty($parsed['location'])) {
                $reply .= " in **{$parsed['location']}**";
            }
            $reply .= ":\n\n";
        } elseif (!empty($parsed['service'])) {
            $reply = "I found {$count} resource(s) for **{$parsed['service']}**. Here are the top matches:\n\n";
        } else {
            $reply = "Based on your search, here are the most relevant resources I found:\n\n";
        }

        // List top resources with details
        foreach ($resources as $i => $r) {
            $num = $i + 1;
            $reply .= "**{$num}. {$r->name}**";
            if ($r->type) $reply .= " — {$r->type}";
            $reply .= "\n";
            if ($r->location) $reply .= "   Location: {$r->location}\n";
            if ($r->phone) $reply .= "   Phone: {$r->phone}\n";
            if ($r->website) $reply .= "   Website: {$r->website}\n";
            $reply .= "\n";
        }

        // Follow-up
        if (empty($parsed['location']) && $count > 0) {
            $reply .= "Would you like me to narrow these down by **location**? Just tell me your city or area.";
        } elseif ($count >= 5) {
            $reply .= "I have more results available. Would you like to see more, or would you like to narrow it down further (e.g., insurance accepted, in-person vs virtual)?";
        } else {
            $reply .= "Would you like more details about any of these, or would you like to search for something else?";
        }

        return $reply;
    }

    /**
     * Multi-strategy search
     */
    private static function multi_search($search_terms) {
        $all = [];

        // Full text search
        $results = GG_Database::search_resources($search_terms, '', '', 10);
        foreach ($results as $r) {
            $all[$r->id] = $r;
        }

        // Search individual important words
        $words = preg_split('/\s+/', $search_terms);
        foreach ($words as $w) {
            if (strlen($w) > 3) {
                $results = GG_Database::search_resources($w, '', '', 5);
                foreach ($results as $r) {
                    if (!isset($all[$r->id])) {
                        $all[$r->id] = $r;
                    }
                }
            }
            if (count($all) >= 15) break;
        }

        return array_values($all);
    }

    /**
     * Multi-strategy search using parsed intent
     */
    private static function multi_search_parsed($parsed) {
        $all = [];

        // Search by category first (most relevant)
        if (!empty($parsed['category'])) {
            $results = GG_Database::search_resources('', $parsed['region'], $parsed['category'], 10);
            foreach ($results as $r) {
                $all[$r->id] = $r;
            }
        }

        // Search by keywords
        foreach ($parsed['keywords'] as $kw) {
            $results = GG_Database::search_resources($kw, $parsed['region'], '', 5);
            foreach ($results as $r) {
                if (!isset($all[$r->id])) {
                    $all[$r->id] = $r;
                }
            }
            if (count($all) >= 15) break;
        }

        // If location specified, boost location matches
        if (!empty($parsed['location'])) {
            $loc_results = GG_Database::search_resources($parsed['location'], $parsed['region'], $parsed['category'], 10);
            // Prepend location matches
            $loc_all = [];
            foreach ($loc_results as $r) {
                $loc_all[$r->id] = $r;
            }
            foreach ($all as $id => $r) {
                if (!isset($loc_all[$id])) {
                    $loc_all[$id] = $r;
                }
            }
            $all = $loc_all;
        }

        return array_values($all);
    }

    /**
     * Extract search terms from conversation
     */
    private static function extract_search_terms($message, $history) {
        $terms = $message;
        // Add relevant terms from history
        foreach ($history as $h) {
            if ($h['role'] === 'user') {
                $terms .= ' ' . $h['content'];
            }
        }
        return $terms;
    }

    /**
     * Check if message is a greeting
     */
    private static function is_greeting($msg) {
        $greetings = ['hi', 'hello', 'hey', 'good morning', 'good afternoon', 'good evening', 'shalom', 'howdy', 'sup', 'whats up'];
        foreach ($greetings as $g) {
            if ($msg === $g || strpos($msg, $g) === 0) return true;
        }
        return false;
    }

    /**
     * Analyze conversation history for context
     */
    private static function analyze_conversation($msg, $history) {
        return [
            'turn_count' => count($history),
            'has_location' => false,
            'has_service' => false,
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

    public static function shortcode($atts) {
        ob_start();
        include GG_PLUGIN_DIR . 'templates/chatbot-inline.php';
        return ob_get_clean();
    }
}
