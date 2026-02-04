<?php
/**
 * Stripe Settings Page
 */

if (!defined('ABSPATH')) exit;

/**
 * Render settings page
 */
function wpwa_stripe_render_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized', 'wpwa-stripe'));
    }
    
    // Save settings
    if (isset($_POST['wpwa_stripe_settings_nonce']) && 
        wp_verify_nonce($_POST['wpwa_stripe_settings_nonce'], 'wpwa_stripe_settings')) {
        wpwa_stripe_save_settings();
        echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
    }
    
    // Get current settings
    $enabled = wpwa_stripe_get_option('enabled', 'no');
    $test_mode = wpwa_stripe_get_option('test_mode', 'yes');
    $test_pub_key = wpwa_stripe_get_option('test_publishable_key');
    $test_secret_key = wpwa_stripe_get_option('test_secret_key');
    $test_webhook_secret = wpwa_stripe_get_option('test_webhook_secret');
    $live_pub_key = wpwa_stripe_get_option('live_publishable_key');
    $live_secret_key = wpwa_stripe_get_option('live_secret_key');
    $live_webhook_secret = wpwa_stripe_get_option('live_webhook_secret');
    
    $webhook_url = home_url('/wpwa-stripe-webhook/');
    
    ?>
    <div class="wrap wpwa-settings-wrap">
        <h1>
            <span class="dashicons dashicons-admin-settings"></span>
            Stripe Settings
        </h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('wpwa_stripe_settings', 'wpwa_stripe_settings_nonce'); ?>
            
            <!-- Enable/Disable -->
            <div class="wpwa-settings-section">
                <h2>General Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Enable Stripe</th>
                        <td>
                            <label>
                                <input type="checkbox" name="enabled" value="yes" <?php checked($enabled, 'yes'); ?>>
                                Enable Stripe payment processing
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Test Mode</th>
                        <td>
                            <label>
                                <input type="checkbox" name="test_mode" value="yes" <?php checked($test_mode, 'yes'); ?>>
                                Enable test mode (use test API keys)
                            </label>
                            <p class="description">Use test mode for development and testing. Disable for live payments.</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Test Keys -->
            <div class="wpwa-settings-section">
                <h2>Test API Keys</h2>
                <p class="description">Get your test keys from <a href="https://dashboard.stripe.com/test/apikeys" target="_blank">Stripe Dashboard (Test Mode)</a></p>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="test_publishable_key">Publishable Key</label>
                        </th>
                        <td>
                            <input type="text" id="test_publishable_key" name="test_publishable_key" 
                                   value="<?php echo esc_attr($test_pub_key); ?>" 
                                   class="regular-text code" placeholder="pk_test_...">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="test_secret_key">Secret Key</label>
                        </th>
                        <td>
                            <input type="password" id="test_secret_key" name="test_secret_key" 
                                   value="<?php echo esc_attr($test_secret_key); ?>" 
                                   class="regular-text code" placeholder="sk_test_...">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="test_webhook_secret">Webhook Secret</label>
                        </th>
                        <td>
                            <input type="password" id="test_webhook_secret" name="test_webhook_secret" 
                                   value="<?php echo esc_attr($test_webhook_secret); ?>" 
                                   class="regular-text code" placeholder="whsec_...">
                            <p class="description">
                                Webhook URL: <code><?php echo esc_html($webhook_url); ?></code>
                                <button type="button" class="button button-small" onclick="navigator.clipboard.writeText('<?php echo esc_js($webhook_url); ?>')">Copy</button>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Live Keys -->
            <div class="wpwa-settings-section">
                <h2>Live API Keys</h2>
                <p class="description">Get your live keys from <a href="https://dashboard.stripe.com/apikeys" target="_blank">Stripe Dashboard (Live Mode)</a></p>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="live_publishable_key">Publishable Key</label>
                        </th>
                        <td>
                            <input type="text" id="live_publishable_key" name="live_publishable_key" 
                                   value="<?php echo esc_attr($live_pub_key); ?>" 
                                   class="regular-text code" placeholder="pk_live_...">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="live_secret_key">Secret Key</label>
                        </th>
                        <td>
                            <input type="password" id="live_secret_key" name="live_secret_key" 
                                   value="<?php echo esc_attr($live_secret_key); ?>" 
                                   class="regular-text code" placeholder="sk_live_...">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="live_webhook_secret">Webhook Secret</label>
                        </th>
                        <td>
                            <input type="password" id="live_webhook_secret" name="live_webhook_secret" 
                                   value="<?php echo esc_attr($live_webhook_secret); ?>" 
                                   class="regular-text code" placeholder="whsec_...">
                            <p class="description">
                                Webhook URL: <code><?php echo esc_html($webhook_url); ?></code>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Connection Test -->
            <div class="wpwa-settings-section">
                <h2>Connection Test</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">API Connection</th>
                        <td>
                            <button type="button" class="button" id="test-stripe-connection">
                                Test Connection
                            </button>
                            <span id="connection-status"></span>
                        </td>
                    </tr>
                </table>
            </div>
            
            <p class="submit">
                <input type="submit" class="button button-primary" value="Save Settings">
            </p>
        </form>
    </div>
    
    <style>
    .wpwa-settings-wrap { background: #fff; padding: 20px; margin: 20px 20px 20px 0; }
    .wpwa-settings-wrap h1 { display: flex; align-items: center; gap: 10px; }
    .wpwa-settings-wrap .dashicons { font-size: 32px; width: 32px; height: 32px; }
    .wpwa-settings-section { margin-bottom: 30px; padding: 20px; background: #f9f9f9; border-radius: 5px; }
    .wpwa-settings-section h2 { margin-top: 0; }
    .form-table th { width: 250px; }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        $('#test-stripe-connection').on('click', function() {
            var $btn = $(this);
            var $status = $('#connection-status');
            
            $btn.prop('disabled', true).text('Testing...');
            $status.html('<span style="color: #999;">⏳ Connecting...</span>');
            
            $.post(ajaxurl, {
                action: 'wpwa_test_stripe_connection',
                nonce: '<?php echo wp_create_nonce('wpwa_test_connection'); ?>'
            }, function(response) {
                if (response.success) {
                    $status.html('<span style="color: #46b450;">✓ Connected successfully!</span>');
                } else {
                    $status.html('<span style="color: #dc3232;">✗ Connection failed: ' + response.data.message + '</span>');
                }
            }).fail(function() {
                $status.html('<span style="color: #dc3232;">✗ Request failed</span>');
            }).always(function() {
                $btn.prop('disabled', false).text('Test Connection');
            });
        });
    });
    </script>
    <?php
}

/**
 * Save settings
 */
function wpwa_stripe_save_settings() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    wpwa_stripe_update_option('enabled', isset($_POST['enabled']) ? 'yes' : 'no');
    wpwa_stripe_update_option('test_mode', isset($_POST['test_mode']) ? 'yes' : 'no');
    
    wpwa_stripe_update_option('test_publishable_key', sanitize_text_field($_POST['test_publishable_key']));
    wpwa_stripe_update_option('test_secret_key', sanitize_text_field($_POST['test_secret_key']));
    wpwa_stripe_update_option('test_webhook_secret', sanitize_text_field($_POST['test_webhook_secret']));
    
    wpwa_stripe_update_option('live_publishable_key', sanitize_text_field($_POST['live_publishable_key']));
    wpwa_stripe_update_option('live_secret_key', sanitize_text_field($_POST['live_secret_key']));
    wpwa_stripe_update_option('live_webhook_secret', sanitize_text_field($_POST['live_webhook_secret']));
}

/**
 * AJAX: Test Stripe connection
 */
add_action('wp_ajax_wpwa_test_stripe_connection', 'wpwa_stripe_ajax_test_connection');
function wpwa_stripe_ajax_test_connection() {
    check_ajax_referer('wpwa_test_connection', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }
    
    if (!wpwa_stripe_init()) {
        wp_send_json_error(array('message' => 'Stripe SDK not loaded or API key missing'));
    }
    
    try {
        // Test by retrieving account info
        $account = \Stripe\Account::retrieve();
        
        wp_send_json_success(array(
            'account_id' => $account->id,
            'name' => $account->business_profile->name ?? 'N/A'
        ));
        
    } catch (Exception $e) {
        wp_send_json_error(array('message' => $e->getMessage()));
    }
}