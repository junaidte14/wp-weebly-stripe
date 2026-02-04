<?php
/**
 * Customer Management Functions
 */

if (!defined('ABSPATH')) exit;

/**
 * Get customer by Weebly user ID
 */
function wpwa_stripe_get_customer($weebly_user_id) {
    return wpwa_stripe_get_customer_by_weebly_id($weebly_user_id);
}

/**
 * Get all customers
 */
function wpwa_stripe_get_all_customers($limit = 100, $offset = 0) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_customers';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT %d OFFSET %d",
        $limit,
        $offset
    ), ARRAY_A);
}

/**
 * Get customer count
 */
function wpwa_stripe_get_customer_count() {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_customers';
    
    return $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
}

/**
 * Get customer lifetime value
 */
function wpwa_stripe_get_customer_lifetime_value($weebly_user_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_transactions';
    
    return $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(amount) FROM `{$table}` WHERE weebly_user_id = %s AND status = 'succeeded'",
        $weebly_user_id
    ));
}