<?php
/**
 * SiteOverlay Pro - Site Tracking Database Migration
 * 
 * CONSTITUTIONAL COMPLIANCE:
 * - Non-blocking database creation
 * - Graceful error handling
 * - Safe table creation with proper indexing
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Run site tracking database migration
 * CONSTITUTIONAL RULE: Non-blocking database operations
 */
function siteoverlay_run_site_tracking_migration() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'siteoverlay_sites';
    $charset_collate = $wpdb->get_charset_collate();
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    if (!$table_exists) {
        // Create the site tracking table
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            site_signature varchar(64) NOT NULL,
            site_url varchar(255) NOT NULL,
            site_title varchar(255) NOT NULL,
            wp_version varchar(20) NOT NULL,
            plugin_version varchar(20) NOT NULL,
            overlay_count int(11) DEFAULT 0,
            license_key varchar(255) DEFAULT '',
            license_type varchar(50) DEFAULT 'unlicensed',
            first_tracked datetime DEFAULT CURRENT_TIMESTAMP,
            last_tracked datetime DEFAULT CURRENT_TIMESTAMP,
            tracking_status varchar(20) DEFAULT 'active',
            PRIMARY KEY (id),
            UNIQUE KEY site_signature (site_signature),
            KEY site_url (site_url),
            KEY license_type (license_type),
            KEY last_tracked (last_tracked),
            KEY tracking_status (tracking_status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        try {
            dbDelta($sql);
            
            // Log successful table creation
            error_log('SiteOverlay: Site tracking table created successfully');
            
            // Set migration flag
            update_option('siteoverlay_site_tracking_migrated', '2.0.1');
            
        } catch (Exception $e) {
            // CONSTITUTIONAL RULE: Handle failures gracefully
            error_log('SiteOverlay: Site tracking table creation failed: ' . $e->getMessage());
        }
    } else {
        // Table exists, check if we need to update structure
        $current_version = get_option('siteoverlay_site_tracking_migrated', '0');
        
        if (version_compare($current_version, '2.0.1', '<')) {
            // Update table structure if needed
            $update_sql = array();
            
            // Check if tracking_status column exists
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'tracking_status'");
            if (empty($column_exists)) {
                $update_sql[] = "ALTER TABLE $table_name ADD COLUMN tracking_status varchar(20) DEFAULT 'active' AFTER last_tracked";
            }
            
            // Check if license_type column exists
            $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'license_type'");
            if (empty($column_exists)) {
                $update_sql[] = "ALTER TABLE $table_name ADD COLUMN license_type varchar(50) DEFAULT 'unlicensed' AFTER license_key";
            }
            
            // Execute updates if needed
            if (!empty($update_sql)) {
                try {
                    foreach ($update_sql as $sql) {
                        $wpdb->query($sql);
                    }
                    
                    // Log successful update
                    error_log('SiteOverlay: Site tracking table updated successfully');
                    
                } catch (Exception $e) {
                    // CONSTITUTIONAL RULE: Handle failures gracefully
                    error_log('SiteOverlay: Site tracking table update failed: ' . $e->getMessage());
                }
            }
            
            // Update migration version
            update_option('siteoverlay_site_tracking_migrated', '2.0.1');
        }
    }
}

/**
 * Cleanup function for plugin deactivation
 */
function siteoverlay_cleanup_site_tracking() {
    // CONSTITUTIONAL RULE: Don't delete data on deactivation
    // Keep site tracking data for user convenience
    // Only clear scheduled events
    wp_clear_scheduled_hook('siteoverlay_track_site_usage');
    wp_clear_scheduled_hook('siteoverlay_validate_site_limits');
}

/**
 * Uninstall function for complete removal
 */
function siteoverlay_uninstall_site_tracking() {
    global $wpdb;
    
    // Only remove if user explicitly uninstalls
    if (get_option('siteoverlay_remove_data_on_uninstall', false)) {
        $table_name = $wpdb->prefix . 'siteoverlay_sites';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        // Clean up options
        delete_option('siteoverlay_site_signature');
        delete_option('siteoverlay_last_tracking');
        delete_option('siteoverlay_site_tracking_migrated');
        delete_option('siteoverlay_site_limit_warning');
    }
}

// Register activation and deactivation hooks
register_activation_hook(SITEOVERLAY_RR_PLUGIN_FILE, 'siteoverlay_run_site_tracking_migration');
register_deactivation_hook(SITEOVERLAY_RR_PLUGIN_FILE, 'siteoverlay_cleanup_site_tracking');

// Run migration on plugin load (for existing installations)
add_action('plugins_loaded', 'siteoverlay_run_site_tracking_migration', 1);
?> 