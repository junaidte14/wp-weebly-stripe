<?php
/**
 * Stripe Subscriptions Management Page
 */

if (!defined('ABSPATH')) exit;

/**
 * Render Stripe subscriptions page
 */
function wpwa_stripe_render_subscriptions_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized', 'wpwa-stripe'));
    }
    
    // Handle actions
    if (isset($_GET['action']) && isset($_GET['subscription_id']) && isset($_GET['_wpnonce'])) {
        $action = sanitize_text_field($_GET['action']);
        $subscription_id = sanitize_text_field($_GET['subscription_id']);
        
        if ($action === 'cancel' && wp_verify_nonce($_GET['_wpnonce'], 'cancel_subscription_' . $subscription_id)) {
            $result = wpwa_stripe_cancel_subscription($subscription_id, false);
            if ($result) {
                echo '<div class="notice notice-success"><p>Subscription cancelled successfully.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Failed to cancel subscription.</p></div>';
            }
        } elseif ($action === 'reactivate' && wp_verify_nonce($_GET['_wpnonce'], 'reactivate_subscription_' . $subscription_id)) {
            $result = wpwa_stripe_reactivate_subscription($subscription_id);
            if ($result) {
                echo '<div class="notice notice-success"><p>Subscription reactivated successfully.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Failed to reactivate subscription.</p></div>';
            }
        }
    }
    
    // Get filter parameters
    $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'active';
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    // Get subscriptions
    $subscriptions = wpwa_stripe_get_all_subscriptions(array(
        'status' => $status_filter,
        'search' => $search
    ));
    
    // Get stats
    $active_count = wpwa_stripe_get_active_subscriptions_count();
    //$mrr = wpwa_stripe_get_mrr();
    $expiring_soon = wpwa_stripe_get_expiring_subscriptions(7);
    $expiring_count = count($expiring_soon);
    
    ?>
    <div class="wrap wpwa-subscriptions-wrap">
        <h1>
            <span class="dashicons dashicons-update"></span>
            Stripe Subscriptions
        </h1>
        
        <!-- Stats -->
        <div class="wpwa-stats-grid">
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(46,204,113,0.1); color: #2ecc71;">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Active Subscriptions</div>
                    <div class="wpwa-stat-value"><?php echo number_format($active_count); ?></div>
                </div>
            </div>
            
            <!-- <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(52,152,219,0.1); color: #3498db;">
                    <span class="dashicons dashicons-money-alt"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Monthly Recurring Revenue</div>
                    <div class="wpwa-stat-value"><?php echo wpwa_stripe_format_price($mrr); ?></div>
                </div>
            </div> -->
            
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(243,156,18,0.1); color: #f39c12;">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Expiring in 7 Days</div>
                    <div class="wpwa-stat-value"><?php echo number_format($expiring_count); ?></div>
                </div>
            </div>
            
            <!-- <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(155,89,182,0.1); color: #9b59b6;">
                    <span class="dashicons dashicons-chart-line"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Avg Subscription Value</div>
                    <div class="wpwa-stat-value"><?php echo wpwa_stripe_format_price($active_count > 0 ? $mrr / $active_count : 0); ?></div>
                </div>
            </div> -->
        </div>
        
        <!-- Filters -->
        <div class="wpwa-filters-bar">
            <form method="get" action="">
                <input type="hidden" name="page" value="wpwa-stripe-subscriptions">
                
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="active" <?php selected($status_filter, 'active'); ?>>Active</option>
                    <option value="past_due" <?php selected($status_filter, 'past_due'); ?>>Past Due</option>
                    <option value="canceled" <?php selected($status_filter, 'canceled'); ?>>Canceled</option>
                    <option value="incomplete" <?php selected($status_filter, 'incomplete'); ?>>Incomplete</option>
                </select>
                
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search by User ID...">
                
                <button type="submit" class="button">Filter</button>
                <a href="?page=wpwa-stripe-subscriptions" class="button">Reset</a>
            </form>
        </div>
        
        <!-- Subscriptions Table -->
        <div class="wpwa-table-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Status</th>
                        <th>Current Period</th>
                        <th>Next Billing</th>
                        <th>Cancel at Period End</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subscriptions)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px;">
                                No subscriptions found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($subscriptions as $subscription): 
                            $product = wpwa_stripe_get_product($subscription['product_id']);
                            $product_name = $product ? $product['name'] : 'Unknown Product';
                        ?>
                        <tr>
                            <td>
                                <small><code><?php echo substr($subscription['stripe_subscription_id'], 0, 20); ?>...</code></small>
                            </td>
                            <td>
                                <code><?php echo esc_html($subscription['weebly_user_id']); ?></code>
                                <?php if ($subscription['weebly_site_id']): ?>
                                    <br><small>Site: <?php echo esc_html($subscription['weebly_site_id']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($product_name); ?></td>
                            <td><?php echo wpwa_get_subscription_status_badge($subscription['status']); ?></td>
                            <td>
                                <?php echo date('M j, Y', strtotime($subscription['current_period_start'])); ?>
                                <br>
                                <small>to <?php echo date('M j, Y', strtotime($subscription['current_period_end'])); ?></small>
                            </td>
                            <td>
                                <?php if ($subscription['status'] === 'active' && !$subscription['cancel_at_period_end']): ?>
                                    <strong><?php echo date('M j, Y', strtotime($subscription['current_period_end'])); ?></strong>
                                <?php else: ?>
                                    <span style="color: #999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($subscription['cancel_at_period_end']): ?>
                                    <span style="color: #e74c3c; font-weight: 600;">✓ Yes</span>
                                <?php else: ?>
                                    <span style="color: #999;">No</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($subscription['created_at'])); ?></td>
                            <td>
                                <a href="https://dashboard.stripe.com/<?php echo wpwa_stripe_is_test_mode() ? 'test/' : ''; ?>subscriptions/<?php echo $subscription['stripe_subscription_id']; ?>" 
                                   target="_blank" class="button button-small">View in Stripe</a>
                                
                                <?php if ($subscription['status'] === 'active'): ?>
                                    <?php if ($subscription['cancel_at_period_end']): ?>
                                        <a href="?page=wpwa-stripe-subscriptions&action=reactivate&subscription_id=<?php echo $subscription['stripe_subscription_id']; ?>&_wpnonce=<?php echo wp_create_nonce('reactivate_subscription_' . $subscription['stripe_subscription_id']); ?>" 
                                           class="button button-small"
                                           onclick="return confirm('Reactivate this subscription?')">
                                            Reactivate
                                        </a>
                                    <?php else: ?>
                                        <a href="?page=wpwa-stripe-subscriptions&action=cancel&subscription_id=<?php echo $subscription['stripe_subscription_id']; ?>&_wpnonce=<?php echo wp_create_nonce('cancel_subscription_' . $subscription['stripe_subscription_id']); ?>" 
                                           class="button button-small"
                                           onclick="return confirm('Cancel this subscription at period end?')">
                                            Cancel
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <style>
    .wpwa-subscriptions-wrap { background: #fff; padding: 20px; margin: 20px 20px 20px 0; }
    .wpwa-subscriptions-wrap h1 { display: flex; align-items: center; gap: 10px; margin-bottom: 25px; }
    .wpwa-subscriptions-wrap .dashicons { font-size: 32px; width: 32px; height: 32px; }
    
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
    </style>
    <?php
}

/**
 * Get all subscriptions with filters
 */
function wpwa_stripe_get_all_subscriptions($args = array()) {
    global $wpdb;
    
    $defaults = array(
        'status' => '',
        'search' => '',
        'limit' => 100,
        'offset' => 0
    );
    
    $args = wp_parse_args($args, $defaults);
    
    $table = $wpdb->prefix . 'wpwa_stripe_subscriptions';
    
    $where = array('1=1');
    $values = array();
    
    if ($args['status']) {
        $where[] = 'status = %s';
        $values[] = $args['status'];
    }
    
    if ($args['search']) {
        $where[] = 'weebly_user_id LIKE %s';
        $values[] = '%' . $wpdb->esc_like($args['search']) . '%';
    }
    
    $where_sql = implode(' AND ', $where);
    
    $query = "SELECT * FROM `{$table}` WHERE {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
    $values[] = $args['limit'];
    $values[] = $args['offset'];
    
    if (!empty($values)) {
        $query = $wpdb->prepare($query, $values);
    }
    
    return $wpdb->get_results($query, ARRAY_A);
}

/**
 * Get subscription status badge
 */
function wpwa_get_subscription_status_badge($status) {
    $badges = array(
        'active' => '<span style="background: #e6fffa; color: #047481; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700;">✅ Active</span>',
        'past_due' => '<span style="background: #fffbeb; color: #92400e; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700;">⚠️ Past Due</span>',
        'canceled' => '<span style="background: #fef2f2; color: #991b1b; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700;">❌ Canceled</span>',
        'incomplete' => '<span style="background: #f3f4f6; color: #374151; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700;">⏸️ Incomplete</span>',
        'trialing' => '<span style="background: #e0e7ff; color: #3730a3; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700;">🎁 Trialing</span>'
    );
    
    return $badges[$status] ?? '<span>' . esc_html($status) . '</span>';
}