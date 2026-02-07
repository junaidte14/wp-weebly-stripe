<?php
/**
 * Stripe Customers Management Page
 */

if (!defined('ABSPATH')) exit;

/**
 * Render Stripe customers page
 */
function wpwa_stripe_render_customers_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized', 'wpwa-stripe'));
    }
    
    // Get filter parameters
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    
    // Pagination
    $per_page = 25;
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($page - 1) * $per_page;
    
    // Get customers
    $customers = wpwa_stripe_get_all_customers($per_page, $offset);
    
    // Apply search filter if needed
    if ($search) {
        $customers = array_filter($customers, function($c) use ($search) {
            return stripos($c['weebly_user_id'], $search) !== false || 
                   stripos($c['email'], $search) !== false ||
                   stripos($c['name'], $search) !== false;
        });
    }
    
    $total_count = wpwa_stripe_get_customer_count();
    $total_pages = ceil($total_count / $per_page);
    
    // Get stats
    $total_customers = wpwa_stripe_get_customer_count();
    
    ?>
    <div class="wrap wpwa-customers-wrap">
        <h1>
            <span class="dashicons dashicons-admin-users"></span>
            Stripe Customers
        </h1>
        
        <!-- Stats -->
        <div class="wpwa-stats-grid">
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(52,152,219,0.1); color: #3498db;">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Total Customers</div>
                    <div class="wpwa-stat-value"><?php echo number_format($total_customers); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="wpwa-filters-bar">
            <form method="get" action="">
                <input type="hidden" name="page" value="wpwa-stripe-customers">
                <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search by User ID, Email, or Name...">
                <button type="submit" class="button">Search</button>
                <a href="?page=wpwa-stripe-customers" class="button">Reset</a>
            </form>
        </div>
        
        <!-- Customers Table -->
        <div class="wpwa-table-container">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Stripe Customer ID</th>
                        <th>Weebly User ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Lifetime Value</th>
                        <th>Active Subscriptions</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 40px;">
                                No customers found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($customers as $customer): 
                            $lifetime_value = wpwa_stripe_get_customer_lifetime_value($customer['weebly_user_id']);
                            $subscriptions = wpwa_stripe_get_user_subscriptions($customer['weebly_user_id'], 'active');
                            $active_subs_count = count($subscriptions);
                        ?>
                        <tr>
                            <td><strong>#<?php echo $customer['id']; ?></strong></td>
                            <td>
                                <small><code><?php echo substr($customer['stripe_customer_id'], 0, 20); ?>...</code></small>
                            </td>
                            <td><code><?php echo esc_html($customer['weebly_user_id']); ?></code></td>
                            <td><?php echo esc_html($customer['name']); ?></td>
                            <td>
                                <a href="mailto:<?php echo esc_attr($customer['email']); ?>">
                                    <?php echo esc_html($customer['email']); ?>
                                </a>
                            </td>
                            <td><strong><?php echo wpwa_stripe_format_price($lifetime_value ?: 0); ?></strong></td>
                            <td>
                                <?php if ($active_subs_count > 0): ?>
                                    <span style="color: #2ecc71; font-weight: 600;"><?php echo $active_subs_count; ?> Active</span>
                                <?php else: ?>
                                    <span style="color: #999;">None</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($customer['created_at'])); ?></td>
                            <td>
                                <a href="https://dashboard.stripe.com/<?php echo wpwa_stripe_is_test_mode() ? 'test/' : ''; ?>customers/<?php echo $customer['stripe_customer_id']; ?>" 
                                   target="_blank" class="button button-small">View in Stripe</a>
                                
                                <a href="?page=wpwa-stripe-orders&s=<?php echo urlencode($customer['weebly_user_id']); ?>" 
                                   class="button button-small">View Orders</a>
                                
                                <?php if ($active_subs_count > 0): ?>
                                    <a href="?page=wpwa-stripe-subscriptions&s=<?php echo urlencode($customer['weebly_user_id']); ?>" 
                                       class="button button-small">View Subscriptions</a>
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
    .wpwa-customers-wrap { background: #fff; padding: 20px; margin: 20px 20px 20px 0; }
    .wpwa-customers-wrap h1 { display: flex; align-items: center; gap: 10px; margin-bottom: 25px; }
    .wpwa-customers-wrap .dashicons { font-size: 32px; width: 32px; height: 32px; }
    
    .wpwa-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px; }
    .wpwa-stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); display: flex; gap: 15px; align-items: center; }
    .wpwa-stat-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
    .wpwa-stat-icon .dashicons { font-size: 24px; width: 24px; height: 24px; }
    .wpwa-stat-label { font-size: 12px; color: #666; text-transform: uppercase; margin-bottom: 5px; }
    .wpwa-stat-value { font-size: 24px; font-weight: 700; color: #1d2327; }
    
    .wpwa-filters-bar { background: #f9f9f9; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
    .wpwa-filters-bar form { display: flex; gap: 10px; flex-wrap: wrap; }
    .wpwa-filters-bar input[type="search"] { padding: 6px 10px; min-width: 300px; }
    
    .wpwa-table-container { overflow-x: auto; }
    .wpwa-pagination { text-align: center; padding: 20px 0; }
    .wpwa-pagination .page-numbers { display: inline-block; padding: 8px 12px; margin: 0 2px; background: #fff; border: 1px solid #ddd; text-decoration: none; }
    .wpwa-pagination .page-numbers.current { background: #2271b1; color: #fff; border-color: #2271b1; }
    </style>
    <?php
}