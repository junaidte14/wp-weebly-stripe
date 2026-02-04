<?php
/**
 * Admin Scripts & Styles
 */

if (!defined('ABSPATH')) exit;

/**
 * Enqueue admin assets
 */
add_action('admin_enqueue_scripts', 'wpwa_stripe_enqueue_admin_assets');
function wpwa_stripe_enqueue_admin_assets($hook) {
    // Only load on our plugin pages
    $our_pages = array(
        'toplevel_page_wpwa-stripe',
        'wp-weebly-stripe_page_wpwa-stripe-products',
        'wp-weebly-stripe_page_wpwa-stripe-orders',
        'wp-weebly-stripe_page_wpwa-stripe-settings'
    );
    
    if (!in_array($hook, $our_pages) && get_post_type() !== 'wpwa_product') {
        return;
    }
    
    // Enqueue admin CSS
    wp_enqueue_style(
        'wpwa-stripe-admin',
        WPWA_STRIPE_URL . 'admin/css/admin.css',
        array(),
        WPWA_STRIPE_VERSION
    );
    
    // Enqueue admin JS
    wp_enqueue_script(
        'wpwa-stripe-admin',
        WPWA_STRIPE_URL . 'admin/js/admin.js',
        array('jquery'),
        WPWA_STRIPE_VERSION,
        true
    );
    
    // Localize script
    wp_localize_script('wpwa-stripe-admin', 'wpwaStripe', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wpwa_stripe_admin'),
        'confirmDelete' => __('Are you sure you want to delete this?', 'wpwa-stripe'),
        'confirmSync' => __('Sync this product to Stripe?', 'wpwa-stripe')
    ));
}