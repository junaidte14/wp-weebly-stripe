<?php
/**
 * Stripe SDK Initialization
 */

if (!defined('ABSPATH')) exit;

/**
 * Load Stripe PHP library
 */
function wpwa_stripe_load_sdk() {
    static $loaded = false;
    
    if ($loaded) {
        return true;
    }
    
    $stripe_lib = WPWA_STRIPE_DIR . '/lib/stripe-php/init.php';
    
    if (!file_exists($stripe_lib)) {
        wpwa_stripe_log('Stripe SDK not found at: ' . $stripe_lib);
        return false;
    }
    
    require_once $stripe_lib;
    
    $loaded = true;
    return true;
}

/**
 * Initialize Stripe with API key
 */
function wpwa_stripe_init() {
    if (!wpwa_stripe_load_sdk()) {
        return false;
    }
    
    $api_key = wpwa_stripe_get_api_key();
    
    if (empty($api_key)) {
        wpwa_stripe_log('Stripe API key not configured');
        return false;
    }
    
    try {
        \Stripe\Stripe::setApiKey($api_key);
        \Stripe\Stripe::setAppInfo(
            'WP Weebly Apps - Stripe Edition',
            WPWA_STRIPE_VERSION,
            home_url()
        );
        return true;
    } catch (Exception $e) {
        wpwa_stripe_log('Stripe init error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if Stripe is properly configured
 */
function wpwa_stripe_is_configured() {
    $test_key = wpwa_stripe_get_option('test_secret_key');
    $live_key = wpwa_stripe_get_option('live_secret_key');
    
    if (wpwa_stripe_is_test_mode()) {
        return !empty($test_key);
    }
    
    return !empty($live_key);
}

/**
 * Get Stripe webhook secret
 */
function wpwa_stripe_get_webhook_secret() {
    if (wpwa_stripe_is_test_mode()) {
        return wpwa_stripe_get_option('test_webhook_secret');
    }
    return wpwa_stripe_get_option('live_webhook_secret');
}