<?php
/**
 * Renewal Email Template
 */

if (!defined('ABSPATH')) exit;

/**
 * Send renewal email
 */
function wpwa_stripe_send_renewal_email($transaction_id) {
    $transaction = wpwa_stripe_get_transaction($transaction_id);
    
    if (!$transaction || $transaction['transaction_type'] !== 'subscription_renewal') {
        return false;
    }
    
    $product = wpwa_stripe_get_product($transaction['product_id']);
    $customer = wpwa_stripe_get_customer_by_weebly_id($transaction['weebly_user_id']);
    $subscription = wpwa_stripe_get_subscription_by_stripe_id($transaction['stripe_subscription_id']);
    
    if (!$product || !$customer || !$subscription) {
        return false;
    }
    
    $to = $customer['email'];
    $subject = sprintf('[%s] Subscription Renewed - %s', get_bloginfo('name'), $product['name']);
    
    $message = wpwa_stripe_get_renewal_email_html(array(
        'transaction' => $transaction,
        'product' => $product,
        'customer' => $customer,
        'subscription' => $subscription
    ));
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: Codoplex by HASSAN JUNAID <admin@codoplex.com>',
        'Cc: junaidte14@gmail.com'
    );
    
    return wp_mail($to, $subject, $message, $headers);
}

/**
 * Get renewal email HTML
 */
function wpwa_stripe_get_renewal_email_html($data) {
    $transaction = $data['transaction'];
    $product = $data['product'];
    $customer = $data['customer'];
    $subscription = $data['subscription'];
    
    $next_billing_date = date('F j, Y', strtotime($subscription['current_period_end']));
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #2ecc71; color: #fff; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #fff; padding: 30px; border: 1px solid #ddd; border-top: none; }
            .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 8px 8px; }
            .success-box { background: #d4edda; padding: 20px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #28a745; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>✓ Subscription Renewed!</h1>
            </div>
            <div class="content">
                <p>Dear <?php echo esc_html($customer['name'] ?: 'Customer'); ?>,</p>
                
                <p>Great news! Your subscription to <strong><?php echo esc_html($product['name']); ?></strong> has been successfully renewed.</p>
                
                <div class="success-box">
                    <p><strong>Renewal Details:</strong></p>
                    <p>Amount Charged: <strong><?php echo wpwa_stripe_format_price($transaction['amount']); ?></strong></p>
                    <p>Next Billing Date: <strong><?php echo $next_billing_date; ?></strong></p>
                </div>
                
                <p>Your subscription remains active and you can continue enjoying uninterrupted access to your Weebly app.</p>
                
                <p>Thank you for your continued support!</p>
                
                <p>Best regards,<br>
                The <?php echo get_bloginfo('name'); ?> Team</p>
            </div>
            <div class="footer">
                <p>&copy; <?php echo date('Y'); ?> <?php echo get_bloginfo('name'); ?>. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}