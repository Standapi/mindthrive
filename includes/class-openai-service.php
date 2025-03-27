<?php
if (!defined('ABSPATH'))
    exit;

class MindThrive_OpenAI_Service
{

    public static function get_system_prompt()
    {
        return "You are a compassionate AI therapist. You are here to listen, ask questions, and help sort through thoughts.";
    }

    public static function build_payload($user_id, $message, $include_history = true)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mindthrive_chat_logs';

        $messages = [
            ['role' => 'system', 'content' => self::get_system_prompt()]
        ];

        if ($include_history) {
            $history = $wpdb->get_results($wpdb->prepare(
                "SELECT message_text, ai_response FROM {$table_name} WHERE user_id = %d ORDER BY created_at DESC LIMIT 20",
                $user_id
            ));

            $history = array_reverse($history);

            foreach ($history as $msg) {
                $messages[] = ['role' => 'user', 'content' => $msg->message_text];
                $messages[] = ['role' => 'assistant', 'content' => $msg->ai_response];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        $model = defined('MINDTHRIVE_OPENAI_MODEL') ? MINDTHRIVE_OPENAI_MODEL : 'gpt-4o-mini';

        $payload = [
        "model" => $model,
        "messages" => $messages,
        "temperature" => 0.7,
        "max_tokens" => 1000,
        "top_p" => 1,
        "stream" => false
        ];

        // ✅ Add o3-specific options
        if ($model === 'o3-mini') {
        $payload["response_format"] = ["type" => "text"];
        $payload["reasoning_effort"] = "medium";
        $payload["store"] = true;
        }

        return $payload;

            }
}
