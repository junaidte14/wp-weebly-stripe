<?php
/**
 * Order/Transaction Management
 */

if (!defined('ABSPATH')) exit;

/**
 * Create transaction record
 */
function wpwa_stripe_create_transaction($data) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_transactions';
    
    $defaults = array(
        'transaction_type' => 'one_time',
        'stripe_payment_intent_id' => null,
        'stripe_invoice_id' => null,
        'stripe_subscription_id' => null,
        'stripe_customer_id' => '',
        'weebly_user_id' => '',
        'weebly_site_id' => null,
        'product_id' => 0,
        'amount' => 0,
        'currency' => 'USD',
        'status' => 'pending',
        'access_token' => null,
        'final_url' => null,
        'weebly_notified' => 0,
        'metadata' => null
    );
    
    $data = wp_parse_args($data, $defaults);
    
    $wpdb->insert($table, $data);
    
    return $wpdb->insert_id;
}

/**
 * Get transaction by ID
 */
function wpwa_stripe_get_transaction($transaction_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_transactions';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE id = %d",
        $transaction_id
    ), ARRAY_A);
}

/**
 * Get transaction by payment intent
 */
function wpwa_stripe_get_transaction_by_payment_intent($payment_intent_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_transactions';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE stripe_payment_intent_id = %s",
        $payment_intent_id
    ), ARRAY_A);
}

/**
 * Get transaction by subscription
 */
function wpwa_stripe_get_transaction_by_subscription($subscription_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_transactions';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE stripe_subscription_id = %s ORDER BY created_at DESC LIMIT 1",
        $subscription_id
    ), ARRAY_A);
}

/**
 * Get all transactions for Weebly user
 */
function wpwa_stripe_get_user_transactions($weebly_user_id, $limit = 50) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_transactions';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE weebly_user_id = %s ORDER BY created_at DESC LIMIT %d",
        $weebly_user_id,
        $limit
    ), ARRAY_A);
}

/**
 * Get all transactions with filters
 */
function wpwa_stripe_get_transactions($args = array()) {
    global $wpdb;
    
    $defaults = array(
        'status' => null,
        'product_id' => null,
        'transaction_type' => null,
        'limit' => 50,
        'offset' => 0,
        'orderby' => 'created_at',
        'order' => 'DESC'
    );
    
    $args = wp_parse_args($args, $defaults);
    
    $table = $wpdb->prefix . 'wpwa_stripe_transactions';
    
    $where = array('1=1');
    $values = array();
    
    if ($args['status']) {
        $where[] = 'status = %s';
        $values[] = $args['status'];
    }
    
    if ($args['product_id']) {
        $where[] = 'product_id = %d';
        $values[] = $args['product_id'];
    }
    
    if ($args['transaction_type']) {
        $where[] = 'transaction_type = %s';
        $values[] = $args['transaction_type'];
    }
    
    $where_sql = implode(' AND ', $where);
    
    $query = "SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY {$args['orderby']} {$args['order']} LIMIT %d OFFSET %d";
    $values[] = $args['limit'];
    $values[] = $args['offset'];
    
    if (!empty($values)) {
        $query = $wpdb->prepare($query, $values);
    }
    
    return $wpdb->get_results($query, ARRAY_A);
}

/**
 * Get transaction count
 */
function wpwa_stripe_get_transaction_count($status = null) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_transactions';
    
    if ($status) {
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE status = %s",
            $status
        ));
    }
    
    return $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
}

/**
 * Get total revenue
 */
function wpwa_stripe_get_total_revenue($status = 'succeeded') {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_transactions';
    
    return $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(amount) FROM `{$table}` WHERE status = %s",
        $status
    ));
}

/**
 * Update transaction
 */
function wpwa_stripe_update_transaction($transaction_id, $data) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_transactions';
    
    return $wpdb->update(
        $table,
        $data,
        array('id' => $transaction_id)
    );
}