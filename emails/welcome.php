<?php
/**
 * Welcome Email Template for New Stripe Subscribers
 */

if (!defined('ABSPATH')) exit;

/**
 * Send welcome email to new subscriber
 */
function wpwa_stripe_send_subscriber_welcome_email($subscription_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_subscriptions';
    $subscription = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE id = %d",
        $subscription_id
    ), ARRAY_A);
    
    if (!$subscription) {
        return false;
    }
    
    $product = wpwa_stripe_get_product($subscription['product_id']);
    $customer = wpwa_stripe_get_customer_by_weebly_id($subscription['weebly_user_id']);
    
    if (!$product || !$customer) {
        return false;
    }
    
    $to = $customer['email'];
    $subject = sprintf('🎉 Welcome to %s - Your Subscription is Active!', $product['name']);
    
    $message = wpwa_stripe_get_subscriber_welcome_html(array(
        'subscription' => $subscription,
        'product' => $product,
        'customer' => $customer
    ));
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Codoplex by HASSAN JUNAID <admin@codoplex.com>',
        'Cc: junaidte14@gmail.com'
    );
    
    return wp_mail($to, $subject, $message, $headers);
}

/**
 * Get subscriber welcome email HTML
 */
function wpwa_stripe_get_subscriber_welcome_html($data) {
    $subscription = $data['subscription'];
    $product = $data['product'];
    $customer = $data['customer'];
    
    $site_name = 'Codoplex by HASSAN JUNAID';
    $support_email = 'admin@codoplex.com';
    $site_url = home_url();
    
    // Calculate dates
    $start_date = date('F j, Y', strtotime($subscription['current_period_start']));
    $expiry_date = date('F j, Y', strtotime($subscription['current_period_end']));
    $next_billing = date('F j, Y', strtotime($subscription['current_period_end']));
    
    // Get latest transaction for this subscription
    global $wpdb;
    $trans_table = $wpdb->prefix . 'wpwa_stripe_transactions';
    $transaction = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$trans_table}` WHERE stripe_subscription_id = %s ORDER BY created_at DESC LIMIT 1",
        $subscription['stripe_subscription_id']
    ), ARRAY_A);
    
    $amount_paid = $transaction ? wpwa_stripe_format_price($transaction['amount']) : 'N/A';
    
    // Billing cycle
    $cycle_text = '';
    if ($product['is_recurring']) {
        $cycle_text = $product['cycle_length'] . ' ' . $product['cycle_unit'];
        if ($product['cycle_length'] > 1) {
            $cycle_text .= 's';
        }
    }
    
    ob_start();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to <?php echo esc_html($product['name']); ?></title>
</head>
<body style="margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f4f7fa; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">
    
    <!-- Email Container -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0; padding: 0; background-color: #f4f7fa;">
        <tr>
            <td style="padding: 40px 20px;">
                
                <!-- Main Email Card -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);">
                    
                    <!-- Header with Gradient -->
                    <tr>
                        <td style="padding: 0; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding: 50px 40px; text-align: center;">
                                        <h1 style="margin: 0 0 10px; font-size: 32px; font-weight: 700; color: #ffffff; line-height: 1.2;">🎉 Welcome Aboard!</h1>
                                        <p style="margin: 0; font-size: 18px; color: rgba(255, 255, 255, 0.95); font-weight: 400;">Your subscription is now active</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Success Message -->
                    <tr>
                        <td style="padding: 40px 40px 30px;">
                            <p style="margin: 0 0 20px; font-size: 16px; color: #2d3748; line-height: 1.6;">
                                Dear <strong><?php echo esc_html($customer['name'] ?: 'Valued Customer'); ?></strong>,
                            </p>
                            <p style="margin: 0 0 20px; font-size: 16px; color: #2d3748; line-height: 1.6;">
                                Thank you for subscribing to <strong style="color: #667eea;"><?php echo esc_html($product['name']); ?></strong>! I'm excited to have you on board. Your subscription is now active and ready to use.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Subscription Details Card -->
                    <tr>
                        <td style="padding: 0 40px 30px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #f6f8fc 0%, #e9ecf5 100%); border-radius: 12px; border: 1px solid #e2e8f0;">
                                <tr>
                                    <td style="padding: 30px;">
                                        <h2 style="margin: 0 0 20px; font-size: 20px; font-weight: 700; color: #1a202c;">📋 Subscription Details</h2>
                                        
                                        <!-- Details Table -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="padding: 12px 0; border-bottom: 1px solid rgba(0,0,0,0.08);">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="width: 40%; font-size: 14px; color: #718096; font-weight: 600;">Product:</td>
                                                            <td style="width: 60%; font-size: 14px; color: #2d3748; font-weight: 600;"><?php echo esc_html($product['name']); ?></td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            
                                            <tr>
                                                <td style="padding: 12px 0; border-bottom: 1px solid rgba(0,0,0,0.08);">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="width: 40%; font-size: 14px; color: #718096;">Amount Paid:</td>
                                                            <td style="width: 60%; font-size: 14px; color: #2d3748; font-weight: 600;"><?php echo $amount_paid; ?></td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            
                                            <tr>
                                                <td style="padding: 12px 0; border-bottom: 1px solid rgba(0,0,0,0.08);">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="width: 40%; font-size: 14px; color: #718096;">Billing Cycle:</td>
                                                            <td style="width: 60%; font-size: 14px; color: #2d3748;">Every <?php echo esc_html($cycle_text); ?></td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            
                                            <tr>
                                                <td style="padding: 12px 0; border-bottom: 1px solid rgba(0,0,0,0.08);">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="width: 40%; font-size: 14px; color: #718096;">Start Date:</td>
                                                            <td style="width: 60%; font-size: 14px; color: #2d3748;"><?php echo $start_date; ?></td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            
                                            <tr>
                                                <td style="padding: 12px 0; border-bottom: 1px solid rgba(0,0,0,0.08);">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="width: 40%; font-size: 14px; color: #718096;">Next Billing:</td>
                                                            <td style="width: 60%; font-size: 14px; color: #2d3748; font-weight: 600;"><?php echo $next_billing; ?></td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                            
                                            <tr>
                                                <td style="padding: 12px 0;">
                                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                        <tr>
                                                            <td style="width: 40%; font-size: 14px; color: #718096;">Status:</td>
                                                            <td style="width: 60%;">
                                                                <span style="display: inline-block; padding: 4px 12px; background-color: #c6f6d5; color: #22543d; border-radius: 20px; font-size: 12px; font-weight: 700; text-transform: uppercase;">✓ Active</span>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Technical Details (Collapsed) -->
                    <tr>
                        <td style="padding: 0 40px 30px;">
                            <details style="background-color: #fafbfc; border-radius: 8px; border: 1px solid #e2e8f0; padding: 20px;">
                                <summary style="cursor: pointer; font-size: 14px; font-weight: 600; color: #4a5568; outline: none;">🔧 Technical Details (Click to expand)</summary>
                                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                                    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                        <tr>
                                            <td style="padding: 8px 0;">
                                                <span style="font-size: 13px; color: #718096;">Weebly User ID:</span>
                                                <code style="display: block; margin-top: 4px; padding: 8px; background-color: #edf2f7; border-radius: 4px; font-size: 12px; color: #2d3748; font-family: 'Courier New', monospace; word-break: break-all;"><?php echo esc_html($subscription['weebly_user_id']); ?></code>
                                            </td>
                                        </tr>
                                        <?php if ($subscription['weebly_site_id']): ?>
                                        <tr>
                                            <td style="padding: 8px 0;">
                                                <span style="font-size: 13px; color: #718096;">Weebly Site ID:</span>
                                                <code style="display: block; margin-top: 4px; padding: 8px; background-color: #edf2f7; border-radius: 4px; font-size: 12px; color: #2d3748; font-family: 'Courier New', monospace; word-break: break-all;"><?php echo esc_html($subscription['weebly_site_id']); ?></code>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        <tr>
                                            <td style="padding: 8px 0;">
                                                <span style="font-size: 13px; color: #718096;">Subscription ID:</span>
                                                <code style="display: block; margin-top: 4px; padding: 8px; background-color: #edf2f7; border-radius: 4px; font-size: 12px; color: #2d3748; font-family: 'Courier New', monospace; word-break: break-all;"><?php echo esc_html($subscription['stripe_subscription_id']); ?></code>
                                            </td>
                                        </tr>
                                    </table>
                                </div>
                            </details>
                        </td>
                    </tr>
                    
                    <!-- Divider -->
                    <tr>
                        <td style="padding: 0 40px;">
                            <div style="height: 1px; background: linear-gradient(90deg, transparent, #e2e8f0, transparent);"></div>
                        </td>
                    </tr>
                    
                    <!-- Upsell Section -->
                    <tr>
                        <td style="padding: 40px 40px 30px;">
                            <h2 style="margin: 0 0 20px; font-size: 22px; font-weight: 700; color: #1a202c; text-align: center;">🚀 Get More Value</h2>
                            <p style="margin: 0 0 25px; font-size: 15px; color: #4a5568; text-align: center; line-height: 1.6;">
                                Unlock even more possibilities with our other premium offerings:
                            </p>
                            
                            <!-- Offer Cards -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td style="padding-bottom: 15px;">
                                        <!-- Master Subscription Card -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #fef5e7 0%, #fdebd0 100%); border-radius: 10px; border: 2px solid #f39c12; overflow: hidden;">
                                            <tr>
                                                <td style="padding: 25px;">
                                                    <div style="display: inline-block; padding: 4px 12px; background-color: #f39c12; color: #ffffff; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; margin-bottom: 12px;">⭐ Best Value</div>
                                                    <h3 style="margin: 0 0 10px; font-size: 18px; font-weight: 700; color: #1a202c;">Master Subscription - All Apps Access</h3>
                                                    <p style="margin: 0 0 15px; font-size: 14px; color: #4a5568; line-height: 1.5;">
                                                        Get unlimited access to <strong>all our Weebly apps</strong> with a single subscription. Save money and unlock every feature we offer!
                                                    </p>
                                                    <a href="<?php echo esc_url($site_url); ?>" style="display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px; box-shadow: 0 4px 12px rgba(243, 156, 18, 0.3);">Learn More →</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <td>
                                        <!-- Other Apps Card -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%); border-radius: 10px; border: 1px solid #66bb6a; overflow: hidden;">
                                            <tr>
                                                <td style="padding: 25px;">
                                                    <h3 style="margin: 0 0 10px; font-size: 18px; font-weight: 700; color: #1a202c;">Browse Our Other Apps</h3>
                                                    <p style="margin: 0 0 15px; font-size: 14px; color: #4a5568; line-height: 1.5;">
                                                        Discover more powerful Weebly apps designed to grow your business. Each app can be subscribed to individually.
                                                    </p>
                                                    <a href="<?php echo esc_url($site_url); ?>" style="display: inline-block; padding: 12px 24px; background-color: #66bb6a; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px;">View All Apps →</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Divider -->
                    <tr>
                        <td style="padding: 0 40px;">
                            <div style="height: 1px; background: linear-gradient(90deg, transparent, #e2e8f0, transparent);"></div>
                        </td>
                    </tr>
                    
                    <!-- Support Section -->
                    <tr>
                        <td style="padding: 40px 40px 30px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #ebf8ff 0%, #bee3f8 100%); border-radius: 10px; border: 1px solid #90cdf4; padding: 25px;">
                                <tr>
                                    <td style="text-align: center;">
                                        <div style="font-size: 32px; margin-bottom: 15px;">💬</div>
                                        <h3 style="margin: 0 0 10px; font-size: 18px; font-weight: 700; color: #1a202c;">Need Help?</h3>
                                        <p style="margin: 0 0 15px; font-size: 14px; color: #4a5568; line-height: 1.6;">
                                            If you have any questions, encounter any issues, or need assistance setting up your app, I'm here to help!
                                        </p>
                                        <a href="mailto:<?php echo esc_attr($support_email); ?>" style="display: inline-block; padding: 12px 28px; background-color: #3182ce; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 14px; margin-right: 10px;">Contact Support</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Personal Note -->
                    <tr>
                        <td style="padding: 0 40px 40px;">
                            <div style="background-color: #f7fafc; border-left: 4px solid #667eea; padding: 20px; border-radius: 6px;">
                                <p style="margin: 0 0 10px; font-size: 15px; color: #2d3748; line-height: 1.6; font-style: italic;">
                                    "Thank you for choosing <?php echo esc_html($product['name']); ?>. As the developer and owner, I'm personally committed to ensuring you have the best experience possible. Your success is my success!"
                                </p>
                                <p style="margin: 0; font-size: 14px; color: #4a5568; font-weight: 600;">
                                    — <?php echo esc_html($site_name); ?> Team
                                </p>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%); text-align: center;">
                            <p style="margin: 0 0 10px; font-size: 14px; color: #cbd5e0;">
                                <strong style="color: #ffffff;"><?php echo esc_html($site_name); ?></strong>
                            </p>
                            <p style="margin: 0 0 15px; font-size: 13px; color: #a0aec0; line-height: 1.6;">
                                Your subscription will automatically renew on <?php echo $next_billing; ?>.<br>
                                You can manage your subscription anytime from your Weebly dashboard.
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #718096;">
                                <a href="<?php echo esc_url($site_url); ?>" style="color: #90cdf4; text-decoration: none;">Visit Website</a> | 
                                <a href="mailto:<?php echo esc_attr($support_email); ?>" style="color: #90cdf4; text-decoration: none;">Contact Support</a>
                            </p>
                            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255, 255, 255, 0.1);">
                                <p style="margin: 0; font-size: 11px; color: #718096; line-height: 1.5;">
                                    © <?php echo date('Y'); ?> <?php echo esc_html($site_name); ?>. All rights reserved.<br>
                                    This email was sent to <?php echo esc_html($customer['email']); ?>
                                </p>
                            </div>
                        </td>
                    </tr>
                    
                </table>
                
            </td>
        </tr>
    </table>
    
</body>
</html>
    <?php
    return ob_get_clean();
}

/**
 * Hook to send welcome email when subscription is created
 * Call this from the webhook handler after subscription creation
 */
//add_action('wpwa_stripe_subscription_created', 'wpwa_stripe_send_subscriber_welcome_email', 10, 1);