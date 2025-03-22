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

    // Daily limit logic
    $message_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d AND DATE(created_at) = %s",
        $user_id,
        $date
    ));

    if (current_user_can('administrator')) {
        $max_messages = PHP_INT_MAX;
    } elseif (current_user_can('heal_user')) {
        $max_messages = 100;
    } elseif (current_user_can('empower_user')) {
        $max_messages = 50;
    } elseif (current_user_can('support_user')) {
        $max_messages = 20;
    } else {
        $max_messages = 5;
    }

    if ($message_count >= $max_messages) {
        wp_send_json_error(['message' => 'You have reached your daily message limit. Please upgrade your plan or contact support.']);
    }

    // Build OpenAI request
    $payload = mindthrive_build_openai_payload($user_id, $message);

    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . trim(MINDTHRIVE_OPENAI_API_KEY),
            'Content-Type'  => 'application/json'
        ],
        'body'    => json_encode($payload),
        'method'  => 'POST',
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error(['message' => 'AI server error: ' . $response->get_error_message()]);
    }

    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($response_body['choices'][0]['message']['content'])) {
        wp_send_json_error(['message' => 'AI response failed. Please try again.']);
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
