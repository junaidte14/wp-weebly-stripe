<?php
/**
 * Universal Access Control System
 * Checks: Legacy WC Orders + Stripe Transactions + Whitelist
 */

if (!defined('ABSPATH')) exit;

/**
 * Universal access check - THE MAIN FUNCTION
 * 
 * @param string $weebly_user_id
 * @param int $product_id Current product ID (not old_pr_id)
 * @return array ['has_access' => bool, 'source' => string, 'details' => array]
 */
function wpwa_user_has_access($weebly_user_id, $product_id, $site_id = '') {
    // Priority 1: Whitelist (highest priority - always grants access)
    $whitelist_check = wpwa_check_whitelist_access($weebly_user_id, $product_id, $site_id);
    if ($whitelist_check['has_access']) {
        return $whitelist_check;
    }
    
    // Priority 2: Active Stripe subscription
    $stripe_sub_check = wpwa_check_stripe_subscription_access($weebly_user_id, $product_id, $site_id);
    if ($stripe_sub_check['has_access']) {
        return $stripe_sub_check;
    }
    
    // Priority 3: Recent Stripe one-time purchase
    $stripe_purchase_check = wpwa_check_stripe_purchase_access($weebly_user_id, $product_id, $site_id);
    if ($stripe_purchase_check['has_access']) {
        return $stripe_purchase_check;
    }
    
    // Priority 4: Legacy WC active subscription
    /* $legacy_sub_check = wpwa_check_legacy_subscription_access($weebly_user_id, $product_id, $site_id);
    if ($legacy_sub_check['has_access']) {
        return $legacy_sub_check;
    } */
    
    // Priority 5: Legacy WC one-time purchase (LIFETIME ACCESS)
    $legacy_purchase_check = wpwa_check_legacy_purchase_access($weebly_user_id, $product_id, $site_id);
    if ($legacy_purchase_check['has_access']) {
        return $legacy_purchase_check;
    }
    
    // No access found
    return array(
        'has_access' => false,
        'source' => null,
        'details' => array()
    );
}

/**
 * Check 1: Whitelist Access
 */
function wpwa_check_whitelist_access($weebly_user_id, $product_id, $site_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_whitelist';
    
    $whitelist_entry = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` 
         WHERE weebly_user_id = %s 
         AND product_id = %d 
         AND status = 'active'
         AND (expiry_date IS NULL OR expiry_date > NOW())",
        $weebly_user_id,
        $product_id
    ), ARRAY_A);
    
    if ($whitelist_entry) {
        return array(
            'has_access' => true,
            'source' => 'whitelist',
            'details' => array(
                'whitelist_id' => $whitelist_entry['id'],
                'granted_by' => $whitelist_entry['granted_by'],
                'expiry_date' => $whitelist_entry['expiry_date'],
                'reason' => $whitelist_entry['reason']
            )
        );
    }
    
    return array('has_access' => false);
}

/**
 * Check 2: Active Stripe Subscription
 */
function wpwa_check_stripe_subscription_access($weebly_user_id, $product_id, $site_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_subscriptions';
    
    $subscription = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` 
         WHERE weebly_user_id = %s
         AND weebly_site_id = %s 
         AND product_id = %d 
         AND status = 'active' 
         AND current_period_end > NOW()",
        $weebly_user_id,
        $site_id,
        $product_id
    ), ARRAY_A);
    
    if ($subscription) {
        return array(
            'has_access' => true,
            'source' => 'stripe_subscription',
            'details' => array(
                'subscription_id' => $subscription['stripe_subscription_id'],
                'current_period_end' => $subscription['current_period_end'],
                'status' => $subscription['status']
            )
        );
    }
    
    return array('has_access' => false);
}

/**
 * Check 3: Recent Stripe One-Time Purchase
 */
function wpwa_check_stripe_purchase_access($weebly_user_id, $product_id, $site_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_transactions';
    
    // Check for successful one-time payment
    $transaction = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` 
         WHERE weebly_user_id = %s
         AND weebly_site_id = %s 
         AND product_id = %d 
         AND transaction_type = 'one_time' 
         AND status = 'succeeded' 
         ORDER BY created_at DESC 
         LIMIT 1",
        $weebly_user_id,
        $site_id,
        $product_id
    ), ARRAY_A);
    
    if ($transaction) {
        return array(
            'has_access' => true,
            'source' => 'stripe_purchase',
            'details' => array(
                'transaction_id' => $transaction['id'],
                'purchase_date' => $transaction['created_at'],
                'amount' => $transaction['amount']
            )
        );
    }
    
    return array('has_access' => false);
}

/**
 * Check 4: Legacy WC Active Subscription [can be removed]
 */
function wpwa_check_legacy_subscription_access($weebly_user_id, $product_id, $site_id) {
    global $wpdb;
    
    // Get old product ID
    $old_pr_id = get_post_meta($product_id, '_wpwa_old_pr_id', true);
    
    if (!$old_pr_id) {
        return array('has_access' => false);
    }
    
    $table = $wpdb->prefix . 'wpwa_archived_subscriptions';
    
    // Check for active subscription (not expired, not revoked)
    $subscription = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` 
         WHERE weebly_user_id = %s 
         AND product_id = %d 
         AND status IN ('active', 'grace') 
         AND (expiry_date IS NULL OR expiry_date > NOW())
         ORDER BY created_at DESC 
         LIMIT 1",
        $weebly_user_id,
        $old_pr_id
    ), ARRAY_A);
    
    if ($subscription) {
        return array(
            'has_access' => true,
            'source' => 'woocommerce_subscription',
            'details' => array(
                'wc_order_id' => $subscription['wc_order_id'],
                'expiry_date' => $subscription['expiry_date'],
                'status' => $subscription['status'],
                'renewal_count' => $subscription['renewal_count']
            )
        );
    }
    
    return array('has_access' => false);
}

/**
 * Check 5: Legacy WC One-Time Purchase (LIFETIME ACCESS)
 */
function wpwa_check_legacy_purchase_access($weebly_user_id, $product_id, $site_id) {
    global $wpdb;
    
    // Get old product ID
    $old_pr_id = get_post_meta($product_id, '_wpwa_old_pr_id', true);
    
    if (!$old_pr_id) {
        return array('has_access' => false);
    }
    
    $table = $wpdb->prefix . 'wpwa_archived_orders';
    
    // Check for ANY completed purchase (grants lifetime access)
    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` 
         WHERE weebly_user_id = %s
         AND weebly_site_id = %s 
         AND product_id = %d 
         AND status IN ('completed', 'processing') 
         ORDER BY order_date DESC 
         LIMIT 1",
        $weebly_user_id,
        $site_id,
        $old_pr_id
    ), ARRAY_A);
    
    if ($order) {
        return array(
            'has_access' => true,
            'source' => 'woocommerce_lifetime',
            'details' => array(
                'wc_order_id' => $order['wc_order_id'],
                'order_number' => $order['order_number'],
                'purchase_date' => $order['order_date'],
                'amount' => $order['amount'],
                'note' => 'Legacy one-time purchase - lifetime access granted'
            )
        );
    }
    
    return array('has_access' => false);
}

/**
 * Update access token for user (works for both legacy and new)
 * 
 * @param string $weebly_user_id
 * @param int $product_id
 * @param string $access_token
 * @param string $source Where access was granted from
 */
function wpwa_update_user_access_token($weebly_user_id, $weebly_site_id, $product_id, $access_token, $source = '') {
    global $wpdb;
    
    $encrypted_token = wpwa_stripe_encrypt_token($access_token);
    
    // Update based on source
    switch ($source) {
        case 'stripe_subscription':
            $table = $wpdb->prefix . 'wpwa_stripe_subscriptions';
            $wpdb->update(
                $table,
                array('access_token' => $encrypted_token),
                array('weebly_user_id' => $weebly_user_id, 'weebly_site_id' => $weebly_site_id, 'product_id' => $product_id)
            );
            break;
            
        case 'stripe_purchase':
            $table = $wpdb->prefix . 'wpwa_stripe_transactions';
            $wpdb->update(
                $table,
                array('access_token' => $encrypted_token),
                array(
                    'weebly_user_id' => $weebly_user_id,
                    'weebly_site_id' => $weebly_site_id, 
                    'product_id' => $product_id,
                    'transaction_type' => 'one_time'
                ),
                array('%s'),
                array('%s', '%s', '%d', '%s')
            );
            break;
            
        case 'woocommerce_subscription':
        case 'woocommerce_lifetime':
            // Update legacy archived_orders table
            $old_pr_id = get_post_meta($product_id, '_wpwa_old_pr_id', true);
            if ($old_pr_id) {
                $table = $wpdb->prefix . 'wpwa_archived_orders';
                $wpdb->update(
                    $table,
                    array('access_token' => $encrypted_token),
                    array('weebly_user_id' => $weebly_user_id, 'weebly_site_id' => $weebly_site_id, 'product_id' => $old_pr_id)
                );
            }
            break;
            
        case 'whitelist':
            // Whitelist doesn't need token update (managed separately)
            break;
    }
    
    // Log the token update
    wpwa_stripe_log('Access token updated', array(
        'user_id' => $weebly_user_id,
        'site_id' => $weebly_site_id,
        'product_id' => $product_id,
        'source' => $source
    ));
}

/**
 * Log access grant for analytics
 */
function wpwa_log_access_grant($weebly_user_id, $weebly_site_id, $product_id, $source) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_access_log';
    
    $wpdb->insert($table, array(
        'weebly_user_id' => $weebly_user_id,
        'weebly_site_id' => $weebly_site_id,
        'product_id' => $product_id,
        'access_source' => $source,
        'granted_at' => current_time('mysql')
    ));
}

/**
 * Helper: Get access summary for admin display
 */
function wpwa_get_user_access_summary($weebly_user_id, $product_id) {
    $access = wpwa_user_has_access($weebly_user_id, $product_id, '');
    
    if (!$access['has_access']) {
        return 'No Access';
    }
    
    $labels = array(
        'whitelist' => '🎁 Whitelisted',
        'stripe_subscription' => '🔄 Active Subscription (Stripe)',
        'stripe_purchase' => '💳 Purchased (Stripe)',
        'woocommerce_subscription' => '🔄 Active Subscription (Legacy WC)',
        'woocommerce_lifetime' => '⭐ Lifetime Access (Legacy WC)'
    );
    
    return $labels[$access['source']] ?? 'Unknown';
}