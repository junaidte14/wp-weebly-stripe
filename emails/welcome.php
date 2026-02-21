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
    
    $site_name = 'Codoplex';
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
                                    <td style="padding: 40px 40px 35px; text-align: center;">
                                        <h1 style="margin: 0 0 8px; font-size: 32px; font-weight: 700; color: #ffffff; line-height: 1.2;">🎉 Welcome to Codoplex!</h1>
                                        <p style="margin: 0; font-size: 16px; color: rgba(255, 255, 255, 0.95); font-weight: 400;">Your subscription is now active</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Success Message (More Compact) -->
                    <tr>
                        <td style="padding: 35px 40px 25px;">
                            <p style="margin: 0 0 15px; font-size: 16px; color: #2d3748; line-height: 1.6;">
                                Dear <strong><?php echo esc_html($customer['name'] ?: 'Valued Customer'); ?></strong>,
                            </p>
                            <p style="margin: 0; font-size: 16px; color: #2d3748; line-height: 1.6;">
                                Thank you for subscribing to <strong style="color: #667eea;"><?php echo esc_html($product['name']); ?></strong>! Your subscription is active and ready to use.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Subscription Details Card (COMPACT VERSION) -->
                    <tr>
                        <td style="padding: 0 40px 30px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #f6f8fc 0%, #e9ecf5 100%); border-radius: 10px; border: 1px solid #e2e8f0;">
                                <tr>
                                    <td style="padding: 20px 25px;">
                                        <h2 style="margin: 0 0 15px; font-size: 18px; font-weight: 700; color: #1a202c;">📋 Subscription Summary</h2>
                                        
                                        <!-- Compact 2-Column Layout -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <!-- Left Column -->
                                                <td style="width: 50%; vertical-align: top; padding-right: 10px;">
                                                    <div style="margin-bottom: 12px;">
                                                        <div style="font-size: 12px; color: #718096; margin-bottom: 3px;">Product</div>
                                                        <div style="font-size: 14px; color: #2d3748; font-weight: 600;"><?php echo esc_html($product['name']); ?></div>
                                                    </div>
                                                    <div style="margin-bottom: 12px;">
                                                        <div style="font-size: 12px; color: #718096; margin-bottom: 3px;">Amount Paid</div>
                                                        <div style="font-size: 14px; color: #2d3748; font-weight: 600;"><?php echo $amount_paid; ?></div>
                                                    </div>
                                                    <div>
                                                        <div style="font-size: 12px; color: #718096; margin-bottom: 3px;">Billing Cycle</div>
                                                        <div style="font-size: 14px; color: #2d3748;">Every <?php echo esc_html($cycle_text); ?></div>
                                                    </div>
                                                </td>
                                                
                                                <!-- Right Column -->
                                                <td style="width: 50%; vertical-align: top; padding-left: 10px;">
                                                    <div style="margin-bottom: 12px;">
                                                        <div style="font-size: 12px; color: #718096; margin-bottom: 3px;">Start Date</div>
                                                        <div style="font-size: 14px; color: #2d3748;"><?php echo $start_date; ?></div>
                                                    </div>
                                                    <div style="margin-bottom: 12px;">
                                                        <div style="font-size: 12px; color: #718096; margin-bottom: 3px;">Next Billing</div>
                                                        <div style="font-size: 14px; color: #2d3748; font-weight: 600;"><?php echo $next_billing; ?></div>
                                                    </div>
                                                    <div>
                                                        <div style="font-size: 12px; color: #718096; margin-bottom: 3px;">Status</div>
                                                        <span style="display: inline-block; padding: 3px 10px; background-color: #c6f6d5; color: #22543d; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase;">✓ Active</span>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Technical Details Toggle (Inline, More Compact) -->
                                        <details style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(0,0,0,0.08);">
                                            <summary style="cursor: pointer; font-size: 12px; font-weight: 600; color: #718096; outline: none;">🔧 View Technical Details</summary>
                                            <div style="margin-top: 10px; font-size: 11px; color: #718096; line-height: 1.6;">
                                                <div style="margin-bottom: 5px;"><strong>User ID:</strong> <code style="background-color: #edf2f7; padding: 2px 6px; border-radius: 3px; font-family: monospace;"><?php echo esc_html($subscription['weebly_user_id']); ?></code></div>
                                                <?php if ($subscription['weebly_site_id']): ?>
                                                <div style="margin-bottom: 5px;"><strong>Site ID:</strong> <code style="background-color: #edf2f7; padding: 2px 6px; border-radius: 3px; font-family: monospace;"><?php echo esc_html($subscription['weebly_site_id']); ?></code></div>
                                                <?php endif; ?>
                                                <div><strong>Subscription ID:</strong> <code style="background-color: #edf2f7; padding: 2px 6px; border-radius: 3px; font-family: monospace;"><?php echo esc_html($subscription['stripe_subscription_id']); ?></code></div>
                                            </div>
                                        </details>
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
                    
                    <!-- ALL-ACCESS SUBSCRIPTION PROMOTION (EXPANDED) -->
                    <tr>
                        <td style="padding: 35px 40px 30px;">
                            <h2 style="margin: 0 0 12px; font-size: 24px; font-weight: 700; color: #1a202c; text-align: center;">🚀 Unlock Everything with All-Access</h2>
                            <p style="margin: 0 0 25px; font-size: 15px; color: #4a5568; text-align: center; line-height: 1.5;">
                                Get unlimited access to <strong>all 17 Codoplex Weebly apps</strong> — across every website on your account!
                            </p>
                            
                            <!-- All-Access Hero Card -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; overflow: hidden; box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);">
                                <tr>
                                    <td style="padding: 30px 28px;">
                                        <!-- Badge -->
                                        <div style="text-align: center; margin-bottom: 15px;">
                                            <span style="display: inline-block; padding: 6px 14px; background-color: #fbbf24; color: #1a202c; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">⭐ BEST VALUE - SAVE 70%</span>
                                        </div>
                                        
                                        <!-- Title & Price -->
                                        <h3 style="margin: 0 0 8px; font-size: 26px; font-weight: 800; color: #ffffff; text-align: center; line-height: 1.2;">All-Access Subscription</h3>
                                        <div style="text-align: center; margin-bottom: 20px;">
                                            <span style="font-size: 42px; font-weight: 900; color: #fbbf24; line-height: 1;">$49.99</span>
                                            <span style="font-size: 18px; color: rgba(255,255,255,0.9);">/year</span>
                                        </div>
                                        
                                        <!-- Benefits Grid (2 Columns) -->
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-bottom: 20px;">
                                            <tr>
                                                <td style="width: 50%; padding: 8px 8px 8px 0; vertical-align: top;">
                                                    <div style="font-size: 14px; color: #ffffff; line-height: 1.4;">
                                                        <strong>✓</strong> All 17 Apps Included
                                                    </div>
                                                </td>
                                                <td style="width: 50%; padding: 8px 0 8px 8px; vertical-align: top;">
                                                    <div style="font-size: 14px; color: #ffffff; line-height: 1.4;">
                                                        <strong>✓</strong> Unlimited Websites
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="width: 50%; padding: 8px 8px 8px 0; vertical-align: top;">
                                                    <div style="font-size: 14px; color: #ffffff; line-height: 1.4;">
                                                        <strong>✓</strong> Future Apps Free
                                                    </div>
                                                </td>
                                                <td style="width: 50%; padding: 8px 0 8px 8px; vertical-align: top;">
                                                    <div style="font-size: 14px; color: #ffffff; line-height: 1.4;">
                                                        <strong>✓</strong> Priority Support
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                        
                                        <!-- CTA Button -->
                                        <div style="text-align: center;">
                                            <a href="https://www.weebly.com/app-center/codo-apps" style="display: inline-block; padding: 14px 36px; background-color: #fbbf24; color: #1a202c; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 16px; box-shadow: 0 4px 14px rgba(251, 191, 36, 0.4); text-transform: uppercase; letter-spacing: 0.5px;">Upgrade to All-Access →</a>
                                        </div>
                                        
                                        <!-- Social Proof -->
                                        <div style="text-align: center; margin-top: 15px;">
                                            <span style="font-size: 13px; color: rgba(255,255,255,0.85); font-style: italic;">⭐ Trusted by 2,000+ Weebly power users</span>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- ALL 17 APPS SHOWCASE -->
                    <tr>
                        <td style="padding: 0 40px 35px;">
                            <h2 style="margin: 0 0 20px; font-size: 20px; font-weight: 700; color: #1a202c; text-align: center;">Explore All 17 Premium Weebly Apps</h2>
                            
                            <!-- Apps Grid -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #fafbfc; border-radius: 10px; padding: 20px; border: 1px solid #e2e8f0;">
                                <tr>
                                    <td>
                                        <!-- Navigation & Layout Apps -->
                                        <div style="margin-bottom: 20px;">
                                            <div style="font-size: 13px; font-weight: 700; color: #667eea; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;">🧭 Navigation & Layout</div>
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                <tr>
                                                    <td style="width: 50%; padding: 5px 5px 5px 0;">
                                                        <a href="https://www.weebly.com/app-center/mega-menu2" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 8px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0; transition: all 0.2s;">
                                                            <strong>🍔 Mega Menu</strong>
                                                        </a>
                                                    </td>
                                                    <td style="width: 50%; padding: 5px 0 5px 5px;">
                                                        <a href="https://www.weebly.com/app-center/codo-tabs1" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 8px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0;">
                                                            <strong>🗂️ Codo Tabs</strong>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="width: 50%; padding: 5px 5px 5px 0;">
                                                        <a href="https://www.weebly.com/app-center/vertical-tabs2" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 8px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0;">
                                                            <strong>↕️ Vertical Tabs</strong>
                                                        </a>
                                                    </td>
                                                    <td style="width: 50%; padding: 5px 0 5px 5px;">
                                                        <a href="https://www.weebly.com/app-center/tab-headers" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 8px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0;">
                                                            <strong>📌 Tab Headers</strong>
                                                        </a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                        
                                        <!-- Content & Blog Apps -->
                                        <div style="margin-bottom: 20px;">
                                            <div style="font-size: 13px; font-weight: 700; color: #667eea; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;">✍️ Content & Blog</div>
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                <tr>
                                                    <td style="width: 50%; padding: 5px 5px 5px 0;">
                                                        <a href="https://www.weebly.com/app-center/multi-column-blog" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 8px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0;">
                                                            <strong>📰 Multi Column Blog</strong>
                                                        </a>
                                                    </td>
                                                    <td style="width: 50%; padding: 5px 0 5px 5px;">
                                                        <a href="https://www.weebly.com/app-center/post-columns" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 8px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0;">
                                                            <strong>📑 Post Columns</strong>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="width: 50%; padding: 5px 5px 5px 0;">
                                                        <a href="https://www.weebly.com/app-center/timeline3" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 8px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0;">
                                                            <strong>📅 Timeline</strong>
                                                        </a>
                                                    </td>
                                                    <td style="width: 50%; padding: 5px 0 5px 5px;">
                                                        <a href="https://www.weebly.com/app-center/breadcrumb" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 8px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0;">
                                                            <strong>🍞 Breadcrumb</strong>
                                                        </a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                        
                                        <!-- Media & Visual Apps -->
                                        <div style="margin-bottom: 20px;">
                                            <div style="font-size: 13px; font-weight: 700; color: #667eea; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;">🎨 Media & Visual Design</div>
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                <tr>
                                                    <td style="width: 50%; padding: 5px 5px 5px 0;">
                                                        <a href="https://www.weebly.com/app-center/video-lightbox1" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 8px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0;">
                                                            <strong>🎬 Video Lightbox</strong>
                                                        </a>
                                                    </td>
                                                    <td style="width: 50%; padding: 5px 0 5px 5px;">
                                                        <a href="https://www.weebly.com/app-center/masonry-layout" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 8px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0;">
                                                            <strong>🖼️ Masonry Layout</strong>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="width: 50%; padding: 5px 5px 5px 0;">
                                                        <a href="https://www.weebly.com/app-center/image-overlay" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 8px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0;">
                                                            <strong>✨ Image Overlay</strong>
                                                        </a>
                                                    </td>
                                                    <td style="width: 50%; padding: 5px 0 5px 5px;">
                                                        <a href="https://www.weebly.com/app-center/image-text" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 8px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0;">
                                                            <strong>🖼 Image Text</strong>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="width: 50%; padding: 5px 5px 5px 0;">
                                                        <a href="https://www.weebly.com/app-center/colored-lines" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 8px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0;">
                                                            <strong>🌈 Colored Lines</strong>
                                                        </a>
                                                    </td>
                                                    <td style="width: 50%; padding: 5px 0 5px 5px;">
                                                        <a href="https://www.weebly.com/app-center/vertical-lines" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 8px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0;">
                                                            <strong>➖ Vertical Lines</strong>
                                                        </a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                        
                                        <!-- SEO & Conversion Apps -->
                                        <div>
                                            <div style="font-size: 13px; font-weight: 700; color: #667eea; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 10px;">📈 SEO & Conversion</div>
                                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                                <tr>
                                                    <td style="width: 50%; padding: 5px 5px 5px 0;">
                                                        <a href="https://www.weebly.com/app-center/seo-headlines" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 8px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0;">
                                                            <strong>🔍 SEO Headlines</strong>
                                                        </a>
                                                    </td>
                                                    <td style="width: 50%; padding: 5px 0 5px 5px;">
                                                        <a href="https://www.weebly.com/app-center/progress-bar" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 8px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0;">
                                                            <strong>📊 Progress Bar</strong>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td colspan="2" style="padding: 5px 5px 5px 0;">
                                                        <a href="https://www.weebly.com/app-center/auto-popup" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 8px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0;">
                                                            <strong>💬 Auto Popup</strong>
                                                        </a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                        
                                        <!-- View All Apps CTA -->
                                        <div style="margin-top: 20px; text-align: center; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                                            <a href="https://www.weebly.com/app-center/developer/13952731" style="display: inline-block; padding: 12px 28px; background-color: #667eea; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">Browse All Apps on Weebly App Center →</a>
                                        </div>
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
                    
                    <!-- Support Section (Compact) -->
                    <tr>
                        <td style="padding: 30px 40px 25px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background: linear-gradient(135deg, #ebf8ff 0%, #bee3f8 100%); border-radius: 10px; border: 1px solid #90cdf4; padding: 20px;">
                                <tr>
                                    <td>
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="width: 20%; text-align: center; vertical-align: middle;">
                                                    <div style="font-size: 40px;">💬</div>
                                                </td>
                                                <td style="width: 80%; vertical-align: middle; padding-left: 15px;">
                                                    <h3 style="margin: 0 0 5px; font-size: 16px; font-weight: 700; color: #1a202c;">Need Help Getting Started?</h3>
                                                    <p style="margin: 0 0 10px; font-size: 13px; color: #4a5568; line-height: 1.5;">
                                                        I'm here to help with installation, setup, or any questions you have.
                                                    </p>
                                                    <a href="mailto:<?php echo esc_attr($support_email); ?>" style="display: inline-block; padding: 8px 18px; background-color: #3182ce; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 13px;">Contact Support →</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Personal Note (Compact) -->
                    <tr>
                        <td style="padding: 0 40px 35px;">
                            <div style="background-color: #f7fafc; border-left: 4px solid #667eea; padding: 15px 18px; border-radius: 6px;">
                                <p style="margin: 0 0 8px; font-size: 14px; color: #2d3748; line-height: 1.5; font-style: italic;">
                                    "Thank you for choosing <?php echo esc_html($product['name']); ?>. As the developer, I'm personally committed to your success!"
                                </p>
                                <p style="margin: 0; font-size: 13px; color: #4a5568; font-weight: 600;">
                                    — Junaid Hassan, Weebly Apps Developer
                                </p>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 25px 40px; background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%); text-align: center;">
                            <p style="margin: 0 0 8px; font-size: 14px; color: #cbd5e0;">
                                <strong style="color: #ffffff;"><?php echo esc_html($site_name); ?></strong>
                            </p>
                            <p style="margin: 0 0 12px; font-size: 12px; color: #a0aec0; line-height: 1.5;">
                                Auto-renews on <?php echo $next_billing; ?> • Manage in your Weebly dashboard
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #718096;">
                                <a href="<?php echo esc_url($site_url); ?>" style="color: #90cdf4; text-decoration: none;">Visit Website</a> | 
                                <a href="mailto:<?php echo esc_attr($support_email); ?>" style="color: #90cdf4; text-decoration: none;">Support</a> | 
                                <a href="https://www.weebly.com/app-center/developer/13952731" style="color: #90cdf4; text-decoration: none;">Browse Apps</a>
                            </p>
                            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255, 255, 255, 0.1);">
                                <p style="margin: 0; font-size: 11px; color: #718096; line-height: 1.4;">
                                    © <?php echo date('Y'); ?> <?php echo esc_html($site_name); ?>. All rights reserved.<br>
                                    Sent to <?php echo esc_html($customer['email']); ?>
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