<?php
/**
 * Receipt Email Template
 */

if (!defined('ABSPATH')) exit;

/**
 * Send receipt email
 */
function wpwa_stripe_send_receipt_email($transaction_id) {
    $transaction = wpwa_stripe_get_transaction($transaction_id);
    
    if (!$transaction) {
        return false;
    }
    
    $product = wpwa_stripe_get_product($transaction['product_id']);
    $customer = wpwa_stripe_get_customer_by_weebly_id($transaction['weebly_user_id']);
    
    if (!$product || !$customer) {
        return false;
    }
    
    $to = $customer['email'];
    $subject = sprintf('[%s] Payment Receipt - Order #%d', get_bloginfo('name'), $transaction_id);
    
    $message = wpwa_stripe_get_receipt_email_html(array(
        'transaction' => $transaction,
        'product' => $product,
        'customer' => $customer
    ));
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Codoplex <admin@codoplex.com>',
        'Cc: junaidte14@gmail.com'
    );
    
    return wp_mail($to, $subject, $message, $headers);
}

/**
 * Get receipt email HTML
 */
function wpwa_stripe_get_receipt_email_html($data) {
    $transaction = $data['transaction'];
    $product = $data['product'];
    $customer = $data['customer'];
    
    $site_name = get_bloginfo('name');
    $date = date('F j, Y', strtotime($transaction['created_at']));
    
    ob_start();
    ?>
    <!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Hover effects */
        .app-link:hover {
            background-color: #f7fafc !important;
            border-color: #667eea !important;
            color: #667eea !important;
        }

        /* Mobile Responsive */
        @media only screen and (max-width: 600px) {
            .email-wrapper {
                margin: 20px 10px !important;
            }
            .header, .content, .footer {
                padding-left: 20px !important;
                padding-right: 20px !important;
            }
            .header h1 {
                font-size: 26px !important;
            }
            .app-cell {
                display: block !important;
                width: 100% !important;
            }
        }
    </style>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f7fa;">

    <div class="email-wrapper" style="max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);">
        
        <!-- Header -->
        <div class="header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 40px 40px 35px; text-align: center;">
            <h1 style="margin: 0 0 8px; font-size: 32px; font-weight: 700; line-height: 1.2; color: #ffffff;">✓ Payment Received!</h1>
            <p style="margin: 0; font-size: 16px; opacity: 0.95; color: #ffffff;">Thank you for your purchase</p>
        </div>
        
        <!-- Main Content -->
        <div class="content" style="padding: 35px 40px 25px;">
            <p style="margin: 0 0 15px; font-size: 16px; color: #2d3748; line-height: 1.6;">Dear <strong><?php echo esc_html($customer['name'] ?: 'Valued Customer'); ?></strong>,</p>
            
            <p style="margin: 0 0 15px; font-size: 16px; color: #2d3748; line-height: 1.6;">Thank you for your purchase! Your subscription has been successfully processed and <strong><?php echo esc_html($product['name']); ?></strong> is now active on your Weebly account.</p>
            
            <!-- Compact Order Details -->
            <div style="background: linear-gradient(135deg, #f6f8fc 0%, #e9ecf5 100%); padding: 20px 25px; border-radius: 10px; margin: 20px 0 30px; border: 1px solid #e2e8f0;">
                <h3 style="margin: 0 0 15px; font-size: 18px; font-weight: 700; color: #1a202c;">📋 Order Summary</h3>
                
                <div style="display: table; width: 100%;">
                    <div style="display: table-row;">
                        <div style="display: table-cell; padding: 6px 0; vertical-align: top; width: 40%; font-size: 13px; color: #718096; font-weight: 600;">Order Number</div>
                        <div style="display: table-cell; padding: 6px 0; vertical-align: top; width: 60%; font-size: 13px; color: #2d3748; font-weight: 600;">#<?php echo $transaction['id']; ?></div>
                    </div>
                    <div style="display: table-row;">
                        <div style="display: table-cell; padding: 6px 0; vertical-align: top; width: 40%; font-size: 13px; color: #718096; font-weight: 600;">Date</div>
                        <div style="display: table-cell; padding: 6px 0; vertical-align: top; width: 60%; font-size: 13px; color: #2d3748; font-weight: 600;"><?php echo $date; ?></div>
                    </div>
                    <div style="display: table-row;">
                        <div style="display: table-cell; padding: 6px 0; vertical-align: top; width: 40%; font-size: 13px; color: #718096; font-weight: 600;">Product</div>
                        <div style="display: table-cell; padding: 6px 0; vertical-align: top; width: 60%; font-size: 13px; color: #2d3748; font-weight: 600;"><?php echo esc_html($product['name']); ?></div>
                    </div>
                    <?php if ($product['is_recurring']): ?>
                    <div style="display: table-row;">
                        <div style="display: table-cell; padding: 6px 0; vertical-align: top; width: 40%; font-size: 13px; color: #718096; font-weight: 600;">Billing</div>
                        <div style="display: table-cell; padding: 6px 0; vertical-align: top; width: 60%; font-size: 13px; color: #2d3748; font-weight: 600;">
                            Every <?php echo $product['cycle_length']; ?> 
                            <?php echo $product['cycle_unit']; ?><?php echo $product['cycle_length'] > 1 ? 's' : ''; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #e2e8f0; text-align: right;">
                    <div style="font-size: 13px; color: #718096; margin-bottom: 5px;">Total Paid</div>
                    <div style="font-size: 28px; font-weight: 900; color: #667eea;"><?php echo wpwa_stripe_format_price($transaction['amount']); ?></div>
                </div>
            </div>
            
            <p style="margin: 0 0 15px; font-size: 16px; color: #2d3748; line-height: 1.6;"><strong>What's Next?</strong></p>
            <p style="margin: 0 0 15px; font-size: 16px; color: #2d3748; line-height: 1.6;">Your app is ready to use! Access it from your Weebly dashboard and start building amazing websites.</p>
        </div>
        
        <!-- Divider -->
        <div style="height: 1px; background: linear-gradient(90deg, transparent, #e2e8f0, transparent); margin: 0 40px;"></div>
        
        <!-- All-Access Subscription Promo -->
        <div class="content" style="padding: 35px 40px 25px;">
            <h2 style="margin: 0 0 12px; font-size: 24px; font-weight: 700; color: #1a202c; text-align: center;">🚀 Want More? Get All 17 Apps!</h2>
            <p style="margin: 0 0 25px; font-size: 15px; color: #4a5568; text-align: center; line-height: 1.5;">
                Upgrade to our <strong>All-Access Subscription</strong> and unlock every Codoplex app across all your Weebly websites.
            </p>
            
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px 28px; border-radius: 12px; margin: 30px 0; text-align: center; box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);">
                <div style="display: inline-block; padding: 6px 14px; background-color: #fbbf24; color: #1a202c; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 15px;">⭐ BEST VALUE - SAVE 70%</div>
                
                <h2 style="margin: 0 0 8px; font-size: 26px; font-weight: 800; color: #ffffff; line-height: 1.2;">All-Access Subscription</h2>
                
                <div style="margin: 0 0 20px;">
                    <span style="font-size: 42px; font-weight: 900; color: #fbbf24; line-height: 1;">$49.99</span>
                    <span style="font-size: 18px; color: rgba(255,255,255,0.9);">/year</span>
                </div>
                
                <div style="display: table; width: 100%; margin-bottom: 20px;">
                    <div style="display: table-row;">
                        <div style="display: table-cell; padding: 8px; font-size: 14px; color: #ffffff; line-height: 1.4; vertical-align: top;">
                            <strong>✓</strong> All 17 Apps Included
                        </div>
                        <div style="display: table-cell; padding: 8px; font-size: 14px; color: #ffffff; line-height: 1.4; vertical-align: top;">
                            <strong>✓</strong> Unlimited Websites
                        </div>
                    </div>
                    <div style="display: table-row;">
                        <div style="display: table-cell; padding: 8px; font-size: 14px; color: #ffffff; line-height: 1.4; vertical-align: top;">
                            <strong>✓</strong> Future Apps Free
                        </div>
                        <div style="display: table-cell; padding: 8px; font-size: 14px; color: #ffffff; line-height: 1.4; vertical-align: top;">
                            <strong>✓</strong> Priority Support
                        </div>
                    </div>
                </div>
                
                <a href="https://www.weebly.com/app-center/codo-apps" style="display: inline-block; padding: 14px 36px; background-color: #fbbf24; color: #1a202c; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 16px; box-shadow: 0 4px 14px rgba(251, 191, 36, 0.4); text-transform: uppercase; letter-spacing: 0.5px;">Upgrade to All-Access →</a>
                
                <div style="margin-top: 15px; font-size: 13px; color: rgba(255,255,255,0.85); font-style: italic;">⭐ Trusted by 2,000+ Weebly power users</div>
            </div>
        </div>
        
        <!-- All Apps Showcase -->
        <div class="content" style="padding: 35px 40px 25px;">
            <div style="margin: 30px 0;">
                <h2 style="margin: 0 0 20px; font-size: 22px; font-weight: 700; color: #1a202c; text-align: center;">Explore All 17 Premium Weebly Apps</h2>
                
                <div style="background-color: #fafbfc; border-radius: 10px; padding: 20px; border: 1px solid #e2e8f0;">
                    
                    <!-- Navigation & Layout -->
                    <div style="margin-bottom: 20px;">
                        <div style="font-size: 13px; font-weight: 700; color: #667eea; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 10px;">🧭 Navigation & Layout</div>
                        <div style="display: table; width: 100%;">
                            <div style="display: table-row;">
                                <div class="app-cell" style="display: table-cell; width: 50%; padding: 5px; vertical-align: top;">
                                    <a href="https://www.weebly.com/app-center/mega-menu2" class="app-link" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 10px 12px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0; font-weight: 600;">🍔 Mega Menu</a>
                                </div>
                                <div class="app-cell" style="display: table-cell; width: 50%; padding: 5px; vertical-align: top;">
                                    <a href="https://www.weebly.com/app-center/codo-tabs1" class="app-link" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 10px 12px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0; font-weight: 600;">🗂️ Codo Tabs</a>
                                </div>
                            </div>
                            <div style="display: table-row;">
                                <div class="app-cell" style="display: table-cell; width: 50%; padding: 5px; vertical-align: top;">
                                    <a href="https://www.weebly.com/app-center/vertical-tabs2" class="app-link" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 10px 12px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0; font-weight: 600;">↕️ Vertical Tabs</a>
                                </div>
                                <div class="app-cell" style="display: table-cell; width: 50%; padding: 5px; vertical-align: top;">
                                    <a href="https://www.weebly.com/app-center/tab-headers" class="app-link" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 10px 12px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0; font-weight: 600;">📌 Tab Headers</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Content & Blog -->
                    <div style="margin-bottom: 20px;">
                        <div style="font-size: 13px; font-weight: 700; color: #667eea; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 10px;">✍️ Content & Blog</div>
                        <div style="display: table; width: 100%;">
                            <div style="display: table-row;">
                                <div class="app-cell" style="display: table-cell; width: 50%; padding: 5px; vertical-align: top;">
                                    <a href="https://www.weebly.com/app-center/multi-column-blog" class="app-link" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 10px 12px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0; font-weight: 600;">📰 Multi Column Blog</a>
                                </div>
                                <div class="app-cell" style="display: table-cell; width: 50%; padding: 5px; vertical-align: top;">
                                    <a href="https://www.weebly.com/app-center/post-columns" class="app-link" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 10px 12px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0; font-weight: 600;">📑 Post Columns</a>
                                </div>
                            </div>
                            <div style="display: table-row;">
                                <div class="app-cell" style="display: table-cell; width: 50%; padding: 5px; vertical-align: top;">
                                    <a href="https://www.weebly.com/app-center/timeline3" class="app-link" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 10px 12px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0; font-weight: 600;">📅 Timeline</a>
                                </div>
                                <div class="app-cell" style="display: table-cell; width: 50%; padding: 5px; vertical-align: top;">
                                    <a href="https://www.weebly.com/app-center/breadcrumb" class="app-link" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 10px 12px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0; font-weight: 600;">🍞 Breadcrumb</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Media & Visual Design -->
                    <div style="margin-bottom: 20px;">
                        <div style="font-size: 13px; font-weight: 700; color: #667eea; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 10px;">🎨 Media & Visual Design</div>
                        <div style="display: table; width: 100%;">
                            <div style="display: table-row;">
                                <div class="app-cell" style="display: table-cell; width: 50%; padding: 5px; vertical-align: top;">
                                    <a href="https://www.weebly.com/app-center/video-lightbox1" class="app-link" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 10px 12px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0; font-weight: 600;">🎬 Video Lightbox</a>
                                </div>
                                <div class="app-cell" style="display: table-cell; width: 50%; padding: 5px; vertical-align: top;">
                                    <a href="https://www.weebly.com/app-center/masonry-layout" class="app-link" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 10px 12px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0; font-weight: 600;">🖼️ Masonry Layout</a>
                                </div>
                            </div>
                            <div style="display: table-row;">
                                <div class="app-cell" style="display: table-cell; width: 50%; padding: 5px; vertical-align: top;">
                                    <a href="https://www.weebly.com/app-center/image-overlay" class="app-link" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 10px 12px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0; font-weight: 600;">✨ Image Overlay</a>
                                </div>
                                <div class="app-cell" style="display: table-cell; width: 50%; padding: 5px; vertical-align: top;">
                                    <a href="https://www.weebly.com/app-center/image-text" class="app-link" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 10px 12px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0; font-weight: 600;">🖼 Image Text</a>
                                </div>
                            </div>
                            <div style="display: table-row;">
                                <div class="app-cell" style="display: table-cell; width: 50%; padding: 5px; vertical-align: top;">
                                    <a href="https://www.weebly.com/app-center/colored-lines" class="app-link" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 10px 12px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0; font-weight: 600;">🌈 Colored Lines</a>
                                </div>
                                <div class="app-cell" style="display: table-cell; width: 50%; padding: 5px; vertical-align: top;">
                                    <a href="https://www.weebly.com/app-center/vertical-lines" class="app-link" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 10px 12px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0; font-weight: 600;">➖ Vertical Lines</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- SEO & Conversion -->
                    <div style="margin-bottom: 0;">
                        <div style="font-size: 13px; font-weight: 700; color: #667eea; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 10px;">📈 SEO & Conversion</div>
                        <div style="display: table; width: 100%;">
                            <div style="display: table-row;">
                                <div class="app-cell" style="display: table-cell; width: 50%; padding: 5px; vertical-align: top;">
                                    <a href="https://www.weebly.com/app-center/seo-headlines" class="app-link" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 10px 12px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0; font-weight: 600;">🔍 SEO Headlines</a>
                                </div>
                                <div class="app-cell" style="display: table-cell; width: 50%; padding: 5px; vertical-align: top;">
                                    <a href="https://www.weebly.com/app-center/progress-bar" class="app-link" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 10px 12px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0; font-weight: 600;">📊 Progress Bar</a>
                                </div>
                            </div>
                            <div style="display: table-row;">
                                <div class="app-cell" style="display: table-cell; width: 50%; padding: 5px; vertical-align: top;">
                                    <a href="https://www.weebly.com/app-center/auto-popup" class="app-link" style="display: block; font-size: 13px; color: #2d3748; text-decoration: none; padding: 10px 12px; background-color: #ffffff; border-radius: 6px; border: 1px solid #e2e8f0; font-weight: 600;">💬 Auto Popup</a>
                                </div>
                                <div class="app-cell" style="display: table-cell; width: 50%; padding: 5px; vertical-align: top;">
                                    <!-- Empty cell for layout balance -->
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Browse All CTA -->
                    <div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0;">
                        <a href="https://www.weebly.com/app-center/developer/13952731" style="display: inline-block; padding: 12px 28px; background-color: #667eea; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;">View All Apps on Weebly App Center →</a>
                    </div>
                    
                </div>
            </div>
        </div>
        
        <!-- Divider -->
        <div style="height: 1px; background: linear-gradient(90deg, transparent, #e2e8f0, transparent); margin: 0 40px;"></div>
        
        <!-- Support Section -->
        <div class="content" style="padding: 35px 40px 25px;">
            <div style="background: linear-gradient(135deg, #ebf8ff 0%, #bee3f8 100%); border-radius: 10px; border: 1px solid #90cdf4; padding: 20px; margin: 25px 0;">
                <div style="display: table; width: 100%;">
                    <div style="display: table-cell; width: 60px; text-align: center; vertical-align: middle; font-size: 40px;">💬</div>
                    <div style="display: table-cell; vertical-align: middle; padding-left: 15px;">
                        <h3 style="margin: 0 0 5px; font-size: 16px; font-weight: 700; color: #1a202c;">Need Help Getting Started?</h3>
                        <p style="margin: 0 0 10px; font-size: 13px; color: #4a5568; line-height: 1.5;">I'm here to help with installation, setup, or any questions you have about your app.</p>
                        <a href="https://codoplex.com/contact" style="display: inline-block; padding: 8px 18px; background-color: #3182ce; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 13px;">Contact Support →</a>
                    </div>
                </div>
            </div>
            
            <!-- Personal Note -->
            <div style="background-color: #f7fafc; border-left: 4px solid #667eea; padding: 15px 18px; border-radius: 6px; margin: 25px 0;">
                <p style="margin: 0 0 8px; font-size: 14px; color: #2d3748; line-height: 1.5; font-style: italic;">"Thank you for choosing <?php echo esc_html($product['name']); ?>. As the developer, I'm personally committed to ensuring you have the best experience possible!"</p>
                <p style="margin: 0; font-size: 13px; color: #4a5568; font-weight: 600; font-style: normal;">— Junaid Hassan, Weebly Apps Developer</p>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer" style="background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%); padding: 25px 40px; text-align: center;">
            <p style="margin: 0 0 12px; font-size: 14px; color: #ffffff; font-weight: 700;">Codoplex</p>
            <p style="margin: 0 0 8px; font-size: 12px; color: #a0aec0; line-height: 1.5;">
                Premium Weebly apps built by Junaid Hassan<br>
                Weebly, WordPress, and Full-Stack Developer
            </p>
            <p style="margin: 0 0 8px; font-size: 12px; color: #a0aec0; line-height: 1.5;">
                <a href="https://codoplex.com" style="color: #90cdf4; text-decoration: none; margin: 0 8px;">Visit Website</a> |
                <a href="https://codoplex.com/contact" style="color: #90cdf4; text-decoration: none; margin: 0 8px;">Support</a> |
                <a href="https://www.weebly.com/app-center/developer/13952731" style="color: #90cdf4; text-decoration: none; margin: 0 8px;">Browse Apps</a>
            </p>
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid rgba(255, 255, 255, 0.1);">
                <p style="margin: 0; font-size: 11px; color: #718096; line-height: 1.5;">
                    © <?php echo date('Y'); ?> Codoplex. All rights reserved.<br>
                    Sent to <?php echo esc_html($customer['email']); ?>
                </p>
            </div>
        </div>
        
    </div>
</body>
</html>
    <?php
    return ob_get_clean();
}