<?php
/**
 * Orders Management Page
 */

if (!defined('ABSPATH')) exit;

/**
 * Render orders page
 */
function wpwa_stripe_render_orders_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized', 'wpwa-stripe'));
    }
    
    // Get filter parameters
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $type_filter = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    // Pagination
    $per_page = 25;
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($page - 1) * $per_page;
    
    // Build query args
    $args = array(
        'limit' => $per_page,
        'offset' => $offset
    );
    
    if ($status_filter) {
        $args['status'] = $status_filter;
    }
    
    if ($type_filter) {
        $args['transaction_type'] = $type_filter;
    }
    
    // Get transactions
    $transactions = wpwa_stripe_get_transactions($args);
    $total_count = wpwa_stripe_get_transaction_count($status_filter);
    $total_pages = ceil($total_count / $per_page);
    
    // Get stats
    $total_revenue = wpwa_stripe_get_total_revenue('succeeded');
    $succeeded_count = wpwa_stripe_get_transaction_count('succeeded');
    $pending_count = wpwa_stripe_get_transaction_count('pending');
    $failed_count = wpwa_stripe_get_transaction_count('failed');
    
    ?>
    <div class="wrap wpwa-orders-wrap">
        <h1>
            <span class="dashicons dashicons-cart"></span>
            Orders & Transactions
        </h1>
        
        <!-- Stats -->
        <div class="wpwa-stats-grid">
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(46,204,113,0.1); color: #2ecc71;">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Total Revenue</div>
                    <div class="wpwa-stat-value"><?php echo wpwa_stripe_format_price($total_revenue); ?></div>
                </div>
            </div>
            
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(52,152,219,0.1); color: #3498db;">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Successful</div>
                    <div class="wpwa-stat-value"><?php echo number_format($succeeded_count); ?></div>
                </div>
            </div>
            
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(243,156,18,0.1); color: #f39c12;">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Pending</div>
                    <div class="wpwa-stat-value"><?php echo number_format($pending_count); ?></div>
                </div>
            </div>
            
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(231,76,60,0.1); color: #e74c3c;">
                    <span class="dashicons dashicons-dismiss"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Failed</div>
                    <div class="wpwa-stat-value"><?php echo number_format($failed_count); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="wpwa-filters-bar">
            <form method="get" action="">
                <input type="hidden" name="page" value="wpwa-stripe-orders">
                
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="succeeded" <?php selected($status_filter, 'succeeded'); ?>>Succeeded</option>
                    <option value="pending" <?php selected($status_filter, 'pending'); ?>>Pending</option>
                    <option value="failed" <?php selected($status_filter, 'failed'); ?>>Failed</option>
                    <option value="refunded" <?php selected($status_filter, 'refunded'); ?>>Refunded</option>
                </select>
                
                <select name="type">
                    <option value="">All Types</option>
                    <option value="one_time" <?php selected($type_filter, 'one_time'); ?>>One-time</option>
                    <option value="subscription_initial" <?php selected($type_filter, 'subscription_initial'); ?>>Subscription (Initial)</option>
                    <option value="subscription_renewal" <?php selected($type_filter, 'subscription_renewal'); ?>>Subscription (Renewal)</option>
                </select>
                
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search by User ID...">
                
                <button type="submit" class="button">Filter</button>
                <a href="?page=wpwa-stripe-orders" class="button">Reset</a>
            </form>
        </div>
        
        <!-- Orders Table -->
        <div class="wpwa-table-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Product</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Weebly</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px;">
                                No transactions found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $transaction): 
                            $product = wpwa_stripe_get_product($transaction['product_id']);
                            $product_name = $product ? $product['name'] : 'Unknown Product';
                        ?>
                        <tr>
                            <td><strong>#<?php echo $transaction['id']; ?></strong></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($transaction['created_at'])); ?></td>
                            <td><?php echo wpwa_stripe_get_type_badge($transaction['transaction_type']); ?></td>
                            <td><?php echo esc_html($product_name); ?></td>
                            <td>
                                <code><?php echo esc_html($transaction['weebly_user_id']); ?></code>
                                <?php if ($transaction['weebly_site_id']): ?>
                                    <br><small>Site: <?php echo esc_html($transaction['weebly_site_id']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo wpwa_stripe_format_price($transaction['amount']); ?></strong></td>
                            <td><?php echo wpwa_stripe_get_status_badge($transaction['status']); ?></td>
                            <td>
                                <?php if ($transaction['weebly_notified']): ?>
                                    <span style="color: #46b450;">✓ Notified</span>
                                <?php else: ?>
                                    <span style="color: #999;">Not notified</span>
                                    <?php if ($transaction['status'] === 'succeeded'): ?>
                                        <br><button class="button button-small wpwa-notify-btn" data-id="<?php echo $transaction['id']; ?>">Notify Now</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="https://dashboard.stripe.com/<?php echo wpwa_stripe_is_test_mode() ? 'test/' : ''; ?>payments/<?php echo $transaction['stripe_payment_intent_id']; ?>" 
                                   target="_blank" class="button button-small">View in Stripe</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="wpwa-pagination">
            <?php
            echo paginate_links(array(
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'prev_text' => '&laquo; Previous',
                'next_text' => 'Next &raquo;',
                'total' => $total_pages,
                'current' => $page
            ));
            ?>
        </div>
        <?php endif; ?>
    </div>
    
    <style>
    .wpwa-orders-wrap { background: #fff; padding: 20px; margin: 20px 20px 20px 0; }
    .wpwa-orders-wrap h1 { display: flex; align-items: center; gap: 10px; margin-bottom: 25px; }
    .wpwa-orders-wrap .dashicons { font-size: 32px; width: 32px; height: 32px; }
    
    .wpwa-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .wpwa-stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); display: flex; gap: 15px; align-items: center; }
    .wpwa-stat-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
    .wpwa-stat-icon .dashicons { font-size: 24px; width: 24px; height: 24px; }
    .wpwa-stat-label { font-size: 12px; color: #666; text-transform: uppercase; margin-bottom: 5px; }
    .wpwa-stat-value { font-size: 24px; font-weight: 700; color: #1d2327; }
    
    .wpwa-filters-bar { background: #f9f9f9; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
    .wpwa-filters-bar form { display: flex; gap: 10px; flex-wrap: wrap; }
    .wpwa-filters-bar select, .wpwa-filters-bar input[type="search"] { padding: 6px 10px; }
    
    .wpwa-table-container { overflow-x: auto; }
    .wpwa-pagination { text-align: center; padding: 20px 0; }
    .wpwa-pagination .page-numbers { display: inline-block; padding: 8px 12px; margin: 0 2px; background: #fff; border: 1px solid #ddd; text-decoration: none; }
    .wpwa-pagination .page-numbers.current { background: #2271b1; color: #fff; border-color: #2271b1; }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        $('.wpwa-notify-btn').on('click', function() {
            var $btn = $(this);
            var transactionId = $btn.data('id');
            
            if (!confirm('Send Weebly payment notification?')) return;
            
            $btn.prop('disabled', true).text('Sending...');
            
            $.post(ajaxurl, {
                action: 'wpwa_manual_notify_weebly',
                nonce: '<?php echo wp_create_nonce('wpwa_notify'); ?>',
                transaction_id: transactionId
            }, function(response) {
                if (response.success) {
                    alert('Notification sent!');
                    location.reload();
                } else {
                    alert('Error: ' + response.data.message);
                    $btn.prop('disabled', false).text('Notify Now');
                }
            });
        });
    });
    </script>
    <?php
}

/**
 * Get status badge HTML
 */
function wpwa_stripe_get_status_badge($status) {
    $badges = array(
        'succeeded' => '<span style="background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">✓ Succeeded</span>',
        'pending' => '<span style="background: #fff3cd; color: #856404; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">⏳ Pending</span>',
        'failed' => '<span style="background: #f8d7da; color: #721c24; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">✗ Failed</span>',
        'refunded' => '<span style="background: #e2e3e5; color: #383d41; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">↩ Refunded</span>'
    );
    
    return $badges[$status] ?? '<span style="padding: 4px 10px;">' . esc_html($status) . '</span>';
}

/**
 * Get type badge HTML
 */
function wpwa_stripe_get_type_badge($type) {
    $badges = array(
        'one_time' => '<span style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 3px; font-size: 11px;">One-time</span>',
        'subscription_initial' => '<span style="background: #f3e5f5; color: #7b1fa2; padding: 4px 8px; border-radius: 3px; font-size: 11px;">Sub (Initial)</span>',
        'subscription_renewal' => '<span style="background: #fff3e0; color: #e65100; padding: 4px 8px; border-radius: 3px; font-size: 11px;">Sub (Renewal)</span>'
    );
    
    return $badges[$type] ?? '<span>' . esc_html($type) . '</span>';
}

/**
 * AJAX: Manual Weebly notification
 */
add_action('wp_ajax_wpwa_manual_notify_weebly', 'wpwa_stripe_ajax_manual_notify');
function wpwa_stripe_ajax_manual_notify() {
    check_ajax_referer('wpwa_notify', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized'));
    }
    
    $transaction_id = absint($_POST['transaction_id']);
    
    $result = wpwa_stripe_notify_weebly($transaction_id);
    
    if ($result) {
        wp_send_json_success();
    } else {
        wp_send_json_error(array('message' => 'Notification failed. Check logs.'));
    }
}