<?php
/**
 * Weebly OAuth Flow → Stripe Checkout
 * Replaces old WooCommerce checkout flow
 */

if (!defined('ABSPATH')) exit;

// Load dependencies
require_once WPWA_STRIPE_DIR . '/lib/Util/HMAC.php';
require_once WPWA_STRIPE_DIR . '/lib/Weebly/WeeblyClient.php';

function wpwa_stripe_handle_phase_one() {
    error_log("WPWA Debug: Entering handle_phase_one. Params: " . print_r($_GET, true));

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
    
    error_log("WPWA Debug: [Phase 2] Token obtained. Final Redirect URL: " . $final_url);

    // Get user info from Weebly API
    $user_info = wpwa_stripe_get_weebly_user_info($access_token, $user_id);
    $email = $user_info['email'] ?? '';
    $name = $user_info['name'] ?? '';
    
    error_log("WPWA Debug: [Phase 2] Weebly User Info: Email: $email, Name: $name");

    // Check if user already has active access
    if (wpwa_stripe_user_has_active_access($user_id, $product['id'])) {
        error_log("WPWA Debug: [Phase 2] Active access detected for User $user_id. Skipping checkout.");
        
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
    
    error_log("WPWA Debug: [Phase 2] Creating Stripe Checkout Session with args: " . print_r($checkout_args, true));

    $session_result = wpwa_stripe_create_checkout_session($checkout_args);

    // Check if the result is an array and specifically extract the 'url'
    if (is_array($session_result) && !empty($session_result['url'])) {
        $checkout_url = $session_result['url'];
    } else {
        error_log("WPWA Error: Stripe session failed. Response: " . print_r($session_result, true));
        wp_die('Failed to create checkout session. Error: ' . ($session_result['message'] ?? 'Unknown Error'));
    }

    error_log("WPWA Debug: [Phase 2] Redirecting to Stripe URL: " . $checkout_url);
    wp_redirect($checkout_url);
    exit;

}

/**
 * Handle OAuth callback and redirect to Stripe Checkout
 */
function wpwa_stripe_handle_oauth_callback($product) {
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

    error_log("WPWA Debug: [Phase 2] Token obtained. Final Redirect URL: " . $final_url);
    
    // Get user info from Weebly API (optional - to get email/name)
    $user_info = wpwa_stripe_get_weebly_user_info($access_token, $user_id);
    
    $email = $user_info['email'] ?? '';
    $name = $user_info['name'] ?? '';

    error_log("WPWA Debug: [Phase 2] Weebly User Info: Email: $email, Name: $name");
    
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
    
    error_log("WPWA Debug: [Phase 2] Creating Stripe Checkout Session with args: " . print_r($checkout_args, true));

    $session_result = wpwa_stripe_create_checkout_session($checkout_args);
    
    if (is_array($session_result) && !empty($session_result['url'])) {
        $checkout_url = $session_result['url'];
    } else {
        error_log("WPWA Error: Stripe session failed. Response: " . print_r($session_result, true));
        wp_die('Failed to create checkout session. Error: ' . ($session_result['message'] ?? 'Unknown Error'));
    }
    
    error_log("WPWA Debug: [Phase 2] Redirecting to Stripe URL: " . $checkout_url);
    wp_redirect($checkout_url);
    exit;
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