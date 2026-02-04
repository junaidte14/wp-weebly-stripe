<?php
/**
 * Stripe Subscription Management
 */

if (!defined('ABSPATH')) exit;

/**
 * Create subscription record in database
 */
function wpwa_stripe_create_subscription_record($subscription_data) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_subscriptions';
    
    $data = array(
        'stripe_subscription_id' => $subscription_data['stripe_subscription_id'],
        'stripe_customer_id' => $subscription_data['stripe_customer_id'],
        'weebly_user_id' => $subscription_data['weebly_user_id'],
        'weebly_site_id' => $subscription_data['weebly_site_id'] ?? null,
        'product_id' => $subscription_data['product_id'],
        'status' => $subscription_data['status'],
        'current_period_start' => $subscription_data['current_period_start'],
        'current_period_end' => $subscription_data['current_period_end'],
        'cancel_at_period_end' => $subscription_data['cancel_at_period_end'] ?? 0,
        'access_token' => $subscription_data['access_token'] ?? null,
        'metadata' => json_encode($subscription_data['metadata'] ?? array())
    );
    
    $wpdb->insert($table, $data);
    
    return $wpdb->insert_id;
}

/**
 * Update subscription record
 */
function wpwa_stripe_update_subscription_record($stripe_subscription_id, $updates) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_subscriptions';
    
    return $wpdb->update(
        $table,
        $updates,
        array('stripe_subscription_id' => $stripe_subscription_id)
    );
}

/**
 * Get subscription by Stripe ID
 */
function wpwa_stripe_get_subscription_by_stripe_id($stripe_subscription_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_subscriptions';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE stripe_subscription_id = %s",
        $stripe_subscription_id
    ), ARRAY_A);
}

/**
 * Get active subscriptions for Weebly user
 */
function wpwa_stripe_get_user_subscriptions($weebly_user_id, $status = 'active') {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_subscriptions';
    
    if ($status) {
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE weebly_user_id = %s AND status = %s ORDER BY created_at DESC",
            $weebly_user_id,
            $status
        ), ARRAY_A);
    }
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE weebly_user_id = %s ORDER BY created_at DESC",
        $weebly_user_id
    ), ARRAY_A);
}

/**
 * Cancel subscription
 */
function wpwa_stripe_cancel_subscription($stripe_subscription_id, $immediately = false) {
    if (!wpwa_stripe_init()) {
        return false;
    }
    
    try {
        if ($immediately) {
            // Cancel immediately
            $subscription = \Stripe\Subscription::update($stripe_subscription_id, array(
                'cancel_at_period_end' => false
            ));
            \Stripe\Subscription::cancel($stripe_subscription_id);
            
            wpwa_stripe_update_subscription_record($stripe_subscription_id, array(
                'status' => 'canceled',
                'cancel_at_period_end' => 0
            ));
        } else {
            // Cancel at period end
            $subscription = \Stripe\Subscription::update($stripe_subscription_id, array(
                'cancel_at_period_end' => true
            ));
            
            wpwa_stripe_update_subscription_record($stripe_subscription_id, array(
                'cancel_at_period_end' => 1
            ));
        }
        
        return true;
        
    } catch (Exception $e) {
        wpwa_stripe_log('Subscription cancel error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Reactivate cancelled subscription
 */
function wpwa_stripe_reactivate_subscription($stripe_subscription_id) {
    if (!wpwa_stripe_init()) {
        return false;
    }
    
    try {
        $subscription = \Stripe\Subscription::update($stripe_subscription_id, array(
            'cancel_at_period_end' => false
        ));
        
        wpwa_stripe_update_subscription_record($stripe_subscription_id, array(
            'cancel_at_period_end' => 0,
            'status' => 'active'
        ));
        
        return true;
        
    } catch (Exception $e) {
        wpwa_stripe_log('Subscription reactivate error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has active subscription for product
 */
function wpwa_stripe_user_has_active_subscription($weebly_user_id, $product_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_subscriptions';
    
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM `{$table}` 
         WHERE weebly_user_id = %s 
         AND product_id = %d 
         AND status = 'active' 
         AND current_period_end > NOW()",
        $weebly_user_id,
        $product_id
    ));
    
    return $count > 0;
}