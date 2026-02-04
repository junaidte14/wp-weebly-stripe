<?php
/**
 * Stripe Checkout Session Creation
 */

if (!defined('ABSPATH')) exit;

/**
 * Create Stripe checkout session
 */
function wpwa_stripe_create_checkout_session($args) {
    if (!wpwa_stripe_init()) {
        return array('success' => false, 'message' => 'Stripe not initialized');
    }
    
    $required = array('product_id', 'weebly_user_id', 'access_token', 'final_url');
    foreach ($required as $key) {
        if (empty($args[$key])) {
            return array('success' => false, 'message' => "Missing required argument: {$key}");
        }
    }
    
    $product = wpwa_stripe_get_or_sync_product($args['product_id']);
    if (!$product) {
        return array('success' => false, 'message' => 'Product not found or sync failed');
    }
    
    $email = !empty($args['email']) ? $args['email'] : '';
    $name  = !empty($args['name']) ? $args['name'] : '';
    $is_recurring = !empty($product['is_recurring']);

    try {
        $base_url = 'https://' . $_SERVER['HTTP_HOST'] . '/wpwa-stripe-checkout/';
        
        // 1. Prepare Line Items
        $line_items = array(array(
            'price' => $product['stripe_price_id'],
            'quantity' => 1
        ));

        // 2. Marca da Bollo logic (using 77.47 threshold)
        // Ensure $product['amount'] is in the same unit as the threshold (e.g., Euros)
        $threshold = 77.47;
        $price_amount = $product['amount'] ?? 0; 
        
        if ($price_amount > $threshold) {
            $line_items[] = array(
                'price' => 'price_1Sx8NKJD53wI1t0lSk52gfu0',
                'quantity' => 1
            );
        }

        $session_data = array(
            'mode' => $is_recurring ? 'subscription' : 'payment',
            'line_items' => $line_items,
            'allow_promotion_codes' => true, 
            'billing_address_collection' => 'required', 
            'tax_id_collection' => [
                'enabled' => true, 
            ],
            'success_url' => add_query_arg(['action' => 'success', 'session_id' => '{CHECKOUT_SESSION_ID}'], $base_url),
            'cancel_url'  => add_query_arg(['action' => 'cancel'], $base_url),
            'metadata' => array(
                'weebly_user_id' => $args['weebly_user_id'],
                'weebly_site_id' => $args['weebly_site_id'] ?? '',
                'product_id'     => $args['product_id'],
                'access_token'   => wpwa_stripe_encrypt_token($args['access_token']),
                'final_url'      => $args['final_url']
            )
        );

        // --- CUSTOMER & EMAIL LOGIC ---
        if (!empty($email)) {
            $stripe_customer_id = wpwa_stripe_get_or_create_customer(
                $args['weebly_user_id'],
                $email,
                $name
            );
            if ($stripe_customer_id) {
                $session_data['customer'] = $stripe_customer_id;
                // 'auto' syncs the address provided during checkout to the Stripe Customer object
                $session_data['customer_update'] = [
                    'address' => 'auto',
                    'name'    => 'auto'
                ];
            }
        } else {
            if (!$is_recurring) {
                $session_data['customer_creation'] = 'always';
            }
        }

        if ($is_recurring) {
            $session_data['subscription_data'] = array(
                'metadata' => array(
                    'weebly_user_id' => $args['weebly_user_id'],
                    'weebly_site_id' => $args['weebly_site_id'] ?? '',
                    'product_id'     => $args['product_id'],
                    'access_token'   => wpwa_stripe_encrypt_token($args['access_token'])
                )
            );
        }
        
        $session = \Stripe\Checkout\Session::create($session_data);
        
        return array(
            'success'    => true,
            'session_id' => $session->id,
            'url'        => $session->url 
        );
        
    } catch (Exception $e) {
        error_log('WPWA Stripe Error: ' . $e->getMessage());
        return array('success' => false, 'message' => $e->getMessage());
    }
}

/**
 * Handle checkout success redirect
 */
function wpwa_stripe_handle_checkout_success() {
    if (!isset($_GET['session_id'])) {
        wp_die('Invalid session');
    }
    
    if (!wpwa_stripe_init()) {
        wp_die('Stripe not initialized');
    }
    
    try {
        $session_id = sanitize_text_field($_GET['session_id']);
        $session = \Stripe\Checkout\Session::retrieve($session_id);
        
        if ($session->payment_status === 'paid') {
            // Get final URL from metadata
            $final_url = $session->metadata->final_url ?? home_url();
            
            // Redirect to Weebly finish URL
            wp_redirect($final_url);
            exit;
        } else {
            wp_die('Payment not completed');
        }
        
    } catch (Exception $e) {
        wpwa_stripe_log('Checkout success error: ' . $e->getMessage());
        wp_die('Error processing payment: ' . $e->getMessage());
    }
}

/**
 * Handle checkout cancel
 */
function wpwa_stripe_handle_checkout_cancel() {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Payment Cancelled</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
            .container { max-width: 600px; margin: 0 auto; }
            h1 { color: #e74c3c; }
            .button { display: inline-block; padding: 12px 24px; background: #3498db; 
                     color: #fff; text-decoration: none; border-radius: 4px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Payment Cancelled</h1>
            <p>Your payment was cancelled. You can try again or contact support if you need help.</p>
            <a href="<?php echo home_url(); ?>" class="button">Return Home</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Route handler for checkout endpoints
 */
add_action('template_redirect', 'wpwa_stripe_checkout_router');
function wpwa_stripe_checkout_router() {
    // Get the action from the URL ?action=...
    $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
    error_log("WPWA Debug: Checkout Router hit. Action: " . $action);
    if ( $action === 'success' ) {
        wpwa_stripe_handle_checkout_success();
    } elseif ( $action === 'cancel' ) {
        wpwa_stripe_handle_checkout_cancel();
    } else {
        error_log("WPWA Error: No valid action param found. Path was: " . $_SERVER['REQUEST_URI']);
        //wp_die('Invalid checkout action. Please go back to Weebly.');
    }
}