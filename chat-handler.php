<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles AI Chat AJAX Request
 */
function mindthrive_handle_chat()
{
    check_ajax_referer('mindthrive-chat-nonce', 'security');
    mindthrive_verify_request();

    $user_id = get_current_user_id();
    $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
    $today = date('Y-m-d');

    require_once plugin_dir_path(__FILE__) . 'includes/class-openai-service.php';
    $payload = MindThrive_OpenAI_Service::build_payload($user_id, $message);

    require_once plugin_dir_path(__FILE__) . 'includes/class-usage-tracker.php';

    if (MindThrive_UsageTracker::is_over_limit($user_id)) {
        wp_send_json_error(['message' => 'You have reached your daily message limit.']);
    }


    // Send to OpenAI
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . trim(MINDTHRIVE_OPENAI_API_KEY),
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode($payload),
        'method' => 'POST',
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

    require_once plugin_dir_path(__FILE__) . 'includes/class-chat-logger.php';
    MindThrive_ChatLogger::log_full_message($user_id, $message, $ai_reply);

    // Update usage
    $usage['count']++;
    MindThrive_UsageTracker::increment_usage($user_id);


    wp_send_json_success(['message' => $ai_reply]);
}



add_action('wp_ajax_mindthrive_chat', 'mindthrive_handle_chat');
add_action('wp_ajax_nopriv_mindthrive_chat', 'mindthrive_handle_chat');
