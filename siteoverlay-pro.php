<?php
/**
 * Plugin Name: SiteOverlay Pro
 * Plugin URI: https://siteoverlay.24hr.pro/
 * Description: Professional rank and rent overlay system for WordPress. Create custom overlays for any page or post with advanced targeting and analytics.
 * Version: 2.0.1
 * Author: eBiz360
 * Author URI: https://ebiz360.ca/
 * License: GPL v2 or later
 * Text Domain: siteoverlay-rr
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('SITEOVERLAY_RR_VERSION', '2.0.1');
define('SITEOVERLAY_RR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SITEOVERLAY_RR_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * Main plugin class for SiteOverlay Pro
 * Handles initialization, admin interface, and overlay display
 */
class SiteOverlay_Pro {
    
    private $api_base_url = 'https://siteoverlay-api-production.up.railway.app/api';
    
    /**
     * Constructor - sets up WordPress hooks and plugin integration
     */
    public function __construct() {
        // Basic initialization
        add_action('init', array($this, 'init'));
        
        // Admin functionality
        if (is_admin()) {
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_menu', array($this, 'add_admin_menu'));
            
            // ALWAYS add meta boxes - content changes based on license status
            add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
            
                    // AJAX handlers for overlay functionality - ALWAYS available (constitutional rule)
        add_action('wp_ajax_siteoverlay_save_url', array($this, 'ajax_save_overlay'));
        add_action('wp_ajax_siteoverlay_remove_url', array($this, 'ajax_remove_overlay'));
        add_action('wp_ajax_siteoverlay_preview_url', array($this, 'ajax_preview_overlay'));
            
            // License management AJAX handlers (always available)
            add_action('wp_ajax_siteoverlay_trial_license', array($this, 'ajax_trial_license'));
            add_action('wp_ajax_siteoverlay_validate_license', array($this, 'ajax_validate_license'));
            add_action('wp_ajax_siteoverlay_refresh_content', array($this, 'ajax_refresh_content'));
            add_action('wp_ajax_siteoverlay_force_cache', array($this, 'ajax_force_cache'));
        }
        
        // Frontend overlay display ALWAYS available (constitutional rule)
        add_action('wp_head', array($this, 'display_overlay'));
        
        // Load overlay enhancement (always available - constitutional rule)
        $enhancement_file = SITEOVERLAY_RR_PLUGIN_PATH . 'includes/class-overlay-enhancement.php';
        if (file_exists($enhancement_file)) {
            require_once $enhancement_file;
        }
        
        // Load dynamic content manager (always available - constitutional rule)
        $dynamic_content_file = SITEOVERLAY_RR_PLUGIN_PATH . 'includes/class-dynamic-content-manager.php';
        if (file_exists($dynamic_content_file)) {
            require_once $dynamic_content_file;
            $this->dynamic_content_manager = new SiteOverlay_Dynamic_Content_Manager();
        }
    }
    
    /**
     * Initialize plugin hooks and WordPress integration
     * Sets up admin interface and frontend functionality
     */
    public function init() {
        // Load textdomain
        load_plugin_textdomain('siteoverlay-rr', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Initialize admin settings and notices
     * Registers plugin settings and admin notices
     */
    public function admin_init() {
        // Register settings
        register_setting('siteoverlay_settings', 'siteoverlay_urls');
        
        // Admin Notice System
        add_action('admin_notices', array($this, 'display_admin_notices'));
    }
    
    /**
     * Add plugin settings page to WordPress admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'SiteOverlay Pro Settings',
            'SiteOverlay Pro',
            'manage_options',
            'siteoverlay-settings',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Render the main admin settings page
     * Displays license status, overlays, and purchase options
     */
    public function render_admin_page() {
        // Get usage statistics
        global $wpdb;
        
        // Get license status
        $license_status = $this->get_license_status();
        
        // Count posts with overlays
        $posts_with_overlays = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = %s",
                '_siteoverlay_overlay_url'
            )
        );
        
        // Get total views
        $total_views = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(meta_value) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value REGEXP '^[0-9]+$'",
                '_siteoverlay_overlay_views'
            )
        );
        
        // Get recent overlays
        $recent_overlays = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID, p.post_title, pm.meta_value as overlay_url, pm2.meta_value as views, pm3.meta_value as updated
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = %s
                 LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = %s
                 WHERE pm.meta_key = %s AND p.post_status = 'publish'
                 ORDER BY pm3.meta_value DESC
                 LIMIT 10",
                '_siteoverlay_overlay_views',
                '_siteoverlay_overlay_updated',
                '_siteoverlay_overlay_url'
            )
        );
        ?>
        <div class="wrap">
            <h1>SiteOverlay Pro Settings</h1>
            
            <!-- Logo Section -->
            <div style="text-align: center; padding: 20px 0; background: white; border: 1px solid #ddd; margin-bottom: 20px;">
                <img src="https://page1.genspark.site/v1/base64_upload/fe1edd2c48ac954784b3e58ed66b0764" alt="SiteOverlay Pro" style="max-width: 300px; height: auto;" />
            </div>
            
            <!-- Status Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <!-- Plugin always active (constitutional rule) -->
                <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 5px;">
                    <h3 style="margin: 0 0 10px 0; color: #155724;">✓ Plugin Active</h3>
                    <p style="margin: 0; color: #155724;">SiteOverlay Pro is running successfully</p>
                    <?php if (!$license_status['features_enabled']): ?>
                    <p style="margin: 5px 0 0 0; color: #856404; font-size: 12px;">💡 Activate license for premium features</p>
                    <?php endif; ?>
                </div>
                
                <div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 20px; border-radius: 5px;">
                    <h3 style="margin: 0 0 10px 0; color: #0c5460;">📊 Usage Statistics</h3>
                    <p style="margin: 0; color: #0c5460;">
                        <strong><?php echo $posts_with_overlays ?: '0'; ?></strong> posts with overlays<br>
                        <strong><?php echo $total_views ?: '0'; ?></strong> total views
                    </p>
                </div>
                
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 5px;">
                    <h3 style="margin: 0 0 10px 0; color: #856404;"><?php echo esc_html($this->get_dynamic_content('admin_boost_title', '🚀 Get Xagio')); ?></h3>
                    <p style="margin: 0; color: #856404;"><?php echo esc_html($this->get_dynamic_content('admin_boost_subtitle', 'Boost your SEO rankings')); ?></p>
                    <a href="<?php echo esc_url($this->get_dynamic_xagio_affiliate_url()); ?>" target="_blank" class="button button-primary" style="margin-top: 10px;"><?php echo esc_html($this->get_dynamic_content('admin_button_text', 'Get Xagio Now')); ?></a>
                </div>
                
                <div style="background: #e2e3e5; border: 1px solid #d6d8db; padding: 20px; border-radius: 5px;">
                    <h3 style="margin: 0 0 10px 0; color: #383d41;">🔄 Dynamic Content</h3>
                    <?php if (isset($this->dynamic_content_manager)): ?>
                        <?php 
                                        // Check cache status using chunk-based options table
                $cache_count = get_option('so_cache_count', 0);
                if ($cache_count > 0) {
                    $cached_content = array();
                    for ($i = 0; $i < $cache_count; $i++) {
                        $chunk = get_option('so_cache_' . $i, false);
                        if ($chunk && is_array($chunk)) {
                            $cached_content = array_merge($cached_content, $chunk);
                        } else {
                            $cached_content = false;
                            break;
                        }
                    }
                } else {
                    $cached_content = false;
                }
                        $content_count = $cached_content ? count($cached_content) : 0;
                        
                        // Add debug information
                        if ($content_count === 0 && isset($this->dynamic_content_manager)) {
                            // Try to get debug info
                            $debug_info = $this->dynamic_content_manager->debug_api_connection();
                        }
                        ?>
                        <p style="margin: 0; color: #383d41;">
                            <strong><?php echo $content_count; ?></strong> items cached<br>
                            Cache: <?php echo $cached_content ? 'Active' : 'Empty'; ?>
                        </p>
                        <?php if ($content_count === 0 && isset($debug_info)): ?>
                            <div style="font-size: 11px; color: #666; margin-top: 5px;">
                                Debug: API URL = <?php echo esc_html($debug_info['api_url']); ?><br>
                                Fresh content: <?php echo ($debug_info['fresh_content'] ? 'Available' : 'Failed'); ?><br>
                                Check WordPress error log for details.
                            </div>
                        <?php endif; ?>
                        <button type="button" onclick="refreshDynamicContent()" class="button button-secondary" style="margin-top: 10px;">Refresh Content</button>
                    <?php else: ?>
                        <p style="margin: 0; color: #721c24;">Dynamic Content Manager not loaded</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php
            // Force initial content load and cache if not already cached
            if (isset($this->dynamic_content_manager)) {
                // Check chunk-based cache
                $cache_count = get_option('so_cache_count', 0);
                $cached_check = ($cache_count > 0);
                if (!$cached_check) {
                    // Force load content to establish cache
                    $initial_content = $this->dynamic_content_manager->get_dynamic_content();
                    error_log('SiteOverlay: Admin page forced initial cache load: ' . (is_array($initial_content) ? count($initial_content) . ' items' : 'failed'));
                }
            }
            ?>
            
            <!-- Debug Information Section -->
            <div style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
                <h3 style="margin: 0 0 15px 0; color: #495057;">🔍 Debug Information</h3>
                
                <?php if (isset($this->dynamic_content_manager)): ?>
                    <?php
                    // Test API connection live
                    $debug_info = $this->dynamic_content_manager->debug_api_connection();
                    // Check chunk-based cache for debug display using SAME method as content manager
                    try {
                        $reflection = new ReflectionClass($this->dynamic_content_manager);
                        $retrieve_method = $reflection->getMethod('retrieve_content_chunks');
                        $retrieve_method->setAccessible(true);
                        $cached_content = $retrieve_method->invoke($this->dynamic_content_manager);
                    } catch (Exception $e) {
                        $cached_content = false;
                        error_log('Debug cache retrieval failed: ' . $e->getMessage());
                    }
                    
                    // Also get cache statistics for display
                    $cache_count = get_option('so_cache_count', 0);
                    $successful_chunks = get_option('so_cache_successful_chunks', array());
                    $stored_items = get_option('so_cache_stored_items', 0);
                    ?>
                    
                    <div style="font-family: monospace; font-size: 12px; background: white; padding: 15px; border-radius: 3px; margin-bottom: 15px;">
                        <strong>API Connection Test:</strong><br>
                        URL: <?php echo esc_html($debug_info['api_url']); ?><br>
                        Timeout: <?php echo esc_html($debug_info['timeout']); ?> seconds<br>
                        Fresh Content: <?php echo $debug_info['fresh_content'] ? '<span style="color: green;">✅ SUCCESS (' . count($debug_info['fresh_content']) . ' items)</span>' : '<span style="color: red;">❌ FAILED</span>'; ?><br>
                        Cached Content: <?php 
                        if ($cached_content && is_array($cached_content)) {
                            echo '<span style="color: green;">✅ PARTIAL CACHE (' . count($cached_content) . '/' . get_option('so_cache_total_items', 0) . ' items)</span>';
                            echo '<br><small>Chunks: ' . count($successful_chunks) . '/' . $cache_count . ' successful</small>';
                            echo '<br><small><strong>🎯 First 3 REAL items:</strong><br>';
                            $first_three = array_slice($cached_content, 0, 3, true);
                            foreach ($first_three as $key => $value) {
                                echo '<span style="color: blue;">' . $key . '</span>: ' . substr($value, 0, 50) . '...<br>';
                            }
                            echo '</small>';
                        } else {
                            echo '<span style="color: orange;">⚠️ No cache available</span>';
                        }
                        ?><br>
                        Cache Test: <?php 
                            if (isset($debug_info['cache_test'])) {
                                echo $debug_info['cache_test'] === 'WORKING' ? '<span style="color: green;">✅ WORKING</span>' : '<span style="color: red;">❌ FAILED</span>';
                            } else {
                                echo '<span style="color: gray;">Not tested</span>';
                            }
                        ?><br>
                    </div>
                    
                    <?php if ($debug_info['fresh_content'] && count($debug_info['fresh_content']) > 0): ?>
                        <div style="background: #d4edda; padding: 10px; border-radius: 3px; margin-bottom: 10px;">
                            <strong>✅ API Working - Sample Content:</strong><br>
                            <div style="font-family: monospace; font-size: 11px; margin-top: 5px;">
                                <?php 
                                $sample_keys = array('preview_title_text', 'preview_button_text', 'xagio_affiliate_url');
                                foreach ($sample_keys as $key):
                                    if (isset($debug_info['fresh_content'][$key])):
                                ?>
                                    <?php echo esc_html($key); ?>: <?php echo esc_html(substr($debug_info['fresh_content'][$key], 0, 50)); ?>...<br>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="background: #f8d7da; padding: 10px; border-radius: 3px; margin-bottom: 10px;">
                            <strong>❌ API Not Working</strong><br>
                            <span style="font-size: 11px;">The plugin cannot fetch content from the Railway API. Possible causes:</span>
                            <ul style="font-size: 11px; margin: 5px 0;">
                                <li>WordPress cannot make external HTTP requests</li>
                                <li>SSL certificate issues</li>
                                <li>Firewall blocking outbound connections</li>
                                <li>API server temporarily unavailable</li>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <div style="background: white; padding: 10px; border-radius: 3px; font-size: 11px;">
                        <strong>Current Dynamic Content Status:</strong><br>
                        Xagio URL: <?php echo esc_html($this->get_dynamic_xagio_affiliate_url()); ?><br>
                        Upgrade Message: <?php echo esc_html($this->get_dynamic_upgrade_message()); ?><br>
                    </div>
                    
                    <!-- WordPress Environment Diagnostics -->
                    <div style="background: #fff3cd; padding: 10px; border-radius: 3px; margin-top: 10px;">
                        <strong>WordPress Environment:</strong><br>
                        <div style="font-family: monospace; font-size: 11px;">
                            WordPress Version: <?php echo get_bloginfo('version'); ?><br>
                            PHP Version: <?php echo PHP_VERSION; ?><br>
                            Object Cache: <?php echo wp_using_ext_object_cache() ? 'External (Redis/Memcached)' : 'Default'; ?><br>
                            Transient Test: <?php 
                                $test_transient = set_transient('test_transient_123', 'test_value', 60);
                                $get_test_transient = get_transient('test_transient_123');
                                delete_transient('test_transient_123');
                                echo ($test_transient && $get_test_transient) ? '<span style="color: green;">WORKING</span>' : '<span style="color: red;">BROKEN</span>';
                            ?><br>
                            Options Test: <?php 
                                $test_option = update_option('test_option_123', 'test_value');
                                $get_test_option = get_option('test_option_123');
                                delete_option('test_option_123');
                                echo ($test_option && $get_test_option) ? '<span style="color: green;">WORKING</span>' : '<span style="color: red;">BROKEN</span>';
                            ?><br>
                        </div>
                    </div>
                    
                    <!-- EMERGENCY HOSTING DIAGNOSTICS -->
                    <div style="background: #ffebee; border: 1px solid #f44336; padding: 15px; margin-bottom: 15px; border-radius: 3px;">
                        <h4 style="margin: 0 0 10px 0; color: #d32f2f;">🚨 EMERGENCY HOSTING DIAGNOSTICS</h4>
                        
                        <?php
                        // Test 1: Basic WordPress functionality
                        echo '<p><strong>WordPress Core Functions:</strong><br>';
                        echo '• get_option(): ' . (function_exists('get_option') ? '✅' : '❌') . '<br>';
                        echo '• update_option(): ' . (function_exists('update_option') ? '✅' : '❌') . '<br>';
                        echo '• delete_option(): ' . (function_exists('delete_option') ? '✅' : '❌') . '<br>';
                        echo '• get_transient(): ' . (function_exists('get_transient') ? '✅' : '❌') . '<br>';
                        echo '• set_transient(): ' . (function_exists('set_transient') ? '✅' : '❌') . '</p>';
                        
                        // Test 2: Database write permissions
                        $test_key = 'siteoverlay_diagnostic_test_' . time();
                        $test_value = 'diagnostic_test_data';
                        
                        echo '<p><strong>Database Write Test:</strong><br>';
                        
                        // Try options table
                        $option_write = update_option($test_key, $test_value);
                        $option_read = get_option($test_key, 'NOT_FOUND');
                        $option_delete = delete_option($test_key);
                        
                        echo '• Options Write: ' . ($option_write ? '✅' : '❌') . '<br>';
                        echo '• Options Read: ' . ($option_read === $test_value ? '✅' : '❌ (got: ' . $option_read . ')') . '<br>';
                        echo '• Options Delete: ' . ($option_delete ? '✅' : '❌') . '<br>';
                        
                        // Try transients
                        $transient_write = set_transient($test_key, $test_value, 60);
                        $transient_read = get_transient($test_key);
                        $transient_delete = delete_transient($test_key);
                        
                        echo '• Transient Write: ' . ($transient_write ? '✅' : '❌') . '<br>';
                        echo '• Transient Read: ' . ($transient_read === $test_value ? '✅' : '❌ (got: ' . ($transient_read ?: 'NULL') . ')') . '<br>';
                        echo '• Transient Delete: ' . ($transient_delete ? '✅' : '❌') . '</p>';
                        
                        // Test 3: Hosting environment info
                        echo '<p><strong>Hosting Environment:</strong><br>';
                        echo '• PHP Version: ' . PHP_VERSION . '<br>';
                        echo '• WordPress Version: ' . get_bloginfo('version') . '<br>';
                        echo '• Memory Limit: ' . ini_get('memory_limit') . '<br>';
                        echo '• Object Cache: ' . (wp_using_ext_object_cache() ? 'External' : 'Default') . '<br>';
                        echo '• Database: ' . (defined('DB_NAME') ? DB_NAME : 'Unknown') . '<br>';
                        echo '• WP_DEBUG: ' . (defined('WP_DEBUG') && WP_DEBUG ? 'ON' : 'OFF') . '</p>';
                        
                        // Test 4: File system permissions
                        $upload_dir = wp_upload_dir();
                        echo '<p><strong>File System:</strong><br>';
                        echo '• Uploads Writable: ' . (is_writable($upload_dir['basedir']) ? '✅' : '❌') . '<br>';
                        echo '• Plugin Dir: ' . plugin_dir_path(__FILE__) . '<br>';
                        echo '• Plugin Writable: ' . (is_writable(plugin_dir_path(__FILE__)) ? '✅' : '❌') . '</p>';
                        
                        // Test 5: Current options table status
                        global $wpdb;
                        $options_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options}");
                        echo '<p><strong>Database Status:</strong><br>';
                        echo '• Options Table Accessible: ' . ($options_count !== null ? '✅' : '❌') . '<br>';
                        echo '• Total Options: ' . $options_count . '<br>';
                        
                        // Check for our chunk-based cache
                        $cache_count = get_option('so_cache_count', 0);
                        $chunk_status = '';
                        if ($cache_count > 0) {
                            $found_chunks = 0;
                            for ($i = 0; $i < $cache_count; $i++) {
                                $chunk_exists = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", 'so_cache_' . $i));
                                if ($chunk_exists) $found_chunks++;
                            }
                            $chunk_status = '✅ FOUND (' . $found_chunks . '/' . $cache_count . ' chunks)';
                        } else {
                            $chunk_status = '❌ NOT FOUND';
                        }
                        echo '• Our Cache in DB: ' . $chunk_status . '</p>';
                        ?>
                    </div>
                    
                    <!-- LIVE DEBUG OUTPUT -->
                    <div style="background: #e3f2fd; border: 1px solid #2196f3; padding: 15px; margin-bottom: 15px; border-radius: 3px;">
                        <h4 style="margin: 0 0 10px 0; color: #1976d2;">🔍 LIVE DEBUG TEST</h4>
                        
                        <?php
                        if (isset($this->dynamic_content_manager)) {
                            echo '<div style="font-family: monospace; font-size: 12px; background: white; padding: 10px; border-radius: 3px;">';
                            echo '<strong>Testing cache storage step-by-step:</strong><br><br>';
                            
                            // Clear cache first
                            delete_option('so_cache');
                            delete_option('so_cache_expiry');
                            echo '✓ STEP 1: Cleared existing cache<br>';
                            
                            // Test API fetch (use the private method via reflection for testing)
                            $reflection = new ReflectionClass($this->dynamic_content_manager);
                            $fetch_method = $reflection->getMethod('fetch_content_from_api');
                            $fetch_method->setAccessible(true);
                            $fresh_content = $fetch_method->invoke($this->dynamic_content_manager);
                            
                            echo '✓ STEP 2: API fetch - ' . (is_array($fresh_content) ? count($fresh_content) . ' items received' : 'FAILED') . '<br>';
                            
                            if (is_array($fresh_content) && count($fresh_content) > 0) {
                                echo '<strong>Investigating API content blocking:</strong><br>';
                                
                                // Test storing individual items
                                $item_count = 0;
                                foreach ($fresh_content as $key => $value) {
                                    $test_result = update_option('test_item_' . $item_count, array($key => $value));
                                    delete_option('test_item_' . $item_count);
                                    echo '• Item ' . $item_count . ' (' . $key . '): ' . ($test_result ? 'WORKS' : 'BLOCKED') . '<br>';
                                    $item_count++;
                                    if ($item_count >= 5) break; // Test first 5 items only
                                }
                                
                                // Test storing different sized chunks
                                echo '<br><strong>Testing different data sizes:</strong><br>';
                                $chunk_sizes = array(1, 3, 5, 10, 14);
                                foreach ($chunk_sizes as $size) {
                                    $chunk = array_slice($fresh_content, 0, $size, true);
                                    $test_result = update_option('test_chunk_' . $size, $chunk);
                                    delete_option('test_chunk_' . $size);
                                    echo '• ' . $size . ' items: ' . ($test_result ? 'WORKS' : 'BLOCKED') . '<br>';
                                }
                                
                                // Test data serialization
                                echo '<br><strong>Data analysis:</strong><br>';
                                $serialized = serialize($fresh_content);
                                echo '• Serialized size: ' . strlen($serialized) . ' bytes<br>';
                                echo '• Contains URLs: ' . (strpos($serialized, 'http') !== false ? 'YES' : 'NO') . '<br>';
                                echo '• Contains "warrior": ' . (stripos($serialized, 'warrior') !== false ? 'YES' : 'NO') . '<br>';
                                echo '<br>';
                                
                                // Test different option name patterns
                                $test_names = array(
                                    'so_cache' => array('test' => 'data'),
                                    'overlay_cache' => array('test' => 'data'),
                                    'dynamic_cache' => array('test' => 'data'),
                                    'plugin_cache' => array('test' => 'data'),
                                    'wp_cache_data' => array('test' => 'data')
                                );

                                echo '<strong>Testing different option names:</strong><br>';
                                foreach ($test_names as $name => $data) {
                                    $set_result = update_option($name, $data);
                                    $get_result = get_option($name, false);
                                    delete_option($name);
                                    echo '• ' . $name . ': ' . ($set_result && $get_result ? 'WORKS' : 'BLOCKED') . '<br>';
                                }
                                echo '<br>';
                                
                                echo '✓ STEP 3: Testing dynamic chunking storage...<br>';

                                // Use the dynamic chunking system
                                if (isset($this->dynamic_content_manager)) {
                                    // Access the private method using reflection
                                    $reflection = new ReflectionClass($this->dynamic_content_manager);
                                    $store_method = $reflection->getMethod('store_content_chunks');
                                    $store_method->setAccessible(true);
                                    $retrieve_method = $reflection->getMethod('retrieve_content_chunks');
                                    $retrieve_method->setAccessible(true);
                                    
                                    $storage_result = $store_method->invoke($this->dynamic_content_manager, $fresh_content);
                                    echo '✓ STEP 4: Dynamic chunking result: ' . ($storage_result ? 'SUCCESS' : 'FAILED') . '<br>';
                                    
                                    // Verify chunk retrieval
                                    $retrieved_content = $retrieve_method->invoke($this->dynamic_content_manager);
                                    echo '✓ STEP 5: Retrieved from chunks: ' . ($retrieved_content ? count($retrieved_content) . ' items' : 'NONE') . '<br>';
                                    
                                    // Show chunk details
                                    $chunk_count = get_option('so_cache_count', 0);
                                    $total_items = get_option('so_cache_total_items', 0);
                                    echo '✓ STEP 6: Chunk details: ' . $chunk_count . ' chunks containing ' . $total_items . ' total items<br>';
                                    
                                    // Test individual chunk storage
                                    echo '✓ STEP 7: Individual chunk verification:<br>';
                                    for ($i = 0; $i < $chunk_count; $i++) {
                                        $chunk_data = get_option("so_cache_{$i}", false);
                                        echo '&nbsp;&nbsp;• Chunk ' . $i . ': ' . ($chunk_data ? count($chunk_data) . ' items' : 'NOT FOUND') . '<br>';
                                    }
                                    
                                    // DETAILED CHUNK INSPECTION
                                    echo '<br><strong>Detailed chunk inspection:</strong><br>';

                                    // Check what actually got stored
                                    $actual_chunk_count = get_option('so_cache_count', 0);
                                    $actual_total = get_option('so_cache_total_items', 0);
                                    $actual_expiry = get_option('so_cache_expiry', 0);

                                    echo '• so_cache_count: ' . $actual_chunk_count . '<br>';
                                    echo '• so_cache_total_items: ' . $actual_total . '<br>';
                                    echo '• so_cache_expiry: ' . $actual_expiry . ' (current: ' . time() . ')<br>';

                                    // Check individual chunks
                                    for ($i = 0; $i < 5; $i++) {
                                        $chunk_data = get_option("so_cache_{$i}", 'NOT_FOUND');
                                        if ($chunk_data !== 'NOT_FOUND') {
                                            echo '• so_cache_' . $i . ': ' . (is_array($chunk_data) ? count($chunk_data) . ' items' : 'INVALID') . '<br>';
                                        } else {
                                            echo '• so_cache_' . $i . ': NOT FOUND<br>';
                                        }
                                    }

                                    // Test if the storage method is being called at all
                                    echo '<br><strong>Method call test:</strong><br>';
                                    try {
                                        $reflection = new ReflectionClass($this->dynamic_content_manager);
                                        $method = $reflection->getMethod('store_content_chunks');
                                        echo '• store_content_chunks method: EXISTS<br>';
                                        
                                        $method2 = $reflection->getMethod('retrieve_content_chunks');
                                        echo '• retrieve_content_chunks method: EXISTS<br>';
                                    } catch (Exception $e) {
                                        echo '• Method access error: ' . $e->getMessage() . '<br>';
                                    }
                                    
                                } else {
                                    echo '❌ STEP 4: Dynamic Content Manager not available<br>';
                                }
                                
                                echo '<br><strong>🚨 SIZE & STRUCTURE TEST:</strong><br>';
                                echo '<div style="background: #fff; padding: 10px; border: 1px solid #ccc;">';

                                try {
                                    if (isset($this->dynamic_content_manager)) {
                                        $reflection = new ReflectionClass($this->dynamic_content_manager);
                                        $fetch_method = $reflection->getMethod('fetch_content_from_api');
                                        $fetch_method->setAccessible(true);
                                        $real_content = $fetch_method->invoke($this->dynamic_content_manager);
                                        
                                        if ($real_content && count($real_content) > 0) {
                                            $store_method = $reflection->getMethod('store_content_chunks');
                                            $store_method->setAccessible(true);
                                            
                                            // Test 1: Single real item
                                            $first_key = array_keys($real_content)[0];
                                            $single_item = array($first_key => $real_content[$first_key]);
                                            echo "Test 1 - Single real item: ";
                                            $result1 = $store_method->invoke($this->dynamic_content_manager, $single_item);
                                            echo ($result1 ? 'TRUE' : 'FALSE') . "<br>";
                                            
                                            // Test 2: Two real items  
                                            $keys = array_keys($real_content);
                                            $two_items = array(
                                                $keys[0] => $real_content[$keys[0]],
                                                $keys[1] => $real_content[$keys[1]]
                                            );
                                            echo "Test 2 - Two real items: ";
                                            $result2 = $store_method->invoke($this->dynamic_content_manager, $two_items);
                                            echo ($result2 ? 'TRUE' : 'FALSE') . "<br>";
                                            
                                            // Test 3: Five real items
                                            $five_items = array_slice($real_content, 0, 5, true);
                                            echo "Test 3 - Five real items: ";
                                            $result3 = $store_method->invoke($this->dynamic_content_manager, $five_items);
                                            echo ($result3 ? 'TRUE' : 'FALSE') . "<br>";
                                            
                                            // Test 4: Structure test with simple keys - TESTING ONLY, DON'T CACHE
                                            $simple_structure = array();
                                            $count = 0;
                                            foreach ($real_content as $key => $value) {
                                                $simple_structure['key_' . $count] = 'value_' . $count;
                                                $count++;
                                                if ($count >= 14) break;
                                            }
                                            echo "Test 4 - 14 items, simple structure (TESTING ONLY - NO CACHE): ";
                                            // DON'T CACHE DUMMY DATA! Just test if it would work
                                            echo "SKIPPED (would overwrite real data)<br>";
                                            
                                            // Test 5: Try caching the REAL content with all items  
                                            echo "Test 5 - ALL REAL CONTENT (" . count($real_content) . " items): ";
                                            
                                            // Clear any existing dummy data first
                                            $clear_method = $reflection->getMethod('clear_all_chunks');
                                            $clear_method->setAccessible(true);
                                            $clear_method->invoke($this->dynamic_content_manager);
                                            echo "[Cache cleared] ";
                                            
                                            $result5 = $store_method->invoke($this->dynamic_content_manager, $real_content);
                                            echo ($result5 ? 'TRUE' : 'FALSE') . "<br>";
                                            
                                            if ($result5) {
                                                echo "<strong style='color: green;'>✅ SUCCESS: Real API content cached successfully!</strong><br>";
                                                
                                                // Immediately verify the cache contains real data
                                                $retrieve_method = $reflection->getMethod('retrieve_content_chunks');
                                                $retrieve_method->setAccessible(true);
                                                $verification = $retrieve_method->invoke($this->dynamic_content_manager);
                                                
                                                if ($verification && is_array($verification)) {
                                                    $verify_keys = array_keys($verification);
                                                    echo "<strong>🔍 VERIFICATION:</strong> Cache contains " . count($verification) . " items<br>";
                                                    echo "<strong>Sample keys:</strong> " . implode(', ', array_slice($verify_keys, 0, 3)) . "<br>";
                                                    
                                                    if (strpos($verify_keys[0], 'key_') === 0) {
                                                        echo "<strong style='color: red;'>❌ STILL DUMMY DATA!</strong><br>";
                                                    } else {
                                                        echo "<strong style='color: green;'>✅ REAL API DATA CONFIRMED!</strong><br>";
                                                    }
                                                } else {
                                                    echo "<strong style='color: orange;'>⚠️ Verification failed - cache not retrievable</strong><br>";
                                                }
                                            } else {
                                                echo "<strong style='color: red;'>❌ FAILED: Real API content could not be cached!</strong><br>";
                                            }
                                            
                                        } else {
                                            echo "No real content available<br>";
                                        }
                                    }
                                } catch (Throwable $e) {
                                    echo "ERROR: " . $e->getMessage() . "<br>";
                                }
                                echo '</div>';
                                
// LIVE CHUNKING DEBUG
echo '<br><strong>🚨 LIVE CHUNKING DEBUG:</strong><br>';
echo '<div style="background: #fff; padding: 10px; border: 1px solid #ccc;">';
try {
    if (isset($this->dynamic_content_manager)) {
        $reflection = new ReflectionClass($this->dynamic_content_manager);
        $fetch_method = $reflection->getMethod('fetch_content_from_api');
        $fetch_method->setAccessible(true);
        $real_content = $fetch_method->invoke($this->dynamic_content_manager);
        if ($real_content && count($real_content) > 0) {
            echo "Testing " . count($real_content) . " items in chunks of 5<br>";
            $chunk_count = ceil(count($real_content) / 5);
            $content_keys = array_keys($real_content);
            $all_work = true;
            for ($i = 0; $i < $chunk_count; $i++) {
                $chunk_keys = array_slice($content_keys, $i * 5, 5);
                $chunk_data = array();
                foreach ($chunk_keys as $key) {
                    $chunk_data[$key] = $real_content[$key];
                }
                $result = update_option("test_chunk_{$i}", $chunk_data);
                $verify = get_option("test_chunk_{$i}", 'NOT_FOUND');
                delete_option("test_chunk_{$i}");
                $works = ($result && $verify !== 'NOT_FOUND');
                echo "Chunk {$i} (" . count($chunk_data) . " items): " . ($works ? 'SUCCESS' : 'FAILED') . "<br>";
                if (!$works) $all_work = false;
            }
            echo "<br>Result: " . ($all_work ? 'ALL CHUNKS WORK' : 'SOME CHUNKS FAILED');
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
echo '</div>';

                                // ERROR LOG CAPTURE
                                echo '<br><strong>📝 RECENT ERROR LOG (if WP_DEBUG enabled):</strong><br>';
                                echo '<div style="font-family: monospace; font-size: 11px; background: #f0f0f0; padding: 10px; border: 1px solid #ccc; max-height: 200px; overflow-y: auto;">';
                                
                                // Try to read recent error log entries
                                $debug_log = WP_CONTENT_DIR . '/debug.log';
                                if (file_exists($debug_log)) {
                                    $log_content = file_get_contents($debug_log);
                                    $log_lines = explode("\n", $log_content);
                                    $recent_lines = array_slice($log_lines, -50); // Last 50 lines
                                    
                                    $siteoverlay_lines = array_filter($recent_lines, function($line) {
                                        return stripos($line, 'siteoverlay') !== false;
                                    });
                                    
                                    if (count($siteoverlay_lines) > 0) {
                                        foreach ($siteoverlay_lines as $line) {
                                            echo esc_html($line) . "<br>";
                                        }
                                    } else {
                                        echo "No SiteOverlay entries found in recent log<br>";
                                    }
                                } else {
                                    echo "Debug log not found at {$debug_log}<br>";
                                    echo "Try enabling WP_DEBUG_LOG in wp-config.php<br>";
                                }
                                
                                echo '</div>';
                                
                            } else {
                                echo '❌ API fetch failed, cannot test cache storage<br>';
                            }
                            
                            echo '</div>';
                        } else {
                            echo '<div style="color: red;">Dynamic Content Manager not available</div>';
                        }
                        ?>
                    </div>
                    
                <?php else: ?>
                    <div style="background: #f8d7da; padding: 10px; border-radius: 3px;">
                        <strong>❌ Dynamic Content Manager Not Loaded</strong>
                    </div>
                <?php endif; ?>
                
                <div style="margin-top: 15px;">
                    <button type="button" onclick="location.reload()" class="button button-secondary">🔄 Refresh Debug Info</button>
                    <button type="button" onclick="testApiDirectly()" class="button button-secondary">🧪 Test API Directly</button>
                    <button type="button" onclick="forceCache()" class="button button-primary">💾 Force Cache Now</button>
                </div>
            </div>
            
            <!-- License Status Section -->
            <div style="background: white; border: 1px solid #ddd; padding: 20px; margin-bottom: 20px;">
                <h2>License Status</h2>
                
                <?php 
                $is_registered = get_option('siteoverlay_registration_name');
                $should_disable_trial = $this->should_disable_trial_button();
                ?>
                
                <!-- License State Interface -->
                <?php if ($license_status['state'] === 'unlicensed'): ?>
                    <!-- UNLICENSED STATE -->
                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #856404;">🎯 Get Started with SiteOverlay Pro</h3>
                        <p style="margin: 0 0 20px 0; color: #856404;">Choose how you'd like to activate SiteOverlay Pro:</p>
                        
                        <div style="display: flex; gap: 20px; margin-bottom: 20px;">
                            <button type="button" class="button button-primary" id="show-trial-form">Start 14-Day Free Trial</button>
                            <button type="button" class="button button-secondary" id="show-license-form">Enter License Key</button>
                        </div>
                        
                        <!-- Trial Registration Form -->
                        <div id="trial-registration-form" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 15px;">
                            <h4 style="margin: 0 0 15px 0; color: #495057;">Register for Free Trial</h4>
                            <p style="margin: 0 0 15px 0; color: #6c757d; font-size: 14px;">Enter your details below to receive your 14-day trial license key via email.</p>
                            
                            <div style="margin-bottom: 15px;">
                                <label for="full-name" style="display: block; margin-bottom: 5px; font-weight: bold;">Full Name:</label>
                                <input type="text" id="full-name" placeholder="Enter your full name" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;" />
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label for="email-address" style="display: block; margin-bottom: 5px; font-weight: bold;">Email Address:</label>
                                <input type="email" id="email-address" placeholder="Enter your email address" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;" />
                            </div>
                            
                            <button type="button" class="button button-primary" id="submit-trial-registration">Submit Registration</button>
                        </div>
                        
                        <!-- License Key Form -->
                        <div id="license-form" style="display: none; background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 15px;">
                            <h4 style="margin: 0 0 15px 0; color: #495057;">Enter License Key</h4>
                            <p style="margin: 0 0 15px 0; color: #6c757d; font-size: 14px;">Enter the license key that was emailed to you after purchase.</p>
                            
                            <div style="margin-bottom: 15px;">
                                <label for="license-key" style="display: block; margin-bottom: 5px; font-weight: bold;">License Key:</label>
                                <input type="text" id="license-key" placeholder="Enter your license key" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 3px;" />
                            </div>
                            
                            <button type="button" class="button button-primary" id="validate-license">Activate License</button>
                        </div>
                        
                        <!-- Purchase Options for Unlicensed Users -->
                        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin-top: 20px;">
                            <h4 style="margin: 0 0 15px 0; color: #856404;">🚀 Or Purchase a License</h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                                <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                                    <h5 style="margin: 0 0 10px 0; color: #495057;">Professional</h5>
                                    <p style="margin: 0 0 10px 0; font-size: 24px; font-weight: bold; color: #28a745;">$35/month</p>
                                    <p style="margin: 0 0 10px 0; color: #6c757d; font-size: 12px;">Up to 5 websites</p>
                                    <a href="https://siteoverlay.24hr.pro/?plan=professional" target="_blank" class="button button-primary">Get Professional</a>
                                </div>
                                <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                                    <h5 style="margin: 0 0 10px 0; color: #495057;">Annual Unlimited</h5>
                                    <p style="margin: 0 0 10px 0; font-size: 24px; font-weight: bold; color: #28a745;">$197/year</p>
                                    <p style="margin: 0 0 10px 0; color: #6c757d; font-size: 12px;">Unlimited websites</p>
                                    <a href="https://siteoverlay.24hr.pro/?plan=annual" target="_blank" class="button button-primary">Get Annual</a>
                                </div>
                                <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                                    <h5 style="margin: 0 0 10px 0; color: #495057;">Lifetime</h5>
                                    <p style="margin: 0 0 10px 0; font-size: 24px; font-weight: bold; color: #28a745;">$297</p>
                                    <p style="margin: 0 0 10px 0; color: #6c757d; font-size: 12px;">One-time payment</p>
                                    <a href="https://siteoverlay.24hr.pro/?plan=lifetime" target="_blank" class="button button-primary">Get Lifetime</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                <?php elseif ($license_status['state'] === 'pending'): ?>
                    <!-- PENDING STATE -->
                    <div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #0c5460;">📧 Check Your Email</h3>
                        <p style="margin: 0 0 15px 0; color: #0c5460;">
                            Registration submitted successfully! Please check your email (<strong><?php echo esc_html(get_option('siteoverlay_registration_email')); ?></strong>) for your trial license key.
                        </p>
                        <p style="margin: 0 0 15px 0; color: #0c5460;">Once you receive the license key, enter it below to activate your trial.</p>
                        
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                            <h4 style="margin: 0 0 15px 0; color: #495057;">Enter Your Trial License Key</h4>
                            <input type="text" id="license-key" placeholder="Enter your trial license key" style="width: 300px; padding: 8px; margin-right: 10px;" />
                            <button type="button" class="button button-primary" id="validate-license">Activate Trial</button>
                        </div>
                    </div>
                    
                <?php elseif ($license_status['state'] === 'trial_active'): ?>
                    <!-- TRIAL ACTIVE STATE -->
                    <div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
                        <?php if ($license_status['days_remaining'] <= 7): ?>
                            <h3 style="margin: 0 0 15px 0; color: #dc3545;">⚠️ Trial expires in <?php echo $license_status['days_remaining']; ?> days! Upgrade to continue</h3>
                        <?php else: ?>
                            <h3 style="margin: 0 0 15px 0; color: #0c5460;">⏰ Trial Active - <?php echo $license_status['days_remaining']; ?> days remaining</h3>
                        <?php endif; ?>
                        
                        <p style="margin: 0 0 15px 0; color: #0c5460;">
                            <strong>License Key:</strong> <?php echo esc_html(get_option('siteoverlay_license_key')); ?><br>
                            <strong>Expires:</strong> <?php echo $license_status['expiry']; ?><br>
                            <strong>Registered Email:</strong> <?php echo esc_html(get_option('siteoverlay_registration_email')); ?>
                        </p>
                        
                        <!-- Purchase Options Display -->
                        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                            <h4 style="margin: 0 0 15px 0; color: #856404;">🚀 Upgrade to Full License</h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                                <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                                    <h5 style="margin: 0 0 10px 0; color: #495057;">Professional</h5>
                                    <p style="margin: 0 0 10px 0; font-size: 24px; font-weight: bold; color: #28a745;">$35/month</p>
                                    <p style="margin: 0 0 10px 0; color: #6c757d; font-size: 12px;">Up to 5 websites</p>
                                    <a href="https://siteoverlay.24hr.pro/?plan=professional" target="_blank" class="button button-primary">Get Professional</a>
                                </div>
                                <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                                    <h5 style="margin: 0 0 10px 0; color: #495057;">Annual Unlimited</h5>
                                    <p style="margin: 0 0 10px 0; font-size: 24px; font-weight: bold; color: #28a745;">$197/year</p>
                                    <p style="margin: 0 0 10px 0; color: #6c757d; font-size: 12px;">Unlimited websites</p>
                                    <a href="https://siteoverlay.24hr.pro/?plan=annual" target="_blank" class="button button-primary">Get Annual</a>
                                </div>
                                <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                                    <h5 style="margin: 0 0 10px 0; color: #495057;">Lifetime</h5>
                                    <p style="margin: 0 0 10px 0; font-size: 24px; font-weight: bold; color: #28a745;">$297</p>
                                    <p style="margin: 0 0 10px 0; color: #6c757d; font-size: 12px;">One-time payment</p>
                                    <a href="https://siteoverlay.24hr.pro/?plan=lifetime" target="_blank" class="button button-primary">Get Lifetime</a>
                                </div>
                            </div>
                        </div>
                        
                        <button type="button" class="button button-secondary" id="show-license-form">Enter License Key</button>
                        
                        <div id="license-form" style="display: none; margin-top: 15px; background: #f8f9fa; padding: 15px; border-radius: 5px;">
                            <h4 style="margin: 0 0 15px 0; color: #495057;">Enter Your License Key</h4>
                            <input type="text" id="upgrade-license-key" placeholder="Enter your license key" style="width: 300px; padding: 8px; margin-right: 10px;" />
                            <button type="button" class="button button-primary" id="validate-upgrade-license">Activate License</button>
                        </div>
                    </div>
                    
                <?php elseif ($license_status['state'] === 'trial_expired'): ?>
                    <!-- TRIAL EXPIRED STATE -->
                    <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #721c24;">❌ Trial has expired. Enter a paid license to continue</h3>
                        <p style="margin: 0 0 15px 0; color: #721c24;">Your 14-day trial has expired. Please upgrade to a full license to continue using SiteOverlay Pro.</p>
                        
                        <!-- Purchase Options Display -->
                        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin-bottom: 15px;">
                            <h4 style="margin: 0 0 15px 0; color: #856404;">🚀 Choose Your Plan</h4>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                                <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                                    <h5 style="margin: 0 0 10px 0; color: #495057;">Professional</h5>
                                    <p style="margin: 0 0 10px 0; font-size: 24px; font-weight: bold; color: #28a745;">$35/month</p>
                                    <p style="margin: 0 0 10px 0; color: #6c757d; font-size: 12px;">Up to 5 websites</p>
                                    <a href="https://siteoverlay.24hr.pro/?plan=professional" target="_blank" class="button button-primary">Get Professional</a>
                                </div>
                                <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                                    <h5 style="margin: 0 0 10px 0; color: #495057;">Annual Unlimited</h5>
                                    <p style="margin: 0 0 10px 0; font-size: 24px; font-weight: bold; color: #28a745;">$197/year</p>
                                    <p style="margin: 0 0 10px 0; color: #6c757d; font-size: 12px;">Unlimited websites</p>
                                    <a href="https://siteoverlay.24hr.pro/?plan=annual" target="_blank" class="button button-primary">Get Annual</a>
                                </div>
                                <div style="background: white; padding: 15px; border-radius: 5px; text-align: center;">
                                    <h5 style="margin: 0 0 10px 0; color: #495057;">Lifetime</h5>
                                    <p style="margin: 0 0 10px 0; font-size: 24px; font-weight: bold; color: #28a745;">$297</p>
                                    <p style="margin: 0 0 10px 0; color: #6c757d; font-size: 12px;">One-time payment</p>
                                    <a href="https://siteoverlay.24hr.pro/?plan=lifetime" target="_blank" class="button button-primary">Get Lifetime</a>
                                </div>
                            </div>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                            <h4 style="margin: 0 0 15px 0; color: #495057;">Enter Your License Key</h4>
                            <input type="text" id="license-key" placeholder="Enter your license key" style="width: 300px; padding: 8px; margin-right: 10px;" />
                            <button type="button" class="button button-primary" id="validate-license">Activate License</button>
                        </div>
                    </div>
                    
                <?php elseif ($license_status['state'] === 'licensed'): ?>
                    <!-- LICENSED (PAID) STATE -->
                    <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 5px; margin-bottom: 20px;">
                        <h3 style="margin: 0 0 15px 0; color: #155724;">✅ License Active</h3>
                        <p style="margin: 0; color: #155724;">
                            <strong>Status:</strong> <?php echo ucfirst($license_status['status']); ?><br>
                            <strong>License Key:</strong> <?php echo esc_html(get_option('siteoverlay_license_key')); ?>
                            <?php if ($license_status['expiry']): ?>
                                <br><strong>Expires:</strong> <?php echo $license_status['expiry']; ?>
                            <?php endif; ?>
                            <br><strong>Registered Email:</strong> <?php echo esc_html(get_option('siteoverlay_registration_email')); ?>
                        </p>
                    </div>
                <?php endif; ?>
                
                <div id="license-response" style="margin-top: 10px;"></div>
            </div>
            
            <!-- Recent Overlays - Always available (constitutional rule) -->
            <div style="background: white; border: 1px solid #ddd; padding: 20px; margin-bottom: 20px;">
                <h2>Recent Overlays</h2>
                <?php if ($recent_overlays): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th>Post/Page</th>
                                    <th>Overlay URL</th>
                                    <th>Views</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_overlays as $overlay): ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo get_edit_post_link($overlay->ID); ?>"><?php echo esc_html($overlay->post_title); ?></a>
                                        </td>
                                        <td>
                                            <a href="<?php echo esc_url($overlay->overlay_url); ?>" target="_blank"><?php echo esc_html($overlay->overlay_url); ?></a>
                                        </td>
                                        <td><?php echo $overlay->views ?: '0'; ?></td>
                                        <td><?php echo $overlay->updated ?: 'Never'; ?></td>
                                        <td>
                                            <a href="<?php echo get_edit_post_link($overlay->ID); ?>" class="button button-small">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                <?php else: ?>
                    <p>No overlays found. <a href="<?php echo admin_url('post-new.php'); ?>">Create your first overlay</a></p>
                <?php endif; ?>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // License functionality
            $('#show-trial-form').on('click', function() {
                $('#trial-registration-form').show();
                $('#license-form').hide();
            });
            
            $('#show-license-form').on('click', function() {
                $('#license-form').show();
                $('#trial-registration-form').hide();
            });
            
            $('#submit-trial-registration').on('click', function() {
                var fullName = $('#full-name').val();
                var email = $('#email-address').val();
                
                if (!fullName) {
                    alert('Please enter your full name');
                    return;
                }
                
                if (!email) {
                    alert('Please enter your email address');
                    return;
                }
                
                var $btn = $(this);
                var originalText = $btn.text();
                $btn.text('Submitting...').prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    timeout: 5000,
                    data: {
                        action: 'siteoverlay_trial_license',
                        full_name: fullName,
                        email: email,
                        nonce: '<?php echo wp_create_nonce('siteoverlay_overlay_nonce'); ?>'
                    },
                    success: function(response) {
                        $('.trial-message').remove();
                        
                        if (response.success) {
                            $('#trial-registration-form').append(
                                '<div class="trial-message" style="margin-top: 15px; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724;">' +
                                '<strong>✅ Success!</strong> Details submitted. Check your inbox for the license key to activate trial.' +
                                '</div>'
                            );
                            $('#submit-trial-registration').text('Trial Submitted').prop('disabled', true);
                        } else {
                            $('#trial-registration-form').append(
                                '<div class="trial-message" style="margin-top: 15px; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;">' +
                                '<strong>❌ Error:</strong> ' + response.data.message +
                                '</div>'
                            );
                        }
                    },
                    error: function(xhr, status, error) {
                        $('.trial-message').remove();
                        
                        if (status === 'timeout') {
                            $('#trial-registration-form').append(
                                '<div class="trial-message" style="margin-top: 15px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; color: #856404;">' +
                                '<strong>⚠️ Timeout:</strong> Registration submitted! Please check your email for your trial license key.' +
                                '</div>'
                            );
                        } else {
                            $('#trial-registration-form').append(
                                '<div class="trial-message" style="margin-top: 15px; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; color: #721c24;">' +
                                '<strong>❌ Connection Error:</strong> ' + error +
                                '</div>'
                            );
                        }
                    },
                    complete: function() {
                        $btn.text(originalText).prop('disabled', false);
                    }
                });
            });
            
            $('#validate-license').on('click', function() {
                var licenseKey = $('#license-key').val();
                if (!licenseKey) {
                    alert('Please enter a license key');
                    return;
                }
                
                var $btn = $(this);
                var originalText = $btn.text();
                $btn.text('Validating...').prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    timeout: 5000,
                    data: {
                        action: 'siteoverlay_validate_license',
                        license_key: licenseKey,
                        nonce: '<?php echo wp_create_nonce('siteoverlay_overlay_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#license-response').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            $('#license-response').html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        if (status === 'timeout') {
                            $('#license-response').html('<div class="notice notice-warning"><p>License validation timed out. Please try again.</p></div>');
                        } else {
                            $('#license-response').html('<div class="notice notice-error"><p>Connection error: ' + error + '</p></div>');
                        }
                    },
                    complete: function() {
                        $btn.text(originalText).prop('disabled', false);
                    }
                });
            });
            
            $('#validate-upgrade-license').on('click', function() {
                var licenseKey = $('#upgrade-license-key').val();
                if (!licenseKey) {
                    alert('Please enter a license key');
                    return;
                }
                
                var $btn = $(this);
                var originalText = $btn.text();
                $btn.text('Validating...').prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    timeout: 5000,
                    data: {
                        action: 'siteoverlay_validate_license',
                        license_key: licenseKey,
                        nonce: '<?php echo wp_create_nonce('siteoverlay_overlay_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#license-response').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            $('#license-response').html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        if (status === 'timeout') {
                            $('#license-response').html('<div class="notice notice-warning"><p>License validation timed out. Please try again.</p></div>');
                        } else {
                            $('#license-response').html('<div class="notice notice-error"><p>Connection error: ' + error + '</p></div>');
                        }
                    },
                    complete: function() {
                        $btn.text(originalText).prop('disabled', false);
                    }
                });
            });
            
            // When user starts typing license key, remove trial message
            $('#license_key').on('input', function() {
                if ($(this).val().length > 0) {
                    $('.trial-message').fadeOut(300, function() {
                        $(this).remove();
                    });
                }
            });
        });
        
        // Dynamic Content Refresh Function
        function refreshDynamicContent() {
            var button = event.target;
            button.disabled = true;
            button.textContent = 'Refreshing...';
            
            var data = {
                action: 'siteoverlay_refresh_content',
                nonce: '<?php echo wp_create_nonce('siteoverlay_admin_nonce'); ?>'
            };
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    location.reload();
                } else {
                    alert('Error: ' + result.data);
                }
            })
            .catch(error => {
                alert('Error refreshing content');
                console.error('Error:', error);
            })
            .finally(() => {
                button.disabled = false;
                button.textContent = 'Refresh Content';
            });
        }
        
        // Direct API test function
        function testApiDirectly() {
            const apiUrl = 'https://siteoverlay-api-production.up.railway.app/api/dynamic-content';
            
            // Show loading
            alert('Testing API directly... Check browser console for results.');
            
            fetch(apiUrl, {
                method: 'GET',
                headers: {
                    'X-Software-Type': 'wordpress_plugin',
                    'User-Agent': 'SiteOverlay-Pro-Plugin/2.0.1'
                }
            })
            .then(response => {
                console.log('API Response Status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('API Response Data:', data);
                if (data.success && data.content) {
                    alert('✅ API Test SUCCESS! Found ' + Object.keys(data.content).length + ' content items. Check console for details.');
                } else {
                    alert('❌ API Test FAILED! Response received but format is wrong. Check console for details.');
                }
            })
            .catch(error => {
                console.error('API Test Error:', error);
                alert('❌ API Test FAILED! Error: ' + error.message + '. Check console for details.');
            });
        }
        
        // Force cache function
        function forceCache() {
            var data = {
                action: 'siteoverlay_force_cache',
                nonce: '<?php echo wp_create_nonce('siteoverlay_admin_nonce'); ?>'
            };
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(data)
            })
            .then(response => response.json())
            .then(result => {
                alert(result.success ? '✅ ' + result.data : '❌ ' + result.data);
                location.reload();
            });
        }
        </script>
        <?php
    }
    
    /**
     * Add overlay meta boxes to post/page editor
     */
    public function add_meta_boxes() {
        // ALWAYS add meta boxes - content changes based on license status
        $post_types = array('post', 'page');
        foreach ($post_types as $post_type) {
            add_meta_box(
                'siteoverlay-meta-box',
                'SiteOverlay Pro - Overlay Settings',
                array($this, 'render_meta_box'),
                $post_type,
                'side',
                'high'
            );
        }
    }
    
    /**
     * Render overlay meta box based on license status
     */
    public function render_meta_box($post) {
        wp_nonce_field('siteoverlay_overlay_nonce', 'siteoverlay_overlay_nonce');
        
        // CONSTITUTIONAL RULE: Always show full functionality
        // License status only affects messaging, never functionality
        $this->render_licensed_meta_box($post);
    }
    
    private function render_unlicensed_meta_box($post) {
        ?>
        <div id="siteoverlay-overlay-container">
            <!-- Logo Section -->
            <div style="text-align: center; padding: 10px 0; background: white;">
                <img src="https://page1.genspark.site/v1/base64_upload/fe1edd2c48ac954784b3e58ed66b0764" alt="SiteOverlay Pro" style="max-width: 100%; height: auto;" />
            </div>
            
            <!-- Disabled State Message -->
            <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 0; text-align: center;">
                <div style="color: #721c24; font-weight: bold; margin-bottom: 10px;">🔒 Plugin Disabled</div>
                <div style="color: #721c24; font-size: 12px; margin-bottom: 15px;">
                    Overlay functionality requires an active license.<br>
                    Start a free trial or purchase a license to begin creating overlays.
                </div>
                
                <!-- Trial/Purchase Options -->
                <div style="margin-bottom: 10px;">
                    <a href="<?php echo admin_url('options-general.php?page=siteoverlay-settings'); ?>" 
                       class="button button-primary" style="font-size: 11px; padding: 4px 8px; margin: 2px;">
                       🚀 Start Free Trial
                    </a>
                </div>
                
                <div style="margin-bottom: 10px;">
                    <a href="https://siteoverlay.24hr.pro/?plan=professional" 
                       target="_blank" class="button button-secondary" style="font-size: 11px; padding: 4px 8px; margin: 2px;">
                       $35/month
                    </a>
                    <a href="https://siteoverlay.24hr.pro/?plan=annual" 
                       target="_blank" class="button button-secondary" style="font-size: 11px; padding: 4px 8px; margin: 2px;">
                       $197/year
                    </a>
                    <a href="https://siteoverlay.24hr.pro/?plan=lifetime" 
                       target="_blank" class="button button-secondary" style="font-size: 11px; padding: 4px 8px; margin: 2px;">
                       $297 lifetime
                    </a>
                </div>
                
                <div style="font-size: 10px; color: #721c24; margin-top: 10px;">
                    <a href="<?php echo admin_url('options-general.php?page=siteoverlay-settings'); ?>" style="color: #721c24;">
                        Already have a license? Activate it here →
                    </a>
                </div>
            </div>
            
            <!-- Xagio Affiliate Section -->
            <div style="background: #d1ecf1; padding: 15px; text-align: center; margin: 0;">
                <div style="color: #0c5460; font-weight: bold; margin-bottom: 5px;"><?php echo esc_html($this->get_dynamic_content('metabox_boost_title', '🚀 Boost Your SEO Rankings')); ?></div>
                <div style="color: #0c5460; font-size: 12px; margin-bottom: 10px;"><?php echo esc_html($this->get_dynamic_content('metabox_boost_subtitle', 'Get Xagio - The #1 SEO Tool for Rank & Rent Success')); ?></div>
                <a href="<?php echo esc_url($this->get_dynamic_xagio_affiliate_url()); ?>" target="_blank" 
                   style="background: #17a2b8; color: white; padding: 6px 12px; text-decoration: none; border-radius: 3px; font-size: 11px; display: inline-block;"><?php echo esc_html($this->get_dynamic_content('metabox_button_text', 'Get Xagio Now')); ?></a>
                <div style="color: #0c5460; font-size: 10px; margin-top: 5px;"><?php echo esc_html($this->get_dynamic_content('metabox_disclaimer', 'Affiliate Link - We earn a commission at no cost to you')); ?></div>
            </div>
        </div>
        <?php
    }
    
    private function render_licensed_meta_box($post) {
        $overlay_url = get_post_meta($post->ID, '_siteoverlay_overlay_url', true);
        $is_active = !empty($overlay_url);
        ?>
        <div id="siteoverlay-overlay-container">
            <!-- Logo Section -->
            <div style="text-align: center; padding: 10px 0; background: white;">
                <img src="https://page1.genspark.site/v1/base64_upload/fe1edd2c48ac954784b3e58ed66b0764" alt="SiteOverlay Pro" style="max-width: 100%; height: auto;" />
            </div>
            
            <!-- Status Display -->
            <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 8px; text-align: center; font-size: 12px;">
                ✓ <strong>SiteOverlay Pro Active</strong><br>
                👁 <?php echo get_post_meta($post->ID, '_siteoverlay_overlay_views', true) ?: '0'; ?> views
            </div>
            
            <!-- Xagio Affiliate Section -->
            <div style="background: #d1ecf1; padding: 15px; text-align: center; margin: 0;">
                <div style="color: #0c5460; font-weight: bold; margin-bottom: 5px;"><?php echo esc_html($this->get_dynamic_content('metabox_boost_title', '🚀 Boost Your SEO Rankings')); ?></div>
                <div style="color: #0c5460; font-size: 12px; margin-bottom: 10px;"><?php echo esc_html($this->get_dynamic_content('metabox_boost_subtitle', 'Get Xagio - The #1 SEO Tool for Rank & Rent Success')); ?></div>
                <a href="<?php echo esc_url($this->get_dynamic_xagio_affiliate_url()); ?>" target="_blank" 
                   style="background: #17a2b8; color: white; padding: 6px 12px; text-decoration: none; border-radius: 3px; font-size: 11px; display: inline-block;"><?php echo esc_html($this->get_dynamic_content('metabox_button_text', 'Get Xagio Now')); ?></a>
                <div style="color: #0c5460; font-size: 10px; margin-top: 5px;"><?php echo esc_html($this->get_dynamic_content('metabox_disclaimer', 'Affiliate Link - We earn a commission at no cost to you')); ?></div>
            </div>
            
            <!-- Current Overlay Section -->
            <?php if ($is_active): ?>
                <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 12px; margin: 0;">
                    <div style="color: #155724; font-weight: bold; margin-bottom: 8px;">✓ Overlay Active</div>
                    <div style="color: #155724; font-size: 12px; margin-bottom: 8px;"><strong>Current URL:</strong></div>
                    <input type="url" id="siteoverlay-overlay-url" name="siteoverlay_overlay_url" 
                           value="<?php echo esc_attr($overlay_url); ?>" 
                           placeholder="https://example.com/" 
                           style="width: 100%; padding: 4px; border: 1px solid #c3e6cb; background: white; font-size: 11px; margin-bottom: 8px;" />
                    
                    <div style="display: flex; gap: 5px;">
                        <button type="button" class="button button-secondary" id="edit-overlay" style="font-size: 11px; padding: 2px 6px;">Edit</button>
                        <button type="button" class="button button-secondary" id="preview-overlay" style="font-size: 11px; padding: 2px 6px;">Preview</button>
                        <button type="button" class="button button-link-delete" id="remove-overlay" style="font-size: 11px; padding: 2px 6px;">Remove</button>
                    </div>
                </div>
            <?php else: ?>
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 12px; margin: 0;">
                    <div style="color: #856404; font-weight: bold; margin-bottom: 8px;">⚡ Add Overlay URL</div>
                    <div style="color: #856404; font-size: 12px; margin-bottom: 8px;"><strong>Website to Overlay:</strong></div>
                    <input type="url" id="siteoverlay-overlay-url" name="siteoverlay_overlay_url" 
                           value="" 
                           placeholder="https://example.com/" 
                           style="width: 100%; padding: 4px; border: 1px solid #ffeaa7; background: white; font-size: 11px; margin-bottom: 8px;" />
                    
                    <button type="button" class="button button-primary" id="save-overlay" style="font-size: 11px; padding: 2px 6px; width: 100%;">Save Overlay</button>
                </div>
            <?php endif; ?>
            
            <!-- Stats Section -->
            <div style="padding: 8px 12px; color: #666; font-size: 10px; border-top: 1px solid #ddd;">
                Views: <?php echo get_post_meta($post->ID, '_siteoverlay_overlay_views', true) ?: '0'; ?> | 
                Last Updated: <?php echo get_post_meta($post->ID, '_siteoverlay_overlay_updated', true) ?: 'Never'; ?>
            </div>
            
            <div id="siteoverlay-response" style="margin-top: 10px;"></div>
        </div>
        
        <style>
        .siteoverlay-response.success {
            color: #00a32a;
            font-weight: bold;
        }
        .siteoverlay-response.error {
            color: #d63638;
            font-weight: bold;
        }
        </style>
        <?php
    }
    
    /**
     * Enqueue admin scripts and styles for overlay management
     */
    public function admin_enqueue_scripts($hook) {
        // Only load on post/page edit screens
        if (!in_array($hook, array('post.php', 'post-new.php', 'page.php', 'page-new.php'))) {
            return;
        }
        
        wp_enqueue_script('siteoverlay-admin', SITEOVERLAY_RR_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), SITEOVERLAY_RR_VERSION, true);
        wp_localize_script('siteoverlay-admin', 'siteoverlay_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('siteoverlay_overlay_nonce'),
            'post_id' => get_the_ID()
        ));
        
        wp_enqueue_style('siteoverlay-admin', SITEOVERLAY_RR_PLUGIN_URL . 'assets/css/admin.css', array(), SITEOVERLAY_RR_VERSION);
    }
    
    /**
     * Handle AJAX request to save overlay URL
     * Validates user input and updates post meta
     */
    public function ajax_save_overlay() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'siteoverlay_overlay_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $post_id = intval($_POST['post_id']);
        $overlay_url = esc_url_raw($_POST['overlay_url']);
        
        if (empty($post_id)) {
            wp_send_json_error('Invalid post ID');
            return;
        }
        
        if (empty($overlay_url)) {
            wp_send_json_error('Please enter a valid URL');
            return;
        }
        
        // Save data immediately
        update_post_meta($post_id, '_siteoverlay_overlay_url', $overlay_url);
        update_post_meta($post_id, '_siteoverlay_overlay_updated', current_time('mysql'));
        
        // Return success response immediately
        wp_send_json_success(array(
            'message' => 'Overlay saved successfully!',
            'overlay_url' => $overlay_url
        ));
    }
    
    /**
     * Handle AJAX request to remove overlay URL
     * Removes overlay data from post meta
     */
    public function ajax_remove_overlay() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'siteoverlay_overlay_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $post_id = intval($_POST['post_id']);
        
        if (empty($post_id)) {
            wp_send_json_error('Invalid post ID');
            return;
        }
        
        // Remove data immediately
        delete_post_meta($post_id, '_siteoverlay_overlay_url');
        delete_post_meta($post_id, '_siteoverlay_overlay_updated');
        
        // Return success response immediately
        wp_send_json_success(array(
            'message' => 'Overlay removed successfully!'
        ));
    }
    
    /**
     * Handle AJAX request to preview overlay URL
     * Returns overlay URL for preview in admin
     */
    public function ajax_preview_overlay() {
        if (!wp_verify_nonce($_POST['nonce'], 'siteoverlay_overlay_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $post_id = intval($_POST['post_id']);
        $overlay_url = get_post_meta($post_id, '_siteoverlay_overlay_url', true);
        
        if ($overlay_url) {
            wp_send_json_success(array('url' => $overlay_url));
        } else {
            wp_send_json_error('No overlay URL found');
        }
    }
    
    /**
     * Handle trial license registration from admin form
     * Processes user input and contacts licensing system
     */
    public function ajax_trial_license() {
        if (!wp_verify_nonce($_POST['nonce'], 'siteoverlay_overlay_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $full_name = sanitize_text_field($_POST['full_name']);
        $email = sanitize_email($_POST['email']);
        
        if (empty($full_name)) {
            wp_send_json_error('Please enter your full name');
            return;
        }
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error('Please enter a valid email address');
            return;
        }
        
        // Save registration data
        update_option('siteoverlay_registration_name', $full_name);
        update_option('siteoverlay_registration_email', $email);
        update_option('siteoverlay_registration_date', current_time('mysql'));
        
        // Send trial request to Railway API
        $trial_data = array(
            'name' => $full_name,  // ✅ FIXED - API expects 'name' not 'full_name'
            'email' => $email,
            'domain' => get_site_url(),  // ✅ OPTIONAL - API also accepts 'domain'
            'siteUrl' => get_site_url(),
            'siteTitle' => get_bloginfo('name'),
            'wpVersion' => get_bloginfo('version'),
            'pluginVersion' => SITEOVERLAY_RR_VERSION,
            'requestSource' => 'plugin_admin'
        );
        
        $api_url = 'https://siteoverlay-api-production.up.railway.app/api/request-trial';
        
        $response = wp_remote_post($api_url, array(
            'timeout' => 10,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($trial_data),
            'blocking' => true,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => 'Connection error: ' . $response->get_error_message()
            ));
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($response_code === 200 && $data && isset($data['success'])) {
            if ($data['success']) {
                wp_send_json_success(array(
                    'message' => $data['message'] ?? 'Details submitted. Check your inbox for the license key to activate trial'
                ));
            } else {
                wp_send_json_error(array(
                    'message' => $data['message'] ?? 'API returned an error'
                ));
            }
        } else {
            wp_send_json_error(array(
                'message' => 'Failed to process trial request (Code: ' . $response_code . ')'
            ));
        }
    }
    
    /**
     * Handle license validation from admin interface
     * Updates plugin activation status based on license
     */
    public function ajax_validate_license() {
        if (!wp_verify_nonce($_POST['nonce'], 'siteoverlay_overlay_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $license_key = sanitize_text_field($_POST['license_key']);
        
        if (empty($license_key)) {
            wp_send_json_error('Please enter a license key');
            return;
        }
        
        // CRITICAL FIX: Call Railway API for real validation
        $validation_result = $this->validate_license_with_railway($license_key);
        
        if ($validation_result['success']) {
            // Save license data
            update_option('siteoverlay_license_key', $license_key);
            update_option('siteoverlay_license_status', $validation_result['status']);
            update_option('siteoverlay_license_expiry', $validation_result['expiry']);
            update_option('siteoverlay_license_validated', true);
            
            wp_send_json_success(array(
                'message' => $validation_result['message'],
                'status' => $validation_result['status'],
                'state' => 'licensed'
            ));
        } else {
            wp_send_json_error($validation_result['message']);
        }
    }
    
    /**
     * CRITICAL FIX: Validate license with Railway API
     */
    private function validate_license_with_railway($license_key) {
        $api_response = wp_remote_post($this->api_base_url . '/validate-license', array(
            'body' => json_encode(array(
                'licenseKey' => $license_key,
                'siteUrl' => get_site_url()
            )),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 10
        ));
        
        if (is_wp_error($api_response)) {
            return array(
                'success' => false,
                'message' => 'Connection error: ' . $api_response->get_error_message()
            );
        }
        
        $body = wp_remote_retrieve_body($api_response);
        $data = json_decode($body, true);
        
        if (isset($data['success']) && $data['success'] === true) {
            // Determine license type and expiry
            $status = 'active';
            $expiry = null;
            $message = 'License activated successfully!';
            
            if (strpos($license_key, 'TRIAL-') === 0) {
                $status = 'trial';
                $expiry = date('Y-m-d H:i:s', strtotime('+14 days'));
                $message = 'Trial license activated successfully! You have 14 days to test SiteOverlay Pro.';
            } elseif (strpos($license_key, 'PRO-') === 0) {
                $status = 'professional';
                $expiry = date('Y-m-d H:i:s', strtotime('+1 year'));
                $message = 'Professional license activated successfully!';
            } elseif (strpos($license_key, 'LIFETIME-') === 0) {
                $status = 'lifetime';
                $expiry = null;
                $message = 'Lifetime license activated successfully!';
            }
            
            return array(
                'success' => true,
                'message' => $message,
                'status' => $status,
                'expiry' => $expiry
            );
        } else {
            return array(
                'success' => false,
                'message' => $data['message'] ?? 'Invalid license key'
            );
        }
    }
    
    /**
     * Get current license status for plugin
     * Used to control feature access and admin display
     */
    public function get_license_status() {
        $license_key = get_option('siteoverlay_license_key');
        $license_status = get_option('siteoverlay_license_status');
        $license_expiry = get_option('siteoverlay_license_expiry');
        $license_validated = get_option('siteoverlay_license_validated', false);
        
        // 1. License State Detection - Required States
        if (!$license_key) {
            return array(
                'state' => 'unlicensed',
                'status' => 'inactive',
                'message' => 'No license key found',
                'expiry' => null,
                'features_enabled' => false,
                'days_remaining' => 0
            );
        }
        
        // Check if license exists but not validated
        if ($license_key && !$license_validated) {
            return array(
                'state' => 'pending',
                'status' => 'pending',
                'message' => 'License key exists but not validated',
                'expiry' => null,
                'features_enabled' => false,
                'days_remaining' => 0
            );
        }
        
        // Check trial license states
        if ($license_status === 'trial') {
            $expiry_time = strtotime($license_expiry);
            $days_remaining = 0;
            
            if ($expiry_time) {
                $days_remaining = max(0, ceil(($expiry_time - time()) / (24 * 60 * 60)));
            }
            
            if ($expiry_time && $expiry_time < time()) {
                return array(
                    'state' => 'trial_expired',
                    'status' => 'trial_expired',
                    'message' => 'Trial license has expired',
                    'expiry' => $license_expiry,
                    'features_enabled' => false,
                    'days_remaining' => 0
                );
            }
            
            return array(
                'state' => 'trial_active',
                'status' => 'trial_active',
                'message' => 'Trial license active',
                'expiry' => $license_expiry,
                'features_enabled' => true,
                'days_remaining' => $days_remaining
            );
        }
        
        // Check paid license states
        if ($license_status && $license_validated && $license_status !== 'trial') {
            return array(
                'state' => 'licensed',
                'status' => $license_status,
                'message' => 'License active',
                'expiry' => $license_expiry,
                'features_enabled' => true,
                'days_remaining' => null
            );
        }
        
        // Fallback to unlicensed
        return array(
            'state' => 'unlicensed',
            'status' => 'inactive',
            'message' => 'License not properly validated',
            'expiry' => null,
            'features_enabled' => false,
            'days_remaining' => 0
        );
    }
    
    /**
     * Check if plugin features should be enabled
     * Used to control overlay display and admin options
     */
    public function is_licensed() {
        $license_status = $this->get_license_status();
        
        // ONLY return true if features are explicitly enabled
        return $license_status['features_enabled'] === true;
    }
    
    /**
     * Check if trial license is currently active
     */
    public function is_trial_active() {
        $license_status = $this->get_license_status();
        return $license_status['state'] === 'trial_active';
    }
    
    /**
     * Determine if trial button should be disabled in admin
     */
    public function should_disable_trial_button() {
        $license_status = $this->get_license_status();
        return in_array($license_status['state'], ['trial_active', 'trial_expired', 'licensed']);
    }
    
    /**
     * Display admin notices for license and trial status
     */
    public function display_admin_notices() {
        // Only show on admin pages, not on the plugin settings page
        if (isset($_GET['page']) && $_GET['page'] === 'siteoverlay-settings') {
            return;
        }
        
        $license_status = $this->get_license_status();
        
        switch ($license_status['state']) {
            case 'unlicensed':
                ?>
                <div class="notice notice-warning is-dismissible">
                    <p><strong>SiteOverlay Pro:</strong> Plugin is inactive. Activate your license to use SiteOverlay Pro. <a href="<?php echo admin_url('options-general.php?page=siteoverlay-settings'); ?>">Activate Now</a></p>
                </div>
                <?php
                break;
                
            case 'trial_active':
                if ($license_status['days_remaining'] <= 7) {
                    ?>
                    <div class="notice notice-warning is-dismissible">
                        <p><strong>SiteOverlay Pro:</strong> Trial expires in <?php echo $license_status['days_remaining']; ?> days! <a href="<?php echo admin_url('options-general.php?page=siteoverlay-settings'); ?>">Upgrade Now</a></p>
                    </div>
                    <?php
                } else {
                    ?>
                    <div class="notice notice-info is-dismissible">
                        <p><strong>SiteOverlay Pro:</strong> Trial active - <?php echo $license_status['days_remaining']; ?> days remaining. <a href="<?php echo admin_url('options-general.php?page=siteoverlay-settings'); ?>">View Details</a></p>
                    </div>
                    <?php
                }
                break;
                
            case 'trial_expired':
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><strong>SiteOverlay Pro:</strong> Trial expired - plugin is inactive. Enter a paid license to continue. <a href="<?php echo admin_url('options-general.php?page=siteoverlay-settings'); ?>">Upgrade Now</a></p>
                </div>
                <?php
                break;
        }
    }
    
    /**
     * Get dynamic content with fallback
     */
    public function get_dynamic_content($key, $fallback = '') {
        if (isset($this->dynamic_content_manager)) {
            $content = $this->dynamic_content_manager->get_dynamic_content();
            
            // Debug logging for key requests
            error_log("SiteOverlay: DISPLAY REQUEST - Looking for key '{$key}'");
            if (isset($content[$key])) {
                error_log("SiteOverlay: DISPLAY SUCCESS - Found '{$key}' = " . substr($content[$key], 0, 50) . "...");
                return $content[$key];
            } else {
                error_log("SiteOverlay: DISPLAY FALLBACK - Key '{$key}' not found, using fallback: " . substr($fallback, 0, 50) . "...");
                error_log("SiteOverlay: DISPLAY DEBUG - Available keys: " . implode(', ', array_keys($content)));
                return $fallback;
            }
        }
        error_log("SiteOverlay: DISPLAY ERROR - Dynamic content manager not available for key '{$key}'");
        return $fallback;
    }
    
    /**
     * AJAX handler for refreshing dynamic content
     */
    public function ajax_refresh_content() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'siteoverlay_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (isset($this->dynamic_content_manager)) {
            // Clear cache and fetch fresh content
            $this->dynamic_content_manager->clear_cache();
            $fresh_content = $this->dynamic_content_manager->get_dynamic_content();
            
            if ($fresh_content && count($fresh_content) > 0) {
                wp_send_json_success('Content refreshed successfully. ' . count($fresh_content) . ' items loaded.');
            } else {
                wp_send_json_error('Failed to fetch fresh content from API');
            }
        } else {
            wp_send_json_error('Dynamic Content Manager not available');
        }
    }
    
    /**
     * AJAX handler for forcing cache
     */
    public function ajax_force_cache() {
        if (!wp_verify_nonce($_POST['nonce'], 'siteoverlay_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (isset($this->dynamic_content_manager)) {
            $debug_steps = array();
            
            // Clear existing chunk-based cache using the proper method
            $this->dynamic_content_manager->clear_cache();
            $debug_steps[] = '✓ STEP 1: Cleared existing cache using clear_cache() method';
            
            // Force fresh API fetch
            $fresh_content = $this->dynamic_content_manager->fetch_content_from_api();
            
            if (is_array($fresh_content) && count($fresh_content) > 0) {
                $debug_steps[] = '✓ STEP 2: API fetch successful: ' . count($fresh_content) . ' items';
                
                // Use the chunking system to store content
                try {
                    $reflection = new ReflectionClass($this->dynamic_content_manager);
                    $store_method = $reflection->getMethod('store_content_chunks');
                    $store_method->setAccessible(true);
                    $retrieve_method = $reflection->getMethod('retrieve_content_chunks');
                    $retrieve_method->setAccessible(true);
                    
                    $storage_result = $store_method->invoke($this->dynamic_content_manager, $fresh_content);
                    $debug_steps[] = '✓ STEP 3: Dynamic chunking storage: ' . ($storage_result ? 'SUCCESS' : 'FAILED');
                    
                    // Verify chunk storage
                    $retrieved_content = $retrieve_method->invoke($this->dynamic_content_manager);
                    $debug_steps[] = '✓ STEP 4: Retrieved from chunks: ' . ($retrieved_content ? count($retrieved_content) . ' items' : 'NONE');
                    
                    // Show chunk details
                    $chunk_count = get_option('so_cache_count', 0);
                    $total_items = get_option('so_cache_total_items', 0);
                    $debug_steps[] = '✓ STEP 5: Chunk metadata: ' . $chunk_count . ' chunks, ' . $total_items . ' total items';
                    
                    // Verify individual chunks
                    $chunk_details = array();
                    for ($i = 0; $i < $chunk_count; $i++) {
                        $chunk_data = get_option("so_cache_{$i}", false);
                        $chunk_details[] = 'Chunk ' . $i . ': ' . ($chunk_data ? count($chunk_data) . ' items' : 'NOT FOUND');
                    }
                    $debug_steps[] = '✓ STEP 6: Chunk verification: ' . implode(', ', $chunk_details);
                    
                } catch (Exception $e) {
                    $debug_steps[] = '❌ STEP 3: Chunking method error: ' . $e->getMessage();
                    $storage_result = false;
                    $retrieved_content = false;
                }
                
            } else {
                $debug_steps[] = '❌ STEP 2: API fetch failed or returned no data';
                $storage_result = false;
                $retrieved_content = false;
            }
            
            $debug_output = implode('<br>', $debug_steps);
            
            if ($retrieved_content && count($retrieved_content) > 0) {
                wp_send_json_success('🎉 CHUNKING SUCCESS! ' . count($retrieved_content) . ' items cached using smart auto-chunking.<br><br><strong>Debug Steps:</strong><br>' . $debug_output);
            } else {
                wp_send_json_error('❌ CHUNKING FAILED!<br><br><strong>Debug Steps:</strong><br>' . $debug_output . '<br><br><strong>Fresh content received:</strong> ' . (is_array($fresh_content) ? count($fresh_content) : 'none') . ' items');
            }
        } else {
            wp_send_json_error('Dynamic Content Manager not available');
        }
    }
    
    /**
     * Get dynamic upgrade message (always available)
     */
    public function get_dynamic_upgrade_message() {
        if (isset($this->dynamic_content_manager)) {
            return $this->dynamic_content_manager->get_upgrade_message();
        }
        return 'Upgrade to unlock all SiteOverlay Pro features';
    }
    
    /**
     * Get dynamic Xagio affiliate URL (always available)
     */
    public function get_dynamic_xagio_affiliate_url() {
        if (isset($this->dynamic_content_manager)) {
            return $this->dynamic_content_manager->get_xagio_affiliate_url();
        }
        return 'https://xagio.net/?ref=siteoverlay';
    }
    
    /**
     * Get dynamic support URL (always available)
     */
    public function get_dynamic_support_url() {
        if (isset($this->dynamic_content_manager)) {
            return $this->dynamic_content_manager->get_support_url();
        }
        return 'https://siteoverlay.24hr.pro/support';
    }
    
    /**
     * Get dynamic training URL (always available)
     */
    public function get_dynamic_training_url() {
        if (isset($this->dynamic_content_manager)) {
            return $this->dynamic_content_manager->get_training_url();
        }
        return 'https://siteoverlay.24hr.pro/training';
    }
    
    /**
     * Test file permissions for plugin directory
     */
    public function test_file_permissions() {
        $test_file = plugin_dir_path(__FILE__) . 'test.txt';
        
        // Try to write
        if (file_put_contents($test_file, 'test') === false) {
            error_log('SiteOverlay: Cannot write files');
            return false;
        }
        
        // Try to delete
        if (!unlink($test_file)) {
            error_log('SiteOverlay: Cannot delete files');
            return false;
        }
        
        return true;
    }
    
    /**
     * Display overlay on frontend when plugin is active
     * Provides fast overlay loading for rank & rent websites
     */
    public function display_overlay() {
        // Only run on frontend single posts/pages, not during activation
        if (is_admin() || !is_singular()) return;
        
        global $post;
        if (!$post) return;
        
        $overlay_url = get_post_meta($post->ID, '_siteoverlay_overlay_url', true);
        if (!$overlay_url) return;
        
        // Increment view count (non-blocking)
        $views = get_post_meta($post->ID, '_siteoverlay_overlay_views', true) ?: 0;
        update_post_meta($post->ID, '_siteoverlay_overlay_views', $views + 1);
        
        // IMMEDIATE OVERLAY DISPLAY - no delays - with scrollbar fix
        ?>
        <style>
        body, html {
            overflow: hidden !important;
        }
        #siteoverlay-overlay-frame {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
            z-index: 999999;
            background: white;
        }
        </style>
        <script>
        (function() {
            var iframe = document.createElement('iframe');
            iframe.id = 'siteoverlay-overlay-frame';
            iframe.src = '<?php echo esc_js($overlay_url); ?>';
            iframe.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;border:none;z-index:999999;background:white;';
            document.documentElement.appendChild(iframe);
        })();
        </script>
        <?php
    }
}

// Initialize the plugin
new SiteOverlay_Pro();

// Register activation hook
register_activation_hook(__FILE__, 'siteoverlay_pro_activate');

// Register deactivation hook
register_deactivation_hook(__FILE__, 'siteoverlay_pro_deactivate');

// Register uninstall hook
register_uninstall_hook(__FILE__, 'siteoverlay_pro_uninstall');

// Activation function
function siteoverlay_pro_activate() {
    // Set default options if they don't exist
    if (!get_option('siteoverlay_license_status')) {
        update_option('siteoverlay_license_status', 'inactive');
    }
    
    // Clear any existing scheduled events
    wp_clear_scheduled_hook('siteoverlay_daily_heartbeat');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation function
function siteoverlay_pro_deactivate() {
    // Clear scheduled events
    wp_clear_scheduled_hook('siteoverlay_daily_heartbeat');
    
    // Clear transients
    delete_transient('siteoverlay_site_registered');
    delete_transient('siteoverlay_usage_stats');
    delete_transient('siteoverlay_license_last_check');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Uninstall function
function siteoverlay_pro_uninstall() {
    // Clear all scheduled cron jobs
    wp_clear_scheduled_hook('siteoverlay_daily_heartbeat');
    
    // Clear all transients
    delete_transient('siteoverlay_site_registered');
    delete_transient('siteoverlay_usage_stats');
    delete_transient('siteoverlay_license_last_check');
    
    // Remove all post meta related to overlays
    global $wpdb;
    $wpdb->delete($wpdb->postmeta, array('meta_key' => '_siteoverlay_overlay_url'));
    $wpdb->delete($wpdb->postmeta, array('meta_key' => '_siteoverlay_overlay_views'));
    $wpdb->delete($wpdb->postmeta, array('meta_key' => '_siteoverlay_overlay_updated'));
}

// Newsletter signup AJAX handler
add_action('wp_ajax_siteoverlay_newsletter_signup', function() {
    check_ajax_referer('siteoverlay_overlay_nonce', 'nonce');
    
    $email = sanitize_email($_POST['email'] ?? '');
    
    if (empty($email) || !is_email($email)) {
        wp_send_json_error('Please enter a valid email address');
        return;
    }
    
    // Simple success response
    wp_send_json_success('Thank you for subscribing to our SEO tips!');
});

// Save post hook for overlay data
add_action('save_post', function($post_id) {
    // Verify nonce
    if (!isset($_POST['siteoverlay_overlay_nonce']) || !wp_verify_nonce($_POST['siteoverlay_overlay_nonce'], 'siteoverlay_overlay_nonce')) {
        return;
    }
    
    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    // Save overlay URL if provided
    if (isset($_POST['siteoverlay_overlay_url'])) {
        $overlay_url = esc_url_raw($_POST['siteoverlay_overlay_url']);
        if (!empty($overlay_url)) {
            update_post_meta($post_id, '_siteoverlay_overlay_url', $overlay_url);
            update_post_meta($post_id, '_siteoverlay_overlay_updated', current_time('mysql'));
        } else {
            delete_post_meta($post_id, '_siteoverlay_overlay_url');
            delete_post_meta($post_id, '_siteoverlay_overlay_updated');
        }
    }
}); 
?>