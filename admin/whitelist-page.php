<?php
/**
 * Whitelist Management Page
 */

if (!defined('ABSPATH')) exit;

/**
 * Render whitelist management page
 */
function wpwa_stripe_render_whitelist_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Unauthorized', 'wpwa-stripe'));
    }
    
    // Handle form submissions
    if (isset($_POST['wpwa_add_whitelist_nonce']) && 
        wp_verify_nonce($_POST['wpwa_add_whitelist_nonce'], 'wpwa_add_whitelist')) {
        
        $result = wpwa_add_to_whitelist(array(
            'weebly_user_id' => sanitize_text_field($_POST['weebly_user_id']),
            'product_id' => absint($_POST['product_id']),
            'reason' => sanitize_textarea_field($_POST['reason']),
            'expiry_date' => !empty($_POST['expiry_date']) ? sanitize_text_field($_POST['expiry_date']) : null
        ));
        
        if ($result) {
            echo '<div class="notice notice-success"><p>User added to whitelist successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Failed to add user to whitelist.</p></div>';
        }
    }
    
    // Handle CSV import
    if (isset($_POST['wpwa_import_whitelist_nonce']) && 
        wp_verify_nonce($_POST['wpwa_import_whitelist_nonce'], 'wpwa_import_whitelist')) {
        
        if (!empty($_FILES['whitelist_csv']['tmp_name'])) {
            $product_id = absint($_POST['import_product_id']);
            $result = wpwa_import_whitelist_csv($_FILES['whitelist_csv']['tmp_name'], $product_id);
            
            echo '<div class="notice notice-success"><p>';
            printf('Import complete! Success: %d, Failed: %d', $result['success'], $result['failed']);
            if (!empty($result['errors'])) {
                echo '<br>Errors: ' . implode(', ', array_slice($result['errors'], 0, 5));
            }
            echo '</p></div>';
        }
    }
    
    // Handle revoke
    if (isset($_GET['action']) && $_GET['action'] === 'revoke' && isset($_GET['id'])) {
        check_admin_referer('wpwa_revoke_whitelist_' . $_GET['id']);
        
        global $wpdb;
        $table = $wpdb->prefix . 'wpwa_whitelist';
        
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$table}` WHERE id = %d",
            absint($_GET['id'])
        ), ARRAY_A);
        
        if ($entry) {
            wpwa_remove_from_whitelist($entry['weebly_user_id'], $entry['product_id']);
            echo '<div class="notice notice-success"><p>Whitelist entry revoked.</p></div>';
        }
    }
    
    // Get stats
    $active_count = wpwa_get_whitelist_count('active');
    $revoked_count = wpwa_get_whitelist_count('revoked');
    
    // Get entries
    $entries = wpwa_get_whitelist_entries(array('limit' => 50));
    
    ?>
    <div class="wrap wpwa-whitelist-wrap">
        <h1>
            <span class="dashicons dashicons-admin-users"></span>
            Whitelist Management
        </h1>
        
        <!-- Stats -->
        <div class="wpwa-stats-grid">
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(46,204,113,0.1); color: #2ecc71;">
                    <span class="dashicons dashicons-yes-alt"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Active Whitelist Entries</div>
                    <div class="wpwa-stat-value"><?php echo number_format($active_count); ?></div>
                </div>
            </div>
            
            <div class="wpwa-stat-card">
                <div class="wpwa-stat-icon" style="background: rgba(231,76,60,0.1); color: #e74c3c;">
                    <span class="dashicons dashicons-dismiss"></span>
                </div>
                <div class="wpwa-stat-content">
                    <div class="wpwa-stat-label">Revoked Entries</div>
                    <div class="wpwa-stat-value"><?php echo number_format($revoked_count); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Add New User -->
        <div class="wpwa-add-whitelist-section">
            <h2>Add User to Whitelist</h2>
            <form method="post" class="wpwa-whitelist-form">
                <?php wp_nonce_field('wpwa_add_whitelist', 'wpwa_add_whitelist_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="weebly_user_id">Weebly User ID *</label></th>
                        <td>
                            <input type="text" id="weebly_user_id" name="weebly_user_id" 
                                   class="regular-text" required>
                            <p class="description">The Weebly user ID to grant access</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="product_id">Product *</label></th>
                        <td>
                            <select id="product_id" name="product_id" required>
                                <option value="">Select Product</option>
                                <?php
                                $products = wpwa_stripe_get_all_products();
                                foreach ($products as $product) {
                                    echo '<option value="' . $product['id'] . '">' . esc_html($product['name']) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="reason">Reason</label></th>
                        <td>
                            <textarea id="reason" name="reason" rows="3" class="large-text"></textarea>
                            <p class="description">Why is this user being whitelisted?</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="expiry_date">Expiry Date</label></th>
                        <td>
                            <input type="datetime-local" id="expiry_date" name="expiry_date">
                            <p class="description">Leave empty for permanent access</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt"></span>
                        Add to Whitelist
                    </button>
                </p>
            </form>
        </div>
        
        <hr>
        
        <!-- Bulk Import -->
        <div class="wpwa-import-section">
            <h2>Bulk Import from CSV</h2>
            <p>Upload a CSV file with columns: <code>weebly_user_id, reason, expiry_date</code></p>
            <p><a href="<?php echo WPWA_STRIPE_URL . 'admin/sample-whitelist.csv'; ?>" class="button">Download Sample CSV</a></p>
            
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('wpwa_import_whitelist', 'wpwa_import_whitelist_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th><label for="import_product_id">Product</label></th>
                        <td>
                            <select id="import_product_id" name="import_product_id" required>
                                <option value="">Select Product</option>
                                <?php
                                foreach ($products as $product) {
                                    echo '<option value="' . $product['id'] . '">' . esc_html($product['name']) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="whitelist_csv">CSV File</label></th>
                        <td>
                            <input type="file" id="whitelist_csv" name="whitelist_csv" accept=".csv" required>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button">
                        <span class="dashicons dashicons-upload"></span>
                        Import CSV
                    </button>
                </p>
            </form>
        </div>
        
        <hr>
        
        <!-- Whitelist Entries Table -->
        <h2>Current Whitelist Entries</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Weebly User ID</th>
                    <th>Product</th>
                    <th>Granted By</th>
                    <th>Reason</th>
                    <th>Expiry</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($entries)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px;">
                            No whitelist entries yet.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($entries as $entry): 
                        $product = wpwa_stripe_get_product($entry['product_id']);
                        $product_name = $product ? $product['name'] : 'Unknown Product';
                    ?>
                    <tr>
                        <td><?php echo $entry['id']; ?></td>
                        <td><code><?php echo esc_html($entry['weebly_user_id']); ?></code></td>
                        <td><?php echo esc_html($product_name); ?></td>
                        <td><?php echo esc_html($entry['granted_by']); ?></td>
                        <td><?php echo esc_html($entry['reason']); ?></td>
                        <td>
                            <?php 
                            if ($entry['expiry_date']) {
                                echo date('Y-m-d H:i', strtotime($entry['expiry_date']));
                            } else {
                                echo '∞ Permanent';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($entry['status'] === 'active'): ?>
                                <span style="color: #2ecc71;">✓ Active</span>
                            <?php else: ?>
                                <span style="color: #e74c3c;">✗ Revoked</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('Y-m-d', strtotime($entry['created_at'])); ?></td>
                        <td>
                            <?php if ($entry['status'] === 'active'): ?>
                                <a href="?page=wpwa-stripe-whitelist&action=revoke&id=<?php echo $entry['id']; ?>&_wpnonce=<?php echo wp_create_nonce('wpwa_revoke_whitelist_' . $entry['id']); ?>" 
                                   class="button button-small"
                                   onclick="return confirm('Revoke whitelist access for this user?')">
                                    Revoke
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <style>
    .wpwa-whitelist-wrap { background: #fff; padding: 20px; margin: 20px 20px 20px 0; }
    .wpwa-whitelist-wrap h1 { display: flex; align-items: center; gap: 10px; }
    .wpwa-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 25px 0; }
    .wpwa-stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); display: flex; gap: 15px; align-items: center; }
    .wpwa-stat-icon { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
    .wpwa-stat-icon .dashicons { font-size: 24px; width: 24px; height: 24px; }
    .wpwa-stat-label { font-size: 12px; color: #666; text-transform: uppercase; margin-bottom: 5px; }
    .wpwa-stat-value { font-size: 24px; font-weight: 700; color: #1d2327; }
    .wpwa-add-whitelist-section, .wpwa-import-section { background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0; }
    </style>
    <?php
}