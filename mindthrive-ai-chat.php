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
function mindthrive_verify_request()
{
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Please log in.']);
    }

    if (!defined('MINDTHRIVE_OPENAI_API_KEY') || !MINDTHRIVE_OPENAI_API_KEY) {
        wp_send_json_error(['message' => 'API key not configured.']);
    }
}


function mindthrive_ai_install()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'mindthrive_chat_logs';
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
function mindthrive_ai_enqueue_assets()
{
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
        'ajaxurl' => admin_url('admin-ajax.php'),
        'security' => wp_create_nonce('mindthrive-chat-nonce'), // matches PHP
    ));
}
add_action('wp_enqueue_scripts', 'mindthrive_ai_enqueue_assets');

/**
 * Shortcode to Display the Chat Interface
 */
function mindthrive_chat_shortcode()
{
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
function fetch_chat_history()
{
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Please log in.']);
    }

    require_once plugin_dir_path(__FILE__) . 'includes/class-chat-logger.php';

    $user_id = get_current_user_id();
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

    $history = MindThrive_ChatLogger::get_history($user_id, 20, $offset);

    global $wpdb;
    $total = $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}mindthrive_chat_logs WHERE user_id = %d", $user_id)
    );

    wp_send_json_success([
        'history' => $history,
        'total' => (int) $total
    ]);

}




add_action('wp_ajax_fetch_chat_history', 'fetch_chat_history');

function mindthrive_get_message_usage()
{   $midnight = strtotime('tomorrow'); // midnight tonight

    require_once plugin_dir_path(__FILE__) . 'includes/class-usage-tracker.php';

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not logged in.']);
    }

    $user_id = get_current_user_id();
    $today = date('Y-m-d');

    $usage = get_user_meta($user_id, 'mindthrive_daily_usage', true);
    $message_count = (is_array($usage) && isset($usage['date'], $usage['count']) && $usage['date'] === $today)
        ? $usage['count']
        : 0;

    if (current_user_can('administrator')) {
        $max = PHP_INT_MAX;
    } elseif (current_user_can('heal_user')) {
        $max = 9999;
    } elseif (current_user_can('empower_user')) {
        $max = 50;
    } elseif (current_user_can('support_user')) {
        $max = 20;
    } else {
        $max = 5;
    }

    wp_send_json_success([
        'used' => $message_count,
        'max'  => $max,
        'role' => MindThrive_UsageTracker::get_role_slug($user_id),
        'reset_at' => $midnight
    ]);
    
}

add_action('wp_ajax_get_message_usage', 'mindthrive_get_message_usage');
add_action('wp_ajax_nopriv_get_message_usage', 'mindthrive_get_message_usage');



function mindthrive_handle_chat_stream()
{
    // ✅ Step 1: Disable buffering and compression
    while (ob_get_level()) {
        ob_end_clean();
    }

    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }

    @ini_set('zlib.output_compression', 'Off');
    @ini_set('output_buffering', 'Off');
    @ini_set('implicit_flush', '1');

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');

    ob_implicit_flush(true);

    // ✅ Step 2: Security and access check
    check_ajax_referer('mindthrive-chat-nonce', 'security');
    mindthrive_verify_request();

    global $wpdb;
    $table_name = $wpdb->prefix . 'mindthrive_chat_logs';
    $user_id = get_current_user_id();
    $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';

    require_once plugin_dir_path(__FILE__) . 'includes/class-usage-tracker.php';

    if (MindThrive_UsageTracker::is_over_limit($user_id)) {
        echo "data: " . json_encode(['error' => 'You have reached your daily message limit.']) . "\n\n";
        ob_flush();
        flush();
        exit;
    }




    // ✅ Step 3: Insert initial user message to DB
    require_once plugin_dir_path(__FILE__) . 'includes/class-chat-logger.php';
    $log_id = MindThrive_ChatLogger::log_user_message($user_id, $message);

    require_once plugin_dir_path(__FILE__) . 'includes/class-openai-service.php';
    $payload = MindThrive_OpenAI_Service::build_payload($user_id, $message);
    // ✅ Step 4: Prepare GPT streaming request
    $payload['stream'] = true;

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . trim(MINDTHRIVE_OPENAI_API_KEY),
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($wpdb, $table_name, $log_id) {
            $lines = explode("\n", $data);
            foreach ($lines as $line) {
                if (strpos($line, 'data: ') === 0) {
                    $jsonData = substr($line, 6);
                    if (trim($jsonData) === '[DONE]') {
                        echo "data: [DONE]\n\n";
                        ob_flush();
                        flush();
                        
                    }
                    $json = json_decode($jsonData, true);
                    if (!empty($json['choices'][0]['delta']['content'])) {
                        $content = $json['choices'][0]['delta']['content'];
                        echo "data: " . json_encode(['content' => $content]) . "\n\n";
                        ob_flush();
                        flush();


                        // ✅ Update DB response incrementally
                        MindThrive_ChatLogger::update_ai_response($log_id, $content);

                    }
                }
            }


        }
    ]);

    // ✅ Step 5: Execute and clean up
    curl_exec($ch);
    // Once full message received, increment usage
    MindThrive_UsageTracker::increment_usage($user_id);

    return strlen($data);
    if (curl_errno($ch)) {
        echo "data: " . json_encode(['error' => curl_error($ch)]) . "\n\n";
        ob_flush();
        flush();
    }

    curl_close($ch);
    exit;
}





add_action('wp_ajax_mindthrive_chat_stream', 'mindthrive_handle_chat_stream');
add_action('wp_ajax_nopriv_mindthrive_chat_stream', 'mindthrive_handle_chat_stream');

function clear_chat_history()
{
    check_ajax_referer('mindthrive-chat-nonce', 'security');

    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Not authorized.']);
    }

    require_once plugin_dir_path(__FILE__) . 'includes/class-chat-logger.php';

    $user_id = get_current_user_id();
    $result = MindThrive_ChatLogger::clear_history($user_id);

    if ($result !== false) {
        wp_send_json_success();
    } else {
        wp_send_json_error(['message' => 'Failed to clear chat history.']);
    }
}

add_action('wp_ajax_clear_chat_history', 'clear_chat_history');
