<?php
/**
 * Order/Transaction Management
 */

if (!defined('ABSPATH')) exit;

/**
 * Create or Update a Transaction Record
 * * Handles out-of-order webhooks by checking for existing IDs 
 * and allowing null payment intents.
 */
function wpwa_stripe_create_transaction($data) {
    global $wpdb;
    $table = $wpdb->prefix . 'wpwa_stripe_transactions';

    // 1. Identify the Unique Key
    // In Subscriptions, the Invoice ID is the most reliable unique key for a payment.
    $invoice_id = $data['stripe_invoice_id'] ?? null;
    $subscription_id = $data['stripe_subscription_id'] ?? null;
    $payment_intent = $data['stripe_payment_intent_id'] ?? null;

    // 2. Check for Duplicates
    // We check if this payment already exists via Invoice ID or Payment Intent
    $existing_id = null;
    if ($invoice_id) {
        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE stripe_invoice_id = %s", 
            $invoice_id
        ));
    } elseif ($payment_intent) {
        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE stripe_payment_intent_id = %s", 
            $payment_intent
        ));
    }

    // 3. Prepare Data for Database
    $insert_data = array(
        'transaction_type'         => $data['transaction_type'],
        'stripe_payment_intent_id' => $payment_intent ?: null, // Force NULL if empty
        'stripe_subscription_id'   => $subscription_id,
        'stripe_invoice_id'        => $invoice_id,
        'stripe_customer_id'       => $data['stripe_customer_id'],
        'weebly_user_id'           => $data['weebly_user_id'],
        'weebly_site_id'           => $data['weebly_site_id'] ?? null,
        'product_id'               => $data['product_id'],
        'amount'                   => $data['amount'],
        'currency'                 => strtoupper($data['currency']),
        'status'                   => $data['status'] ?? 'succeeded',
        'access_token'             => $data['access_token'] ?? null,
        'metadata'                 => $data['metadata'] ?? null
    );

    // 4. Update or Insert
    if ($existing_id) {
        // If it exists, update it (useful if metadata arrived in a later webhook)
        $wpdb->update($table, $insert_data, array('id' => $existing_id));
        return $existing_id;
    } else {
        // Insert new record
        $insert_data['created_at'] = current_time('mysql');
        $insert_data['weebly_notified'] = 0;
        $inserted = $wpdb->insert($table, $insert_data);
        return $inserted ? $wpdb->insert_id : false;
    }
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
        "SELECT * FROM `{$table}` 
         WHERE stripe_subscription_id = %s 
         ORDER BY created_at DESC, id DESC 
         LIMIT 1",
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