<?php
/**
 * Stripe Transactions Management Page
 */

if (!defined('ABSPATH')) exit;

/**
 * Render Stripe transactions page
 */
function wpwa_stripe_render_transactions_page() {
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
    
    // Get transactions
    $transactions = wpwa_stripe_get_transactions(array(
        'status' => $status_filter,
        'transaction_type' => $type_filter,
        'limit' => $per_page,
        'offset' => $offset
    ));
    
    // Apply search filter if needed
    if ($search) {
        $transactions = array_filter($transactions, function($t) use ($search) {
            return stripos($t['weebly_user_id'], $search) !== false;
        });
    }
    
    $total_count = wpwa_stripe_get_transaction_count($status_filter);
    $total_pages = ceil($total_count / $per_page);
    
    // Get stats
    $total_revenue = wpwa_stripe_get_total_revenue('succeeded');
    $pending_revenue = wpwa_stripe_get_total_revenue('pending');
    $total_transactions = wpwa_stripe_get_transaction_count();
    $succeeded_count = wpwa_stripe_get_transaction_count('succeeded');
    
    ?>
    <div class="wrap wpwa-transactions-wrap">
        <h1>
            <span class="dashicons dashicons-money-alt"></span>
            Stripe Transactions
        </h1>
        
        <!-- Stats -->
        <div class="wpwa-stats-grid">
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(46,204,113,0.1); color: #2ecc71;">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Total Revenue (Succeeded)</div>
                    <div class="wpwa-stat-value"><?php echo wpwa_stripe_format_price($total_revenue); ?></div>
                </div>
            </div>
            
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(243,156,18,0.1); color: #f39c12;">
                    <span class="dashicons dashicons-clock"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Pending Revenue</div>
                    <div class="wpwa-stat-value"><?php echo wpwa_stripe_format_price($pending_revenue); ?></div>
                </div>
            </div>
            
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(52,152,219,0.1); color: #3498db;">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Total Transactions</div>
                    <div class="wpwa-stat-value"><?php echo number_format($total_transactions); ?></div>
                    <small style="color: #666;">Succeeded: <?php echo number_format($succeeded_count); ?></small>
                </div>
            </div>
            
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(155,89,182,0.1); color: #9b59b6;">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Avg Transaction Value</div>
                    <div class="wpwa-stat-value"><?php echo wpwa_stripe_format_price($total_transactions > 0 ? $total_revenue / $succeeded_count : 0); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="wpwa-filters-bar">
            <form method="get" action="">
                <input type="hidden" name="page" value="wpwa-stripe-transactions">
                
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
                    <option value="subscription_initial" <?php selected($type_filter, 'subscription_initial'); ?>>Subscription Initial</option>
                    <option value="subscription_renewal" <?php selected($type_filter, 'subscription_renewal'); ?>>Subscription Renewal</option>
                </select>
                
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search by User ID...">
                
                <button type="submit" class="button">Filter</button>
                <a href="?page=wpwa-stripe-transactions" class="button">Reset</a>
            </form>
        </div>
        
        <!-- Transactions Table -->
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
                        <th>Stripe ID</th>
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
                                <?php if ($transaction['stripe_payment_intent_id']): ?>
                                    <small><code><?php echo substr($transaction['stripe_payment_intent_id'], 0, 20); ?>...</code></small>
                                <?php elseif ($transaction['stripe_subscription_id']): ?>
                                    <small><code><?php echo substr($transaction['stripe_subscription_id'], 0, 20); ?>...</code></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $stripe_id = $transaction['stripe_payment_intent_id'] ?: $transaction['stripe_invoice_id'];
                                if ($stripe_id): 
                                    $url_path = $transaction['stripe_payment_intent_id'] ? 'payments' : 'invoices';
                                ?>
                                    <a href="https://dashboard.stripe.com/<?php echo wpwa_stripe_is_test_mode() ? 'test/' : ''; ?><?php echo $url_path; ?>/<?php echo $stripe_id; ?>" 
                                       target="_blank" class="button button-small">View in Stripe</a>
                                <?php endif; ?>
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
    .wpwa-transactions-wrap { background: #fff; padding: 20px; margin: 20px 20px 20px 0; }
    .wpwa-transactions-wrap h1 { display: flex; align-items: center; gap: 10px; margin-bottom: 25px; }
    .wpwa-transactions-wrap .dashicons { font-size: 32px; width: 32px; height: 32px; }
    
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
    <?php
}