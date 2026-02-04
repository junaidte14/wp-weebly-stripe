<?php
/**
 * Whitelist Management System
 */

if (!defined('ABSPATH')) exit;

/**
 * Add user to whitelist
 * 
 * @param array $args {
 *     @type string $weebly_user_id Required
 *     @type int    $product_id Required
 *     @type string $granted_by Admin username
 *     @type string $reason Why access was granted
 *     @type string $expiry_date Optional: 'YYYY-MM-DD HH:MM:SS' or null for permanent
 * }
 * @return int|false Whitelist ID on success, false on failure
 */
function wpwa_add_to_whitelist($args) {
    global $wpdb;
    
    $defaults = array(
        'weebly_user_id' => '',
        'product_id' => 0,
        'granted_by' => wp_get_current_user()->user_login,
        'reason' => '',
        'expiry_date' => null
    );
    
    $args = wp_parse_args($args, $defaults);
    
    // Validation
    if (empty($args['weebly_user_id']) || empty($args['product_id'])) {
        return false;
    }
    
    $table = $wpdb->prefix . 'wpwa_whitelist';
    
    // Check if already whitelisted
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM `{$table}` 
         WHERE weebly_user_id = %s 
         AND product_id = %d 
         AND status = 'active'",
        $args['weebly_user_id'],
        $args['product_id']
    ));
    
    if ($existing) {
        // Update existing entry
        $updated = $wpdb->update(
            $table,
            array(
                'granted_by' => $args['granted_by'],
                'reason' => $args['reason'],
                'expiry_date' => $args['expiry_date'],
                'updated_at' => current_time('mysql')
            ),
            array('id' => $existing),
            array('%s', '%s', '%s', '%s'),
            array('%d')
        );
        
        return $updated ? $existing : false;
    }
    
    // Insert new entry
    $inserted = $wpdb->insert(
        $table,
        array(
            'weebly_user_id' => $args['weebly_user_id'],
            'product_id' => $args['product_id'],
            'granted_by' => $args['granted_by'],
            'reason' => $args['reason'],
            'expiry_date' => $args['expiry_date'],
            'status' => 'active'
        ),
        array('%s', '%d', '%s', '%s', '%s', '%s')
    );
    
    return $inserted ? $wpdb->insert_id : false;
}

/**
 * Remove user from whitelist
 */
function wpwa_remove_from_whitelist($weebly_user_id, $product_id) {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_whitelist';
    
    return $wpdb->update(
        $table,
        array('status' => 'revoked', 'updated_at' => current_time('mysql')),
        array('weebly_user_id' => $weebly_user_id, 'product_id' => $product_id),
        array('%s', '%s'),
        array('%s', '%d')
    );
}

/**
 * Get all whitelisted users
 */
function wpwa_get_whitelist_entries($args = array()) {
    global $wpdb;
    
    $defaults = array(
        'status' => 'active',
        'product_id' => null,
        'limit' => 100,
        'offset' => 0
    );
    
    $args = wp_parse_args($args, $defaults);
    
    $table = $wpdb->prefix . 'wpwa_whitelist';
    
    $where = array('1=1');
    $values = array();
    
    if ($args['status']) {
        $where[] = 'status = %s';
        $values[] = $args['status'];
    }
    
    if ($args['product_id']) {
        $where[] = 'product_id = %d';
        $values[] = $args['product_id'];
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
 * Get whitelist count
 */
function wpwa_get_whitelist_count($status = 'active') {
    global $wpdb;
    
    $table = $wpdb->prefix . 'wpwa_whitelist';
    
    if ($status) {
        return $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}` WHERE status = %s",
            $status
        ));
    }
    
    return $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`");
}

/**
 * Bulk import whitelist from CSV
 * 
 * @param string $csv_path Path to CSV file
 * @param int $product_id Product to whitelist for
 * @return array ['success' => int, 'failed' => int, 'errors' => array]
 */
function wpwa_import_whitelist_csv($csv_path, $product_id) {
    if (!file_exists($csv_path)) {
        return array('success' => 0, 'failed' => 0, 'errors' => array('File not found'));
    }
    
    $success = 0;
    $failed = 0;
    $errors = array();
    
    $file = fopen($csv_path, 'r');
    $headers = fgetcsv($file); // Skip header row
    
    while (($row = fgetcsv($file)) !== false) {
        $weebly_user_id = trim($row[0]);
        $reason = isset($row[1]) ? trim($row[1]) : 'Bulk import';
        $expiry = isset($row[2]) && !empty($row[2]) ? $row[2] : null;
        
        if (empty($weebly_user_id)) {
            $failed++;
            $errors[] = "Row skipped: Empty user ID";
            continue;
        }
        
        $result = wpwa_add_to_whitelist(array(
            'weebly_user_id' => $weebly_user_id,
            'product_id' => $product_id,
            'reason' => $reason,
            'expiry_date' => $expiry
        ));
        
        if ($result) {
            $success++;
        } else {
            $failed++;
            $errors[] = "Failed to add user: {$weebly_user_id}";
        }
    }
    
    fclose($file);
    
    return array(
        'success' => $success,
        'failed' => $failed,
        'errors' => $errors
    );
}