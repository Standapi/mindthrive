<?php
/**
 * Plugin Name: MindThrive GPT4o Mini Chat
 * Plugin URI:  https://mindthrive.me/
 * Description: A custom AI-powered therapist chat system for MindThrive, using the GPT4o Mini model.
 * Version:     1.0
 * Author:      Your Name
 * Author URI:  https://mindthrive.me/
 * License:     GPL2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Create the database table upon plugin activation.
 */
function mindthrive_verify_request() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Please log in.']);
    }

    if (!defined('MINDTHRIVE_OPENAI_API_KEY') || !MINDTHRIVE_OPENAI_API_KEY) {
        wp_send_json_error(['message' => 'API key not configured.']);
    }
}


function mindthrive_get_system_prompt() {
    return "You are a compassionate, insightful, and highly skilled AI therapist. Your approach is empathetic, supportive, and non-judgmental. Each response you provide includes these elements clearly structured:

1. **Empathy & Validation:** Start by acknowledging and validating the user's feelings genuinely and warmly.
2. **Reflection & Insight:** Offer a gentle, insightful reflection or perspective based on psychological principles.
3. **Therapeutic Techniques:** Clearly incorporate relevant therapeutic approaches such as Cognitive Behavioral Therapy (CBT), mindfulness practices, motivational interviewing, or acceptance and commitment therapy (ACT) where suitable.
4. **Relevant Questioning:** Always end by asking a thoughtful, reflective, open-ended question that encourages further emotional exploration, self-awareness, and insight.

Continuously remember past interactions and refer naturally to previous points discussed. If the user indicates severe distress or suicidal ideation, provide a supportive message urging immediate professional help.";
}



function mindthrive_ai_install() {
    global $wpdb;

    $table_name     = $wpdb->prefix . 'mindthrive_chat_logs';
    $charset_collate = $wpdb->get_charset_collate();

    // SQL to create table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        message_text TEXT NOT NULL,
        ai_response  TEXT NOT NULL,
        created_at   DATETIME NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
// Adjust path below if your plugin folder name differs
register_activation_hook(__FILE__, 'mindthrive_ai_install');

/**
 * Enqueue Scripts & Styles
 */
function mindthrive_ai_enqueue_assets() {
    // Enqueue the CSS
    wp_enqueue_style(
        'mindthrive-chat-style',
        plugin_dir_url(__FILE__) . 'css/chat-style.css',
        array(),
        '1.0',
        'all'
    );

    // Enqueue the JS
    wp_enqueue_script(
        'mindthrive-chat-script',
        plugin_dir_url(__FILE__) . 'js/chat-script.js',
        array('jquery'), // dependencies
        '1.0',
        true // in footer
    );

    // Localize (pass data to JS)
    wp_localize_script('mindthrive-chat-script', 'mindthriveChat', array(
        'ajaxurl'  => admin_url('admin-ajax.php'),
        'security' => wp_create_nonce('mindthrive-chat-nonce'), // matches PHP
    ));
}
add_action('wp_enqueue_scripts', 'mindthrive_ai_enqueue_assets');

/**
 * Shortcode to Display the Chat Interface
 */
function mindthrive_chat_shortcode() {
    ob_start();
    include plugin_dir_path(__FILE__) . 'chat-interface.php';
    return ob_get_clean();
}
add_shortcode('mindthrive_ai_chat', 'mindthrive_chat_shortcode');

/**
 * Include the AJAX Handler
 */
include plugin_dir_path(__FILE__) . 'chat-handler.php';

/**
 * Fetch user's recent chat history.
 */
function fetch_chat_history() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Please log in.']);
    }

    global $wpdb;
    $user_id = get_current_user_id();
    $table_name = $wpdb->prefix . 'mindthrive_chat_logs';

    $history = $wpdb->get_results($wpdb->prepare(
        "SELECT message_text, ai_response FROM {$table_name} WHERE user_id = %d ORDER BY created_at ASC LIMIT 20",
        $user_id
    ));

    wp_send_json_success(['history' => $history]);
}

add_action('wp_ajax_fetch_chat_history', 'fetch_chat_history');

if ( ! defined( 'ABSPATH' ) ) exit;

function mindthrive_handle_chat_stream() {
    check_ajax_referer('mindthrive-chat-nonce', 'security');
    mindthrive_verify_request();


    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    global $wpdb;
    $table_name = $wpdb->prefix . 'mindthrive_chat_logs';
    $user_id = get_current_user_id();
    $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';

    // Insert user's message
    $wpdb->insert($table_name, [
        'user_id' => $user_id,
        'message_text' => $message,
        'ai_response' => '',
        'created_at' => current_time('mysql')
    ]);
    $log_id = $wpdb->insert_id;

    // Retrieve last 20 messages (conversation history)
    $history = $wpdb->get_results($wpdb->prepare(
        "SELECT message_text, ai_response FROM {$table_name} WHERE user_id = %d AND id != %d ORDER BY created_at DESC LIMIT 20",
        $user_id,
        $log_id
    ));
    $history = array_reverse($history);

    // Enhanced therapeutic system prompt
    $system_prompt = "You are a compassionate, insightful, and skilled AI therapist. Your responses are empathetic, supportive, and non-judgmental.

Structure each response clearly as follows:
1. Empathize with and validate the user’s feelings genuinely.
2. Provide gentle, insightful reflections or guidance using therapeutic techniques such as Cognitive Behavioral Therapy (CBT), mindfulness, or motivational interviewing when appropriate.
3. End each message clearly with a thoughtful, open-ended question to encourage deeper emotional exploration and self-reflection.

Always refer naturally to previous points discussed. If a user expresses severe distress or mentions self-harm, gently and clearly encourage them to seek professional help immediately.";

    // Prepare messages with history for OpenAI
    $messages = [
        ['role' => 'system', 'content' => $system_prompt]
    ];

    foreach ($history as $msg) {
        $messages[] = ['role' => 'user', 'content' => $msg->message_text];
        $messages[] = ['role' => 'assistant', 'content' => $msg->ai_response];
    }

    $messages[] = ['role' => 'user', 'content' => $message];

    // Initialize cURL streaming with new payload
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . trim(MINDTHRIVE_OPENAI_API_KEY),
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => 'gpt-4o-mini',
            'messages' => $messages,
            'stream' => true,
            'temperature' => 0.7
        ]),
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_WRITEFUNCTION => function($curl, $data) use ($wpdb, $table_name, $log_id) {
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                if (strpos($line, 'data: ') === 0) {
                    $jsonData = substr($line, 6);
                    if (trim($jsonData) === '[DONE]') {
                        echo "data: [DONE]\n\n";
                        ob_flush(); flush();
                        return strlen($data);
                    }
                    $json = json_decode($jsonData, true);
                    if (!empty($json['choices'][0]['delta']['content'])) {
                        $content = $json['choices'][0]['delta']['content'];
                        echo 'data: ' . json_encode(['content' => $content]) . "\n\n";
                        ob_flush(); flush();

                        // Update AI response log incrementally
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$table_name} SET ai_response = CONCAT(ai_response, %s) WHERE id = %d",
                            $content,
                            $log_id
                        ));
                    }
                }
            }
            return strlen($data);
        }
    ]);

    curl_exec($ch);
    curl_close($ch);
    exit;
}



add_action('wp_ajax_mindthrive_chat_stream', 'mindthrive_handle_chat_stream');
add_action('wp_ajax_nopriv_mindthrive_chat_stream', 'mindthrive_handle_chat_stream');

function clear_chat_history() {
    check_ajax_referer('mindthrive-chat-nonce', 'security');
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Not authorized.']);

    global $wpdb;
    $wpdb->delete($wpdb->prefix . 'mindthrive_chat_logs', ['user_id' => get_current_user_id()]);
    
    wp_send_json_success();
}
add_action('wp_ajax_clear_chat_history', 'clear_chat_history');
