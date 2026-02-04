<?php
/**
 * Helper Functions
 */

if (!defined('ABSPATH')) exit;

/**
 * Install database tables
 */
function wpwa_stripe_install_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Customers table
    $sql_customers = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}wpwa_stripe_customers` (
      `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      `stripe_customer_id` VARCHAR(255) NOT NULL,
      `weebly_user_id` VARCHAR(255) NOT NULL,
      `email` VARCHAR(255) NOT NULL,
      `name` VARCHAR(255) DEFAULT NULL,
      `metadata` LONGTEXT DEFAULT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `idx_stripe_customer` (`stripe_customer_id`),
      UNIQUE KEY `idx_weebly_user` (`weebly_user_id`),
      KEY `idx_email` (`email`)
    ) $charset_collate;";
    
    dbDelta($sql_customers);
    
    // Transactions table
    $sql_transactions = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}wpwa_stripe_transactions` (
      `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      `transaction_type` VARCHAR(20) NOT NULL,
      `stripe_payment_intent_id` VARCHAR(255) DEFAULT NULL,
      `stripe_invoice_id` VARCHAR(255) DEFAULT NULL,
      `stripe_subscription_id` VARCHAR(255) DEFAULT NULL,
      `stripe_customer_id` VARCHAR(255) NOT NULL,
      `weebly_user_id` VARCHAR(255) NOT NULL,
      `weebly_site_id` VARCHAR(255) DEFAULT NULL,
      `product_id` BIGINT(20) UNSIGNED NOT NULL,
      `amount` DECIMAL(10,2) NOT NULL,
      `currency` VARCHAR(3) NOT NULL DEFAULT 'USD',
      `status` VARCHAR(50) NOT NULL,
      `access_token` TEXT DEFAULT NULL,
      `final_url` TEXT DEFAULT NULL,
      `weebly_notified` TINYINT(1) NOT NULL DEFAULT 0,
      `metadata` LONGTEXT DEFAULT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      KEY `idx_payment_intent` (`stripe_payment_intent_id`),
      KEY `idx_invoice` (`stripe_invoice_id`),
      KEY `idx_subscription` (`stripe_subscription_id`),
      KEY `idx_customer` (`stripe_customer_id`),
      KEY `idx_weebly_user` (`weebly_user_id`),
      KEY `idx_product` (`product_id`),
      KEY `idx_status` (`status`),
      KEY `idx_created` (`created_at`)
    ) $charset_collate;";
    
    dbDelta($sql_transactions);
    
    // Subscriptions table
    $sql_subscriptions = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}wpwa_stripe_subscriptions` (
      `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      `stripe_subscription_id` VARCHAR(255) NOT NULL,
      `stripe_customer_id` VARCHAR(255) NOT NULL,
      `weebly_user_id` VARCHAR(255) NOT NULL,
      `weebly_site_id` VARCHAR(255) DEFAULT NULL,
      `product_id` BIGINT(20) UNSIGNED NOT NULL,
      `status` VARCHAR(50) NOT NULL,
      `current_period_start` DATETIME NOT NULL,
      `current_period_end` DATETIME NOT NULL,
      `cancel_at_period_end` TINYINT(1) NOT NULL DEFAULT 0,
      `access_token` TEXT DEFAULT NULL,
      `metadata` LONGTEXT DEFAULT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `idx_stripe_subscription` (`stripe_subscription_id`),
      KEY `idx_customer` (`stripe_customer_id`),
      KEY `idx_weebly_user` (`weebly_user_id`),
      KEY `idx_product` (`product_id`),
      KEY `idx_status` (`status`),
      KEY `idx_period_end` (`current_period_end`)
    ) $charset_collate;";
    
    dbDelta($sql_subscriptions);
    
    // Webhook log table
    $sql_webhook = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}wpwa_stripe_webhook_log` (
      `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
      `event_id` VARCHAR(255) NOT NULL,
      `event_type` VARCHAR(100) NOT NULL,
      `payload` LONGTEXT NOT NULL,
      `processed` TINYINT(1) NOT NULL DEFAULT 0,
      `error` TEXT DEFAULT NULL,
      `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `idx_event_id` (`event_id`),
      KEY `idx_event_type` (`event_type`),
      KEY `idx_processed` (`processed`)
    ) $charset_collate;";
    
    dbDelta($sql_webhook);
    
    update_option('wpwa_stripe_db_version', WPWA_STRIPE_VERSION);
}

/**
 * Get option with default
 */
function wpwa_stripe_get_option($key, $default = '') {
    return get_option('wpwa_stripe_' . $key, $default);
}

/**
 * Update option
 */
function wpwa_stripe_update_option($key, $value) {
    return update_option('wpwa_stripe_' . $key, $value);
}

/**
 * Check if Stripe is enabled
 */
function wpwa_stripe_is_enabled() {
    return wpwa_stripe_get_option('enabled') === 'yes';
}

/**
 * Check if in test mode
 */
function wpwa_stripe_is_test_mode() {
    return wpwa_stripe_get_option('test_mode') === 'yes';
}

/**
 * Get Stripe API key
 */
function wpwa_stripe_get_api_key() {
    if (wpwa_stripe_is_test_mode()) {
        return wpwa_stripe_get_option('test_secret_key');
    }
    return wpwa_stripe_get_option('live_secret_key');
}

/**
 * Get Stripe publishable key
 */
function wpwa_stripe_get_publishable_key() {
    if (wpwa_stripe_is_test_mode()) {
        return wpwa_stripe_get_option('test_publishable_key');
    }
    return wpwa_stripe_get_option('live_publishable_key');
}

/**
 * Log error
 */
function wpwa_stripe_log($message, $context = array()) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[WPWA Stripe] ' . $message . ' ' . print_r($context, true));
    }
}

/**
 * Format price
 */
function wpwa_stripe_format_price($amount, $currency = 'USD') {
    return '$' . number_format($amount, 2);
}

/**
 * Encrypt access token
 */
function wpwa_stripe_encrypt_token($plain) {
    if (empty($plain)) return '';
    
    $key = defined('AUTH_KEY') ? AUTH_KEY : wp_salt('auth');
    $iv = substr(hash('sha256', SECURE_AUTH_SALT), 0, 16);
    $cipher = 'aes-256-ctr';
    
    $encrypted = openssl_encrypt($plain, $cipher, $key, 0, $iv);
    return $encrypted ? base64_encode($encrypted) : '';
}

/**
 * Decrypt access token
 */
function wpwa_stripe_decrypt_token($encrypted) {
    if (empty($encrypted)) return '';
    
    $key = defined('AUTH_KEY') ? AUTH_KEY : wp_salt('auth');
    $iv = substr(hash('sha256', SECURE_AUTH_SALT), 0, 16);
    $cipher = 'aes-256-ctr';
    
    $decrypted = openssl_decrypt(base64_decode($encrypted), $cipher, $key, 0, $iv);
    return $decrypted ?: '';
}