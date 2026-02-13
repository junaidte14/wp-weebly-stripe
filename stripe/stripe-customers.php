<?php
/**
 * Stripe Customer Management
 */

if (!defined('ABSPATH')) exit;

/**
 * Get or create Stripe customer
 */
function wpwa_stripe_get_or_create_customer($weebly_user_id, $email, $name = '') {
    global $wpdb;
    
    if (!wpwa_stripe_init()) {
        return null;
    }
    
    $table = $wpdb->prefix . 'wpwa_stripe_customers';
    
    // Check if customer exists in our database
    $customer = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE weebly_user_id = %s",
        $weebly_user_id
    ), ARRAY_A);
    
    if ($customer) {
        return $customer['stripe_customer_id'];
    }
    
    // Create new Stripe customer
    try {
        $stripe_customer = \Stripe\Customer::create(array(
            'email' => $email,
            'name' => $name,
            'metadata' => array(
                'weebly_user_id' => $weebly_user_id,
                'source' => 'wpwa_stripe_plugin'
            )
        ));
        
        // Store in database
        $wpdb->insert($table, array(
            'stripe_customer_id' => $stripe_customer->id,
            'weebly_user_id' => $weebly_user_id,
            'email' => $email,
            'name' => $name,
            'metadata' => json_encode(array(
                'created_via' => 'wpwa_stripe_plugin',
                'created_at' => current_time('mysql')
            ))
        ));
        
        return $stripe_customer->id;
        
    } catch (Exception $e) {
        wpwa_stripe_log('Customer creation error: ' . $e->getMessage(), array(
            'weebly_user_id' => $weebly_user_id,
            'email' => $email
        ));
        return null;
    }
}

/**
 * Get customer by Weebly user ID
 */
function wpwa_stripe_get_customer_by_weebly_id($weebly_user_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_customers';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE weebly_user_id = %s",
        $weebly_user_id
    ), ARRAY_A);
}

/**
 * Get customer by Stripe customer ID
 */
function wpwa_stripe_get_customer_by_stripe_id($stripe_customer_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_customers';
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE stripe_customer_id = %s",
        $stripe_customer_id
    ), ARRAY_A);
}

/**
 * Update customer email
 */
function wpwa_stripe_update_customer_email($weebly_user_id, $new_email) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_customers';
    $customer = wpwa_stripe_get_customer_by_weebly_id($weebly_user_id);
    
    if (!$customer) {
        return false;
    }
    
    // Update in Stripe
    if (wpwa_stripe_init()) {
        try {
            \Stripe\Customer::update($customer['stripe_customer_id'], array(
                'email' => $new_email
            ));
        } catch (Exception $e) {
            wpwa_stripe_log('Customer email update error: ' . $e->getMessage());
        }
    }
    
    // Update in database
    return $wpdb->update(
        $table,
        array('email' => $new_email),
        array('weebly_user_id' => $weebly_user_id)
    );
}

/**
 * Sync a Stripe customer into local DB.
 * Used by webhooks since Weebly never provides email/name.
 * Safe to call multiple times - skips if customer already exists.
 */
function wpwa_stripe_sync_customer_from_stripe( $stripe_customer_id, $weebly_user_id = null ) {
    global $wpdb;

    if ( empty( $stripe_customer_id ) ) {
        return null;
    }

    $table = $wpdb->prefix . 'wpwa_stripe_customers';

    // Already in DB? Nothing to do.
    $existing = wpwa_stripe_get_customer_by_stripe_id( $stripe_customer_id );
    if ( $existing ) {
        return $existing['stripe_customer_id'];
    }

    if ( ! wpwa_stripe_init() ) {
        return null;
    }

    try {
        $stripe_customer = \Stripe\Customer::retrieve( $stripe_customer_id );

        $email = $stripe_customer->email ?? '';
        $name  = $stripe_customer->name  ?? '';

        // Fall back to metadata if weebly_user_id wasn't passed in
        if ( empty( $weebly_user_id ) ) {
            $weebly_user_id = $stripe_customer->metadata['weebly_user_id'] ?? null;
        }

        if ( empty( $weebly_user_id ) ) {
            wpwa_stripe_log( 'Cannot sync customer: weebly_user_id missing', [
                'stripe_customer_id' => $stripe_customer_id,
            ] );
            return null;
        }

        $wpdb->insert( $table, [
            'stripe_customer_id' => $stripe_customer_id,
            'weebly_user_id'     => $weebly_user_id,
            'email'              => $email,
            'name'               => $name,
            'metadata'           => json_encode( [
                'synced_via' => 'webhook',
                'synced_at'  => current_time( 'mysql' ),
            ] ),
        ] );

        wpwa_stripe_log( 'Customer synced from Stripe', [
            'stripe_customer_id' => $stripe_customer_id,
            'weebly_user_id'     => $weebly_user_id,
            'email'              => $email,
        ] );

        return $stripe_customer_id;

    } catch ( Exception $e ) {
        wpwa_stripe_log( 'Customer sync error: ' . $e->getMessage(), [
            'stripe_customer_id' => $stripe_customer_id,
        ] );
        return null;
    }
}