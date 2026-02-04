<?php
/**
 * Stripe Webhook Handler
 */

if (!defined('ABSPATH')) exit;

/**
 * Handle incoming Stripe webhooks
 */
function wpwa_stripe_handle_webhook() {
    // Get webhook secret
    $webhook_secret = wpwa_stripe_get_webhook_secret();
    
    if (empty($webhook_secret)) {
        wpwa_stripe_log('Webhook secret not configured');
        http_response_code(500);
        exit;
    }
    
    // Get raw POST body
    $payload = @file_get_contents('php://input');
    $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    
    if (!wpwa_stripe_init()) {
        wpwa_stripe_log('Stripe not initialized for webhook');
        http_response_code(500);
        exit;
    }
    
    try {
        // Verify webhook signature
        $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);
        
        // Log webhook
        wpwa_stripe_log_webhook($event);
        
        // Process event
        $result = wpwa_stripe_process_webhook_event($event);
        
        // Mark as processed
        wpwa_stripe_mark_webhook_processed($event->id, $result);
        
        http_response_code(200);
        echo json_encode(array('received' => true));
        
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        wpwa_stripe_log('Webhook signature verification failed: ' . $e->getMessage());
        http_response_code(400);
        exit;
    } catch (Exception $e) {
        wpwa_stripe_log('Webhook error: ' . $e->getMessage());
        wpwa_stripe_log_webhook_error($event->id ?? null, $e->getMessage());
        http_response_code(500);
        exit;
    }
}

/**
 * Log webhook to database
 */
function wpwa_stripe_log_webhook($event) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_webhook_log';
    
    $wpdb->insert($table, array(
        'event_id' => $event->id,
        'event_type' => $event->type,
        'payload' => json_encode($event->data->object),
        'processed' => 0
    ));
}

/**
 * Mark webhook as processed
 */
function wpwa_stripe_mark_webhook_processed($event_id, $result) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_webhook_log';
    
    $wpdb->update(
        $table,
        array(
            'processed' => 1,
            'error' => $result['success'] ? null : $result['message']
        ),
        array('event_id' => $event_id)
    );
}

/**
 * Log webhook error
 */
function wpwa_stripe_log_webhook_error($event_id, $error) {
    global $wpdb;
    
    if (!$event_id) return;
    
    $table = $wpdb->prefix . 'wpwa_stripe_webhook_log';
    
    $wpdb->update(
        $table,
        array('error' => $error),
        array('event_id' => $event_id)
    );
}

/**
 * Process webhook event
 */
function wpwa_stripe_process_webhook_event($event) {
    switch ($event->type) {
        // One-time payments
        case 'checkout.session.completed':
            return wpwa_stripe_handle_checkout_completed($event->data->object);
        
        case 'payment_intent.succeeded':
            return wpwa_stripe_handle_payment_succeeded($event->data->object);
        
        case 'payment_intent.payment_failed':
            return wpwa_stripe_handle_payment_failed($event->data->object);
        
        // Subscriptions
        case 'customer.subscription.created':
            return wpwa_stripe_handle_subscription_created($event->data->object);
        
        case 'customer.subscription.updated':
            return wpwa_stripe_handle_subscription_updated($event->data->object);
        
        case 'customer.subscription.deleted':
            return wpwa_stripe_handle_subscription_deleted($event->data->object);
        
        case 'invoice.paid':
            return wpwa_stripe_handle_invoice_paid($event->data->object);
        
        case 'invoice.payment_failed':
            return wpwa_stripe_handle_invoice_failed($event->data->object);
        
        // Refunds
        case 'charge.refunded':
            return wpwa_stripe_handle_refund($event->data->object);
        
        default:
            wpwa_stripe_log('Unhandled webhook event: ' . $event->type);
            return array('success' => true, 'message' => 'Event not handled');
    }
}

/**
 * Handle checkout.session.completed
 */
function wpwa_stripe_handle_checkout_completed($session) {
    // Extract metadata
    $metadata = (array) $session->metadata;
    
    if (empty($metadata['weebly_user_id']) || empty($metadata['product_id'])) {
        return array('success' => false, 'message' => 'Missing metadata');
    }
    
    // Determine transaction type
    $transaction_type = $session->mode === 'subscription' ? 'subscription_initial' : 'one_time';
    
    // Create transaction record
    $transaction_id = wpwa_stripe_create_transaction(array(
        'transaction_type' => $transaction_type,
        'stripe_payment_intent_id' => $session->payment_intent ?? null,
        'stripe_subscription_id' => $session->subscription ?? null,
        'stripe_customer_id' => $session->customer,
        'weebly_user_id' => $metadata['weebly_user_id'],
        'weebly_site_id' => $metadata['weebly_site_id'] ?? null,
        'product_id' => $metadata['product_id'],
        'amount' => $session->amount_total / 100,
        'currency' => strtoupper($session->currency),
        'status' => 'succeeded',
        'access_token' => $metadata['access_token'] ?? null,
        'final_url' => $metadata['final_url'] ?? null,
        'metadata' => json_encode(array(
            'session_id' => $session->id,
            'payment_status' => $session->payment_status
        ))
    ));
    
    // If subscription, it will be handled by customer.subscription.created
    // For one-time, notify Weebly immediately
    if ($transaction_type === 'one_time') {
        wpwa_stripe_notify_weebly($transaction_id);
    }
    
    // Send receipt email
    wpwa_stripe_send_receipt_email($transaction_id);
    
    return array('success' => true, 'transaction_id' => $transaction_id);
}

/**
 * Handle payment_intent.succeeded
 */
function wpwa_stripe_handle_payment_succeeded($payment_intent) {
    // Update transaction status if exists
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_transactions';
    
    $wpdb->update(
        $table,
        array('status' => 'succeeded'),
        array('stripe_payment_intent_id' => $payment_intent->id)
    );
    
    return array('success' => true);
}

/**
 * Handle payment_intent.payment_failed
 */
function wpwa_stripe_handle_payment_failed($payment_intent) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_transactions';
    
    $wpdb->update(
        $table,
        array('status' => 'failed'),
        array('stripe_payment_intent_id' => $payment_intent->id)
    );
    
    return array('success' => true);
}

/**
 * Handle customer.subscription.created
 */
function wpwa_stripe_handle_subscription_created($subscription) {
    $metadata = (array) $subscription->metadata;
    
    // Create subscription record
    wpwa_stripe_create_subscription_record(array(
        'stripe_subscription_id' => $subscription->id,
        'stripe_customer_id' => $subscription->customer,
        'weebly_user_id' => $metadata['weebly_user_id'] ?? '',
        'weebly_site_id' => $metadata['weebly_site_id'] ?? null,
        'product_id' => $metadata['product_id'] ?? 0,
        'status' => $subscription->status,
        'current_period_start' => date('Y-m-d H:i:s', $subscription->current_period_start),
        'current_period_end' => date('Y-m-d H:i:s', $subscription->current_period_end),
        'cancel_at_period_end' => $subscription->cancel_at_period_end ? 1 : 0,
        'access_token' => $metadata['access_token'] ?? null,
        'metadata' => $metadata
    ));
    
    // Notify Weebly for initial subscription
    $transaction = wpwa_stripe_get_transaction_by_subscription($subscription->id);
    if ($transaction && !$transaction['weebly_notified']) {
        wpwa_stripe_notify_weebly($transaction['id']);
    }
    
    return array('success' => true);
}

/**
 * Handle customer.subscription.updated
 */
function wpwa_stripe_handle_subscription_updated($subscription) {
    wpwa_stripe_update_subscription_record($subscription->id, array(
        'status' => $subscription->status,
        'current_period_start' => date('Y-m-d H:i:s', $subscription->current_period_start),
        'current_period_end' => date('Y-m-d H:i:s', $subscription->current_period_end),
        'cancel_at_period_end' => $subscription->cancel_at_period_end ? 1 : 0
    ));
    
    return array('success' => true);
}

/**
 * Handle customer.subscription.deleted
 */
function wpwa_stripe_handle_subscription_deleted($subscription) {
    wpwa_stripe_update_subscription_record($subscription->id, array(
        'status' => 'canceled'
    ));
    
    // TODO: Revoke Weebly access if needed
    
    return array('success' => true);
}

/**
 * Handle invoice.paid (subscription renewals)
 */
function wpwa_stripe_handle_invoice_paid($invoice) {
    // Skip if not subscription invoice
    if (!$invoice->subscription) {
        return array('success' => true);
    }
    
    // Get subscription details
    $subscription_record = wpwa_stripe_get_subscription_by_stripe_id($invoice->subscription);
    
    if (!$subscription_record) {
        return array('success' => false, 'message' => 'Subscription not found');
    }
    
    // Create renewal transaction
    $transaction_id = wpwa_stripe_create_transaction(array(
        'transaction_type' => 'subscription_renewal',
        'stripe_invoice_id' => $invoice->id,
        'stripe_subscription_id' => $invoice->subscription,
        'stripe_customer_id' => $invoice->customer,
        'weebly_user_id' => $subscription_record['weebly_user_id'],
        'weebly_site_id' => $subscription_record['weebly_site_id'],
        'product_id' => $subscription_record['product_id'],
        'amount' => $invoice->amount_paid / 100,
        'currency' => strtoupper($invoice->currency),
        'status' => 'succeeded',
        'access_token' => $subscription_record['access_token'],
        'metadata' => json_encode(array(
            'invoice_id' => $invoice->id,
            'period_start' => $invoice->period_start,
            'period_end' => $invoice->period_end
        ))
    ));
    
    // Send renewal email
    wpwa_stripe_send_renewal_email($transaction_id);
    
    return array('success' => true, 'transaction_id' => $transaction_id);
}

/**
 * Handle invoice.payment_failed
 */
function wpwa_stripe_handle_invoice_failed($invoice) {
    // Get subscription
    $subscription_record = wpwa_stripe_get_subscription_by_stripe_id($invoice->subscription);
    
    if ($subscription_record) {
        // Update status
        wpwa_stripe_update_subscription_record($invoice->subscription, array(
            'status' => 'past_due'
        ));
        
        // TODO: Send payment failed email
    }
    
    return array('success' => true);
}

/**
 * Handle charge.refunded
 */
function wpwa_stripe_handle_refund($charge) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_transactions';
    
    // Find transaction by payment intent
    $transaction = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE stripe_payment_intent_id = %s",
        $charge->payment_intent
    ), ARRAY_A);
    
    if (!$transaction) {
        return array('success' => false, 'message' => 'Transaction not found');
    }
    
    // Update status
    $wpdb->update(
        $table,
        array('status' => 'refunded'),
        array('id' => $transaction['id'])
    );
    
    // TODO: Revoke Weebly access
    
    return array('success' => true);
}

/**
 * Notify Weebly API
 */
function wpwa_stripe_notify_weebly($transaction_id) {
    $transaction = wpwa_stripe_get_transaction($transaction_id);
    
    if (!$transaction || $transaction['weebly_notified']) {
        return false;
    }
    
    $product = wpwa_stripe_get_product($transaction['product_id']);
    if (!$product) {
        return false;
    }
    
    // Decrypt access token
    $access_token = wpwa_stripe_decrypt_token($transaction['access_token']);
    
    if (empty($access_token)) {
        wpwa_stripe_log('Cannot notify Weebly: missing access token', array('transaction_id' => $transaction_id));
        return false;
    }
    
    // Calculate fees (Stripe takes 2.9% + $0.30)
    $gross = $transaction['amount'];
    $fee = ($gross * 0.029) + 0.30;
    $net = $gross - $fee;
    $weebly_payout = $net * 0.30; // Weebly takes 30%
    
    // Send notification to Weebly
    $response = wp_remote_post('https://api.weebly.com/v1/admin/app/payment_notifications', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'x-weebly-access-token' => $access_token
        ),
        'body' => json_encode(array(
            'name' => $product['name'] . ' Purchase',
            'method' => 'purchase',
            'kind' => 'single',
            'term' => 'forever',
            'gross_amount' => $gross,
            'payable_amount' => $weebly_payout,
            'currency' => $transaction['currency']
        )),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        wpwa_stripe_log('Weebly notification failed: ' . $response->get_error_message());
        return false;
    }
    
    $code = wp_remote_retrieve_response_code($response);
    
    if ($code === 200) {
        // Mark as notified
        global $wpdb;
        $table = $wpdb->prefix . 'wpwa_stripe_transactions';
        
        $wpdb->update(
            $table,
            array('weebly_notified' => 1),
            array('id' => $transaction_id)
        );
        
        return true;
    }
    
    wpwa_stripe_log('Weebly notification error', array(
        'code' => $code,
        'response' => wp_remote_retrieve_body($response)
    ));
    
    return false;
}