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
    $trans_table = $wpdb->prefix . 'wpwa_stripe_transactions';
    
    // Get latest transaction for each active subscription
    return $wpdb->get_var("
        SELECT SUM(t.amount) 
        FROM `{$trans_table}` t
        INNER JOIN `{$table}` s ON t.stripe_subscription_id = s.stripe_subscription_id
        WHERE s.status = 'active'
        AND t.id IN (
            SELECT MAX(id) FROM `{$trans_table}` 
            WHERE stripe_subscription_id IS NOT NULL 
            GROUP BY stripe_subscription_id
        )
    ");
}