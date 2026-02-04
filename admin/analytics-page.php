<?php
/**
 * Analytics Dashboard
 */

if (!defined('ABSPATH')) exit;

/**
 * Render analytics page
 */
function wpwa_stripe_render_analytics_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized', 'wpwa-stripe'));
    }
    
    // Get stats
    $total_revenue = wpwa_stripe_get_total_revenue('succeeded');
    $total_transactions = wpwa_stripe_get_transaction_count('succeeded');
    $total_customers = wpwa_stripe_get_customer_count();
    $active_subscriptions = wpwa_stripe_get_active_subscriptions_count();
    $mrr = wpwa_stripe_get_mrr();
    
    // Calculate average order value
    $avg_order_value = $total_transactions > 0 ? $total_revenue / $total_transactions : 0;
    
    // Get recent transactions
    $recent_transactions = wpwa_stripe_get_transactions(array('limit' => 10));
    
    ?>
    <div class="wrap wpwa-analytics-wrap">
        <h1>
            <span class="dashicons dashicons-chart-area"></span>
            Analytics Dashboard
        </h1>
        
        <!-- Key Metrics -->
        <div class="wpwa-metrics-grid">
            <div class="wpwa-metric-card" style="border-left-color: #2ecc71;">
                <div class="wpwa-metric-icon" style="background: rgba(46,204,113,0.1); color: #2ecc71;">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="wpwa-metric-content">
                    <div class="wpwa-metric-label">Total Revenue</div>
                    <div class="wpwa-metric-value"><?php echo wpwa_stripe_format_price($total_revenue); ?></div>
                </div>
            </div>
            
            <div class="wpwa-metric-card" style="border-left-color: #3498db;">
                <div class="wpwa-metric-icon" style="background: rgba(52,152,219,0.1); color: #3498db;">
                    <span class="dashicons dashicons-cart"></span>
                </div>
                <div class="wpwa-metric-content">
                    <div class="wpwa-metric-label">Total Orders</div>
                    <div class="wpwa-metric-value"><?php echo number_format($total_transactions); ?></div>
                </div>
            </div>
            
            <div class="wpwa-metric-card" style="border-left-color: #9b59b6;">
                <div class="wpwa-metric-icon" style="background: rgba(155,89,182,0.1); color: #9b59b6;">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="wpwa-metric-content">
                    <div class="wpwa-metric-label">Total Customers</div>
                    <div class="wpwa-metric-value"><?php echo number_format($total_customers); ?></div>
                </div>
            </div>
            
            <div class="wpwa-metric-card" style="border-left-color: #f39c12;">
                <div class="wpwa-metric-icon" style="background: rgba(243,156,18,0.1); color: #f39c12;">
                    <span class="dashicons dashicons-tag"></span>
                </div>
                <div class="wpwa-metric-content">
                    <div class="wpwa-metric-label">Avg Order Value</div>
                    <div class="wpwa-metric-value"><?php echo wpwa_stripe_format_price($avg_order_value); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Subscription Metrics -->
        <div class="wpwa-secondary-metrics">
            <div class="wpwa-secondary-card">
                <span class="dashicons dashicons-update"></span>
                <div>
                    <div class="label">Active Subscriptions</div>
                    <div class="value"><?php echo number_format($active_subscriptions); ?></div>
                </div>
            </div>
            <div class="wpwa-secondary-card">
                <span class="dashicons dashicons-chart-line"></span>
                <div>
                    <div class="label">Monthly Recurring Revenue</div>
                    <div class="value"><?php echo wpwa_stripe_format_price($mrr); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Recent Transactions -->
        <div class="wpwa-recent-section">
            <h2>Recent Transactions</h2>
            <div class="wpwa-table-container">
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_transactions)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 20px;">No transactions yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_transactions as $transaction): 
                                $product = wpwa_stripe_get_product($transaction['product_id']);
                                $product_name = $product ? $product['name'] : 'Unknown';
                            ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($transaction['created_at'])); ?></td>
                                <td><?php echo esc_html($product_name); ?></td>
                                <td><code><?php echo esc_html($transaction['weebly_user_id']); ?></code></td>
                                <td><strong><?php echo wpwa_stripe_format_price($transaction['amount']); ?></strong></td>
                                <td><?php echo wpwa_stripe_get_status_badge($transaction['status']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <style>
    .wpwa-analytics-wrap { background: #f5f5f5; padding: 20px; margin: 20px 20px 20px 0; }
    .wpwa-analytics-wrap h1 { display: flex; align-items: center; gap: 10px; margin-bottom: 25px; }
    .wpwa-analytics-wrap .dashicons { font-size: 32px; width: 32px; height: 32px; }
    
    .wpwa-metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .wpwa-metric-card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); display: flex; gap: 20px; align-items: center; border-left: 4px solid; }
    .wpwa-metric-icon { width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
    .wpwa-metric-icon .dashicons { font-size: 32px; width: 32px; height: 32px; }
    .wpwa-metric-label { font-size: 13px; color: #666; margin-bottom: 8px; text-transform: uppercase; }
    .wpwa-metric-value { font-size: 32px; font-weight: 700; color: #1d2327; }
    
    .wpwa-secondary-metrics { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .wpwa-secondary-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 20px; border-radius: 8px; display: flex; gap: 15px; align-items: center; box-shadow: 0 4px 15px rgba(102,126,234,0.4); }
    .wpwa-secondary-card .dashicons { font-size: 36px; width: 36px; height: 36px; }
    .wpwa-secondary-card .label { font-size: 12px; opacity: 0.9; }
    .wpwa-secondary-card .value { font-size: 24px; font-weight: 700; }
    
    .wpwa-recent-section { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .wpwa-recent-section h2 { margin-top: 0; }
    </style>
    <?php
}