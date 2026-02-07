<?php
/**
 * Analytics Dashboard - UNIFIED VIEW (UPDATED)
 * Shows: Legacy WC + Stripe Combined + Stripe Subscriptions
 */

if (!defined('ABSPATH')) exit;

/**
 * Render unified analytics page
 */
function wpwa_stripe_render_analytics_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized', 'wpwa-stripe'));
    }
    
    // Get UNIFIED stats
    $stats = wpwa_get_unified_revenue_stats();
    
    // Get recent transactions (unified) - now includes subscription renewals
    $recent_transactions = wpwa_get_unified_transactions_with_subscriptions(array('limit' => 10));
    
    // Get revenue by source (for chart)
    $revenue_by_source = array(
        array('Source', 'Revenue'),
        array('Stripe (New)', floatval($stats['stripe_revenue'])),
        array('WooCommerce (Legacy)', floatval($stats['legacy_revenue']))
    );
    
    ?>
    <div class="wrap wpwa-analytics-wrap">
        <h1>
            <span class="dashicons dashicons-chart-area"></span>
            Analytics Dashboard
            <span class="wpwa-unified-badge">Unified View</span>
        </h1>
        
        <!-- Key Metrics -->
        <div class="wpwa-metrics-grid">
            <div class="wpwa-metric-card" style="border-left-color: #2ecc71;">
                <div class="wpwa-metric-icon" style="background: rgba(46,204,113,0.1); color: #2ecc71;">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="wpwa-metric-content">
                    <div class="wpwa-metric-label">Total Revenue (All Time)</div>
                    <div class="wpwa-metric-value"><?php echo wpwa_stripe_format_price($stats['total_revenue']); ?></div>
                    <div class="wpwa-metric-breakdown">
                        <small>Stripe: <?php echo wpwa_stripe_format_price($stats['stripe_revenue']); ?></small>
                        <small>Legacy: <?php echo wpwa_stripe_format_price($stats['legacy_revenue']); ?></small>
                    </div>
                </div>
            </div>
            
            <div class="wpwa-metric-card" style="border-left-color: #3498db;">
                <div class="wpwa-metric-icon" style="background: rgba(52,152,219,0.1); color: #3498db;">
                    <span class="dashicons dashicons-cart"></span>
                </div>
                <div class="wpwa-metric-content">
                    <div class="wpwa-metric-label">Total Orders</div>
                    <div class="wpwa-metric-value"><?php echo number_format($stats['total_count']); ?></div>
                    <div class="wpwa-metric-breakdown">
                        <small>Stripe: <?php echo number_format($stats['stripe_count']); ?></small>
                        <small>Legacy: <?php echo number_format($stats['legacy_count']); ?></small>
                    </div>
                </div>
            </div>
            
            <div class="wpwa-metric-card" style="border-left-color: #9b59b6;">
                <div class="wpwa-metric-icon" style="background: rgba(155,89,182,0.1); color: #9b59b6;">
                    <span class="dashicons dashicons-update"></span>
                </div>
                <div class="wpwa-metric-content">
                    <div class="wpwa-metric-label">Active Subscriptions</div>
                    <div class="wpwa-metric-value"><?php echo number_format($stats['active_subscriptions']); ?></div>
                    <div class="wpwa-metric-breakdown">
                        <small>Stripe: <?php echo number_format($stats['stripe_subs']); ?></small>
                        <small>Legacy: <?php echo number_format($stats['legacy_subs']); ?></small>
                    </div>
                </div>
            </div>
            
            <div class="wpwa-metric-card" style="border-left-color: #f39c12;">
                <div class="wpwa-metric-icon" style="background: rgba(243,156,18,0.1); color: #f39c12;">
                    <span class="dashicons dashicons-tag"></span>
                </div>
                <div class="wpwa-metric-content">
                    <div class="wpwa-metric-label">Avg Order Value</div>
                    <div class="wpwa-metric-value"><?php echo wpwa_stripe_format_price($stats['avg_order_value']); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Revenue Breakdown Chart -->
        <div class="wpwa-chart-section">
            <h2>Revenue by Source</h2>
            <div id="revenue-chart" style="height: 300px; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);"></div>
        </div>
        
        <!-- Recent Transactions -->
        <div class="wpwa-recent-section">
            <h2>Recent Transactions</h2>
            <div class="wpwa-table-container">
                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th>Source</th>
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
                                <td colspan="6" style="text-align: center; padding: 20px;">No transactions yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_transactions as $transaction): ?>
                            <tr>
                                <td><?php echo wpwa_get_source_badge($transaction['source']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($transaction['date'])); ?></td>
                                <td><?php echo esc_html($transaction['product_name']); ?></td>
                                <td>
                                    <code><?php echo esc_html($transaction['weebly_user_id']); ?></code>
                                    <?php if ($transaction['customer_email']): ?>
                                        <br><small><?php echo esc_html($transaction['customer_email']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?php echo wpwa_stripe_format_price($transaction['amount']); ?></strong></td>
                                <td><?php echo wpwa_stripe_get_status_badge($transaction['status']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Migration Status Panel -->
        <div class="wpwa-migration-status">
            <h2>📊 Data Migration Status</h2>
            <div class="wpwa-migration-info">
                <p><strong>Legacy System:</strong> <?php echo number_format($stats['legacy_count']); ?> WooCommerce orders migrated and preserved</p>
                <p><strong>New System:</strong> <?php echo number_format($stats['stripe_count']); ?> Stripe transactions processed</p>
                <p><strong>Total Historical Data:</strong> <?php echo number_format($stats['total_count']); ?> orders across all systems</p>
                <a href="?page=wpwa-stripe-orders&source=woocommerce" class="button">View Legacy Orders</a>
                <a href="?page=wpwa-stripe-orders&source=stripe" class="button">View Stripe Orders</a>
            </div>
        </div>
    </div>
    
    <style>
    .wpwa-analytics-wrap { background: #f5f5f5; padding: 20px; margin: 20px 20px 20px 0; }
    .wpwa-analytics-wrap h1 { display: flex; align-items: center; gap: 10px; margin-bottom: 25px; }
    .wpwa-unified-badge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
    .wpwa-analytics-wrap .dashicons { font-size: 32px; width: 32px; height: 32px; }
    
    .wpwa-metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .wpwa-metric-card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); display: flex; gap: 20px; align-items: center; border-left: 4px solid; }
    .wpwa-metric-icon { width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
    .wpwa-metric-icon .dashicons { font-size: 32px; width: 32px; height: 32px; }
    .wpwa-metric-label { font-size: 13px; color: #666; margin-bottom: 8px; text-transform: uppercase; }
    .wpwa-metric-value { font-size: 32px; font-weight: 700; color: #1d2327; }
    .wpwa-metric-breakdown { margin-top: 8px; display: flex; gap: 15px; }
    .wpwa-metric-breakdown small { color: #666; font-size: 12px; }
    
    .wpwa-chart-section { margin-bottom: 25px; }
    .wpwa-recent-section { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 25px; }
    .wpwa-recent-section h2 { margin-top: 0; }
    
    .wpwa-migration-status { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(102,126,234,0.4); }
    .wpwa-migration-status h2 { margin-top: 0; color: #fff; }
    .wpwa-migration-info p { margin: 10px 0; }
    .wpwa-migration-info .button { margin-right: 10px; margin-top: 15px; }
    </style>
    
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script>
    google.charts.load('current', {'packages':['corechart']});
    google.charts.setOnLoadCallback(drawChart);

    function drawChart() {
        var data = google.visualization.arrayToDataTable(<?php echo json_encode($revenue_by_source); ?>);

        var options = {
            title: 'Revenue Distribution',
            pieHole: 0.4,
            colors: ['#635bff', '#96588a'],
            legend: { position: 'bottom' }
        };

        var chart = new google.visualization.PieChart(document.getElementById('revenue-chart'));
        chart.draw(data, options);
    }
    </script>
    <?php
}

/**
 * Get unified transactions INCLUDING subscription renewals
 */
function wpwa_get_unified_transactions_with_subscriptions($args = array()) {
    global $wpdb;
    
    $defaults = array(
        'status' => '',
        'type' => '',
        'source' => '',
        'search' => '',
        'limit' => 25,
        'offset' => 0,
        'orderby' => 'date',
        'order' => 'DESC'
    );
    
    $args = wp_parse_args($args, $defaults);
    
    $transactions = array();
    
    // Get Stripe transactions (ALL types including subscription_initial and subscription_renewal)
    if ($args['source'] !== 'woocommerce') {
        $stripe_trans = wpwa_get_stripe_transactions_formatted_all($args);
        $transactions = array_merge($transactions, $stripe_trans);
    }
    
    // Get Legacy WC transactions (if not filtering for stripe only)
    if ($args['source'] !== 'stripe') {
        $legacy_trans = wpwa_get_legacy_transactions_formatted($args);
        $transactions = array_merge($transactions, $legacy_trans);
    }
    
    // Sort by date
    usort($transactions, function($a, $b) use ($args) {
        $time_a = strtotime($a['date']);
        $time_b = strtotime($b['date']);
        return $args['order'] === 'DESC' ? $time_b - $time_a : $time_a - $time_b;
    });
    
    // Apply limit/offset
    return array_slice($transactions, $args['offset'], $args['limit']);
}

/**
 * Get Stripe transactions in unified format (ALL transaction types)
 */
function wpwa_get_stripe_transactions_formatted_all($args) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_stripe_transactions';
    
    $where = array('1=1');
    $values = array();
    
    if ($args['status']) {
        $status_map = array(
            'succeeded' => 'succeeded',
            'pending' => 'pending',
            'failed' => 'failed',
            'refunded' => 'refunded'
        );
        if (isset($status_map[$args['status']])) {
            $where[] = 'status = %s';
            $values[] = $status_map[$args['status']];
        }
    }
    
    if ($args['type'] === 'one_time') {
        $where[] = 'transaction_type = %s';
        $values[] = 'one_time';
    } elseif ($args['type'] === 'subscription') {
        $where[] = 'transaction_type IN (%s, %s)';
        $values[] = 'subscription_initial';
        $values[] = 'subscription_renewal';
    }
    
    if ($args['search']) {
        $where[] = 'weebly_user_id LIKE %s';
        $values[] = '%' . $wpdb->esc_like($args['search']) . '%';
    }
    
    $where_sql = implode(' AND ', $where);
    
    $query = "SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY created_at DESC LIMIT 1000";
    
    if (!empty($values)) {
        $query = $wpdb->prepare($query, $values);
    }
    
    $results = $wpdb->get_results($query, ARRAY_A);
    
    $formatted = array();
    foreach ($results as $row) {
        $product = wpwa_stripe_get_product($row['product_id']);
        
        // Get customer email if available
        $customer_email = '';
        if ($row['stripe_customer_id']) {
            $customer = wpwa_stripe_get_customer_by_stripe_id($row['stripe_customer_id']);
            if ($customer) {
                $customer_email = $customer['email'];
            }
        }
        
        $formatted[] = array(
            'id' => 'ST-' . $row['id'],
            'source' => 'stripe',
            'date' => $row['created_at'],
            'type' => $row['transaction_type'],
            'product_name' => $product ? $product['name'] : 'Unknown',
            'weebly_user_id' => $row['weebly_user_id'],
            'customer_email' => $customer_email,
            'amount' => $row['amount'],
            'status' => $row['status'],
            'stripe_payment_id' => $row['stripe_payment_intent_id'],
            'wc_order_id' => null
        );
    }
    
    return $formatted;
}