<?php
/**
 * Plugin Name: WP Weebly Apps - Stripe Edition
 * Plugin URI: https://codoplex.com
 * Description: Sell Weebly apps with Stripe payments
 * Version: 1.0.0
 * Author: CODOPLEX
 * Author URI: https://codoplex.com
 * License: GPL-2.0+
 * Text Domain: wpwa-stripe
 * Requires at least: 5.8
 * Requires PHP: 7.3
 */

if (!defined('ABSPATH')) exit;

// ============================================
// CONSTANTS
// ============================================
define('WPWA_STRIPE_VERSION', '1.0.0');
define('WPWA_STRIPE_FILE', __FILE__);
define('WPWA_STRIPE_DIR', dirname(__FILE__));
define('WPWA_STRIPE_URL', plugin_dir_url(__FILE__));
define('WPWA_STRIPE_BASENAME', plugin_basename(__FILE__));

// ============================================
// INCLUDES
// ============================================

// Core functions
require_once WPWA_STRIPE_DIR . '/includes/helpers.php';
require_once WPWA_STRIPE_DIR . '/includes/products.php';
require_once WPWA_STRIPE_DIR . '/includes/customers.php';
require_once WPWA_STRIPE_DIR . '/includes/orders.php';
require_once WPWA_STRIPE_DIR . '/includes/subscriptions.php';

// Stripe integration
require_once WPWA_STRIPE_DIR . '/stripe/stripe-init.php';
require_once WPWA_STRIPE_DIR . '/stripe/stripe-products.php';
require_once WPWA_STRIPE_DIR . '/stripe/stripe-customers.php';
require_once WPWA_STRIPE_DIR . '/stripe/stripe-checkout.php';
require_once WPWA_STRIPE_DIR . '/stripe/stripe-subscriptions.php';
require_once WPWA_STRIPE_DIR . '/stripe/stripe-webhook.php';

// Admin
if (is_admin()) {
    require_once WPWA_STRIPE_DIR . '/admin/admin-enqueue.php';
    require_once WPWA_STRIPE_DIR . '/admin/settings-page.php';
    require_once WPWA_STRIPE_DIR . '/admin/products-page.php';
    require_once WPWA_STRIPE_DIR . '/admin/orders-page.php';
    require_once WPWA_STRIPE_DIR . '/admin/analytics-page.php';
    require_once WPWA_STRIPE_DIR . '/admin/whitelist-page.php';
    
    // NEW: Stripe-specific admin pages
    require_once WPWA_STRIPE_DIR . '/admin/stripe-transactions-page.php';
    require_once WPWA_STRIPE_DIR . '/admin/stripe-subscriptions-page.php';
    require_once WPWA_STRIPE_DIR . '/admin/stripe-customers-page.php';
}

// Emails
require_once WPWA_STRIPE_DIR . '/emails/invoice.php';
require_once WPWA_STRIPE_DIR . '/emails/receipt.php';
require_once WPWA_STRIPE_DIR . '/emails/renewal.php';
require_once WPWA_STRIPE_DIR . '/emails/welcome.php';

// Payment flow
require_once WPWA_STRIPE_DIR . '/payments/phase-one.php';

// Add after existing includes
require_once WPWA_STRIPE_DIR . '/includes/access-control.php';
require_once WPWA_STRIPE_DIR . '/includes/whitelist.php';

// ============================================
// ACTIVATION / DEACTIVATION
// ============================================

register_activation_hook(__FILE__, 'wpwa_stripe_activate');
register_deactivation_hook(__FILE__, 'wpwa_stripe_deactivate');

function wpwa_stripe_activate() {
    wpwa_stripe_install_tables();
    wpwa_stripe_register_product_post_type(); // Register before flushing
    flush_rewrite_rules();
}

function wpwa_stripe_deactivate() {
    flush_rewrite_rules();
}

// ============================================
// INITIALIZATION
// ============================================

add_action('init', 'wpwa_weebly_stripe_init');
// Handle routes
add_action('admin_menu', 'wpwa_stripe_admin_menu');

function wpwa_weebly_stripe_init() {
    // Register custom post type
    wpwa_stripe_register_product_post_type();
}

add_action( 'parse_request', function () {
    $path = trim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );

    // 1. Phase One & OAuth Callback
    if ( strpos( $path, 'wpwa_phase_one' ) === 0 ) {
        require_once WPWA_STRIPE_DIR . '/payments/phase-one.php';
        error_log("WPWA Debug: Entering handle_phase_one.");
        wpwa_stripe_handle_phase_one();
        exit;
    }

    // 2. Stripe Checkout Success/Cancel (Unified)
    if ( strpos( $path, 'wpwa-stripe-checkout' ) === 0 ) {
        require_once WPWA_STRIPE_DIR . '/stripe/stripe-checkout.php';
        wpwa_stripe_checkout_router();
        exit;
    }

    // 3. Stripe Webhook
    if ( strpos( $path, 'wpwa-stripe-webhook' ) === 0 ) {
        require_once WPWA_STRIPE_DIR . '/stripe/stripe-webhook.php';
        wpwa_stripe_handle_webhook();
        exit;
    }
}, 0 );

function wpwa_stripe_admin_menu() {
    // Main menu
    add_menu_page(
        __('WP Weebly Apps', 'wpwa-stripe'),
        __('Weebly Apps', 'wpwa-stripe'),
        'manage_options',
        'wpwa-stripe',
        'wpwa_stripe_render_analytics_page',
        'dashicons-cart',
        58
    );
    
    // Submenu pages
    add_submenu_page(
        'wpwa-stripe',
        __('Analytics', 'wpwa-stripe'),
        __('Analytics', 'wpwa-stripe'),
        'manage_options',
        'wpwa-stripe',
        'wpwa_stripe_render_analytics_page'
    );
    
    add_submenu_page(
        'wpwa-stripe',
        __('Products', 'wpwa-stripe'),
        __('Products', 'wpwa-stripe'),
        'manage_options',
        'edit.php?post_type=wpwa_product'
    );
    
    add_submenu_page(
        'wpwa-stripe',
        __('Orders', 'wpwa-stripe'),
        __('Orders (Unified)', 'wpwa-stripe'),
        'manage_options',
        'wpwa-stripe-orders',
        'wpwa_stripe_render_orders_page'
    );
    
    // NEW: Stripe-specific submenu pages
    add_submenu_page(
        'wpwa-stripe',
        __('Stripe Transactions', 'wpwa-stripe'),
        __('Stripe Transactions', 'wpwa-stripe'),
        'manage_options',
        'wpwa-stripe-transactions',
        'wpwa_stripe_render_transactions_page'
    );
    
    add_submenu_page(
        'wpwa-stripe',
        __('Stripe Subscriptions', 'wpwa-stripe'),
        __('Stripe Subscriptions', 'wpwa-stripe'),
        'manage_options',
        'wpwa-stripe-subscriptions',
        'wpwa_stripe_render_subscriptions_page'
    );
    
    add_submenu_page(
        'wpwa-stripe',
        __('Stripe Customers', 'wpwa-stripe'),
        __('Stripe Customers', 'wpwa-stripe'),
        'manage_options',
        'wpwa-stripe-customers',
        'wpwa_stripe_render_customers_page'
    );

    add_submenu_page(
        'wpwa-stripe',
        __('Whitelist', 'wpwa-stripe'),
        __('Whitelist', 'wpwa-stripe'),
        'manage_options',
        'wpwa-stripe-whitelist',
        'wpwa_stripe_render_whitelist_page'
    );
    
    add_submenu_page(
        'wpwa-stripe',
        __('Settings', 'wpwa-stripe'),
        __('Settings', 'wpwa-stripe'),
        'manage_options',
        'wpwa-stripe-settings',
        'wpwa_stripe_render_settings_page'
    );
}