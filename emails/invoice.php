<?php
/**
 * Invoice Email Template (for subscriptions)
 */

if (!defined('ABSPATH')) exit;

/**
 * Send invoice email
 */
function wpwa_stripe_send_invoice_email($transaction_id) {
    $transaction = wpwa_stripe_get_transaction($transaction_id);
    
    if (!$transaction || $transaction['transaction_type'] !== 'subscription_initial') {
        return false;
    }
    
    $product = wpwa_stripe_get_product($transaction['product_id']);
    $customer = wpwa_stripe_get_customer_by_weebly_id($transaction['weebly_user_id']);
    $subscription = wpwa_stripe_get_subscription_by_stripe_id($transaction['stripe_subscription_id']);
    
    if (!$product || !$customer || !$subscription) {
        return false;
    }
    
    $to = $customer['email'];
    $subject = sprintf('[%s] Subscription Invoice - Order #%d', get_bloginfo('name'), $transaction_id);
    
    $message = wpwa_stripe_get_invoice_email_html(array(
        'transaction' => $transaction,
        'product' => $product,
        'customer' => $customer,
        'subscription' => $subscription
    ));
    
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
    );
    
    return wp_mail($to, $subject, $message, $headers);
}

/**
 * Get invoice email HTML
 */
function wpwa_stripe_get_invoice_email_html($data) {
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
            .header { background: #667eea; color: #fff; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #fff; padding: 30px; border: 1px solid #ddd; border-top: none; }
            .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 8px 8px; }
            .info-box { background: #f0f4ff; padding: 20px; border-radius: 4px; margin: 20px 0; border-left: 4px solid #667eea; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>📧 Subscription Invoice</h1>
            </div>
            <div class="content">
                <p>Dear <?php echo esc_html($customer['name'] ?: 'Customer'); ?>,</p>
                
                <p>Your subscription to <strong><?php echo esc_html($product['name']); ?></strong> is now active!</p>
                
                <div class="info-box">
                    <p><strong>Subscription Details:</strong></p>
                    <p>Amount: <strong><?php echo wpwa_stripe_format_price($transaction['amount']); ?></strong></p>
                    <p>Billing Cycle: Every <?php echo $product['cycle_length']; ?> <?php echo $product['cycle_unit']; ?><?php echo $product['cycle_length'] > 1 ? 's' : ''; ?></p>
                    <p>Next Billing Date: <strong><?php echo $next_billing_date; ?></strong></p>
                </div>
                
                <p>Your subscription will automatically renew on the next billing date. You'll receive an email notification before each payment.</p>
                
                <p>To manage or cancel your subscription, please contact our support team.</p>
                
                <p>Thank you for your business!</p>
                
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