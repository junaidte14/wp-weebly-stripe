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
        'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
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
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #2271b1; color: #fff; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
            .header h1 { margin: 0; font-size: 28px; }
            .content { background: #fff; padding: 30px; border: 1px solid #ddd; border-top: none; }
            .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; border-radius: 0 0 8px 8px; }
            .info-box { background: #f9f9f9; padding: 20px; border-radius: 4px; margin: 20px 0; }
            .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e0e0e0; }
            .info-row:last-child { border-bottom: none; }
            .info-label { font-weight: 600; color: #555; }
            .info-value { color: #1d2327; }
            .total-row { background: #e3f2fd; padding: 15px; border-radius: 4px; margin-top: 15px; }
            .total-row .info-label { font-size: 18px; color: #1976d2; }
            .total-row .info-value { font-size: 24px; font-weight: 700; color: #1976d2; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>✓ Payment Received</h1>
            </div>
            <div class="content">
                <p>Dear <?php echo esc_html($customer['name'] ?: 'Customer'); ?>,</p>
                
                <p>Thank you for your purchase! Your payment has been successfully processed.</p>
                
                <div class="info-box">
                    <h3 style="margin-top: 0;">Order Details</h3>
                    
                    <div class="info-row">
                        <span class="info-label">Order Number:</span>
                        <span class="info-value">#<?php echo $transaction['id']; ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Date:</span>
                        <span class="info-value"><?php echo $date; ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Product:</span>
                        <span class="info-value"><?php echo esc_html($product['name']); ?></span>
                    </div>
                    
                    <?php if ($product['is_recurring']): ?>
                    <div class="info-row">
                        <span class="info-label">Billing:</span>
                        <span class="info-value">
                            Recurring every <?php echo $product['cycle_length']; ?> 
                            <?php echo $product['cycle_unit']; ?><?php echo $product['cycle_length'] > 1 ? 's' : ''; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="total-row">
                        <div class="info-row" style="border: none;">
                            <span class="info-label">Total Paid:</span>
                            <span class="info-value"><?php echo wpwa_stripe_format_price($transaction['amount']); ?></span>
                        </div>
                    </div>
                </div>
                
                <p><strong>What's Next?</strong></p>
                <p>Your Weebly app has been activated and is ready to use. You can access it from your Weebly dashboard.</p>
                
                <?php if ($product['is_recurring']): ?>
                <p><strong>Subscription Details:</strong></p>
                <p>Your subscription will automatically renew. You can manage or cancel your subscription at any time by contacting our support team.</p>
                <?php endif; ?>
                
                <p>If you have any questions, please don't hesitate to contact us.</p>
                
                <p>Best regards,<br>
                The <?php echo esc_html($site_name); ?> Team</p>
            </div>
            <div class="footer">
                <p>&copy; <?php echo date('Y'); ?> <?php echo esc_html($site_name); ?>. All rights reserved.</p>
                <p>
                    <a href="<?php echo home_url(); ?>" style="color: #2271b1; text-decoration: none;">Visit Our Website</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}