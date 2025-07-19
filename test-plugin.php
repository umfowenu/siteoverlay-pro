<?php
/**
 * Plugin Name: SiteOverlay Pro Test
 * Plugin URI: https://siteoverlay.24hr.pro/
 * Description: Test version of SiteOverlay Pro
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
        add_action('admin_notices', array($this, 'admin_notice'));
    }
    
    public function init() {
        // Basic initialization
    }
    
    public function admin_notice() {
        echo '<div class="notice notice-success"><p>SiteOverlay Pro Test Plugin is working!</p></div>';
    }
}

// Initialize the plugin
new SiteOverlay_Test(); 