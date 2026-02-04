<?php
/**
 * Product Management Functions
 */

if (!defined('ABSPATH')) exit;

/**
 * Register product custom post type
 */
function wpwa_stripe_register_product_post_type() {
    register_post_type('wpwa_product', array(
        'labels' => array(
            'name' => __('Weebly Products', 'wpwa-stripe'),
            'singular_name' => __('Product', 'wpwa-stripe'),
            'add_new' => __('Add New Product', 'wpwa-stripe'),
            'add_new_item' => __('Add New Product', 'wpwa-stripe'),
            'edit_item' => __('Edit Product', 'wpwa-stripe'),
            'new_item' => __('New Product', 'wpwa-stripe'),
            'view_item' => __('View Product', 'wpwa-stripe'),
            'search_items' => __('Search Products', 'wpwa-stripe'),
            'not_found' => __('No products found', 'wpwa-stripe'),
        ),
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'supports' => array('title', 'editor', 'thumbnail'),
        'has_archive' => false,
        'rewrite' => false
    ));
}

/**
 * Add meta boxes
 */
add_action('add_meta_boxes_wpwa_product', 'wpwa_stripe_add_product_meta_boxes');
function wpwa_stripe_add_product_meta_boxes() {
    add_meta_box(
        'wpwa_product_details',
        __('Product Details', 'wpwa-stripe'),
        'wpwa_stripe_render_product_details_meta_box',
        'wpwa_product',
        'normal',
        'high'
    );
    
    add_meta_box(
        'wpwa_product_weebly',
        __('Weebly Configuration', 'wpwa-stripe'),
        'wpwa_stripe_render_product_weebly_meta_box',
        'wpwa_product',
        'normal',
        'high'
    );
    
    add_meta_box(
        'wpwa_product_stripe',
        __('Stripe Sync', 'wpwa-stripe'),
        'wpwa_stripe_render_product_stripe_meta_box',
        'wpwa_product',
        'side',
        'default'
    );
}

/**
 * Render product details meta box
 */
function wpwa_stripe_render_product_details_meta_box($post) {
    wp_nonce_field('wpwa_product_details', 'wpwa_product_details_nonce');
    
    $price = get_post_meta($post->ID, '_wpwa_price', true);
    $is_recurring = get_post_meta($post->ID, '_wpwa_is_recurring', true);
    $cycle_length = get_post_meta($post->ID, '_wpwa_cycle_length', true) ?: 1;
    $cycle_unit = get_post_meta($post->ID, '_wpwa_cycle_unit', true) ?: 'month';
    
    ?>
    <table class="form-table">
        <tr>
            <th><label for="wpwa_price">Price (USD)</label></th>
            <td>
                <input type="number" step="0.01" min="0" id="wpwa_price" name="wpwa_price" 
                       value="<?php echo esc_attr($price); ?>" class="regular-text" required>
                <p class="description">Product price in USD</p>
            </td>
        </tr>
        <tr>
            <th><label for="wpwa_is_recurring">Recurring Subscription</label></th>
            <td>
                <label>
                    <input type="checkbox" id="wpwa_is_recurring" name="wpwa_is_recurring" 
                           value="1" <?php checked($is_recurring, '1'); ?>>
                    Enable recurring billing
                </label>
            </td>
        </tr>
    </table>
    
    <div id="wpwa_recurring_fields" style="<?php echo $is_recurring ? '' : 'display:none;'; ?>">
        <hr>
        <h4>Recurring Settings</h4>
        <table class="form-table">
            <tr>
                <th><label for="wpwa_cycle_length">Billing Cycle</label></th>
                <td>
                    <input type="number" min="1" id="wpwa_cycle_length" name="wpwa_cycle_length" 
                           value="<?php echo esc_attr($cycle_length); ?>" style="width: 80px;"> 
                    <select name="wpwa_cycle_unit">
                        <option value="day" <?php selected($cycle_unit, 'day'); ?>>Day(s)</option>
                        <option value="week" <?php selected($cycle_unit, 'week'); ?>>Week(s)</option>
                        <option value="month" <?php selected($cycle_unit, 'month'); ?>>Month(s)</option>
                        <option value="year" <?php selected($cycle_unit, 'year'); ?>>Year(s)</option>
                    </select>
                </td>
            </tr>
        </table>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#wpwa_is_recurring').on('change', function() {
            $('#wpwa_recurring_fields').toggle(this.checked);
        });
    });
    </script>
    <?php
}

/**
 * Render Weebly config meta box
 */
function wpwa_stripe_render_product_weebly_meta_box($post) {
    wp_nonce_field('wpwa_product_weebly', 'wpwa_product_weebly_nonce');
    
    $client_id = get_post_meta($post->ID, '_wpwa_client_id', true);
    $client_secret = get_post_meta($post->ID, '_wpwa_client_secret', true);
    $callback_url = home_url('/wpwa_phase_one/?pr_id=' . $post->ID);
    $old_pr_id = get_post_meta($post->ID, '_wpwa_old_pr_id', true);
    $wpwa_app_url = get_post_meta($post->ID, '_wpwa_app_url', true);
    
    ?>
    <table class="form-table">
        <tr>
            <th><label for="wpwa_old_pr_id">Old Product ID</label></th>
            <td>
                <input type="text" id="wpwa_old_pr_id" name="wpwa_old_pr_id" 
                       value="<?php echo esc_attr($old_pr_id); ?>" class="regular-text" required>
            </td>
        </tr>
        <tr>
            <th><label for="wpwa_client_id">Weebly Client ID</label></th>
            <td>
                <input type="text" id="wpwa_client_id" name="wpwa_client_id" 
                       value="<?php echo esc_attr($client_id); ?>" class="regular-text" required>
            </td>
        </tr>
        <tr>
            <th><label for="wpwa_client_secret">Weebly Client Secret</label></th>
            <td>
                <input type="text" id="wpwa_client_secret" name="wpwa_client_secret" 
                       value="<?php echo esc_attr($client_secret); ?>" class="regular-text" required>
            </td>
        </tr>
        <tr>
            <th>Callback URL</th>
            <td>
                <input type="text" value="<?php echo esc_url($callback_url); ?>" 
                       class="regular-text" readonly onclick="this.select();">
                <p class="description">Use this in your Weebly app manifest</p>
            </td>
        </tr>
        <tr>
            <th><label for="wpwa_app_url">Weebly App Center URL</label></th>
            <td>
                <input type="text" id="wpwa_app_url" name="wpwa_app_url" 
                       value="<?php echo esc_attr($wpwa_app_url); ?>" class="regular-text" required>
            </td>
        </tr>
    </table>
    <?php
}

/**
 * Render Stripe sync meta box
 */
function wpwa_stripe_render_product_stripe_meta_box($post) {
    $stripe_product_id = get_post_meta($post->ID, '_wpwa_stripe_product_id', true);
    $stripe_price_id = get_post_meta($post->ID, '_wpwa_stripe_price_id', true);
    
    ?>
    <div style="padding: 10px;">
        <?php if ($stripe_product_id): ?>
            <p><strong>✅ Synced to Stripe</strong></p>
            <p><small>Product ID: <code><?php echo esc_html($stripe_product_id); ?></code></small></p>
            <p><small>Price ID: <code><?php echo esc_html($stripe_price_id); ?></code></small></p>
            <button type="button" class="button" onclick="wpwaResyncStripe(<?php echo $post->ID; ?>)">
                Resync to Stripe
            </button>
        <?php else: ?>
            <p><strong>Not synced yet</strong></p>
            <p><small>Will auto-sync on first checkout</small></p>
            <button type="button" class="button button-primary" onclick="wpwaSyncStripe(<?php echo $post->ID; ?>)">
                Sync to Stripe Now
            </button>
        <?php endif; ?>
    </div>
    
    <script>
    function wpwaSyncStripe(productId) {
        if (!confirm('Sync this product to Stripe?')) return;
        
        fetch(ajaxurl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=wpwa_sync_product_to_stripe&product_id=' + productId + '&nonce=<?php echo wp_create_nonce('wpwa_sync_stripe'); ?>'
        })
        .then(r => r.json())
        .then(data => {
            alert(data.success ? 'Synced successfully!' : 'Error: ' + data.data.message);
            if (data.success) location.reload();
        });
    }
    
    function wpwaResyncStripe(productId) {
        wpwaSyncStripe(productId);
    }
    </script>
    <?php
}

/**
 * Save product meta
 */
add_action('save_post_wpwa_product', 'wpwa_stripe_save_product_meta', 10, 2);
function wpwa_stripe_save_product_meta($post_id, $post) {
    // Verify nonces
    if (!isset($_POST['wpwa_product_details_nonce']) || 
        !wp_verify_nonce($_POST['wpwa_product_details_nonce'], 'wpwa_product_details')) {
        return;
    }
    
    if (!isset($_POST['wpwa_product_weebly_nonce']) || 
        !wp_verify_nonce($_POST['wpwa_product_weebly_nonce'], 'wpwa_product_weebly')) {
        return;
    }
    
    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Avoid autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    // Save product details
    update_post_meta($post_id, '_wpwa_price', sanitize_text_field($_POST['wpwa_price']));
    update_post_meta($post_id, '_wpwa_is_recurring', isset($_POST['wpwa_is_recurring']) ? '1' : '0');
    
    if (isset($_POST['wpwa_is_recurring'])) {
        update_post_meta($post_id, '_wpwa_cycle_length', absint($_POST['wpwa_cycle_length']));
        update_post_meta($post_id, '_wpwa_cycle_unit', sanitize_text_field($_POST['wpwa_cycle_unit']));
    }
    
    // Save Weebly config
    update_post_meta($post_id, '_wpwa_client_id', sanitize_text_field($_POST['wpwa_client_id']));
    update_post_meta($post_id, '_wpwa_client_secret', sanitize_text_field($_POST['wpwa_client_secret']));
    update_post_meta($post_id, '_wpwa_old_pr_id', sanitize_text_field($_POST['wpwa_old_pr_id']));
    update_post_meta($post_id, '_wpwa_app_url', sanitize_text_field($_POST['wpwa_app_url']));
    
    // Mark for Stripe resync if needed
    if (get_post_meta($post_id, '_wpwa_stripe_product_id', true)) {
        update_post_meta($post_id, '_wpwa_stripe_needs_resync', '1');
    }
}

/**
 * Retrieves product data by Post ID or Legacy Product ID
 */
function wpwa_stripe_get_product($product_id) {
    error_log("WPWA Debug: Searching for product with ID/Legacy ID: " . $product_id);

    // 1. Try to get the post directly by ID
    $post = get_post($product_id);

    // 2. If not found or wrong type, try searching by Legacy ID meta
    if (!$post || $post->post_type !== 'wpwa_product') {
        error_log("WPWA Debug: Direct Post ID lookup failed. Searching via '_wpwa_old_pr_id'...");
        
        $args = array(
            'post_type'  => 'wpwa_product',
            'meta_query' => array(
                array(
                    'key'   => '_wpwa_old_pr_id',
                    'value' => $product_id,
                ),
            ),
            'posts_per_page' => 1,
            'fields'         => 'ids', // Only need the ID for now
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            $found_id = $query->posts[0];
            $post = get_post($found_id);
            error_log("WPWA Debug: Found Product Post ID {$found_id} via Legacy ID {$product_id}");
            $pr_id = get_post_meta($post->ID, '_wpwa_old_pr_id', true);
        } else {
            error_log("WPWA Error: No product found for ID: " . $product_id);
            return null;
        }
    }else{
        $pr_id = $post->ID;
    }

    // Final validation of post type
    if (!$post || $post->post_type !== 'wpwa_product') {
        return null;
    }

    // Return data mapping
    return array(
        'id'                => $post->ID,
        'name'              => $post->post_title,
        'description'       => $post->post_content,
        'price'             => floatval(get_post_meta($post->ID, '_wpwa_price', true)),
        'is_recurring'      => get_post_meta($post->ID, '_wpwa_is_recurring', true) === '1',
        'cycle_length'      => absint(get_post_meta($post->ID, '_wpwa_cycle_length', true)),
        'cycle_unit'        => get_post_meta($post->ID, '_wpwa_cycle_unit', true),
        'client_id'         => get_post_meta($post->ID, '_wpwa_client_id', true),
        'client_secret'     => get_post_meta($post->ID, '_wpwa_client_secret', true),
        'stripe_product_id' => get_post_meta($post->ID, '_wpwa_stripe_product_id', true),
        'stripe_price_id'   => get_post_meta($post->ID, '_wpwa_stripe_price_id', true),
        'pr_id'   => $pr_id
    );
}

/**
 * Get all products
 */
function wpwa_stripe_get_all_products() {
    $posts = get_posts(array(
        'post_type' => 'wpwa_product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'title',
        'order' => 'ASC'
    ));
    
    $products = array();
    foreach ($posts as $post) {
        $products[] = wpwa_stripe_get_product($post->ID);
    }
    
    return $products;
}