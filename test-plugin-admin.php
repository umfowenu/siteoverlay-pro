<?php
/**
 * Plugin Name: SiteOverlay Pro Test
 * Plugin URI: https://siteoverlay.24hr.pro/
 * Description: Test version of SiteOverlay Pro with admin interface
 * Version: 2.0.1
 * Author: eBiz360
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('SITEOVERLAY_RR_VERSION', '2.0.1');
define('SITEOVERLAY_RR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SITEOVERLAY_RR_PLUGIN_PATH', plugin_dir_path(__FILE__));

class SiteOverlay_Test {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_notices', array($this, 'admin_notice'));
    }
    
    public function init() {
        // Basic initialization
    }
    
    public function add_admin_menu() {
        add_options_page(
            'SiteOverlay Pro Test',
            'SiteOverlay Test',
            'manage_options',
            'siteoverlay-test',
            array($this, 'admin_page')
        );
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>SiteOverlay Pro Test</h1>
            <div class="notice notice-success">
                <p><strong>✅ Plugin is working correctly!</strong></p>
                <p>This test plugin is functioning properly. You should see this page under Settings > SiteOverlay Test</p>
            </div>
            
            <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; border-radius: 4px;">
                <h2>Test Information</h2>
                <ul>
                    <li><strong>Plugin Version:</strong> <?php echo SITEOVERLAY_RR_VERSION; ?></li>
                    <li><strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?></li>
                    <li><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></li>
                    <li><strong>Site URL:</strong> <?php echo get_site_url(); ?></li>
                </ul>
            </div>
            
            <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 4px;">
                <h3>Next Steps</h3>
                <p>Since this test plugin works, we can now test the full SiteOverlay Pro plugin with confidence.</p>
                <p>The issue was likely just formatting (trailing whitespace) in the PHP files.</p>
            </div>
        </div>
        <?php
    }
    
    public function admin_notice() {
        echo '<div class="notice notice-success"><p>SiteOverlay Pro Test Plugin is working! Check <a href="' . admin_url('options-general.php?page=siteoverlay-test') . '">Settings > SiteOverlay Test</a> for more info.</p></div>';
    }
}

// Initialize the plugin
new SiteOverlay_Test(); 