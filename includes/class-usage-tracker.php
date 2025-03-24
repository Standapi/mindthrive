<?php
if (!defined('ABSPATH')) exit;

class MindThrive_UsageTracker {

    public static function get_today_usage($user_id) {
        $today = date('Y-m-d');
        $usage = get_user_meta($user_id, 'mindthrive_daily_usage', true);

        if (!is_array($usage) || !isset($usage['date']) || $usage['date'] !== $today) {
            $usage = ['date' => $today, 'count' => 0];
            update_user_meta($user_id, 'mindthrive_daily_usage', $usage);
        }

        return $usage;
    }

    public static function get_limit($user_id) {
        if (user_can($user_id, 'administrator')) return PHP_INT_MAX;
        if (user_can($user_id, 'heal_user')) return 9999;
        if (user_can($user_id, 'empower_user')) return 50;
        if (user_can($user_id, 'support_user')) return 20;

        return 5; // default
    }

    public static function increment_usage($user_id) {
        $usage = self::get_today_usage($user_id);
        $usage['count']++;
        update_user_meta($user_id, 'mindthrive_daily_usage', $usage);
    }

    public static function is_over_limit($user_id) {
        $usage = self::get_today_usage($user_id);
        $limit = self::get_limit($user_id);
        return $usage['count'] >= $limit;
    }
}
