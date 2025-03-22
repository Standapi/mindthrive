<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles AI Chat AJAX Request
 */
function mindthrive_handle_chat() {
check_ajax_referer('mindthrive-chat-nonce', 'security');
mindthrive_verify_request();




    $user_id = get_current_user_id();
    $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
    $date    = date('Y-m-d');

    global $wpdb;
    $table_name = $wpdb->prefix . 'mindthrive_chat_logs';

    // Count how many messages the user has sent today
$message_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND DATE(created_at) = %s",
    $user_id,
    $date
));

// Define daily message limits by role or capability
if (current_user_can('administrator')) {
    $max_messages = PHP_INT_MAX; // Unlimited for Admins
} elseif (current_user_can('heal_user')) {
    $max_messages = 100; // Example limit for 'heal_user'
} elseif (current_user_can('empower_user')) {
    $max_messages = 50; // Example limit for 'empower_user'
} elseif (current_user_can('support_user')) {
    $max_messages = 20; // Example limit for 'support_user'
} else {
    $max_messages = 5;  // Default limit for regular users
}

// Check if user exceeded daily limit
if ($message_count >= $max_messages) {
    wp_send_json_error([
        'message' => 'You have reached your daily message limit. Please upgrade your plan or contact support.'
    ]);
}

$system_prompt = mindthrive_get_system_prompt();



    $api_url = "https://api.openai.com/v1/chat/completions";

    $payload = [
        "model"       => "gpt-4o-mini",
        "messages"    => [
            ["role" => "system", "content" => $system_prompt],
            ["role" => "user",   "content" => $message]
        ],
        "temperature" => 0.7,
        "max_tokens"  => 1000,
        "top_p"       => 1,
        "stream"      => false
    ];

    $response = wp_remote_post($api_url, [
        'headers' => [
            'Authorization' => 'Bearer ' . trim(MINDTHRIVE_OPENAI_API_KEY),
            'Content-Type'  => 'application/json'
        ],
        'body'    => json_encode($payload),
        'method'  => 'POST',
        'timeout' => 30,
    ]);

    if ( is_wp_error($response) ) {
        error_log("GPT4o Mini API Error: " . $response->get_error_message());
        wp_send_json_error([
            'message' => 'AI server error: ' . $response->get_error_message()
        ]);
    }

    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    if ( empty($response_body['choices'][0]['message']['content']) ) {
        error_log("Invalid GPT4o Mini API Response: " . json_encode($response_body));
        wp_send_json_error([
            'message' => 'AI response failed. Please try again.'
        ]);
    }

    $ai_reply_raw = $response_body['choices'][0]['message']['content'];
    $ai_reply = wp_kses_post($ai_reply_raw);

    $wpdb->insert($table_name, [
        'user_id'      => $user_id,
        'message_text' => $message,
        'ai_response'  => $ai_reply,
        'created_at'   => current_time('mysql')
    ]);

    wp_send_json_success(['message' => $ai_reply]);
}

add_action('wp_ajax_mindthrive_chat', 'mindthrive_handle_chat');
add_action('wp_ajax_nopriv_mindthrive_chat', 'mindthrive_handle_chat');
