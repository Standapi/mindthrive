<?php
if (!defined('ABSPATH')) exit;

class MindThrive_ChatLogger {

    public static function log_full_message($user_id, $message, $ai_response) {
        global $wpdb;
        $table = self::get_table_name();
    
        return $wpdb->insert($table, [
            'user_id'      => $user_id,
            'message_text' => $message,
            'ai_response'  => $ai_response,
            'created_at'   => current_time('mysql')
        ]);
    }
    

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'mindthrive_chat_logs';
    }

    public static function log_user_message($user_id, $message) {
        global $wpdb;
        $table = self::get_table_name();

        $wpdb->insert($table, [
            'user_id'      => $user_id,
            'message_text' => $message,
            'ai_response'  => '',
            'created_at'   => current_time('mysql')
        ]);

        return $wpdb->insert_id;
    }

    public static function update_ai_response($log_id, $content) {
        global $wpdb;
        $table = self::get_table_name();

        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET ai_response = CONCAT(ai_response, %s) WHERE id = %d",
            $content,
            $log_id
        ));
    }

    public static function get_history($user_id, $limit = 20, $offset = 0) {
        global $wpdb;
        $table = self::get_table_name();
    
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT message_text, ai_response FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $user_id,
            $limit,
            $offset
        ));
    
        return array_reverse($results); // oldest first
    }
    
    

    public static function clear_history($user_id) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->delete($table, ['user_id' => $user_id]);
    }
}
