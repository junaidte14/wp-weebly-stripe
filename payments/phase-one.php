<?php
/**
 * Weebly OAuth Flow → Stripe Checkout
 * Replaces old WooCommerce checkout flow
 */

if (!defined('ABSPATH')) exit;

// Load dependencies safely
if ( ! class_exists( 'HMAC' ) ) {
    require_once WPWA_STRIPE_DIR . '/lib/Util/HMAC.php';
}

if ( ! class_exists( 'WeeblyClient' ) ) {
    require_once WPWA_STRIPE_DIR . '/lib/Weebly/WeeblyClient.php';
}

function wpwa_stripe_handle_phase_one() {
    // --- 1. LEGACY FIXER ---
    if ( strpos( $_SERVER['QUERY_STRING'], '?' ) !== false ) {
        error_log("WPWA Debug: Detected double '?' symbols. Fixing URL.");
        $fixed = str_replace( '?', '&', $_SERVER['QUERY_STRING'] );
        $redirect_to = strtok( $_SERVER['REQUEST_URI'], '?' ) . '?' . $fixed;
        error_log("WPWA Debug: Redirecting to cleaned URL: " . $redirect_to);
        wp_redirect( $redirect_to );
        exit;
    }

    // --- 2. DETERMINE PRODUCT ID ---
    $product_id = 0;
    if ( isset($_GET['state']) ) {
        $state_data = json_decode(base64_decode(rawurldecode($_GET['state'])), true);
        $product_id = isset($state_data['pr_id']) ? absint($state_data['pr_id']) : 0;
        error_log("WPWA Debug: Product ID extracted from State: " . $product_id);
    } 
    
    if ( !$product_id && isset($_GET['pr_id']) ) {
        $product_id = absint($_GET['pr_id']);
        error_log("WPWA Debug: Product ID extracted from URL: " . $product_id);
    }

    if (!$product_id) {
        error_log("WPWA Error: No Product ID found in request.");
        wp_die('Missing product ID');
    }

    $product = wpwa_stripe_get_product($product_id);
    if (!$product) {
        error_log("WPWA Error: Product lookup failed for ID: " . $product_id);
        wp_die('Invalid product');
    }

    // --- 3. ROUTE TO CALLBACK OR INITIATE ---
    if (isset($_GET['authorization_code'])) {
        error_log("WPWA Debug: authorization_code detected. Proceeding to Phase 2 (Callback).");
        wpwa_stripe_handle_oauth_callback($product);
    } else {
        error_log("WPWA Debug: No auth code. Proceeding to Phase 1 (Initiate OAuth).");
        wpwa_stripe_initiate_oauth($product);
    }
}

/**
 * Initiate OAuth Flow
 */
function wpwa_stripe_initiate_oauth($product) {
    $cid   = $product['client_id'];
    $csec  = $product['client_secret'];

    // Verify HMAC
    $hmac_params = [
        'user_id' => $_GET['user_id'] ?? '',
        'timestamp' => $_GET['timestamp'] ?? ''
    ];
    if (isset($_GET['site_id'])) $hmac_params['site_id'] = $_GET['site_id'];

    $hmac_valid = HMAC::isHmacValid(http_build_query($hmac_params), $csec, $_GET['hmac'] ?? '');
    
    if (!$hmac_valid) {
        error_log("WPWA Error: HMAC Validation failed for User: " . ($_GET['user_id'] ?? 'unknown'));
        wp_die('HMAC verification failed');
    }

    // ENCODE STATE
    $state = rawurlencode(base64_encode(json_encode([
        'pr_id' => $product['pr_id'],
        'csrf'  => wp_create_nonce('wpwa_weebly_oauth'),
    ])));

    // CLEAN REDIRECT URI
    $redirect_uri = 'https://' . $_SERVER['HTTP_HOST'] . '/wpwa_phase_one/?pr_id=' . $product['pr_id'];

    $auth_url = 'https://www.weebly.com/app-center/oauth/authorize?' . http_build_query( [
        'client_id'    => $cid,
        'user_id'      => $_GET['user_id'],
        'site_id'      => $_GET['site_id'] ?? '',
        'redirect_uri' => $redirect_uri,
        'state'        => $state,
    ], '', '&', PHP_QUERY_RFC3986 );

    error_log("WPWA Debug: Redirecting to Weebly Auth: " . $auth_url);
    wp_redirect($auth_url);
    exit;
}

/**
 * Handle OAuth callback and redirect to Stripe Checkout
 */
function wpwa_stripe_handle_oauth_callback_old($product) {
    error_log("WPWA Debug: [Phase 2] Callback reached for Product ID: " . $product['id']);

    $client_id = $product['client_id'];
    $client_secret = $product['client_secret'];
    
    $authorization_code = sanitize_text_field($_GET['authorization_code']);
    $user_id = sanitize_text_field($_GET['user_id'] ?? '');
    $site_id = sanitize_text_field($_GET['site_id'] ?? '');
    $callback_url = isset($_GET['callback_url']) ? esc_url_raw($_GET['callback_url']) : '';
    
    error_log("WPWA Debug: [Phase 2] Context - User: $user_id, Site: $site_id, Code: " . substr($authorization_code, 0, 5) . "...");

    // Exchange code for access token
    $weebly_client = new WeeblyClient(
        $client_id,
        $client_secret,
        $user_id,
        $site_id
    );
    
    $token_response = $weebly_client->getAccessToken($authorization_code, $callback_url);
    
    if (empty($token_response->access_token)) {
        error_log("WPWA Error: [Phase 2] Token exchange failed. Response: " . print_r($token_response, true));
        wp_die('Failed to obtain access token from Weebly');
    }
    
    $access_token = $token_response->access_token;
    $final_url = $token_response->callback_url ?? $callback_url;
    
    // Get user info from Weebly API
    $user_info = wpwa_stripe_get_weebly_user_info($access_token, $user_id);
    $email = $user_info['email'] ?? '';
    $name = $user_info['name'] ?? '';
    
    // Check if user already has active access
    if (wpwa_stripe_user_has_active_access($user_id, $product['id'])) {
        
        add_filter('allowed_redirect_hosts', function($hosts) {
            $hosts[] = 'www.weebly.com';
            return $hosts;
        });
        
        wp_safe_redirect($final_url);
        exit;
    }
    
    // Create Stripe Checkout Session
    $checkout_args = array(
        'product_id'     => $product['id'],
        'weebly_user_id' => $user_id,
        'weebly_site_id' => $site_id,
        'access_token'   => $access_token,
        'final_url'      => $final_url,
        'pr_id'          => $product['pr_id'] ? $product['pr_id'] : $product['id']
    );
    
    $session_result = wpwa_stripe_create_checkout_session($checkout_args);

    // Check if the result is an array and specifically extract the 'url'
    if (is_array($session_result) && !empty($session_result['url'])) {
        $checkout_url = $session_result['url'];
    } else {
        wp_die('Failed to create checkout session. Error: ' . ($session_result['message'] ?? 'Unknown Error'));
    }

    wp_redirect($checkout_url);
    exit;

}

/**
 * Handle OAuth callback and redirect to Stripe Checkout
 */
function wpwa_stripe_handle_oauth_callback($product) {
    $client_id = $product['client_id'];
    $client_secret = $product['client_secret'];
    
    $authorization_code = sanitize_text_field($_GET['authorization_code']);
    $user_id = sanitize_text_field($_GET['user_id'] ?? '');
    $site_id = sanitize_text_field($_GET['site_id'] ?? '');
    $callback_url = isset($_GET['callback_url']) ? esc_url_raw($_GET['callback_url']) : '';

    // Exchange code for access token
    $weebly_client = new WeeblyClient(
        $client_id,
        $client_secret,
        $user_id,
        $site_id
    );
    
    $token_response = $weebly_client->getAccessToken($authorization_code, $callback_url);
    
    if (empty($token_response->access_token)) {
        wp_die('Failed to obtain access token from Weebly');
    }
    
    $access_token = $token_response->access_token;
    $final_url = $token_response->callback_url ?? $callback_url;

    // Get user info from Weebly API (optional - to get email/name)
    $user_info = wpwa_stripe_get_weebly_user_info($access_token, $user_id);
    
    $email = $user_info['email'] ?? '';
    $name = $user_info['name'] ?? '';

    error_log("WPWA Debug: [Phase 2] Full User Info: " . json_encode($user_info));
    
    // ========================================
    // UNIVERSAL ACCESS CHECK
    // Checks: Whitelist, Stripe, Legacy WC
    // ========================================
    $access_check = wpwa_user_has_access($user_id, $product['id'], $site_id);
    
    if ($access_check['has_access']) {
        wpwa_stripe_log('Access granted - skipping payment', array(
            'user_id' => $user_id,
            'product_id' => $product['id'],
            'source' => $access_check['source'],
            'details' => $access_check['details']
        ));
        
        // Update access token (important for legacy customers)
        wpwa_update_user_access_token(
            $user_id, 
            $site_id,
            $product['id'], 
            $access_token, 
            $access_check['source']
        );
        
        // Log access grant for analytics
        wpwa_log_access_grant($user_id, $site_id, $product['id'], $access_check['source']);
        
        // Redirect directly to Weebly finish URL
        add_filter('allowed_redirect_hosts', function($hosts) {
            $hosts[] = 'www.weebly.com';
            return $hosts;
        });
        
        wp_safe_redirect($final_url);
        exit;
    }
    
    // No existing access - proceed to Stripe Checkout
    wpwa_stripe_log('No access found - redirecting to checkout', array(
        'user_id' => $user_id,
        'product_id' => $product['id']
    ));
    
    // Create Stripe Checkout Session
    $checkout_args = array(
        'product_id' => $product['id'],
        'weebly_user_id' => $user_id,
        'weebly_site_id' => $site_id,
        'access_token' => $access_token,
        'final_url' => $final_url,
        'pr_id'          => $product['pr_id'] ? $product['pr_id'] : $product['id']
    );

    $session_result = wpwa_stripe_create_checkout_session($checkout_args);
    
    if (is_array($session_result) && !empty($session_result['url'])) {
        $checkout_url = $session_result['url'];
    } else {
        wp_die('Failed to create checkout session. Error: ' . ($session_result['message'] ?? 'Unknown Error'));
    }
    
    //wp_redirect($checkout_url);
    wpwa_stripe_show_product_card($product, $checkout_url, $user_id, $site_id, $email);
    exit;
}

/**
 * Displays a professional product card with integrated site identity and footer
 */
function wpwa_stripe_show_product_card($product, $checkout_url, $user_id, $site_id, $email) {
    get_header();
    // Configuration
    $upsell_url = 'https://codoplex.com/codoplex-weebly-apps-subscription/';
    $site_title = get_bloginfo('name');
    $logo_url   = 'https://codoplex.com/wp-content/uploads/2022/05/cropped-Logo-Icon-300x300codoplex-300x300-1-100x100.png'; 
    // Price Formatting from your $product array
    $price_val = $product['price'] ?? 0.00;
    $display_price = number_format($price_val, 2);
    
    // Determine billing text (e.g., "per year" or "one-time")
    $billing_text = 'one-time payment';
    if (!empty($product['is_recurring'])) {
        $unit = $product['cycle_unit'] ?? 'year';
        $billing_text = 'per ' . $unit;
    }
    ?>
    
    <style>
        .wpwa-checkout-page { 
            padding: 0 20px; 
            background: #f8fafc; 
            display: flex; 
            flex-direction: column;
            align-items: center;
            min-height: 70vh;
        }
        .checkout-container { 
            background: white; 
            border-radius: 16px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.1); 
            max-width: 500px; 
            width: 100%; 
            border: 1px solid #e2e8f0;
            overflow: hidden; /* Keeps header/footer rounded */
        }

        /* Integrated Header */
        .card-header {
            padding: 20px;
            border-bottom: 1px solid #f1f5f9;
            text-align: center;
        }
        .card-header img { max-height: 50px; margin-bottom: 10px; }
        .card-header h2 { margin: 0; font-size: 18px; color: #64748b; font-weight: 500; text-transform: uppercase; letter-spacing: 1px; }

        /* Main Content Body */
        .card-body { padding: 20px; text-align: center; }
        
        .product-badge { background: #e1f5fe; color: #0288d1; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; margin-bottom: 15px; display: inline-block; }
        .card-body h1 { margin: 10px 0; font-size: 28px; color: #1a1a1a; line-height: 1.2; }
        .price-tag { font-size: 24px; font-weight: 700; color: #10b981; margin: 10px 0 25px 0; }
        
        /* Price Display */
        .price-container { margin: 15px 0 25px 0; }
        .price-amount { font-size: 48px; font-weight: 800; color: #10b981; display: block; line-height: 1; }
        .price-currency { font-size: 20px; vertical-align: top; margin-right: 2px; position: relative; top: 8px; }
        .price-period { font-size: 14px; color: #94a3b8; font-weight: 500; margin-top: 5px; display: block; }
        
        .details-box { background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 8px; padding: 15px; margin-bottom: 25px; text-align: left; }
        .details-row { display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 13px; }
        .details-row label { color: #94a3b8; }
        .details-row span { color: #1e293b; font-family: monospace; font-weight: 600; }

        .btn-stripe { display: block; background: #6366f1; color: white !important; text-decoration: none; padding: 16px; border-radius: 8px; font-weight: 700; font-size: 18px; transition: all 0.2s; box-shadow: 0 4px 6px rgba(99, 102, 241, 0.2); }
        .btn-stripe:hover { background: #4f46e5; transform: translateY(-1px); }
        
        .btn-upsell { display: block; background: #fff; color: #6366f1 !important; border: 2px solid #6366f1; text-decoration: none; padding: 12px; border-radius: 8px; font-weight: 600; transition: all 0.2s; margin-top: 20px; font-size: 14px; }
        .btn-upsell:hover { background: #f5f5ff; }
        
        /* Integrated Footer */
        .card-footer {
            background: #f8fafc;
            padding: 20px;
            border-top: 1px solid #f1f5f9;
            text-align: center;
        }
        .footer-links { margin-bottom: 10px; }
        .footer-links a { font-size: 12px; color: #64748b; text-decoration: none; margin: 0 8px; font-weight: 500; }
        .footer-links a:hover { color: #6366f1; }
        .copyright { font-size: 11px; color: #94a3b8; margin: 0; }
        #header,hr{display:none;}
    </style>

    <div class="wpwa-checkout-page">
        <div class="checkout-container">
            <div class="card-header">
                <?php if ($logo_url): ?>
                    <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_title); ?>">
                <?php endif; ?>
                <h2><?php echo esc_html($site_title); ?></h2>
            </div>

            <div class="card-body">
                <div class="product-badge">Confirm Subscription</div>
                <h1><?php echo esc_html($product['name'] ?? 'Product Selection'); ?></h1>
                <div class="price-tag">
                    <?php echo ($product['is_recurring'] ? 'Subscription' : 'Lifetime Access'); ?>
                </div>
                
                <div class="price-container">
                    <span class="price-amount">
                        <span class="price-currency">$</span><?php echo esc_html($display_price); ?>
                    </span>
                    <span class="price-period">
                        <?php echo esc_html($billing_text); ?>
                    </span>
                </div>

                <div class="details-box">
                    <div class="details-row"><label>User ID:</label> <span><?php echo esc_html($user_id); ?></span></div>
                    <div class="details-row"><label>Site ID:</label> <span><?php echo esc_html($site_id); ?></span></div>
                </div>

                <a href="<?php echo esc_url($checkout_url); ?>" class="btn-stripe">Proceed to Secure Payment →</a>

                <a href="<?php echo esc_url($upsell_url); ?>" target="_blank" class="btn-upsell">
                    🚀 Upgrade to All-in-One Whitelist
                </a>
            </div>

            <div class="card-footer">
                <div class="footer-links">
                    <a href="<?php echo esc_url('https://codoplex.com/privacy-policy/'); ?>" target="_blank">Privacy</a>
                    <a href="<?php echo esc_url('https://codoplex.com/terms-and-conditions/'); ?>" target="_blank">Terms</a>
                    <a href="<?php echo esc_url('https://codoplex.com/refund-policy/'); ?>" target="_blank">Refunds</a>
                </div>
                <p class="copyright">
                    © <?php echo date('Y'); ?> <?php echo esc_html($site_title); ?>. All rights reserved.
                </p>
            </div>
        </div>
    </div>

    <?php
}

/**
 * Get Weebly user info
 */
function wpwa_stripe_get_weebly_user_info($access_token, $user_id) {
    $response = wp_remote_get("https://api.weebly.com/v1/user", array(
        'headers' => array(
            'X-Weebly-Access-Token' => $access_token
        ),
        'timeout' => 15
    ));
    
    if (is_wp_error($response)) {
        return array();
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (!$data) {
        return array();
    }
    
    return array(
        'email' => $data['email'] ?? '',
        'name' => $data['name'] ?? ''
    );
}

/**
 * Check if user already has active access
 */
function wpwa_stripe_user_has_active_access($weebly_user_id, $product_id) {
    // Check for active subscription
    $has_subscription = wpwa_stripe_user_has_active_subscription($weebly_user_id, $product_id);
    
    if ($has_subscription) {
        return true;
    }
    
    // TODO: Check whitelist (if implemented)
    
    return false;
}