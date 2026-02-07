<?php
/**
 * Subscription Helper Functions
 */

if (!defined('ABSPATH')) exit;

/**
 * Get active subscriptions count
 */
function wpwa_stripe_get_active_subscriptions_count() {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_subscriptions';
    
    return $wpdb->get_var("SELECT COUNT(*) FROM `{$table}` WHERE status = 'active'");
}

/**
 * Get subscriptions expiring soon
 */
function wpwa_stripe_get_expiring_subscriptions($days = 7) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_subscriptions';
    
    $date = date('Y-m-d H:i:s', strtotime("+{$days} days"));
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `{$table}` 
         WHERE status = 'active' 
         AND current_period_end <= %s 
         AND cancel_at_period_end = 0
         ORDER BY current_period_end ASC",
        $date
    ), ARRAY_A);
}

/**
 * Get monthly recurring revenue (MRR)
 */
function wpwa_stripe_get_mrr() {
    global $wpdb;
    $table = $wpdb->prefix . 'wpwa_stripe_subscriptions';
    // Sum the 'amount' column for all active subscriptions
    $result = $wpdb->get_var("
        SELECT SUM(amount) 
        FROM `{$table}` 
        WHERE status = 'active'
    ");
    // Cast the result to a float (returns 0.0 if result is null)
    return (float) $result;
}