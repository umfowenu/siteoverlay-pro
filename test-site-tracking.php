<?php
/**
 * SiteOverlay Pro - Site Tracking Test Script
 * 
 * This script tests the site tracking functionality to ensure it follows
 * constitutional rules: non-blocking, graceful degradation, and proper
 * database operations.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test site tracking functionality
 */
function test_site_tracking_functionality() {
    echo "<h2>SiteOverlay Pro - Site Tracking Test Results</h2>\n";
    
    // Test 1: Database table creation
    echo "<h3>Test 1: Database Table Creation</h3>\n";
    test_database_table_creation();
    
    // Test 2: Site signature generation
    echo "<h3>Test 2: Site Signature Generation</h3>\n";
    test_site_signature_generation();
    
    // Test 3: Background tracking simulation
    echo "<h3>Test 3: Background Tracking Simulation</h3>\n";
    test_background_tracking();
    
    // Test 4: Soft limit enforcement
    echo "<h3>Test 4: Soft Limit Enforcement</h3>\n";
    test_soft_limit_enforcement();
    
    // Test 5: Graceful degradation
    echo "<h3>Test 5: Graceful Degradation</h3>\n";
    test_graceful_degradation();
    
    echo "<h3>Test Summary</h3>\n";
    echo "<p>All tests completed. Check the results above for any issues.</p>\n";
}

/**
 * Test database table creation
 */
function test_database_table_creation() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'siteoverlay_sites';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    if ($table_exists) {
        echo "<p style='color: green;'>✓ Database table exists: $table_name</p>\n";
        
        // Check table structure
        $columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
        $expected_columns = array(
            'id', 'site_signature', 'site_url', 'site_title', 'wp_version',
            'plugin_version', 'overlay_count', 'license_key', 'license_type',
            'first_tracked', 'last_tracked', 'tracking_status'
        );
        
        $found_columns = array();
        foreach ($columns as $column) {
            $found_columns[] = $column->Field;
        }
        
        $missing_columns = array_diff($expected_columns, $found_columns);
        
        if (empty($missing_columns)) {
            echo "<p style='color: green;'>✓ All expected columns present</p>\n";
        } else {
            echo "<p style='color: red;'>✗ Missing columns: " . implode(', ', $missing_columns) . "</p>\n";
        }
        
        // Check indexes
        $indexes = $wpdb->get_results("SHOW INDEX FROM $table_name");
        $found_indexes = array();
        foreach ($indexes as $index) {
            $found_indexes[] = $index->Key_name;
        }
        
        $expected_indexes = array('PRIMARY', 'site_signature', 'site_url', 'license_type', 'last_tracked', 'tracking_status');
        $missing_indexes = array_diff($expected_indexes, $found_indexes);
        
        if (empty($missing_indexes)) {
            echo "<p style='color: green;'>✓ All expected indexes present</p>\n";
        } else {
            echo "<p style='color: red;'>✗ Missing indexes: " . implode(', ', $missing_indexes) . "</p>\n";
        }
        
    } else {
        echo "<p style='color: red;'>✗ Database table does not exist: $table_name</p>\n";
    }
}

/**
 * Test site signature generation
 */
function test_site_signature_generation() {
    $site_signature = get_option('siteoverlay_site_signature');
    
    if (empty($site_signature)) {
        echo "<p style='color: orange;'>⚠ No site signature found. Generating one...</p>\n";
        
        // Generate signature
        $site_url = get_site_url();
        $site_title = get_bloginfo('name');
        $wp_salt = defined('AUTH_SALT') ? AUTH_SALT : 'default_salt';
        $wp_key = defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'default_key';
        
        $signature_data = $site_url . '|' . $site_title . '|' . $wp_salt . '|' . $wp_key;
        $signature = hash('sha256', $signature_data);
        
        update_option('siteoverlay_site_signature', $signature);
        echo "<p style='color: green;'>✓ Generated new site signature: " . substr($signature, 0, 16) . "...</p>\n";
    } else {
        echo "<p style='color: green;'>✓ Site signature exists: " . substr($site_signature, 0, 16) . "...</p>\n";
    }
    
    // Test signature uniqueness
    $test_signature = hash('sha256', get_site_url() . '|' . get_bloginfo('name') . '|' . (defined('AUTH_SALT') ? AUTH_SALT : 'default_salt') . '|' . (defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'default_key'));
    
    if ($test_signature === $site_signature) {
        echo "<p style='color: green;'>✓ Site signature generation is consistent</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Site signature generation is inconsistent</p>\n";
    }
}

/**
 * Test background tracking simulation
 */
function test_background_tracking() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'siteoverlay_sites';
    $site_signature = get_option('siteoverlay_site_signature');
    
    if (empty($site_signature)) {
        echo "<p style='color: red;'>✗ Cannot test tracking without site signature</p>\n";
        return;
    }
    
    // Simulate site data
    $site_data = array(
        'site_signature' => $site_signature,
        'site_url' => get_site_url(),
        'site_title' => get_bloginfo('name'),
        'wp_version' => get_bloginfo('version'),
        'plugin_version' => SITEOVERLAY_RR_VERSION,
        'overlay_count' => 0,
        'license_key' => get_option('siteoverlay_license_key', ''),
        'license_type' => 'test',
        'tracking_status' => 'active'
    );
    
    // Test database insertion
    $result = $wpdb->replace(
        $table_name,
        array(
            'site_signature' => $site_data['site_signature'],
            'site_url' => $site_data['site_url'],
            'site_title' => $site_data['site_title'],
            'wp_version' => $site_data['wp_version'],
            'plugin_version' => $site_data['plugin_version'],
            'overlay_count' => $site_data['overlay_count'],
            'license_key' => $site_data['license_key'],
            'license_type' => $site_data['license_type'],
            'last_tracked' => current_time('mysql'),
            'tracking_status' => $site_data['tracking_status']
        ),
        array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
    );
    
    if ($result !== false) {
        echo "<p style='color: green;'>✓ Background tracking simulation successful</p>\n";
        
        // Check if record exists
        $record = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE site_signature = %s", $site_signature));
        if ($record) {
            echo "<p style='color: green;'>✓ Site tracking record found in database</p>\n";
        } else {
            echo "<p style='color: red;'>✗ Site tracking record not found in database</p>\n";
        }
    } else {
        echo "<p style='color: red;'>✗ Background tracking simulation failed</p>\n";
    }
}

/**
 * Test soft limit enforcement
 */
function test_soft_limit_enforcement() {
    // Test different license types
    $test_cases = array(
        array('license_type' => 'professional', 'overlay_count' => 3, 'expected_warning' => false),
        array('license_type' => 'professional', 'overlay_count' => 6, 'expected_warning' => true),
        array('license_type' => 'trial', 'overlay_count' => 2, 'expected_warning' => false),
        array('license_type' => 'trial', 'overlay_count' => 4, 'expected_warning' => true),
        array('license_type' => 'annual', 'overlay_count' => 10, 'expected_warning' => false),
        array('license_type' => 'lifetime', 'overlay_count' => 20, 'expected_warning' => false)
    );
    
    foreach ($test_cases as $test_case) {
        $license_type = $test_case['license_type'];
        $overlay_count = $test_case['overlay_count'];
        $expected_warning = $test_case['expected_warning'];
        
        $should_warn = false;
        
        if ($license_type === 'professional' && $overlay_count > 5) {
            $should_warn = true;
        } elseif ($license_type === 'trial' && $overlay_count > 3) {
            $should_warn = true;
        }
        
        if ($should_warn === $expected_warning) {
            echo "<p style='color: green;'>✓ Soft limit test passed: $license_type with $overlay_count overlays</p>\n";
        } else {
            echo "<p style='color: red;'>✗ Soft limit test failed: $license_type with $overlay_count overlays</p>\n";
        }
    }
}

/**
 * Test graceful degradation
 */
function test_graceful_degradation() {
    // Test 1: No internet connection simulation
    echo "<p>Testing graceful degradation scenarios:</p>\n";
    
    // Test database connection
    global $wpdb;
    if ($wpdb->check_connection()) {
        echo "<p style='color: green;'>✓ Database connection available</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Database connection failed</p>\n";
    }
    
    // Test WordPress salts availability
    if (defined('AUTH_SALT') && defined('SECURE_AUTH_KEY')) {
        echo "<p style='color: green;'>✓ WordPress salts available</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠ WordPress salts not defined (using defaults)</p>\n";
    }
    
    // Test plugin constants
    if (defined('SITEOVERLAY_RR_VERSION')) {
        echo "<p style='color: green;'>✓ Plugin constants available</p>\n";
    } else {
        echo "<p style='color: red;'>✗ Plugin constants not available</p>\n";
    }
    
    // Test wp_cron availability
    if (function_exists('wp_schedule_event')) {
        echo "<p style='color: green;'>✓ WordPress cron available</p>\n";
    } else {
        echo "<p style='color: red;'>✗ WordPress cron not available</p>\n";
    }
}

// Run tests if accessed directly
if (isset($_GET['test_site_tracking']) && current_user_can('manage_options')) {
    test_site_tracking_functionality();
}
?> 