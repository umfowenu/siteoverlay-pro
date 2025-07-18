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
 * CONSTITUTIONAL COMPLIANCE with LICENSE ENFORCEMENT
 * - When licensed: Overlay loads instantly using SACRED mechanism from older plugin
 * - When unlicensed: Core functionality disabled until license obtained
 * - License checks are cached for performance (no repeated validations)
 */
class SiteOverlay_Pro {
    
    private $license_manager;
    private $is_licensed = null; // Cached license status for performance
    
    public function __construct() {
        // CONSTITUTIONAL RULE: Initialize core functionality immediately
        add_action('init', array($this, 'init'), 1);
        add_action('wp_loaded', array($this, 'wp_loaded'), 1);
        
        // License manager (loads early for license checking)
        add_action('plugins_loaded', array($this, 'init_license_manager'), 5);
        
        // SACRED: EXACT overlay rendering mechanism from older plugin
        add_action('wp_head', array($this, 'display_overlay'), 1);
        
        // Admin functionality
        if (is_admin()) {
            add_action('admin_init', array($this, 'admin_init'));
            add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
            
            // AJAX handlers (constitutional compliance: immediate response)
            add_action('wp_ajax_siteoverlay_save_url', array($this, 'ajax_save_overlay'));
            add_action('wp_ajax_siteoverlay_remove_url', array($this, 'ajax_remove_overlay'));
            add_action('wp_ajax_siteoverlay_preview_url', array($this, 'ajax_preview_overlay'));
        }
        
        // Background license validation (non-blocking)
        add_action('wp_loaded', array($this, 'background_license_check'), 20);
    }
    
    /**
     * CONSTITUTIONAL COMPLIANCE: Core initialization (no external dependencies)
     */
    public function init() {
        // Load textdomain for internationalization
        load_plugin_textdomain('siteoverlay-rr', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // CONSTITUTIONAL RULE: No external API calls during initialization
        // All core functionality available immediately
    }
    
    public function wp_loaded() {
        // Core functionality fully loaded
        // Ready for overlay operations (if licensed)
    }
    
    /**
     * LICENSE ENFORCEMENT: Initialize license manager early for proper checking
     */
    public function init_license_manager() {
        if (!class_exists('SiteOverlay_License_Manager')) {
            require_once SITEOVERLAY_RR_PLUGIN_PATH . 'includes/class-license-manager.php';
        }
        $this->license_manager = new SiteOverlay_License_Manager();
        
        // Cache license status for performance (no repeated checks)
        $this->is_licensed = $this->license_manager->has_valid_license();
    }
    
    /**
     * Background license check (non-blocking)
     */
    public function background_license_check() {
        // License validation runs in background without blocking overlay
        if ($this->license_manager && !$this->license_manager->has_valid_license()) {
            add_action('admin_notices', array($this, 'license_notice'));
        }
    }
    
    /**
     * Check if plugin is licensed (cached for performance)
     */
    private function is_licensed() {
        if ($this->is_licensed === null) {
            $this->is_licensed = $this->license_manager ? $this->license_manager->has_valid_license() : false;
        }
        return $this->is_licensed;
    }
    
    public function admin_init() {
        // Register settings for overlay URLs
        register_setting('siteoverlay_settings', 'siteoverlay_urls');
    }
    
    /**
     * CONSTITUTIONAL COMPLIANCE: Meta box preserves exact original layout
     */
    public function add_meta_boxes() {
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
     * CONSTITUTIONAL COMPLIANCE: Meta box maintains sacred layout
     * LICENSE ENFORCEMENT: Shows license requirement when unlicensed
     */
    public function render_meta_box($post) {
        wp_nonce_field('siteoverlay_overlay_nonce', 'siteoverlay_overlay_nonce');
        
        // LICENSE ENFORCEMENT: Check license status
        if (!$this->is_licensed()) {
            $this->render_unlicensed_meta_box();
            return;
        }
        
        // CONSTITUTIONAL COMPLIANCE: Original sacred layout when licensed
        $overlay_url = get_post_meta($post->ID, '_siteoverlay_overlay_url', true);
        $is_active = !empty($overlay_url);
        $license_data = $this->license_manager ? $this->license_manager->get_license_data() : array();
        ?>
        
        <div id="siteoverlay-overlay-container">
            <!-- Logo Section (CORE) -->
            <div style="text-align: center; padding: 10px 0; background: white;">
                <img src="https://page1.genspark.site/v1/base64_upload/fe1edd2c48ac954784b3e58ed66b0764" alt="SiteOverlay Pro" style="max-width: 100%; height: auto;" />
            </div>
            
            <!-- License Status Display (ENHANCEMENT - shows licensed user name) -->
            <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 8px; text-align: center; font-size: 12px;">
                ✓ <strong>License Active</strong><br>
                <?php if (isset($license_data['licensed_to']) && $license_data['licensed_to'] !== 'Trial User'): ?>
                    Licensed to: <?php echo esc_html($license_data['licensed_to']); ?><br>
                <?php endif; ?>
                Type: <?php echo ucfirst(str_replace('_', ' ', $license_data['license_type'] ?? 'Licensed')); ?><br>
                👁 <?php echo get_post_meta($post->ID, '_siteoverlay_overlay_views', true) ?: '0'; ?> views
            </div>
            
            <!-- Xagio Affiliate Section (CORE PROMOTIONAL - DO NOT MODIFY) -->
            <div style="background: #d1ecf1; padding: 15px; text-align: center; margin: 0;">
                <div style="color: #0c5460; font-weight: bold; margin-bottom: 5px;">🚀 Boost Your SEO Rankings</div>
                <div style="color: #0c5460; font-size: 12px; margin-bottom: 10px;">Get Xagio - The #1 SEO Tool for Rank & Rent Success</div>
                <a href="https://xagio.net/?ref=siteoverlay" target="_blank" 
                   style="background: #17a2b8; color: white; padding: 6px 12px; text-decoration: none; border-radius: 3px; font-size: 11px; display: inline-block;">Get Xagio Now</a>
                <div style="color: #0c5460; font-size: 10px; margin-top: 5px;">Affiliate Link - We earn a commission at no cost to you</div>
            </div>
            
            <!-- Current Overlay Section (CORE INTERFACE - DO NOT MODIFY LAYOUT) -->
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
            
            <!-- Email Newsletter Section (CORE PROMOTIONAL) -->
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 12px; margin: 0;">
                <div style="color: #856404; font-weight: bold; margin-bottom: 8px;">📧 Get SEO & Rank & Rent Tips</div>
                <input type="email" id="newsletter-email" placeholder="Enter your email" 
                       style="width: 100%; padding: 4px; border: 1px solid #ffeaa7; background: white; font-size: 11px; margin-bottom: 8px;" />
                <button type="button" class="button" id="subscribe-newsletter" style="background: #ffc107; border: 1px solid #ffc107; color: #212529; font-size: 11px; padding: 4px 8px; width: 100%;">Subscribe for Free Tips</button>
            </div>
            
            <!-- Stats Section (CORE) -->
            <div style="padding: 8px 12px; color: #666; font-size: 10px; border-top: 1px solid #ddd;">
                Views: <?php echo get_post_meta($post->ID, '_siteoverlay_overlay_views', true) ?: '0'; ?> | 
                Last Updated: <?php echo get_post_meta($post->ID, '_siteoverlay_overlay_updated', true) ?: 'Never'; ?>
                | License: Active
            </div>
            
            <div id="siteoverlay-response" style="margin-top: 10px;"></div>
        </div>
        
        <!-- CONSTITUTIONAL RULE: Preserve original meta box styling -->
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
     * LICENSE ENFORCEMENT: Render unlicensed state with link to license page
     */
    private function render_unlicensed_meta_box() {
        ?>
        <div id="siteoverlay-overlay-container">
            <!-- Logo Section -->
            <div style="text-align: center; padding: 10px 0; background: white;">
                <img src="https://page1.genspark.site/v1/base64_upload/fe1edd2c48ac954784b3e58ed66b0764" alt="SiteOverlay Pro" style="max-width: 100%; height: auto;" />
            </div>
            
            <!-- License Required Section -->
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; margin: 0; text-align: center;">
                <h4 style="margin: 0 0 10px 0; color: #856404;"><span class="dashicons dashicons-lock"></span> License Required</h4>
                <p style="margin: 10px 0; color: #856404;">SiteOverlay Pro requires a valid license to function.</p>
                <p style="margin: 15px 0;">
                    <a href="<?php echo admin_url('options-general.php?page=siteoverlay-license'); ?>" class="button button-primary">
                        🔑 Get Free Trial or Enter License
                    </a>
                </p>
                <p style="font-size: 11px; color: #666; margin: 5px 0 0 0;">
                    New users can start a free 14-day trial with just name and email.
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * CONSTITUTIONAL COMPLIANCE: Admin scripts enqueue
     */
    public function admin_enqueue_scripts($hook) {
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }
        
        // Only load scripts if licensed (constitutional compliance: no resource waste)
        if (!$this->is_licensed()) {
            return;
        }
        
        wp_enqueue_script(
            'siteoverlay-admin',
            SITEOVERLAY_RR_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            SITEOVERLAY_RR_VERSION,
            true
        );
        
        wp_localize_script('siteoverlay-admin', 'siteoverlay_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('siteoverlay_overlay_nonce'),
            'post_id' => get_the_ID(),
            'strings' => array(
                'saving' => __('Saving...', 'siteoverlay-rr'),
                'saved' => __('Saved successfully!', 'siteoverlay-rr'),
                'error' => __('Error saving overlay URL', 'siteoverlay-rr'),
                'removing' => __('Removing...', 'siteoverlay-rr'),
                'removed' => __('Overlay removed successfully!', 'siteoverlay-rr'),
                'confirm_remove' => __('Are you sure you want to remove this overlay?', 'siteoverlay-rr')
            )
        ));
    }
    
    /**
     * CONSTITUTIONAL COMPLIANCE: AJAX save URL (immediate response)
     * LICENSE ENFORCEMENT: Only works when licensed
     */
    public function ajax_save_overlay() {
        // LICENSE ENFORCEMENT: Check license first
        if (!$this->is_licensed()) {
            wp_send_json_error('License required. Please activate your license first.');
            return;
        }
        
        // CONSTITUTIONAL RULE: Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'siteoverlay_overlay_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // CONSTITUTIONAL RULE: Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $post_id = intval($_POST['post_id']);
        $overlay_url = esc_url_raw($_POST['overlay_url']);
        
        if (empty($post_id) || empty($overlay_url)) {
            wp_send_json_error('Invalid post ID or URL');
            return;
        }
        
        // CONSTITUTIONAL RULE: Save data immediately without external dependencies
        update_post_meta($post_id, '_siteoverlay_overlay_url', $overlay_url);
        update_post_meta($post_id, '_siteoverlay_overlay_updated', current_time('mysql'));
        
        // Track usage for license manager (background operation)
        if ($this->license_manager) {
            $this->license_manager->track_usage($post_id);
        }
        
        // CONSTITUTIONAL RULE: Return success response immediately
        wp_send_json_success(array(
            'message' => 'Overlay saved successfully!',
            'url' => $overlay_url
        ));
    }
    
    /**
     * CONSTITUTIONAL COMPLIANCE: AJAX remove URL (immediate response)
     * LICENSE ENFORCEMENT: Only works when licensed
     */
    public function ajax_remove_overlay() {
        // LICENSE ENFORCEMENT: Check license first
        if (!$this->is_licensed()) {
            wp_send_json_error('License required. Please activate your license first.');
            return;
        }
        
        // CONSTITUTIONAL RULE: Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'], 'siteoverlay_overlay_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // CONSTITUTIONAL RULE: Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $post_id = intval($_POST['post_id']);
        
        if (empty($post_id)) {
            wp_send_json_error('Invalid post ID');
            return;
        }
        
        // CONSTITUTIONAL RULE: Remove data immediately
        delete_post_meta($post_id, '_siteoverlay_overlay_url');
        delete_post_meta($post_id, '_siteoverlay_overlay_updated');
        
        // CONSTITUTIONAL RULE: Return success response immediately
        wp_send_json_success(array(
            'message' => 'Overlay removed successfully!'
        ));
    }
    
    /**
     * AJAX preview URL handler
     */
    public function ajax_preview_overlay() {
        // LICENSE ENFORCEMENT: Check license first
        if (!$this->is_licensed()) {
            wp_send_json_error('License required. Please activate your license first.');
            return;
        }
        
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
     * SACRED: EXACT overlay display mechanism from older plugin
     * CONSTITUTIONAL COMPLIANCE: Immediate loading when licensed
     * LICENSE ENFORCEMENT: Only render when licensed
     */
    public function display_overlay() {
        // LICENSE ENFORCEMENT: Only render overlay when licensed
        if (!$this->is_licensed()) {
            return;
        }
        
        // Only run on frontend single posts/pages, not during activation
        if (is_admin() || !is_singular()) return;
        
        global $post;
        if (!$post) return;
        
        $overlay_url = get_post_meta($post->ID, '_siteoverlay_overlay_url', true);
        if (!$overlay_url) return;
        
        // Increment view count (non-blocking)
        $views = get_post_meta($post->ID, '_siteoverlay_overlay_views', true) ?: 0;
        update_post_meta($post->ID, '_siteoverlay_overlay_views', $views + 1);
        
        // SACRED: EXACT mechanism from older plugin - JavaScript injection for instant loading
        ?>
        <style>
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
    
    /**
     * Enhanced license notices (non-blocking)
     */
    public function license_notice() {
        if (!$this->license_manager) return;
        
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('post', 'page', 'edit-post', 'edit-page'))) {
            return; // Only show on post/page editing screens
        }
        
        if ($this->license_manager->has_valid_license()) {
            $license_data = $this->license_manager->get_license_data();
            
            // Show expiration warning for annual licenses
            if (isset($license_data['expires']) && $license_data['expires'] !== 'Never') {
                $expires = strtotime($license_data['expires']);
                if ($expires) {
                    $days_left = ceil(($expires - time()) / (24 * 60 * 60));
                    if ($days_left <= 7 && $days_left > 0) {
                        echo '<div class="notice notice-warning">';
                        echo '<p><strong>SiteOverlay Pro:</strong> Your license expires in ' . $days_left . ' days. ';
                        echo '<a href="https://siteoverlay.24hr.pro/" target="_blank">Renew your license</a> to continue using all features.</p>';
                        echo '</div>';
                    } elseif ($days_left <= 0) {
                        echo '<div class="notice notice-error">';
                        echo '<p><strong>SiteOverlay Pro:</strong> Your license has expired. ';
                        echo '<a href="https://siteoverlay.24hr.pro/" target="_blank">Renew your license</a> to restore full functionality.</p>';
                        echo '</div>';
                    }
                }
            }
        } else {
            // Show trial/no license notice
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>SiteOverlay Pro:</strong> You\'re using without a license. ';
            echo '<a href="' . admin_url('options-general.php?page=siteoverlay-license') . '">Get your free trial license</a> or ';
            echo '<a href="https://siteoverlay.24hr.pro/" target="_blank">purchase a full license</a>.</p>';
            echo '</div>';
        }
    }
}

// Initialize the plugin
new SiteOverlay_Pro();

// ENHANCEMENT - Newsletter signup AJAX handler
add_action('wp_ajax_siteoverlay_newsletter_signup', function() {
    check_ajax_referer('siteoverlay_overlay_nonce', 'nonce');
    
    $email = sanitize_email($_POST['email'] ?? '');
    
    if (empty($email) || !is_email($email)) {
        wp_send_json_error('Please enter a valid email address');
        return;
    }
    
    // Simple success response - actual API integration handled separately
    wp_send_json_success('Thank you for subscribing to our SEO tips!');
});

// ENHANCEMENT - Save post hook for overlay data
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