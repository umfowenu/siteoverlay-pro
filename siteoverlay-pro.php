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
    
    // License enforcement properties
    private $license_manager;
    private $is_licensed = null;
    
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
            add_action('wp_ajax_siteoverlay_request_paid_license', array($this, 'ajax_request_paid_license'));
            add_action('wp_ajax_siteoverlay_save_license_key', array($this, 'ajax_save_license_key'));
            add_action('wp_ajax_siteoverlay_clear_license_data', array($this, 'ajax_clear_license_data'));
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
     * Check if plugin is licensed (simple direct check)
     */
    private function is_licensed() {
        if ($this->is_licensed === null) {
            // Get the stored license key
            $stored_license_key = get_option('siteoverlay_license_key', '');
            
            if (empty($stored_license_key)) {
                error_log('🔍 No license key stored');
                $this->is_licensed = false;
                return false;
            }
            
            // Check cached validation result (valid for 1 hour)
            $cached_result = get_option('siteoverlay_license_valid', null);
            $last_validated = get_option('siteoverlay_license_validated', 0);
            $cache_duration = 3600; // 1 hour
            
            if ($cached_result !== null && (time() - $last_validated) < $cache_duration) {
                error_log('🔍 Using cached license result: ' . ($cached_result ? 'VALID' : 'INVALID'));
                $this->is_licensed = (bool)$cached_result;
                return $this->is_licensed;
            }
            
            // If no cached result or cache expired, validate now
            $validation_result = $this->validate_license_with_api_sync($stored_license_key);
            
            // Cache the result
            update_option('siteoverlay_license_valid', $validation_result);
            update_option('siteoverlay_license_validated', time());
            
            $this->is_licensed = $validation_result;
        }
        
        return $this->is_licensed;
    }

    private function validate_license_with_api_sync($license_key) {
        error_log('🔍 Validating license with API: ' . substr($license_key, 0, 8) . '...');
        
        $response = wp_remote_post('https://siteoverlay-api-production.up.railway.app/api/validate-license', array(
            'body' => json_encode(array(
                'licenseKey' => $license_key,
                'siteUrl' => home_url()
            )),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 10,
            'blocking' => true
        ));
        
        if (is_wp_error($response)) {
            error_log('🚨 License validation error: ' . $response->get_error_message());
            // CRITICAL FIX: Clear invalid license on API error
            delete_option('siteoverlay_license_key');
            delete_option('siteoverlay_license_valid');
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['success'])) {
            error_log('🚨 Invalid API response format');
            // CRITICAL FIX: Clear invalid license on malformed response
            delete_option('siteoverlay_license_key');
            delete_option('siteoverlay_license_valid');
            return false;
        }
        
        $is_valid = $data['success'] === true;
        error_log('🔍 License validation result: ' . ($is_valid ? 'VALID' : 'INVALID'));
        
        if (!$is_valid) {
            error_log('🚨 License validation failed: ' . ($data['message'] ?? 'Unknown error'));
            
            // CRITICAL FIX: Always clear invalid licenses
            delete_option('siteoverlay_license_key');
            delete_option('siteoverlay_license_valid');
            delete_option('siteoverlay_license_validated');
            
            // Also clear old license status data
            delete_option('siteoverlay_license_status');
            delete_option('siteoverlay_license_expiry');
            
            return false;
        }
        
        error_log('✅ License validation successful');
        return true;
    }
    
    /**
     * Manually clear all license data (for testing/debugging)
     */
    public function clear_all_license_data() {
        delete_option('siteoverlay_license_key');
        delete_option('siteoverlay_license_valid');
        delete_option('siteoverlay_license_validated');
        delete_option('siteoverlay_license_status');
        delete_option('siteoverlay_license_expiry');
        delete_option('siteoverlay_registration_name');
        delete_option('siteoverlay_registration_email');
        
        error_log('🗑️ All license data cleared manually');
    }
    
    /**
     * Display license notice (non-blocking)
     */
    public function license_notice() {
        if (!$this->is_licensed()) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>SiteOverlay Pro:</strong> Plugin is inactive. Activate your license to use SiteOverlay Pro. <a href="' . admin_url('options-general.php?page=siteoverlay-settings') . '">Activate Now</a></p>';
            echo '</div>';
        }
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
                <img src="https://ebiz360.ca/wp-content/uploads/2025/08/siteoverlay-pro-logo.png" alt="SiteOverlay Pro" style="max-width: 300px; height: auto;" />
            </div>
            
            <!-- Status Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <!-- License-aware status display -->
                <?php if ($this->is_licensed()): ?>
                <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 5px;">
                    <h3 style="margin: 0 0 10px 0; color: #155724;">✅ Plugin Licensed & Active</h3>
                    <p style="margin: 0; color: #155724;">SiteOverlay Pro is running successfully</p>
                </div>
                <?php else: ?>
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 5px;">
                    <h3 style="margin: 0 0 10px 0; color: #856404;">⚠️ Plugin Unlicensed</h3>
                    <p style="margin: 0; color: #856404;">Activate your license to enable all features</p>
                </div>
                <?php endif; ?>
                
                <div style="background: #d1ecf1; border: 1px solid #bee5eb; padding: 20px; border-radius: 5px;">
                    <h3 style="margin: 0 0 10px 0; color: #0c5460;">📊 Usage Statistics</h3>
                    <p style="margin: 0; color: #0c5460;">
                        <strong><?php echo $posts_with_overlays ?: '0'; ?></strong> posts with overlays<br>
                        <strong><?php echo $total_views ?: '0'; ?></strong> total views
                    </p>
                </div>
                
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 5px;">
                    <h3 style="margin: 0 0 10px 0; color: #856404;"><?php echo esc_html($this->get_dynamic_content('metabox_boost_title', '🚀 Boost Your SEO Rankings')); ?></h3>
                    <p style="margin: 0; color: #856404;"><?php echo esc_html($this->get_dynamic_content('metabox_boost_subtitle', 'Get Xagio - The #1 SEO Tool for Rank & Rent Success')); ?></p>
                    <a href="<?php echo esc_url($this->get_dynamic_content('metabox_affiliate_url', 'https://xagio.net/?ref=siteoverlay')); ?>" target="_blank" class="button button-primary" style="margin-top: 10px;"><?php echo esc_html($this->get_dynamic_content('metabox_button_text', 'Get Xagio Now')); ?></a>
                </div>
            </div>
            
            <?php
            // Debug the dynamic content calls with error handling
            try {
                $plugin_download_url = $this->get_dynamic_content('plugin_download_url', '#');
                error_log('Plugin download URL retrieved: ' . $plugin_download_url);
            } catch (Exception $e) {
                error_log('Dynamic content error (plugin_download_url): ' . $e->getMessage());
                $plugin_download_url = '#';
            }
            
            try {
                $installation_video_url = $this->get_dynamic_content('installation_video_url', '#');
                error_log('Installation video URL retrieved: ' . $installation_video_url);
            } catch (Exception $e) {
                error_log('Dynamic content error (installation_video_url): ' . $e->getMessage());
                $installation_video_url = '#';
            }
            
            try {
                $installation_guide_pdf_url = $this->get_dynamic_content('installation_guide_pdf_url', '#');
                error_log('Installation guide PDF URL retrieved: ' . $installation_guide_pdf_url);
            } catch (Exception $e) {
                error_log('Dynamic content error (installation_guide_pdf_url): ' . $e->getMessage());
                $installation_guide_pdf_url = '#';
            }
            ?>
            
            <!-- License Status & Downloads Section (Side by Side) -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; align-items: stretch;">
                <!-- License Status Section -->
                <div style="background: white; border: 1px solid #ddd; padding: 20px;">
                    <h2>License Status</h2>
                
                <?php 
                $is_registered = get_option('siteoverlay_registration_name');
                $should_disable_trial = $this->should_disable_trial_button();
                
                // Debug information for license tracking
                $stored_license_key = get_option('siteoverlay_license_key', 'None');
                $last_validated = get_option('siteoverlay_license_validated', 0);
                ?>
                
                <!-- License Debug Information -->
                <div style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; margin-bottom: 20px; border-radius: 3px;">
                    <h4 style="margin: 0 0 10px 0; color: #495057;">Current License Information</h4>
                    <p style="margin: 5px 0; font-size: 13px;"><strong>Stored License Key:</strong> <?php echo esc_html($stored_license_key); ?></p>
                    <p style="margin: 5px 0; font-size: 13px;"><strong>Last Validated:</strong> <?php echo ($last_validated > 0 ? date('Y-m-d H:i:s', $last_validated) : 'Never'); ?></p>
                    <p style="margin: 5px 0; font-size: 13px;"><strong>Cache Status:</strong> <?php echo get_option('siteoverlay_license_valid', null) === null ? 'No cache' : (get_option('siteoverlay_license_valid') ? 'Valid' : 'Invalid'); ?></p>
                    <p style="margin: 5px 0; font-size: 13px;"><strong>License Status:</strong> 
                        <?php 
                        $is_licensed = $this->is_licensed();
                        if ($is_licensed) {
                            echo '✅ Licensed - Plugin Active';
                        } else {
                            echo '❌ Not Licensed - Plugin Disabled';
                        }
                        ?>
                    </p>
                    
                    <!-- MANUAL CLEAR BUTTON -->
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                        <button type="button" class="button button-secondary" id="manual-clear-license" style="background: #dc3545; color: white; border-color: #dc3545;">
                            🗑️ Clear All License Data
                        </button>
                        <p style="font-size: 11px; color: #666; margin: 5px 0 0 0;">Use this to clear superseded/invalid licenses</p>
                    </div>
                </div>
                
                <!-- License Activation Options -->
                <?php if ($license_status['state'] === 'unlicensed'): ?>
                    <!-- UNLICENSED STATE -->
                    <div style="background: white; border: 1px solid #ddd; padding: 20px; margin-bottom: 20px;">
                        <h2>🎯 Get Started with SiteOverlay Pro</h2>
                        <p>Choose how you'd like to activate SiteOverlay Pro:</p>
                        
                        <!-- All Options in One Row -->
                        <div style="display: flex; gap: 10px; margin: 20px 0; flex-wrap: wrap; justify-content: center;">
                            <!-- Trial Button -->
                            <button type="button" class="button button-primary" id="show-trial-form">Start 14-Day Free Trial</button>
                            
                            <!-- Already Purchased Button -->
                            <button type="button" class="button" id="show-paid-license-form">💳 Already Purchased?</button>
                            
                            <!-- Enter License Key Button -->
                            <button type="button" class="button" id="show-license-key-form">🔑 Enter License Key</button>
                        </div>
                        
                        <!-- FORMS GO HERE - Between buttons and purchase options -->
                        
                        <!-- Trial Registration Form (Hidden by default) -->
                        <div id="trial-registration-form" style="display: none; background: #e8f5e8; border: 1px solid #4caf50; padding: 20px; margin: 15px 0; border-radius: 5px;">
                            <h4 style="margin: 0 0 15px 0; color: #388e3c;">🎯 Start Your 14-Day Free Trial</h4>
                            <p style="margin: 0 0 15px 0; color: #388e3c;">Get instant access to SiteOverlay Pro with no commitment.</p>
                            
                            <div style="margin-bottom: 15px;">
                                <input type="text" id="trial-name" placeholder="Enter your full name" style="width: 100%; padding: 8px;" />
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <input type="email" id="trial-email" placeholder="Enter your email address" style="width: 100%; padding: 8px;" />
                            </div>
                            
                            <button type="button" class="button button-primary" id="request-trial">Start Free Trial</button>
                            <div id="trial-response" style="margin-top: 10px;"></div>
                        </div>

                        <!-- Paid License Request Form (Hidden by default) -->
                        <div id="paid-license-form" style="display: none; background: #f0f8ff; border: 1px solid #0073aa; padding: 20px; margin: 15px 0; border-radius: 5px;">
                            <h4 style="margin: 0 0 15px 0; color: #0073aa;">💳 Request Your License Key</h4>
                            <p style="margin: 0 0 15px 0; color: #0073aa;">Enter your purchase details to receive your license key via email.</p>
                            
                            <div style="margin-bottom: 15px;">
                                <input type="text" id="paid-license-name" placeholder="Enter your full name" style="width: 100%; padding: 8px;" />
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <input type="email" id="paid-license-email" placeholder="Enter your email address" style="width: 100%; padding: 8px;" />
                            </div>
                            
                            <button type="button" class="button button-primary" id="request-paid-license">Request License Key</button>
                            <div id="paid-license-response" style="margin-top: 10px;"></div>
                        </div>

                        <!-- License Key Entry Form (Hidden by default) -->
                        <div id="license-key-form" style="display: none; background: #fff2e6; border: 1px solid #ff8c00; padding: 20px; margin: 15px 0; border-radius: 5px;">
                            <h4 style="margin: 0 0 15px 0; color: #ff8c00;">🔑 Activate Your License</h4>
                            <p style="margin: 0 0 15px 0; color: #ff8c00;">Enter your license key to activate SiteOverlay Pro.</p>
                            
                            <div style="margin-bottom: 15px;">
                                <input type="text" id="license-key-input" placeholder="SITE-XXXX-XXXX-XXXX" style="width: 100%; padding: 8px;" />
                            </div>
                            
                            <button type="button" class="button button-primary" id="activate-license">Activate License</button>
                            <div id="license-activation-response" style="margin-top: 10px;"></div>
                        </div>
                        
                        <!-- Purchase Options Section - COMES AFTER FORMS -->
                        <div style="margin: 30px 0;">
                            <h3>🚀 Or Purchase a License</h3>
                            <div style="display: flex; gap: 10px; margin: 15px 0;">
                                <div style="background: #e3f2fd; border: 1px solid #2196f3; padding: 15px; border-radius: 5px; flex: 1; text-align: center;">
                                    <h4 style="margin: 0 0 10px 0; color: #1976d2;">Professional</h4>
                                    <div style="font-size: 18px; font-weight: bold; color: #1976d2;">$35/month</div>
                                    <div style="font-size: 12px; color: #666; margin: 5px 0;">Up to 5 websites</div>
                                    <a href="https://siteoverlay.24hr.pro/?plan=professional" target="_blank" class="button button-primary" style="margin-top: 10px;">Get Professional</a>
                                </div>
                                <div style="background: #e8f5e8; border: 1px solid #4caf50; padding: 15px; border-radius: 5px; flex: 1; text-align: center;">
                                    <h4 style="margin: 0 0 10px 0; color: #388e3c;">Annual Unlimited</h4>
                                    <div style="font-size: 18px; font-weight: bold; color: #388e3c;">$197/year</div>
                                    <div style="font-size: 12px; color: #666; margin: 5px 0;">Unlimited websites</div>
                                    <a href="https://siteoverlay.24hr.pro/?plan=annual" target="_blank" class="button button-primary" style="margin-top: 10px;">Get Annual</a>
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
                    <!-- Trial Active - Show paid options but disable trial -->
                    <div style="background: #e8f5e8; border: 1px solid #4caf50; padding: 20px; text-align: center; border-radius: 5px;">
                        <h2 style="margin: 0; color: #388e3c;">✅ Trial License Active</h2>
                        <p style="margin: 5px 0 0 0; color: #388e3c;">Your 14-day trial is active. Upgrade anytime!</p>
                    </div>
                    
                    <!-- License Options - Trial disabled, others available -->
                    <div style="background: white; border: 1px solid #ddd; padding: 20px; margin-bottom: 20px;">
                        <h2>🎯 Upgrade Your License</h2>
                        <p>Your trial is active. Purchase a license to continue after trial expires:</p>
                        
                        <!-- License Options - Trial grayed out -->
                        <div style="display: flex; gap: 10px; margin: 20px 0; flex-wrap: wrap; justify-content: center;">
                            <!-- Trial Button - Disabled/Grayed Out -->
                            <button type="button" class="button" disabled style="opacity: 0.5; cursor: not-allowed;">✅ Trial Already Active</button>
                            
                            <!-- Already Purchased Button - Still Available -->
                            <button type="button" class="button" id="show-paid-license-form">💳 Already Purchased?</button>
                            
                            <!-- Enter License Key Button - Still Available -->
                            <button type="button" class="button" id="show-license-key-form">🔑 Enter License Key</button>
                        </div>
                        
                        <!-- Keep the same forms (paid license request and license key entry) -->
                        <!-- Paid License Request Form -->
                        <div id="paid-license-form" style="display: none; background: #f0f8ff; border: 1px solid #0073aa; padding: 20px; margin: 15px 0; border-radius: 5px;">
                            <h4 style="margin: 0 0 15px 0; color: #0073aa;">💳 Request Your License Key</h4>
                            <p style="margin: 0 0 15px 0; color: #0073aa;">Enter your purchase details to receive your license key via email.</p>
                            
                            <div style="margin-bottom: 15px;">
                                <input type="text" id="paid-license-name" placeholder="Enter your full name" style="width: 100%; padding: 8px;" />
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <input type="email" id="paid-license-email" placeholder="Enter your email address" style="width: 100%; padding: 8px;" />
                            </div>
                            
                            <button type="button" class="button button-primary" id="request-paid-license">Request License Key</button>
                            <div id="paid-license-response" style="margin-top: 10px;"></div>
                        </div>

                        <!-- License Key Entry Form -->
                        <div id="license-key-form" style="display: none; background: #fff2e6; border: 1px solid #ff8c00; padding: 20px; margin: 15px 0; border-radius: 5px;">
                            <h4 style="margin: 0 0 15px 0; color: #ff8c00;">🔑 Activate Your License</h4>
                            <p style="margin: 0 0 15px 0; color: #ff8c00;">Enter your license key to activate SiteOverlay Pro.</p>
                            
                            <div style="margin-bottom: 15px;">
                                <input type="text" id="license-key-input" placeholder="SITE-XXXX-XXXX-XXXX" style="width: 100%; padding: 8px;" />
                            </div>
                            
                            <button type="button" class="button button-primary" id="activate-license">Activate License</button>
                            <div id="license-activation-response" style="margin-top: 10px;"></div>
                        </div>
                        
                        <!-- Purchase Options Section - Still Available -->
                        <div style="margin: 30px 0;">
                            <h3>🚀 Purchase a License</h3>
                            <div style="display: flex; gap: 10px; margin: 15px 0;">
                                <div style="background: #e3f2fd; border: 1px solid #2196f3; padding: 15px; border-radius: 5px; flex: 1; text-align: center;">
                                    <h4 style="margin: 0 0 10px 0; color: #1976d2;">Professional</h4>
                                    <div style="font-size: 18px; font-weight: bold; color: #1976d2;">$35/month</div>
                                    <div style="font-size: 12px; color: #666; margin: 5px 0;">Up to 5 websites</div>
                                    <a href="https://siteoverlay.24hr.pro/?plan=professional" target="_blank" class="button button-primary" style="margin-top: 10px;">Get Professional</a>
                                </div>
                                <div style="background: #e8f5e8; border: 1px solid #4caf50; padding: 15px; border-radius: 5px; flex: 1; text-align: center;">
                                    <h4 style="margin: 0 0 10px 0; color: #388e3c;">Annual Unlimited</h4>
                                    <div style="font-size: 18px; font-weight: bold; color: #388e3c;">$197/year</div>
                                    <div style="font-size: 12px; color: #666; margin: 5px 0;">Unlimited websites</div>
                                    <a href="https://siteoverlay.24hr.pro/?plan=annual" target="_blank" class="button button-primary" style="margin-top: 10px;">Get Annual</a>
                                </div>
                            </div>
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
                    
                    <!-- LICENSE UPGRADE OPTIONS - Always Available -->
                    <div style="background: white; border: 1px solid #ddd; padding: 20px; margin-bottom: 20px;">
                        <h2>🔄 License Management</h2>
                        <p>Your license is active. You can upgrade or change your license anytime:</p>
                        
                        <!-- License Options - All Available -->
                        <div style="display: flex; gap: 10px; margin: 20px 0; flex-wrap: wrap; justify-content: center;">
                            <!-- Trial Button - Disabled (already have license) -->
                            <button type="button" class="button" disabled style="opacity: 0.5; cursor: not-allowed;">✅ License Already Active</button>
                            
                            <!-- Already Purchased Button - Still Available for upgrades -->
                            <button type="button" class="button" id="show-paid-license-form">💳 Already Purchased Upgrade?</button>
                            
                            <!-- Enter License Key Button - Still Available -->
                            <button type="button" class="button" id="show-license-key-form">🔑 Enter Different License Key</button>
                        </div>
                        
                        <!-- Paid License Request Form -->
                        <div id="paid-license-form" style="display: none; background: #f0f8ff; border: 1px solid #0073aa; padding: 20px; margin: 15px 0; border-radius: 5px;">
                            <h4 style="margin: 0 0 15px 0; color: #0073aa;">💳 Request Your Upgrade License Key</h4>
                            <p style="margin: 0 0 15px 0; color: #0073aa;">Enter your upgrade purchase details to receive your new license key via email.</p>
                            
                            <div style="margin-bottom: 15px;">
                                <input type="text" id="paid-license-name" placeholder="Enter your full name" style="width: 100%; padding: 8px;" />
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <input type="email" id="paid-license-email" placeholder="Enter your email address" style="width: 100%; padding: 8px;" />
                            </div>
                            
                            <button type="button" class="button button-primary" id="request-paid-license">Request Upgrade License Key</button>
                            <div id="paid-license-response" style="margin-top: 10px;"></div>
                        </div>

                        <!-- License Key Entry Form -->
                        <div id="license-key-form" style="display: none; background: #fff2e6; border: 1px solid #ff8c00; padding: 20px; margin: 15px 0; border-radius: 5px;">
                            <h4 style="margin: 0 0 15px 0; color: #ff8c00;">🔑 Activate Different License</h4>
                            <p style="margin: 0 0 15px 0; color: #ff8c00;">Enter a different license key to replace your current license.</p>
                            
                            <div style="margin-bottom: 15px;">
                                <input type="text" id="license-key-input" placeholder="SITE-XXXX-XXXX-XXXX" style="width: 100%; padding: 8px;" />
                            </div>
                            
                            <button type="button" class="button button-primary" id="activate-license">Replace Current License</button>
                            <div id="license-activation-response" style="margin-top: 10px;"></div>
                        </div>
                        
                        <!-- Purchase Options Section - Still Available for upgrades -->
                        <div style="margin: 30px 0;">
                            <h3>🚀 Upgrade Your License</h3>
                            <div style="display: flex; gap: 10px; margin: 15px 0;">
                                <div style="background: #e3f2fd; border: 1px solid #2196f3; padding: 15px; border-radius: 5px; flex: 1; text-align: center;">
                                    <h4 style="margin: 0 0 10px 0; color: #1976d2;">Professional</h4>
                                    <div style="font-size: 18px; font-weight: bold; color: #1976d2;">$35/month</div>
                                    <div style="font-size: 12px; color: #666; margin: 5px 0;">Up to 5 websites</div>
                                    <a href="https://siteoverlay.24hr.pro/?plan=professional" target="_blank" class="button button-primary" style="margin-top: 10px;">Upgrade to Professional</a>
                                </div>
                                <div style="background: #e8f5e8; border: 1px solid #4caf50; padding: 15px; border-radius: 5px; flex: 1; text-align: center;">
                                    <h4 style="margin: 0 0 10px 0; color: #388e3c;">Annual Unlimited</h4>
                                    <div style="font-size: 18px; font-weight: bold; color: #388e3c;">$197/year</div>
                                    <div style="font-size: 12px; color: #666; margin: 5px 0;">Unlimited websites</div>
                                    <a href="https://siteoverlay.24hr.pro/?plan=annual" target="_blank" class="button button-primary" style="margin-top: 10px;">Upgrade to Annual</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div id="license-response" style="margin-top: 10px;"></div>
                </div>
                
                <!-- Downloads & Documentation Section -->
                <div style="background: white; border: 1px solid #ddd; padding: 20px; display: flex; flex-direction: column;">
                    <h3>📥 Downloads & Documentation</h3>
                    
                    <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 5px; margin-top: 15px; flex: 1; display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <p style="margin: 0 0 20px 0;"><strong>Plugin Download:</strong> 
                               <a href="<?php echo esc_url($plugin_download_url); ?>" target="_blank" class="button button-primary">📦 Download Latest Version</a>
                            </p>
                            <p style="margin: 0 0 20px 0;"><strong>Installation Video:</strong> 
                               <a href="<?php echo esc_url($installation_video_url); ?>" target="_blank" class="button">🎥 Watch Tutorial</a>
                            </p>
                            <p style="margin: 0 0 20px 0;"><strong>Installation Guide:</strong> 
                               <a href="<?php echo esc_url($installation_guide_pdf_url); ?>" target="_blank" class="button">📄 Download PDF Guide</a>
                            </p>
                        </div>
                        
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ffeaa7;">
                            <h4 style="margin: 0 0 15px 0; color: #856404;">Recent Overlays</h4>
                            <?php if ($recent_overlays): ?>
                                <table class="wp-list-table widefat fixed striped" style="background: white; border-radius: 3px;">
                                    <thead>
                                        <tr>
                                            <th style="font-size: 11px;">Post/Page</th>
                                            <th style="font-size: 11px;">Overlay URL</th>
                                            <th style="font-size: 11px;">Views</th>
                                            <th style="font-size: 11px;">Last Updated</th>
                                            <th style="font-size: 11px;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_overlays as $overlay): ?>
                                            <tr>
                                                <td style="font-size: 11px;">
                                                    <a href="<?php echo get_edit_post_link($overlay->ID); ?>"><?php echo esc_html($overlay->post_title); ?></a>
                                                </td>
                                                <td style="font-size: 11px;">
                                                    <a href="<?php echo esc_url($overlay->overlay_url); ?>" target="_blank"><?php echo esc_html($overlay->overlay_url); ?></a>
                                                </td>
                                                <td style="font-size: 11px;"><?php echo $overlay->views ?: '0'; ?></td>
                                                <td style="font-size: 11px;"><?php echo $overlay->updated ?: 'Never'; ?></td>
                                                <td style="font-size: 11px;">
                                                    <a href="<?php echo get_edit_post_link($overlay->ID); ?>" class="button button-small">Edit</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p style="margin: 0; color: #856404; text-align: center;">No overlays found. <a href="<?php echo admin_url('post-new.php'); ?>" style="color: #856404;">Create your first overlay</a></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>


        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Newsletter signup
            $('#subscribe-newsletter-admin').on('click', function() {
                var email = $('#newsletter-email-admin').val();
                if (!email) {
                    alert('Please enter your email address');
                    return;
                }
                
                $.post(ajaxurl, {
                    action: 'siteoverlay_newsletter_signup',
                    email: email,
                    nonce: '<?php echo wp_create_nonce('siteoverlay_overlay_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Thank you for subscribing!');
                        $('#newsletter-email-admin').val('');
                    } else {
                        alert('Error: ' + response.data);
                    }
                });
            });
            
            // License functionality (old handlers removed - using new toggle system below)
            
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
            
            // Trial button handler (make sure this works)
            $('#show-trial-form').on('click', function() {
                $('#trial-registration-form').toggle();
                $('#paid-license-form').hide();
                $('#license-key-form').hide();
            });

            // Paid license button handler
            $('#show-paid-license-form').on('click', function() {
                $('#paid-license-form').toggle();
                $('#license-key-form').hide();
                $('#trial-registration-form').hide();
            });

            // License key button handler
            $('#show-license-key-form').on('click', function() {
                $('#license-key-form').toggle();
                $('#paid-license-form').hide();
                $('#trial-registration-form').hide();
            });

            // Trial form submission handler (FIXED)
            $('#request-trial').on('click', function() {
                var name = $('#trial-name').val();
                var email = $('#trial-email').val();
                
                if (!name || !email) {
                    $('#trial-response').html('<span style="color: red;">Please enter both name and email</span>');
                    return;
                }
                
                $(this).prop('disabled', true).text('Processing...');
                
                // Call Railway API directly for trial license
                $.ajax({
                    url: 'https://siteoverlay-api-production.up.railway.app/api/request-trial',
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    data: JSON.stringify({
                        full_name: name,
                        email: email,
                        domain: window.location.origin
                    }),
                    success: function(response) {
                        if (response.success) {
                            $('#trial-response').html('<span style="color: green;">Your information has been submitted. Check your email for license details.</span>');
                            $('#trial-name').val('');
                            $('#trial-email').val('');
                        } else {
                            // Handle specific error messages from Railway API
                            var errorMessage = response.message || 'Trial request failed. Please try again.';
                            $('#trial-response').html('<span style="color: red;">' + errorMessage + '</span>');
                        }
                    },
                    error: function(xhr, status, error) {
                        // Handle connection errors
                        $('#trial-response').html('<span style="color: red;">Error connecting to license server. Please try again.</span>');
                        console.error('Trial request error:', xhr.responseText);
                    },
                    complete: function() {
                        $('#request-trial').prop('disabled', false).text('Start Free Trial');
                    }
                });
            });
            
            // Paid license request (call Railway API directly)
            $('#request-paid-license').on('click', function() {
                var name = $('#paid-license-name').val();
                var email = $('#paid-license-email').val();
                
                if (!name || !email) {
                    $('#paid-license-response').html('<span style="color: red;">Please enter both name and email</span>');
                    return;
                }
                
                $(this).prop('disabled', true).text('Requesting...');
                
                // Call Railway API directly with auto-detected domain
                $.ajax({
                    url: 'https://siteoverlay-api-production.up.railway.app/api/request-paid-license',
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    data: JSON.stringify({
                        name: name,
                        email: email,
                        domain: window.location.origin  // Auto-detect domain from current page
                    }),
                    success: function(response) {
                        if (response.success) {
                            $('#paid-license-response').html('<span style="color: green;">' + response.message + '</span>');
                            $('#paid-license-name').val('');
                            $('#paid-license-email').val('');
                        } else {
                            $('#paid-license-response').html('<span style="color: red;">' + response.message + '</span>');
                        }
                    },
                    error: function() {
                        $('#paid-license-response').html('<span style="color: red;">Error connecting to license server</span>');
                    },
                    complete: function() {
                        $('#request-paid-license').prop('disabled', false).text('Request License Key');
                    }
                });
            });

            // License activation with warning system
            $('#activate-license').on('click', function() {
                var licenseKey = $('#license-key-input').val();
                
                if (!licenseKey) {
                    $('#license-activation-response').html('<span style="color: red;">Please enter your license key</span>');
                    return;
                }
                
                // First, check for existing license
                checkExistingLicenseAndActivate(licenseKey);
            });

            // Function to check for existing license and show warning
            function checkExistingLicenseAndActivate(licenseKey) {
                $('#license-activation-response').html('<span style="color: blue;">Checking existing licenses...</span>');
                
                $.ajax({
                    url: 'https://siteoverlay-api-production.up.railway.app/api/check-existing-license',
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    data: JSON.stringify({
                        siteUrl: window.location.origin
                    }),
                    success: function(response) {
                        if (response.success && response.existing_license) {
                            // Show warning dialog
                            showLicenseReplaceWarning(response.existing_license, licenseKey);
                        } else {
                            // No existing license, proceed directly
                            activateLicense(licenseKey);
                        }
                    },
                    error: function() {
                        // If check fails, proceed anyway (fallback)
                        $('#license-activation-response').html('<span style="color: orange;">Could not check existing license. Proceeding...</span>');
                        setTimeout(function() {
                            activateLicense(licenseKey);
                        }, 1000);
                    }
                });
            }

            // Show warning dialog for license replacement
            function showLicenseReplaceWarning(existingLicense, newLicenseKey) {
                var expiryText = existingLicense.expires ? 
                    ' (expires: ' + existingLicense.expires.split('T')[0] + ')' : 
                    ' (no expiration)';
                
                var warningMessage = 
                    'You currently have an active ' + existingLicense.type + ' license' + expiryText + '.\n\n' +
                    'Activating this new license will replace your current license.\n\n' +
                    'Do you want to continue?';
                
                if (confirm(warningMessage)) {
                    $('#license-activation-response').html('<span style="color: blue;">Replacing existing license...</span>');
                    activateLicense(newLicenseKey);
                } else {
                    $('#license-activation-response').html('<span style="color: gray;">License activation cancelled.</span>');
                }
            }

            // Activate license (same as before)
            function activateLicense(licenseKey) {
                $('#activate-license').prop('disabled', true).text('Activating...');
                
                $.ajax({
                    url: 'https://siteoverlay-api-production.up.railway.app/api/validate-license',
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    data: JSON.stringify({
                        licenseKey: licenseKey,
                        siteUrl: window.location.origin
                    }),
                    success: function(response) {
                        if (response.success) {
                            // SAVE THE NEW LICENSE KEY TO WORDPRESS
                            $.post(ajaxurl, {
                                action: 'siteoverlay_save_license_key',
                                license_key: licenseKey,
                                nonce: '<?php echo wp_create_nonce('siteoverlay_overlay_nonce'); ?>'
                            }, function(saveResponse) {
                                if (saveResponse.success) {
                                    $('#license-activation-response').html('<span style="color: green;">License activated successfully! Reloading page...</span>');
                                    setTimeout(function() { location.reload(); }, 2000);
                                } else {
                                    $('#license-activation-response').html('<span style="color: orange;">License validated but not saved locally. Please try again.</span>');
                                }
                            });
                        } else {
                            $('#license-activation-response').html('<span style="color: red;">' + response.message + '</span>');
                        }
                    },
                    error: function() {
                        $('#license-activation-response').html('<span style="color: red;">Error connecting to license server</span>');
                    },
                    complete: function() {
                        $('#activate-license').prop('disabled', false).text('Activate License');
                    }
                });
            }

            // Manual license clear handler
            $('#manual-clear-license').on('click', function() {
                if (confirm('Are you sure you want to clear all license data? This will reset the plugin to unlicensed state.')) {
                    $(this).prop('disabled', true).text('Clearing...');
                    
                    $.post(ajaxurl, {
                        action: 'siteoverlay_clear_license_data',
                        nonce: '<?php echo wp_create_nonce('siteoverlay_overlay_nonce'); ?>'
                    }, function(response) {
                        if (response.success) {
                            alert('License data cleared successfully. Page will reload.');
                            location.reload();
                        } else {
                            alert('Error: ' + response.data);
                            $('#manual-clear-license').prop('disabled', false).text('🗑️ Clear All License Data');
                        }
                    });
                }
            });
        });
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
        
        // LICENSE ENFORCEMENT: Show different versions based on license status
        if (!$this->is_licensed()) {
            $this->render_unlicensed_meta_box($post);
        } else {
            $this->render_licensed_meta_box($post);
        }
    }
    
    /**
     * Render unlicensed meta box - same as licensed but WITHOUT overlay URL section
     */
    private function render_unlicensed_meta_box($post) {
        ?>
        <div id="siteoverlay-overlay-container">
            <!-- Logo Section -->
            <div style="text-align: center; padding: 10px 0; background: white;">
                <img src="https://ebiz360.ca/wp-content/uploads/2025/08/siteoverlay-pro-logo.png" alt="SiteOverlay Pro" style="max-width: 100%; height: auto;" />
            </div>
            
            <!-- Status Section (Unlicensed) -->
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 8px; text-align: center; font-size: 12px;">
                ⚠️ <strong>License Required</strong><br>
                Type: Unlicensed<br>
                👁 <?php echo get_post_meta($post->ID, '_siteoverlay_overlay_views', true) ?: '0'; ?> views
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
                <a href="<?php echo esc_url($this->get_dynamic_content('metabox_affiliate_url', 'https://xagio.net/?ref=siteoverlay')); ?>" target="_blank" 
                   style="background: #17a2b8; color: white; padding: 6px 12px; text-decoration: none; border-radius: 3px; font-size: 11px; display: inline-block;"><?php echo esc_html($this->get_dynamic_content('metabox_button_text', 'Get Xagio Now')); ?></a>

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
                <img src="https://ebiz360.ca/wp-content/uploads/2025/08/siteoverlay-pro-logo.png" alt="SiteOverlay Pro" style="max-width: 100%; height: auto;" />
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
                <a href="<?php echo esc_url($this->get_dynamic_content('metabox_affiliate_url', 'https://xagio.net/?ref=siteoverlay')); ?>" target="_blank" 
                   style="background: #17a2b8; color: white; padding: 6px 12px; text-decoration: none; border-radius: 3px; font-size: 11px; display: inline-block;"><?php echo esc_html($this->get_dynamic_content('metabox_button_text', 'Get Xagio Now')); ?></a>

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
        // License enforcement - block admin scripts if not licensed
        if (!$this->is_licensed()) {
            return;
        }
        
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
        // License enforcement - block functionality if not licensed
        if (!$this->is_licensed()) {
            wp_send_json_error('Plugin is not licensed. Please activate your license to use this feature.');
            return;
        }
        
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
        // License enforcement - block functionality if not licensed
        if (!$this->is_licensed()) {
            wp_send_json_error('Plugin is not licensed. Please activate your license to use this feature.');
            return;
        }
        
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
        // License enforcement - block functionality if not licensed
        if (!$this->is_licensed()) {
            wp_send_json_error('Plugin is not licensed. Please activate your license to use this feature.');
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
     * AJAX handler for paid license requests
     */
    public function ajax_request_paid_license() {
        if (!wp_verify_nonce($_POST['nonce'], 'siteoverlay_overlay_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        
        if (empty($name) || empty($email)) {
            wp_send_json_error('Please enter both name and email');
            return;
        }
        
        // Send request to Railway API
        $api_response = wp_remote_post($this->api_base_url . '/request-paid-license', array(
            'body' => json_encode(array(
                'name' => $name,
                'email' => $email,
                'siteUrl' => get_site_url()
            )),
            'headers' => array('Content-Type' => 'application/json'),
            'timeout' => 10
        ));
        
        if (is_wp_error($api_response)) {
            wp_send_json_error('Connection error: ' . $api_response->get_error_message());
            return;
        }
        
        $body = wp_remote_retrieve_body($api_response);
        $data = json_decode($body, true);
        
        if (isset($data['success']) && $data['success'] === true) {
            wp_send_json_success($data['message'] ?? 'License request submitted successfully! Check your email.');
        } else {
            wp_send_json_error($data['message'] ?? 'Failed to process license request');
        }
    }
    
    /**
     * AJAX handler to save license key locally
     */
    public function ajax_save_license_key() {
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
            wp_send_json_error('Invalid license key');
            return;
        }
        
        // Save the license key to WordPress options
        update_option('siteoverlay_license_key', $license_key);
        update_option('siteoverlay_license_validated', time());
        
        // Clear any existing license cache
        delete_transient('siteoverlay_license_status');
        delete_option('siteoverlay_license_cache');
        
        error_log('✅ License key saved successfully: ' . substr($license_key, 0, 8) . '...');
        
        wp_send_json_success('License key saved successfully');
    }
    
    /**
     * AJAX handler to manually clear all license data
     */
    public function ajax_clear_license_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'siteoverlay_overlay_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $this->clear_all_license_data();
        
        wp_send_json_success('All license data cleared successfully');
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
            // Determine proper status based on license validation
            $current_status = 'active';  // Default to active for validated paid licenses
            
            // Check if we have real-time validation results
            $license_valid = get_option('siteoverlay_license_valid', null);
            if ($license_valid === false) {
                $current_status = 'inactive';
            }
            
            return array(
                'state' => 'licensed',
                'status' => $current_status,
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
        
        // CRITICAL FIX: Use the same is_licensed() method to avoid cache conflicts
        if (!$this->is_licensed()) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p><strong>SiteOverlay Pro:</strong> Plugin is inactive. Activate your license to use SiteOverlay Pro. <a href="<?php echo admin_url('options-general.php?page=siteoverlay-settings'); ?>">Activate Now</a></p>
            </div>
            <?php
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
     * Get dynamic content from API (always available)
     */
    public function get_dynamic_content($key, $fallback = '') {
        if (isset($this->dynamic_content_manager)) {
            $content = $this->dynamic_content_manager->get_dynamic_content();
            
            // Check if the key exists in the content array
            if (is_array($content) && isset($content[$key])) {
                return $content[$key];  // Return JUST the value for this key
            }
        }
        return $fallback;  // Return fallback if key not found
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
        // License enforcement - block overlay display if not licensed
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