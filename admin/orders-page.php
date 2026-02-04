<?php
/**
 * Orders Management Page - UNIFIED VIEW
 * Shows: Legacy WC Orders + New Stripe Transactions
 */

if (!defined('ABSPATH')) exit;

/**
 * Render unified orders page
 */
function wpwa_stripe_render_orders_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized', 'wpwa-stripe'));
    }
    
    // Get filter parameters
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
    $type_filter = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
    $source_filter = isset($_GET['source']) ? sanitize_text_field($_GET['source']) : ''; // NEW: legacy vs stripe
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    // Pagination
    $per_page = 25;
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($page - 1) * $per_page;
    
    // Get UNIFIED transactions (both legacy + stripe)
    $transactions = wpwa_get_unified_transactions(array(
        'status' => $status_filter,
        'type' => $type_filter,
        'source' => $source_filter,
        'search' => $search,
        'limit' => $per_page,
        'offset' => $offset
    ));
    
    $total_count = wpwa_get_unified_transaction_count($status_filter, $source_filter);
    $total_pages = ceil($total_count / $per_page);
    
    // Get UNIFIED stats
    $stats = wpwa_get_unified_revenue_stats();
    
    ?>
    <div class="wrap wpwa-orders-wrap">
        <h1>
            <span class="dashicons dashicons-cart"></span>
            Orders & Transactions
            <span class="wpwa-unified-badge">Unified View</span>
        </h1>
        
        <!-- Stats -->
        <div class="wpwa-stats-grid">
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(46,204,113,0.1); color: #2ecc71;">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Total Revenue (All Time)</div>
                    <div class="wpwa-stat-value"><?php echo wpwa_stripe_format_price($stats['total_revenue']); ?></div>
                    <small style="color: #666;">
                        Legacy: <?php echo wpwa_stripe_format_price($stats['legacy_revenue']); ?> | 
                        Stripe: <?php echo wpwa_stripe_format_price($stats['stripe_revenue']); ?>
                    </small>
                </div>
            </div>
            
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(52,152,219,0.1); color: #3498db;">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Total Orders</div>
                    <div class="wpwa-stat-value"><?php echo number_format($stats['total_count']); ?></div>
                    <small style="color: #666;">
                        Legacy: <?php echo number_format($stats['legacy_count']); ?> | 
                        Stripe: <?php echo number_format($stats['stripe_count']); ?>
                    </small>
                </div>
            </div>
            
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(155,89,182,0.1); color: #9b59b6;">
                    <span class="dashicons dashicons-update"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Active Subscriptions</div>
                    <div class="wpwa-stat-value"><?php echo number_format($stats['active_subscriptions']); ?></div>
                    <small style="color: #666;">
                        Legacy: <?php echo number_format($stats['legacy_subs']); ?> | 
                        Stripe: <?php echo number_format($stats['stripe_subs']); ?>
                    </small>
                </div>
            </div>
            
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(243,156,18,0.1); color: #f39c12;">
                    <span class="dashicons dashicons-tag"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Avg Order Value</div>
                    <div class="wpwa-stat-value"><?php echo wpwa_stripe_format_price($stats['avg_order_value']); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="wpwa-filters-bar">
            <form method="get" action="">
                <input type="hidden" name="page" value="wpwa-stripe-orders">
                
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="succeeded" <?php selected($status_filter, 'succeeded'); ?>>Succeeded/Completed</option>
                    <option value="pending" <?php selected($status_filter, 'pending'); ?>>Pending/Processing</option>
                    <option value="failed" <?php selected($status_filter, 'failed'); ?>>Failed</option>
                    <option value="refunded" <?php selected($status_filter, 'refunded'); ?>>Refunded</option>
                </select>
                
                <select name="type">
                    <option value="">All Types</option>
                    <option value="one_time" <?php selected($type_filter, 'one_time'); ?>>One-time</option>
                    <option value="subscription" <?php selected($type_filter, 'subscription'); ?>>Subscription</option>
                </select>
                
                <select name="source">
                    <option value="">All Sources</option>
                    <option value="stripe" <?php selected($source_filter, 'stripe'); ?>>🆕 Stripe (New)</option>
                    <option value="woocommerce" <?php selected($source_filter, 'woocommerce'); ?>>📦 WooCommerce (Legacy)</option>
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
                        <th>Source</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Product</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Status</th>
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
                        <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><strong>#<?php echo $transaction['id']; ?></strong></td>
                            <td><?php echo wpwa_get_source_badge($transaction['source']); ?></td>
                            <td><?php echo date('M j, Y g:i A', strtotime($transaction['date'])); ?></td>
                            <td><?php echo wpwa_stripe_get_type_badge($transaction['type']); ?></td>
                            <td><?php echo esc_html($transaction['product_name']); ?></td>
                            <td>
                                <code><?php echo esc_html($transaction['weebly_user_id']); ?></code>
                                <?php if ($transaction['customer_email']): ?>
                                    <br><small><?php echo esc_html($transaction['customer_email']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo wpwa_stripe_format_price($transaction['amount']); ?></strong></td>
                            <td><?php echo wpwa_stripe_get_status_badge($transaction['status']); ?></td>
                            <td>
                                <?php if ($transaction['source'] === 'stripe' && $transaction['stripe_payment_id']): ?>
                                    <a href="https://dashboard.stripe.com/<?php echo wpwa_stripe_is_test_mode() ? 'test/' : ''; ?>payments/<?php echo $transaction['stripe_payment_id']; ?>" 
                                       target="_blank" class="button button-small">View in Stripe</a>
                                <?php elseif ($transaction['source'] === 'woocommerce' && $transaction['wc_order_id']): ?>
                                    <span class="button button-small disabled">Legacy WC #<?php echo $transaction['wc_order_id']; ?></span>
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
    .wpwa-orders-wrap { background: #fff; padding: 20px; margin: 20px 20px 20px 0; }
    .wpwa-orders-wrap h1 { display: flex; align-items: center; gap: 10px; margin-bottom: 25px; }
    .wpwa-unified-badge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
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
    <?php
}

/**
 * Get unified transactions (Legacy WC + Stripe)
 */
function wpwa_get_unified_transactions($args = array()) {
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
    
    // Get Stripe transactions (if not filtering for legacy only)
    if ($args['source'] !== 'woocommerce') {
        $stripe_trans = wpwa_get_stripe_transactions_formatted($args);
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
 * Get Stripe transactions in unified format
 */
function wpwa_get_stripe_transactions_formatted($args) {
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
        
        $formatted[] = array(
            'id' => 'ST-' . $row['id'],
            'source' => 'stripe',
            'date' => $row['created_at'],
            'type' => $row['transaction_type'],
            'product_name' => $product ? $product['name'] : 'Unknown',
            'weebly_user_id' => $row['weebly_user_id'],
            'customer_email' => '', // Not stored in stripe transactions
            'amount' => $row['amount'],
            'status' => $row['status'],
            'stripe_payment_id' => $row['stripe_payment_intent_id'],
            'wc_order_id' => null
        );
    }
    
    return $formatted;
}

/**
 * Get Legacy WC transactions in unified format
 */
function wpwa_get_legacy_transactions_formatted($args) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_archived_orders';
    
    $where = array('1=1');
    $values = array();
    
    if ($args['status']) {
        $status_map = array(
            'succeeded' => array('completed', 'processing'),
            'pending' => array('pending', 'on-hold'),
            'failed' => array('failed', 'cancelled'),
            'refunded' => array('refunded')
        );
        if (isset($status_map[$args['status']])) {
            $placeholders = implode(',', array_fill(0, count($status_map[$args['status']]), '%s'));
            $where[] = "status IN ($placeholders)";
            $values = array_merge($values, $status_map[$args['status']]);
        }
    }
    
    if ($args['search']) {
        $where[] = 'weebly_user_id LIKE %s';
        $values[] = '%' . $wpdb->esc_like($args['search']) . '%';
    }
    
    $where_sql = implode(' AND ', $where);
    
    $query = "SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY order_date DESC LIMIT 1000";
    
    if (!empty($values)) {
        $query = $wpdb->prepare($query, $values);
    }
    
    $results = $wpdb->get_results($query, ARRAY_A);
    
    $formatted = array();
    foreach ($results as $row) {
        // Determine if it was subscription based on archived_subscriptions table
        $sub_table = $wpdb->prefix . 'wpwa_archived_subscriptions';
        $is_subscription = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$sub_table}` WHERE wc_order_id = %d",
            $row['wc_order_id']
        )) > 0;
        
        $status_map = array(
            'completed' => 'succeeded',
            'processing' => 'succeeded',
            'pending' => 'pending',
            'on-hold' => 'pending',
            'failed' => 'failed',
            'cancelled' => 'failed',
            'refunded' => 'refunded'
        );
        
        $formatted[] = array(
            'id' => 'WC-' . $row['wc_order_id'],
            'source' => 'woocommerce',
            'date' => $row['order_date'],
            'type' => $is_subscription ? 'subscription_initial' : 'one_time',
            'product_name' => $row['product_name'],
            'weebly_user_id' => $row['weebly_user_id'],
            'customer_email' => $row['customer_email'],
            'amount' => $row['amount'],
            'status' => $status_map[$row['status']] ?? $row['status'],
            'stripe_payment_id' => null,
            'wc_order_id' => $row['wc_order_id']
        );
    }
    
    return $formatted;
}

/**
 * Get unified transaction count
 */
function wpwa_get_unified_transaction_count($status = '', $source = '') {
    global $wpdb;
    
    $count = 0;
    
    // Stripe count
    if ($source !== 'woocommerce') {
        $table = $wpdb->prefix . 'wpwa_stripe_transactions';
        if ($status) {
            $count += $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `{$table}` WHERE status = %s",
                $status
            ));
        } else {
            $count += $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
        }
    }
    
    // Legacy count
    if ($source !== 'stripe') {
        $table = $wpdb->prefix . 'wpwa_archived_orders';
        if ($status) {
            $status_map = array(
                'succeeded' => array('completed', 'processing'),
                'pending' => array('pending', 'on-hold'),
                'failed' => array('failed', 'cancelled'),
                'refunded' => array('refunded')
            );
            if (isset($status_map[$status])) {
                $placeholders = implode(',', array_fill(0, count($status_map[$status]), '%s'));
                $count += $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM `{$table}` WHERE status IN ($placeholders)",
                    ...$status_map[$status]
                ));
            }
        } else {
            $count += $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
        }
    }
    
    return $count;
}

/**
 * Get unified revenue stats
 */
function wpwa_get_unified_revenue_stats() {
    global $wpdb;
    
    // Stripe stats
    $stripe_table = $wpdb->prefix . 'wpwa_stripe_transactions';
    $stripe_revenue = $wpdb->get_var(
        "SELECT SUM(amount) FROM `{$stripe_table}` WHERE status = 'succeeded'"
    ) ?: 0;
    $stripe_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$stripe_table}`") ?: 0;
    
    // Legacy stats
    $legacy_table = $wpdb->prefix . 'wpwa_archived_orders';
    $legacy_revenue = $wpdb->get_var(
        "SELECT SUM(amount) FROM `{$legacy_table}` WHERE status IN ('completed', 'processing')"
    ) ?: 0;
    $legacy_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$legacy_table}`") ?: 0;
    
    // Subscription stats
    $stripe_subs = wpwa_stripe_get_active_subscriptions_count() ?: 0;
    
    $legacy_sub_table = $wpdb->prefix . 'wpwa_archived_subscriptions';
    $legacy_subs = $wpdb->get_var(
        "SELECT COUNT(*) FROM `{$legacy_sub_table}` 
         WHERE status = 'active' 
         AND (expiry_date IS NULL OR expiry_date > NOW())"
    ) ?: 0;
    
    $total_revenue = $stripe_revenue + $legacy_revenue;
    $total_count = $stripe_count + $legacy_count;
    
    return array(
        'total_revenue' => $total_revenue,
        'stripe_revenue' => $stripe_revenue,
        'legacy_revenue' => $legacy_revenue,
        'total_count' => $total_count,
        'stripe_count' => $stripe_count,
        'legacy_count' => $legacy_count,
        'active_subscriptions' => $stripe_subs + $legacy_subs,
        'stripe_subs' => $stripe_subs,
        'legacy_subs' => $legacy_subs,
        'avg_order_value' => $total_count > 0 ? $total_revenue / $total_count : 0
    );
}

/**
 * Get source badge HTML
 */
function wpwa_get_source_badge($source) {
    $badges = array(
        'stripe' => '<span style="background: #635bff; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">🆕 Stripe</span>',
        'woocommerce' => '<span style="background: #96588a; color: #fff; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">📦 WooCommerce</span>'
    );
    
    return $badges[$source] ?? '<span>' . esc_html($source) . '</span>';
}

/**
 * Get Stripe transaction type badge HTML
 */
function wpwa_stripe_get_type_badge($type) {
    $badges = array(
        'one_time'     => '<span style="background: #e3e8ee; color: #4f5b66; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">🛍️ One-time</span>',
        'subscription' => '<span style="background: #e1f5fe; color: #01579b; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 600;">🔄 Subscription</span>'
    );

    // Return the badge if it exists, otherwise return the sanitized raw type
    return $badges[$type] ?? '<span style="font-size: 11px; font-weight: 600;">' . esc_html($type) . '</span>';
}

/**
 * Get Stripe transaction status badge HTML
 */
function wpwa_stripe_get_status_badge($status) {
    $badges = array(
        'succeeded' => '<span style="background: #e6fffa; color: #047481; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase;">✅ Succeeded</span>',
        'pending'   => '<span style="background: #fffbeb; color: #92400e; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase;">⏳ Pending</span>',
        'failed'    => '<span style="background: #fef2f2; color: #991b1b; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase;">❌ Failed</span>',
        'refunded'  => '<span style="background: #f3f4f6; color: #374151; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase;">↩️ Refunded</span>'
    );

    // Return the badge if it exists, otherwise return a default neutral badge
    return $badges[$status] ?? '<span style="background: #f8f9fa; color: #6c757d; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase;">' . esc_html($status) . '</span>';
}