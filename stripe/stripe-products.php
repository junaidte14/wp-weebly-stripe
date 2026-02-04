<?php
/**
 * Stripe Product Sync Functions
 */

if (!defined('ABSPATH')) exit;

/**
 * Sync product to Stripe (create or update)
 */
function wpwa_stripe_sync_product_to_stripe($product_id) {
    if (!wpwa_stripe_init()) {
        return array('success' => false, 'message' => 'Stripe not initialized');
    }
    
    $product = wpwa_stripe_get_product($product_id);
    if (!$product) {
        return array('success' => false, 'message' => 'Product not found');
    }
    
    try {
        $stripe_product_id = $product['stripe_product_id'];
        
        // Create or update Stripe product
        if ($stripe_product_id) {
            $stripe_product = wpwa_stripe_update_stripe_product($product, $stripe_product_id);
        } else {
            $stripe_product = wpwa_stripe_create_stripe_product($product);
            update_post_meta($product_id, '_wpwa_stripe_product_id', $stripe_product->id);
        }
        
        // Create or update price
        $price = wpwa_stripe_sync_product_price($product, $stripe_product->id);
        update_post_meta($product_id, '_wpwa_stripe_price_id', $price->id);
        
        // Clear resync flag
        delete_post_meta($product_id, '_wpwa_stripe_needs_resync');
        
        return array(
            'success' => true,
            'product_id' => $stripe_product->id,
            'price_id' => $price->id
        );
        
    } catch (Exception $e) {
        wpwa_stripe_log('Product sync error: ' . $e->getMessage(), array('product_id' => $product_id));
        return array('success' => false, 'message' => $e->getMessage());
    }
}

/**
 * Create Stripe product
 */
function wpwa_stripe_create_stripe_product($product) {
    return \Stripe\Product::create(array(
        'name' => $product['name'],
        'description' => wp_strip_all_tags($product['description']),
        'metadata' => array(
            'wp_product_id' => $product['id'],
            'source' => 'wpwa_stripe_plugin'
        )
    ));
}

/**
 * Update Stripe product
 */
function wpwa_stripe_update_stripe_product($product, $stripe_product_id) {
    return \Stripe\Product::update($stripe_product_id, array(
        'name' => $product['name'],
        'description' => wp_strip_all_tags($product['description']),
        'metadata' => array(
            'wp_product_id' => $product['id'],
            'source' => 'wpwa_stripe_plugin'
        )
    ));
}

/**
 * Sync product price
 */
function wpwa_stripe_sync_product_price($product, $stripe_product_id) {
    $existing_price_id = $product['stripe_price_id'];
    
    // Convert current WP price to cents for comparison
    $unit_amount = intval($product['price'] * 100);
    
    // 1. Check if we can reuse the existing price
    if ($existing_price_id) {
        try {
            $existing_price = \Stripe\Price::retrieve($existing_price_id);
            
            // Check if amount and currency match
            $matches = ($existing_price->unit_amount === $unit_amount && $existing_price->currency === 'usd');
            
            // Check if recurring settings match
            if ($product['is_recurring']) {
                if (!$existing_price->recurring || 
                    $existing_price->recurring->interval !== $product['cycle_unit'] || 
                    $existing_price->recurring->interval_count !== intval($product['cycle_length'])) {
                    $matches = false;
                }
            } elseif ($existing_price->type === 'recurring') {
                // Was recurring, now it's a one-time payment
                $matches = false;
            }

            // If everything matches, return the existing price object
            if ($matches && $existing_price->active) {
                return $existing_price;
            }
        } catch (Exception $e) {
            // Price might have been deleted in Stripe dashboard, proceed to create new one
        }
    }

    // 2. If we reached here, the price changed or doesn't exist. 
    // Prepare data for NEW price
    $price_data = array(
        'product' => $stripe_product_id,
        'unit_amount' => $unit_amount,
        'currency' => 'usd',
        'metadata' => array(
            'wp_product_id' => $product['id']
        )
    );
    
    if ($product['is_recurring']) {
        $price_data['recurring'] = array(
            'interval' => $product['cycle_unit'],
            'interval_count' => $product['cycle_length']
        );
    }
    
    // Archive the truly "old" price only now
    if ($existing_price_id) {
        try {
            \Stripe\Price::update($existing_price_id, array('active' => false));
        } catch (Exception $e) {}
    }
    
    // Create new price
    return \Stripe\Price::create($price_data);
}

/**
 * Get or sync product to Stripe (lazy loading)
 */
function wpwa_stripe_get_or_sync_product($product_id) {
    $product = wpwa_stripe_get_product($product_id);
    
    if (!$product) {
        return null;
    }
    
    // Check if needs sync
    $needs_resync = get_post_meta($product_id, '_wpwa_stripe_needs_resync', true);
    
    if (!$product['stripe_product_id'] || $needs_resync) {
        $result = wpwa_stripe_sync_product_to_stripe($product_id);
        
        if (!$result['success']) {
            wpwa_stripe_log('Auto-sync failed for product: ' . $product_id);
            return null;
        }
        
        // Refresh product data
        $product = wpwa_stripe_get_product($product_id);
    }
    
    return $product;
}

/**
 * AJAX handler for manual sync
 */
add_action('wp_ajax_wpwa_sync_product_to_stripe', 'wpwa_stripe_ajax_sync_product');
function wpwa_stripe_ajax_sync_product() {
    check_ajax_referer('wpwa_sync_stripe', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }
    
    $product_id = absint($_POST['product_id']);
    $result = wpwa_stripe_sync_product_to_stripe($product_id);
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}