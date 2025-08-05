<?php
/**
 * SiteOverlay Pro License Manager - FIXED VERSION
 * Enhanced license validation, trial management, and API communication
 * 
 * CONSTITUTIONAL COMPLIANCE:
 * - All license validation runs in background (non-blocking)
 * - Core overlay functionality ONLY works with valid license
 * - Enhancement module only - does not modify core plugin files
 */

if (!defined('ABSPATH')) {
    exit;
}

class SiteOverlay_License_Manager {
    
    private $license_key_option = 'siteoverlay_license_key';
    private $license_data_option = 'siteoverlay_license_data';
    private $trial_start_option = 'siteoverlay_trial_start';
    
    // Railway API Configuration - Updated to use production endpoints
    private $api_base_url = 'https://siteoverlay-api-production.up.railway.app/api';
    private $api_timeout = 30; // Increased timeout for better reliability
    
    public function __construct() {
        // Admin hooks (enhancement only)
        add_action('admin_menu', array($this, 'add_license_page'));
        add_action('admin_init', array($this, 'process_license_form'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Background license validation (non-blocking)
        add_action('wp_loaded', array($this, 'background_license_check'), 20);
        
        // Enhanced AJAX handlers for email-based trials
        add_action('wp_ajax_siteoverlay_validate_license', array($this, 'ajax_validate_license'));
        add_action('wp_ajax_siteoverlay_deactivate_license', array($this, 'ajax_deactivate_license'));
        add_action('wp_ajax_siteoverlay_request_trial', array($this, 'ajax_request_trial'));
        
        // Site tracker integration (non-blocking)
        add_action('wp_ajax_siteoverlay_get_usage_stats', array($this, 'ajax_get_usage_stats'));
        add_action('wp_ajax_siteoverlay_check_site_limits', array($this, 'ajax_check_site_limits'));
    }
    
    /**
     * Background license validation (non-blocking)
     * CONSTITUTIONAL RULE: Never blocks core functionality
     */
    public function background_license_check() {
        error_log('SiteOverlay Debug: Background license check started');
        
        $license_key = $this->get_license_key();
        if (empty($license_key)) {
            error_log('SiteOverlay Debug: No license key found, skipping background check');
            return;
        }
        
        error_log('SiteOverlay Debug: License key found: ' . substr($license_key, 0, 8) . '...');
        
        // Check if we need to validate (cache for 6 hours)
        $last_check = get_transient('siteoverlay_license_last_check');
        error_log('SiteOverlay Debug: Last check time: ' . ($last_check ? date('Y-m-d H:i:s', $last_check) : 'never'));
        
        if ($last_check && (time() - $last_check) < 21600) {
            error_log('SiteOverlay Debug: License check cached, skipping (6 hour cache)');
            return;
        }
        
        error_log('SiteOverlay Debug: Cache expired, running background validation');
        
        // Background validation with short timeout
        $this->validate_license_background();
        set_transient('siteoverlay_license_last_check', time(), 30);
        error_log('SiteOverlay Debug: Background check completed, cache set');
    }
    
    /**
     * Clear all license caches and stored data
     */
    public function clear_license_caches() {
        error_log('SiteOverlay Debug: Clearing all license caches');
        
        // Clear transients
        delete_transient('siteoverlay_license_last_check');
        error_log('SiteOverlay Debug: Cleared transient: siteoverlay_license_last_check');
        
        // Clear options
        delete_option('siteoverlay_license_validated');
        delete_option('siteoverlay_license_data');
        error_log('SiteOverlay Debug: Cleared options: license_validated, license_data');
        
        // Note: We don't clear the license key itself, just the validation cache
        error_log('SiteOverlay Debug: License caches cleared successfully');
    }
    
    /**
     * Background license validation (non-blocking)
     */
    private function validate_license_background() {
        error_log('SiteOverlay Debug: validate_license_background() started');
        
        $license_key = $this->get_license_key();
        if (empty($license_key)) {
            error_log('SiteOverlay Debug: No license key for background validation');
            return;
        }
        
        error_log('SiteOverlay Debug: Making API call to validate license...');
        error_log('SiteOverlay Debug: API URL: ' . $this->api_base_url . '/validate-license');
        
        $request_data = array(
            'licenseKey' => $license_key,
            'siteUrl' => get_site_url(),
            'action' => 'check',
            'pluginVersion' => SITEOVERLAY_RR_VERSION
        );
        
        error_log('SiteOverlay Debug: Request data: ' . print_r($request_data, true));
        
        $response = wp_remote_post($this->api_base_url . '/validate-license', array(
            'timeout' => 5,
            'blocking' => false,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($request_data)
        ));
        
        if (is_wp_error($response)) {
            error_log('SiteOverlay Debug: API call failed with WP_Error: ' . $response->get_error_message());
            return;
        }
        
        error_log('SiteOverlay Debug: API call successful, processing response');
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        error_log('SiteOverlay Debug: API response body: ' . $body);
        error_log('SiteOverlay Debug: API response data: ' . print_r($data, true));
        
        if ($data && isset($data['success'])) {
            if ($data['success']) {
                error_log('SiteOverlay Debug: License validation successful, updating options');
                update_option($this->license_data_option, $data['data']);
                update_option('siteoverlay_license_validated', current_time('mysql'));
                error_log('SiteOverlay Debug: License options updated successfully');
            } else {
                error_log('SiteOverlay Debug: License validation failed, clearing license');
                // License invalid - clear it
                delete_option($this->license_key_option);
                delete_option($this->license_data_option);
                error_log('SiteOverlay Debug: License options cleared');
            }
        } else {
            error_log('SiteOverlay Debug: Invalid API response format');
        }
    }
    }
    
    public function add_license_page() {
        add_options_page(
            'SiteOverlay Pro License',
            'SiteOverlay License',
            'manage_options',
            'siteoverlay-license',
            array($this, 'license_page')
        );
    }
    
    /**
     * Enhanced license validation with Railway API
     */
    private function validate_license_with_railway($license_key, $action = 'check') {
        error_log('SiteOverlay Debug: validate_license_with_railway() called with action: ' . $action);
        
        if (empty($license_key)) {
            error_log('SiteOverlay Debug: Empty license key provided');
            return false;
        }
        
        error_log('SiteOverlay Debug: License key: ' . substr($license_key, 0, 8) . '...');
        
        $site_data = array(
            'licenseKey' => $license_key,
            'siteUrl' => get_site_url(),
            'siteTitle' => get_bloginfo('name'),
            'wpVersion' => get_bloginfo('version'),
            'pluginVersion' => SITEOVERLAY_RR_VERSION,
            'action' => $action,
            'clientIP' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        );
        
        error_log('SiteOverlay Debug: Site data for validation: ' . print_r($site_data, true));
        error_log('SiteOverlay Debug: Making API call to: ' . $this->api_base_url . '/validate-license');
        
        $response = wp_remote_post($this->api_base_url . '/validate-license', array(
            'timeout' => $this->api_timeout,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($site_data),
            'blocking' => true,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            $error_msg = 'Connection Error: ' . $response->get_error_message();
            update_option('siteoverlay_last_error', $error_msg);
            error_log('SiteOverlay License Error: ' . $error_msg);
            error_log('SiteOverlay Debug: API call failed with WP_Error');
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log('SiteOverlay Debug: API response code: ' . $response_code);
        error_log('SiteOverlay Debug: API response body: ' . $body);
        
        if ($response_code !== 200) {
            $error_msg = 'Server Error: HTTP ' . $response_code;
            update_option('siteoverlay_last_error', $error_msg);
            error_log('SiteOverlay Debug: API returned non-200 status code');
            return false;
        }
        
        $data = json_decode($body, true);
        error_log('SiteOverlay Debug: Decoded response data: ' . print_r($data, true));
        
        if ($data && isset($data['success']) && $data['success']) {
            // Store enhanced license data
            $license_info = array(
                'status' => 'valid',
                'license_type' => $data['data']['license_type'] ?? 'unknown',
                'customer_name' => $data['data']['customer_name'] ?? 'Licensed User',
                'licensed_to' => $data['data']['licensed_to'] ?? $data['data']['customer_name'] ?? 'Licensed User',
                'expires' => $data['data']['expires'] ?? 'Never',
                'company' => 'eBiz360',
                'last_validated' => current_time('mysql'),
                'validation_source' => 'railway_api',
                'raw_response' => $data
            );
            
            update_option($this->license_data_option, $license_info);
            delete_option('siteoverlay_last_error');
            
            return true;
        } else {
            $error_msg = isset($data['message']) ? $data['message'] : 'License validation failed';
            update_option('siteoverlay_last_error', $error_msg);
            return false;
        }
    }
    
    /**
     * Get site usage statistics (non-blocking)
     * CONSTITUTIONAL RULE: Information only, never blocks functionality
     */
    public function ajax_get_usage_stats() {
        check_ajax_referer('siteoverlay_license_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $usage_stats = $this->get_site_usage_statistics();
        wp_send_json_success($usage_stats);
    }
    
    /**
     * Check site limits (non-blocking)
     * CONSTITUTIONAL RULE: Warnings only, never blocks functionality
     */
    public function ajax_check_site_limits() {
        check_ajax_referer('siteoverlay_license_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $limit_info = $this->check_site_limits();
        wp_send_json_success($limit_info);
    }
    
    /**
     * Get comprehensive site usage statistics
     */
    private function get_site_usage_statistics() {
        global $wpdb;
        
        // Get site signature
        $site_signature = get_option('siteoverlay_site_signature', '');
        
        // Get overlay count
        $overlay_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_siteoverlay_overlay_url' 
             AND meta_value != ''"
        );
        
        // Get license information
        $license_data = get_option('siteoverlay_license_data', array());
        $license_type = isset($license_data['license_type']) ? $license_data['license_type'] : 'unlicensed';
        
        // Get site tracking information
        $registration_status = get_option('siteoverlay_registration_status', 'unknown');
        $last_tracked = get_option('siteoverlay_last_tracking', 'Never');
        $last_heartbeat = get_option('siteoverlay_last_heartbeat', 'Never');
        $heartbeat_status = get_option('siteoverlay_heartbeat_status', 'unknown');
        
        // Get heartbeat history
        $heartbeat_history = get_option('siteoverlay_heartbeat_history', array());
        $recent_heartbeats = array_slice($heartbeat_history, -5); // Last 5 heartbeats
        
        // Get site tracking record
        $tracking_record = null;
        if (!empty($site_signature)) {
            $tracking_record = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}siteoverlay_sites WHERE site_signature = %s",
                    $site_signature
                )
            );
        }
        
        return array(
            'site_signature' => $site_signature,
            'overlay_count' => intval($overlay_count),
            'license_type' => $license_type,
            'registration_status' => $registration_status,
            'last_tracked' => $last_tracked,
            'last_heartbeat' => $last_heartbeat,
            'heartbeat_status' => $heartbeat_status,
            'tracking_record' => $tracking_record,
            'recent_heartbeats' => $recent_heartbeats,
            'site_limits' => $this->get_site_limits_info($license_type, $overlay_count)
        );
    }
    
    /**
     * Check site limits and return information
     */
    private function check_site_limits() {
        $license_data = get_option('siteoverlay_license_data', array());
        $license_type = isset($license_data['license_type']) ? $license_data['license_type'] : 'unlicensed';
        
        global $wpdb;
        $overlay_count = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_siteoverlay_overlay_url' 
             AND meta_value != ''"
        );
        
        $limits = $this->get_site_limits_info($license_type, $overlay_count);
        
        return array(
            'license_type' => $license_type,
            'current_usage' => intval($overlay_count),
            'limits' => $limits,
            'warnings' => $this->generate_limit_warnings($license_type, $overlay_count)
        );
    }
    
    /**
     * Get site limits information
     */
    private function get_site_limits_info($license_type, $overlay_count) {
        $limits = array(
            'max_sites' => 0,
            'current_usage' => intval($overlay_count),
            'usage_percentage' => 0,
            'unlimited' => false
        );
        
        switch ($license_type) {
            case 'trial':
                $limits['max_sites'] = 3;
                $limits['unlimited'] = false;
                break;
            case 'professional':
                $limits['max_sites'] = 5;
                $limits['unlimited'] = false;
                break;
            case 'annual':
            case 'lifetime':
                $limits['max_sites'] = 0;
                $limits['unlimited'] = true;
                break;
            default:
                $limits['max_sites'] = 0;
                $limits['unlimited'] = false;
        }
        
        if (!$limits['unlimited'] && $limits['max_sites'] > 0) {
            $limits['usage_percentage'] = round(($limits['current_usage'] / $limits['max_sites']) * 100, 2);
        }
        
        return $limits;
    }
    
    /**
     * Generate limit warnings (informational only)
     */
    private function generate_limit_warnings($license_type, $overlay_count) {
        $warnings = array();
        
        switch ($license_type) {
            case 'trial':
                if ($overlay_count >= 3) {
                    $warnings[] = array(
                        'type' => 'warning',
                        'message' => 'You have reached the trial limit of 3 overlays. Consider upgrading to continue using more overlays.',
                        'action' => 'upgrade'
                    );
                } elseif ($overlay_count >= 2) {
                    $warnings[] = array(
                        'type' => 'info',
                        'message' => 'You have used ' . $overlay_count . ' of 3 trial overlays.',
                        'action' => 'monitor'
                    );
                }
                break;
                
            case 'professional':
                if ($overlay_count >= 5) {
                    $warnings[] = array(
                        'type' => 'warning',
                        'message' => 'You have reached the Professional plan limit of 5 overlays. Consider upgrading to Annual or Lifetime for unlimited overlays.',
                        'action' => 'upgrade'
                    );
                } elseif ($overlay_count >= 4) {
                    $warnings[] = array(
                        'type' => 'info',
                        'message' => 'You have used ' . $overlay_count . ' of 5 Professional overlays.',
                        'action' => 'monitor'
                    );
                }
                break;
                
            case 'annual':
            case 'lifetime':
                // No warnings for unlimited plans
                break;
                
            default:
                $warnings[] = array(
                    'type' => 'info',
                    'message' => 'No active license detected. Overlay functionality may be limited.',
                    'action' => 'license'
                );
        }
        
        return $warnings;
    }
    
    /**
     * FIXED: Email-based trial request - DO NOT RETURN LICENSE KEY
     */
    public function ajax_request_trial() {
        // Verify nonce for security
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'siteoverlay_license_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        $full_name = sanitize_text_field($_POST['full_name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $website = esc_url_raw($_POST['website'] ?? '');
        
        if (empty($full_name)) {
            wp_send_json_error('Please enter your full name');
            return;
        }
        
        if (empty($email) || !is_email($email)) {
            wp_send_json_error('Please enter a valid email address');
            return;
        }
        
        // Send trial request to Railway API WITH NAME
        $trial_data = array(
            'full_name' => $full_name,
            'email' => $email,
            'website' => $website,
            'siteUrl' => get_site_url(),
            'siteTitle' => get_bloginfo('name'),
            'wpVersion' => get_bloginfo('version'),
            'pluginVersion' => SITEOVERLAY_RR_VERSION,
            'requestSource' => 'plugin_admin'
        );
        
        // Log the request data being sent (on-screen debug)
        $debug_info = array();
        $debug_info[] = "=== TRIAL REQUEST DEBUG ===";
        $debug_info[] = "Request Data: " . json_encode($trial_data, JSON_PRETTY_PRINT);

        $response = wp_remote_post($this->api_base_url . '/request-trial', array(
            'timeout' => $this->api_timeout,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($trial_data),
            'blocking' => true,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            $debug_info[] = "❌ WP_Error: " . $response->get_error_message();
            wp_send_json_error(array(
                'message' => 'Connection error: ' . $response->get_error_message(),
                'debug' => implode("\n", $debug_info)
            ));
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // Add response debug info
        $debug_info[] = "Response Code: " . $response_code;
        $debug_info[] = "Response Body: " . $body;
        $debug_info[] = "Parsed Data: " . json_encode($data, JSON_PRETTY_PRINT);
        
        if ($response_code === 200 && $data && isset($data['success'])) {
            if ($data['success']) {
                wp_send_json_success(array(
                    'message' => $data['message'] ?? 'Details submitted. Check your inbox for the license key to activate trial',
                    'debug' => implode("\n", $debug_info)
                ));
            } else {
                $debug_info[] = "❌ API Error: " . ($data['message'] ?? 'Unknown error');
                wp_send_json_error(array(
                    'message' => $data['message'] ?? 'API returned an error',
                    'debug' => implode("\n", $debug_info)
                ));
            }
        } else {
            $debug_info[] = "❌ Request Failed - Code: " . $response_code;
            wp_send_json_error(array(
                'message' => 'Failed to process trial request (Code: ' . $response_code . ')',
                'debug' => implode("\n", $debug_info)
            ));
        }
    }
    
    /**
     * Enhanced AJAX license validation
     */
    public function ajax_validate_license() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'siteoverlay_ajax')) {
            wp_die('Security check failed');
        }
        
        $license_key = sanitize_text_field($_POST['license_key'] ?? '');
        $action = sanitize_text_field($_POST['action_type'] ?? 'check');
        
        if (empty($license_key)) {
            wp_send_json_error('Please enter a license key');
            return;
        }
        
        delete_option('siteoverlay_last_error');
        
        if ($this->validate_license_with_railway($license_key, $action)) {
            update_option($this->license_key_option, $license_key);
            $license_data = get_option($this->license_data_option, array());
            
            $message = 'License activated successfully!';
            if (isset($license_data['licensed_to'])) {
                $message .= ' Licensed to: ' . $license_data['licensed_to'];
            }
            
            wp_send_json_success(array(
                'message' => $message,
                'license_data' => $license_data
            ));
        } else {
            $last_error = get_option('siteoverlay_last_error', 'License validation failed');
            wp_send_json_error($last_error);
        }
    }
    
    /**
     * AJAX handler for license deactivation
     */
    public function ajax_deactivate_license() {
        if (!wp_verify_nonce($_POST['nonce'], 'siteoverlay_ajax')) {
            wp_die('Security check failed');
        }
        
        $result = $this->deactivate_license();
        wp_send_json($result);
    }
    
    /**
     * Deactivate current license
     */
    private function deactivate_license() {
        $license_key = $this->get_license_key();
        if (empty($license_key)) {
            return array(
                'success' => false,
                'data' => array('message' => 'No license to deactivate')
            );
        }
        
        $response = wp_remote_post($this->api_base_url . '/validate-license', array(
            'timeout' => 10,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'licenseKey' => $license_key,
                'siteUrl' => get_site_url(),
                'action' => 'deactivate'
            ))
        ));
        
        // Clear local license data regardless of API response
        delete_option($this->license_key_option);
        delete_option($this->license_data_option);
        delete_option('siteoverlay_license_validated');
        delete_option('siteoverlay_last_error');
        
        return array(
            'success' => true,
            'data' => array('message' => 'License deactivated successfully')
        );
    }
    
    public function license_page() {
        $license_key = get_option($this->license_key_option, '');
        $license_data = get_option($this->license_data_option, array());
        $last_error = get_option('siteoverlay_last_error', '');
        $is_licensed = $this->has_valid_license();
        ?>
        
        <div class="wrap">
            <h1>SiteOverlay Pro - License Manager</h1>
            <p class="subtitle">Manage your license, start trials, and view system information</p>
            
            <?php $this->render_license_status($license_data, $is_licensed); ?>
            
            <!-- FIXED: EMAIL-BASED TRIAL SECTION WITH NAME FIELD -->
            <?php if (!$is_licensed): ?>
                <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; border-radius: 4px;">
                    <h2 style="margin-top: 0; color: #0073aa;">🚀 Start Your 14-Day Free Trial</h2>
                    <p>Enter your details below and we'll send your trial license key to your email:</p>
                    
                    <div style="max-width: 500px;">
                        <div style="margin-bottom: 15px;">
                            <label for="trial-name" style="display: block; margin-bottom: 5px; font-weight: bold;">Full Name *</label>
                            <input type="text" 
                                   id="trial-name" 
                                   placeholder="Enter your full name" 
                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;" 
                                   required />
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label for="trial-email" style="display: block; margin-bottom: 5px; font-weight: bold;">Email Address *</label>
                            <input type="email" 
                                   id="trial-email" 
                                   placeholder="Enter your email address" 
                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;" 
                                   required />
                            <small style="color: #666; font-size: 12px;">Your trial license will be sent to this email</small>
                        </div>
                        
                        <div style="margin-bottom: 20px;">
                            <label for="trial-website" style="display: block; margin-bottom: 5px; font-weight: bold;">Website URL (Optional)</label>
                            <input type="url" 
                                   id="trial-website" 
                                   placeholder="https://yourwebsite.com" 
                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;" />
                        </div>
                        
                        <button type="button" id="request-trial-btn" class="button button-primary" style="padding: 12px 24px; font-size: 14px; width: 100%;">
                            📧 Get My Trial License
                        </button>
                        
                        <div id="trial-response" style="margin-top: 15px; display: none;"></div>
                    </div>
                    
                    <p style="margin-top: 20px; font-size: 12px; color: #666;">
                        <strong>What happens next:</strong><br>
                        1. You'll receive an email with your trial license key<br>
                        2. Copy the license key and paste it in the field below<br>
                        3. Click "Activate License" to start your 14-day trial
                    </p>
                </div>
            <?php endif; ?>
            
            <!-- LICENSE ENTRY SECTION -->
            <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; border-radius: 4px;">
                <h2 style="margin-top: 0;">
                    <?php echo $is_licensed ? '🔑 License Management' : '🔑 Enter Your License Key'; ?>
                </h2>
                
                <?php if (!$is_licensed): ?>
                    <p>Enter the license key you received by email to activate SiteOverlay Pro.</p>
                <?php endif; ?>
                
                <form method="post" action="" id="license-form">
                    <?php wp_nonce_field('siteoverlay_license_form'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">License Key</th>
                            <td>
                                <input type="text" 
                                       name="license_key" 
                                       id="license_key" 
                                       value="<?php echo esc_attr($license_key); ?>" 
                                       style="width: 100%; max-width: 500px; padding: 10px; font-family: monospace; font-size: 14px; border: 1px solid #ddd; border-radius: 4px;" 
                                       placeholder="TRIAL-XXXX-XXXX-XXXX or PRO-XXXX-XXXX-XXXX or ANN-XXXX-XXXX-XXXX or LIFE-XXXX-XXXX-XXXX" 
                                       <?php echo $is_licensed ? 'readonly' : ''; ?> />
                                       
                                <?php if ($is_licensed): ?>
                                    <button type="button" id="edit-license-btn" class="button" style="margin-left: 10px;">Edit</button>
                                <?php endif; ?>
                                
                                <p class="description">
                                    <?php if ($is_licensed): ?>
                                        Your license is currently active. Click "Edit" to change it.
                                    <?php else: ?>
                                        Enter the license key you received by email (starts with TRIAL-, PRO-, ANN-, or LIFE-).
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="button" id="validate-license-btn" class="button button-primary" style="padding: 10px 20px;">
                            <?php echo $is_licensed ? '🔄 Revalidate License' : '✅ Activate License'; ?>
                        </button>
                        
                        <?php if ($is_licensed): ?>
                            <button type="button" id="deactivate-license-btn" class="button button-secondary" style="margin-left: 10px;">
                                ❌ Deactivate License
                            </button>
                        <?php endif; ?>
                        
                        <button type="button" id="manual-save-btn" class="button" style="margin-left: 10px;">Save Without Validation</button>
                    </p>
                </form>
                
                <?php if ($last_error): ?>
                    <div class="notice notice-error inline" style="margin: 15px 0;">
                        <p><strong>Validation Error:</strong> <?php echo esc_html($last_error); ?></p>
                        <p><em>Your license key has been saved locally. You can continue using SiteOverlay Pro.</em></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- PURCHASE OPTIONS WITH eBiz360 BRANDING -->
            <?php if (!$is_licensed): ?>
                <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; border-radius: 4px;">
                    <h2 style="margin-top: 0;">💳 Don't Have a License Yet?</h2>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin: 20px 0;">
                        <div style="border: 2px solid #ddd; padding: 20px; text-align: center; border-radius: 8px;">
                            <h3 style="margin: 0 0 10px 0; color: #23282d;">Professional</h3>
                            <div style="font-size: 28px; font-weight: bold; color: #0073aa; margin: 10px 0;">$35/month</div>
                            <ul style="list-style: none; padding: 0; margin: 15px 0; text-align: left;">
                                <li style="padding: 8px 0; border-bottom: 1px solid #eee;">✅ Up to 5 websites</li>
                                <li style="padding: 8px 0; border-bottom: 1px solid #eee;">✅ Premium support</li>
                                <li style="padding: 8px 0; border-bottom: 1px solid #eee;">✅ Regular updates</li>
                                <li style="padding: 8px 0;">✅ 14-day free trial</li>
                            </ul>
                            <a href="https://siteoverlay.24hr.pro/?plan=professional" class="button button-primary" target="_blank" style="padding: 10px 20px;">Purchase Professional</a>
                        </div>
                        
                        <div style="border: 2px solid #28a745; padding: 20px; text-align: center; border-radius: 8px; position: relative;">
                            <div style="background: #28a745; color: white; padding: 4px 12px; border-radius: 12px; font-size: 11px; position: absolute; top: -8px; left: 50%; transform: translateX(-50%); font-weight: bold;">BEST VALUE</div>
                            <h3 style="margin: 0 0 10px 0; color: #23282d;">Annual Unlimited</h3>
                            <div style="font-size: 28px; font-weight: bold; color: #28a745; margin: 10px 0;">$197/year</div>
                            <ul style="list-style: none; padding: 0; margin: 15px 0; text-align: left;">
                                <li style="padding: 8px 0; border-bottom: 1px solid #eee;">✅ Unlimited websites</li>
                                <li style="padding: 8px 0; border-bottom: 1px solid #eee;">✅ Annual billing (save $223/year)</li>
                                <li style="padding: 8px 0; border-bottom: 1px solid #eee;">✅ Premium support</li>
                                <li style="padding: 8px 0;">✅ Regular updates</li>
                            </ul>
                            <a href="https://siteoverlay.24hr.pro/?plan=annual" class="button button-primary" target="_blank" style="padding: 10px 20px;">Purchase Annual</a>
                        </div>

                    </div>
                </div>
            <?php endif; ?>
            
            <!-- SITE USAGE AND TRACKING INFORMATION -->
            <div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; border-radius: 4px;">
                <h2 style="margin-top: 0;">📊 Site Usage & Tracking</h2>
                
                <div id="usage-stats-container">
                    <div style="text-align: center; padding: 20px;">
                        <div class="spinner is-active" style="float: none; margin: 0 auto;"></div>
                        <p>Loading usage statistics...</p>
                    </div>
                </div>
                
                <div id="site-limits-container" style="margin-top: 20px;">
                    <h3>Site Limits & Warnings</h3>
                    <div id="limits-content">
                        <div style="text-align: center; padding: 10px;">
                            <div class="spinner is-active" style="float: none; margin: 0 auto; width: 20px; height: 20px;"></div>
                            <p>Checking site limits...</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- SYSTEM INFORMATION WITH eBiz360 BRANDING -->
            <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 15px; margin: 20px 0; border-radius: 4px;">
                <h3 style="margin-top: 0;">ℹ️ System Information</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                    <div>
                        <p><strong>Plugin Version:</strong> <?php echo SITEOVERLAY_RR_VERSION; ?></p>
                        <p><strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?></p>
                    </div>
                    <div>
                        <p><strong>Site URL:</strong> <?php echo get_site_url(); ?></p>
                        <p><strong>API Status:</strong> <span id="api-status">Testing...</span></p>
                    </div>
                    <div>
                        <p><strong>Developer:</strong> eBiz360</p>
                        <p><strong>Support Email:</strong> <a href="mailto:info@ebiz360.ca">info@ebiz360.ca</a></p>
                    </div>
                    <?php if ($is_licensed && isset($license_data['last_validated'])): ?>
                        <div>
                            <p><strong>Last Validated:</strong> <?php echo date('F j, Y g:i A', strtotime($license_data['last_validated'])); ?></p>
                            <p><strong>Validation Source:</strong> <?php echo ucfirst(str_replace('_', ' ', $license_data['validation_source'] ?? 'Unknown')); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <style>
        .trial-success {
            background: #d4edda !important;
            border-color: #c3e6cb !important;
            color: #155724 !important;
        }
        .trial-error {
            background: #f8d7da !important;
            border-color: #f5c6cb !important;
            color: #721c24 !important;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Test API connection on page load
            testApiConnection();
            
            // Enable editing of license key
            $('#edit-license-btn').on('click', function() {
                $('#license_key').prop('readonly', false).focus();
                $(this).hide();
            });
            
            // FIXED: Email-based trial request - DO NOT DISPLAY LICENSE KEY
            $('#request-trial-btn').on('click', function(e) {
                e.preventDefault();
                
                var fullName = $('#trial-name').val().trim();
                var email = $('#trial-email').val().trim();
                var website = $('#trial-website').val().trim();
                
                if (!fullName) {
                    alert('Please enter your full name');
                    $('#trial-name').focus();
                    return;
                }
                
                if (!email) {
                    alert('Please enter your email address');
                    $('#trial-email').focus();
                    return;
                }
                
                if (!isValidEmail(email)) {
                    alert('Please enter a valid email address');
                    $('#trial-email').focus();
                    return;
                }
                
                var $btn = $(this);
                var originalText = $btn.text();
                $btn.text('Sending trial request...').prop('disabled', true);
                
                $('#trial-response').hide();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'siteoverlay_request_trial',
                        nonce: '<?php echo wp_create_nonce('siteoverlay_license_nonce'); ?>',
                        full_name: fullName,
                        email: email,
                        website: website
                    },
                    success: function(response) {
                        if (response.success) {
                            // FIXED: Do NOT display license key - force email verification
                            var debugInfo = response.data.debug ? '\n\nDEBUG INFO:\n' + response.data.debug : '';
                            $('#trial-response').html('<div class="notice notice-success inline trial-success" style="padding: 15px; margin: 0;"><p><strong>✅ Trial License Sent!</strong><br>' + response.data.message + '<br><br><strong>Next Steps:</strong><br>1. Check your email inbox (and spam folder)<br>2. Copy the license key from the email<br>3. Paste it in the "License Key" field below<br>4. Click "Activate License"</p><pre style="background: #f0f0f0; padding: 10px; margin-top: 10px; font-size: 11px; overflow-x: auto;">' + debugInfo + '</pre></div>').fadeIn();
                            
                            // REMOVED: Auto-fill license key - force manual entry from email
                            // REMOVED: if (response.data.license_key) { $('#license_key').val(response.data.license_key); }
                            
                            // Clear form fields but keep the trial section visible for instructions
                            $('#trial-name, #trial-email, #trial-website').val('');
                            
                            // Focus on license key field to guide user
                            setTimeout(function() {
                                $('#license_key').focus();
                            }, 1000);
                        } else {
                            var debugInfo = response.data.debug ? '\n\nDEBUG INFO:\n' + response.data.debug : '';
                            $('#trial-response').html('<div class="notice notice-error inline trial-error" style="padding: 15px; margin: 0;"><p><strong>❌ Error:</strong> ' + response.data.message + '</p><pre style="background: #f0f0f0; padding: 10px; margin-top: 10px; font-size: 11px; overflow-x: auto;">' + debugInfo + '</pre></div>').fadeIn();
                        }
                    },
                    error: function(xhr, status, error) {
                        var debugInfo = 'Status: ' + status + '\nError: ' + error + '\nResponse: ' + xhr.responseText;
                        $('#trial-response').html('<div class="notice notice-error inline trial-error" style="padding: 15px; margin: 0;"><p><strong>❌ Connection Error:</strong> ' + error + '</p><pre style="background: #f0f0f0; padding: 10px; margin-top: 10px; font-size: 11px; overflow-x: auto;">' + debugInfo + '</pre></div>').fadeIn();
                        console.log('AJAX Error:', xhr.responseText);
                    },
                    complete: function() {
                        $btn.text(originalText).prop('disabled', false);
                    }
                });
            });
            
            // License validation
            $('#validate-license-btn').on('click', function(e) {
                e.preventDefault();
                
                var licenseKey = $('#license_key').val().trim();
                if (!licenseKey) {
                    alert('Please enter a license key');
                    return;
                }
                
                var $btn = $(this);
                var originalText = $btn.text();
                $btn.text('Validating license...').prop('disabled', true);
                
                $('.notice-error').fadeOut();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'siteoverlay_validate_license',
                        nonce: '<?php echo wp_create_nonce('siteoverlay_ajax'); ?>',
                        license_key: licenseKey,
                        action_type: 'activate'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('✅ ' + response.data.message);
                            location.reload();
                        } else {
                            alert('❌ ' + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('❌ Connection error: ' + error);
                        console.log('AJAX Error:', xhr.responseText);
                    },
                    complete: function() {
                        $btn.text(originalText).prop('disabled', false);
                    }
                });
            });
            
            // License deactivation
            $('#deactivate-license-btn').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('Are you sure you want to deactivate this license?')) {
                    return;
                }
                
                var $btn = $(this);
                var originalText = $btn.text();
                $btn.text('Deactivating...').prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'siteoverlay_deactivate_license',
                        nonce: '<?php echo wp_create_nonce('siteoverlay_ajax'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('✅ License deactivated successfully');
                            location.reload();
                        } else {
                            alert('❌ ' + response.data.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('❌ Connection error: ' + error);
                    },
                    complete: function() {
                        $btn.text(originalText).prop('disabled', false);
                    }
                });
            });
            
            // Manual save without validation
            $('#manual-save-btn').on('click', function(e) {
                e.preventDefault();
                
                var licenseKey = $('#license_key').val().trim();
                if (!licenseKey) {
                    alert('Please enter a license key to save');
                    return;
                }
                
                if (confirm('Save license key without online validation?\n\nThis will store the key locally but won\'t verify it with our servers.')) {
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'manual_save_license',
                        value: '1'
                    }).appendTo('#license-form');
                    
                    $('#license-form').submit();
                }
            });
            
            // Enter key support for fields
            $('#trial-name, #trial-email, #trial-website').on('keypress', function(e) {
                if (e.which === 13) {
                    $('#request-trial-btn').click();
                }
            });
            
            $('#license_key').on('keypress', function(e) {
                if (e.which === 13) {
                    $('#validate-license-btn').click();
                }
            });
            
            // Helper function to validate email
            function isValidEmail(email) {
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }
            
            // Test API connection
            function testApiConnection() {
                $.ajax({
                    url: '<?php echo $this->api_base_url; ?>/health',
                    type: 'GET',
                    timeout: 5000,
                    success: function(response) {
                        if (response && response.status === 'ok') {
                            $('#api-status').html('<span style="color: #28a745;">✅ Connected</span>');
                        } else {
                            $('#api-status').html('<span style="color: #ffc107;">⚠️ Limited</span>');
                        }
                    },
                    error: function() {
                        $('#api-status').html('<span style="color: #dc3545;">❌ Offline</span>');
                    }
                });
            }
            
            // Load usage statistics
            function loadUsageStats() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'siteoverlay_get_usage_stats',
                        nonce: '<?php echo wp_create_nonce('siteoverlay_license_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            displayUsageStats(response.data);
                        } else {
                            $('#usage-stats-container').html('<div class="notice notice-error"><p>Error loading usage statistics: ' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        $('#usage-stats-container').html('<div class="notice notice-error"><p>Failed to load usage statistics. Please try refreshing the page.</p></div>');
                    }
                });
            }
            
            // Display usage statistics
            function displayUsageStats(data) {
                var html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
                
                // Site signature
                html += '<div style="background: #f8f9fa; padding: 15px; border-radius: 4px;">';
                html += '<h4 style="margin: 0 0 10px 0;">Site Signature</h4>';
                html += '<p style="margin: 0; font-family: monospace; font-size: 12px;">' + (data.site_signature ? data.site_signature.substring(0, 16) + '...' : 'Not generated') + '</p>';
                html += '</div>';
                
                // Overlay count
                html += '<div style="background: #f8f9fa; padding: 15px; border-radius: 4px;">';
                html += '<h4 style="margin: 0 0 10px 0;">Active Overlays</h4>';
                html += '<p style="margin: 0; font-size: 24px; font-weight: bold; color: #0073aa;">' + data.overlay_count + '</p>';
                html += '</div>';
                
                // License type
                html += '<div style="background: #f8f9fa; padding: 15px; border-radius: 4px;">';
                html += '<h4 style="margin: 0 0 10px 0;">License Type</h4>';
                html += '<p style="margin: 0;">' + data.license_type.charAt(0).toUpperCase() + data.license_type.slice(1) + '</p>';
                html += '</div>';
                
                // Registration status
                html += '<div style="background: #f8f9fa; padding: 15px; border-radius: 4px;">';
                html += '<h4 style="margin: 0 0 10px 0;">Registration</h4>';
                html += '<p style="margin: 0;">' + data.registration_status.charAt(0).toUpperCase() + data.registration_status.slice(1) + '</p>';
                html += '</div>';
                
                // Last tracked
                html += '<div style="background: #f8f9fa; padding: 15px; border-radius: 4px;">';
                html += '<h4 style="margin: 0 0 10px 0;">Last Tracked</h4>';
                html += '<p style="margin: 0;">' + (data.last_tracked !== 'Never' ? data.last_tracked : 'Never') + '</p>';
                html += '</div>';
                
                // Last heartbeat
                html += '<div style="background: #f8f9fa; padding: 15px; border-radius: 4px;">';
                html += '<h4 style="margin: 0 0 10px 0;">Last Heartbeat</h4>';
                html += '<p style="margin: 0;">' + (data.last_heartbeat !== 'Never' ? data.last_heartbeat : 'Never') + '</p>';
                html += '</div>';
                
                html += '</div>';
                
                // Site limits info
                if (data.site_limits) {
                    html += '<div style="margin-top: 20px; padding: 15px; background: #e7f3ff; border-radius: 4px;">';
                    html += '<h4 style="margin: 0 0 10px 0;">Site Limits</h4>';
                    
                    if (data.site_limits.unlimited) {
                        html += '<p style="margin: 0; color: #28a745;"><strong>✅ Unlimited Plan:</strong> No overlay limits</p>';
                    } else {
                        var percentage = data.site_limits.usage_percentage;
                        var color = percentage >= 90 ? '#dc3545' : percentage >= 75 ? '#ffc107' : '#28a745';
                        
                        html += '<p style="margin: 0;"><strong>Usage:</strong> ' + data.site_limits.current_usage + ' / ' + data.site_limits.max_sites + ' overlays</p>';
                        html += '<div style="background: #ddd; height: 20px; border-radius: 10px; margin: 10px 0; overflow: hidden;">';
                        html += '<div style="background: ' + color + '; height: 100%; width: ' + Math.min(percentage, 100) + '%; transition: width 0.3s;"></div>';
                        html += '</div>';
                        html += '<p style="margin: 0; font-size: 12px; color: #666;">' + percentage + '% used</p>';
                    }
                    html += '</div>';
                }
                
                $('#usage-stats-container').html(html);
            }
            
            // Load site limits
            function loadSiteLimits() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'siteoverlay_check_site_limits',
                        nonce: '<?php echo wp_create_nonce('siteoverlay_license_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            displaySiteLimits(response.data);
                        } else {
                            $('#limits-content').html('<div class="notice notice-error"><p>Error checking site limits: ' + response.data + '</p></div>');
                        }
                    },
                    error: function() {
                        $('#limits-content').html('<div class="notice notice-error"><p>Failed to check site limits. Please try refreshing the page.</p></div>');
                    }
                });
            }
            
            // Display site limits
            function displaySiteLimits(data) {
                var html = '';
                
                if (data.warnings && data.warnings.length > 0) {
                    data.warnings.forEach(function(warning) {
                        var noticeClass = warning.type === 'warning' ? 'notice-warning' : 'notice-info';
                        var icon = warning.type === 'warning' ? '⚠️' : 'ℹ️';
                        
                        html += '<div class="notice ' + noticeClass + ' inline" style="margin: 10px 0;">';
                        html += '<p><strong>' + icon + ' ' + warning.message + '</strong></p>';
                        
                        if (warning.action === 'upgrade') {
                            html += '<p><a href="https://siteoverlay.24hr.pro/?plan=upgrade" class="button button-primary" target="_blank">Upgrade Now</a></p>';
                        }
                        html += '</div>';
                    });
                } else {
                    html += '<div class="notice notice-success inline" style="margin: 10px 0;">';
                    html += '<p><strong>✅ All Good!</strong> Your current usage is within your plan limits.</p>';
                    html += '</div>';
                }
                
                // Add usage summary
                html += '<div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px;">';
                html += '<p style="margin: 0;"><strong>Current Usage:</strong> ' + data.current_usage + ' overlays</p>';
                if (!data.limits.unlimited) {
                    html += '<p style="margin: 0;"><strong>Plan Limit:</strong> ' + data.limits.max_sites + ' overlays</p>';
                } else {
                    html += '<p style="margin: 0;"><strong>Plan Limit:</strong> Unlimited</p>';
                }
                html += '</div>';
                
                $('#limits-content').html(html);
            }
            
            // Load data on page load
            loadUsageStats();
            loadSiteLimits();
        });
        </script>
        
        <?php
        // Hook for additional license page content (like site usage stats)
        do_action('siteoverlay_license_page_content');
        ?>
    }
    
    private function render_license_status($license_data, $is_licensed) {
        echo '<div style="background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; border-radius: 4px;">';
        
        if ($is_licensed && !empty($license_data)) {
            $license_type = $license_data['license_type'] ?? 'unknown';
            $licensed_to = $license_data['licensed_to'] ?? $license_data['customer_name'] ?? 'Licensed User';
            $expires = $license_data['expires'] ?? 'Unknown';
            
            echo '<h2 style="color: #00a32a; margin-top: 0;">✅ License Active</h2>';
            echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">';
            echo '<div><strong>License Type:</strong><br>' . ucfirst(str_replace('_', ' ', $license_type)) . '</div>';
            echo '<div><strong>Licensed To:</strong><br>' . esc_html($licensed_to) . '</div>';
            
            if ($expires !== 'Never' && $expires !== 'Unknown') {
                $expires_date = strtotime($expires);
                if ($expires_date) {
                    echo '<div><strong>Expires:</strong><br>' . date('F j, Y', $expires_date) . '</div>';
                } else {
                    echo '<div><strong>Expires:</strong><br>' . esc_html($expires) . '</div>';
                }
            } else {
                echo '<div><strong>Expires:</strong><br>Never</div>';
            }
            
            echo '</div>';
        } else {
            echo '<h2 style="color: #d63638; margin-top: 0;">⚠️ No Active License</h2>';
            echo '<p>You are currently using SiteOverlay Pro without a valid license. Start a free trial or enter your license key below.</p>';
        }
        
        echo '</div>';
    }
    
    public function process_license_form() {
        if (!isset($_POST['siteoverlay_license_form']) || !wp_verify_nonce($_POST['siteoverlay_license_form'])) {
            return;
        }
        
        if (isset($_POST['manual_save_license'])) {
            $license_key = sanitize_text_field($_POST['license_key'] ?? '');
            if (!empty($license_key)) {
                update_option($this->license_key_option, $license_key);
                update_option($this->license_data_option, array(
                    'status' => 'manual',
                    'license_type' => 'manual_entry',
                    'customer_name' => 'Manual Entry',
                    'licensed_to' => 'Manual Entry',
                    'expires' => 'Unknown',
                    'company' => 'eBiz360',
                    'last_validated' => current_time('mysql'),
                    'validation_source' => 'manual_save'
                ));
                
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success"><p>License key saved locally. For full validation, please use the "Activate License" button.</p></div>';
                });
            }
        } elseif (isset($_POST['deactivate_license'])) {
            delete_option($this->license_key_option);
            delete_option($this->license_data_option);
            delete_option('siteoverlay_last_error');
            delete_option('siteoverlay_license_validated');
            
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>License deactivated successfully.</p></div>';
            });
        }
    }
    
    /**
     * Get license key
     */
    public function get_license_key() {
        return get_option($this->license_key_option, '');
    }
    
    /**
     * FIXED: Check if current license is valid - STRICT VALIDATION
     */
    public function has_valid_license() {
        // Always validate with API - ignore cache for maximum security
        $license_key = $this->get_license_key();
        if (empty($license_key)) {
            return false;
        }
        return $this->validate_license_with_railway($license_key, 'check');
    }
    
    /**
     * FIXED: License check for plugin functionality - BLOCKS without license
     */
    public function is_licensed() {
        return $this->has_valid_license(); // Changed from always true to actual check
    }
    
    public function is_trial_active() {
        return !$this->has_valid_license();
    }
    
    public function get_trial_days_remaining() {
        $license_data = get_option($this->license_data_option, array());
        if (isset($license_data['expires']) && $license_data['expires'] !== 'Never') {
            $expires = strtotime($license_data['expires']);
            if ($expires) {
                $days = ceil(($expires - time()) / (24 * 60 * 60));
                return max(0, $days);
            }
        }
        return 14; // Default for trials
    }
    
    /**
     * Get license data
     */
    public function get_license_data() {
        $stored_data = get_option($this->license_data_option, array());
        
        return array_merge(array(
            'status' => 'trial',
            'license_type' => 'trial',
            'sites_used' => 0,
            'customer_name' => 'Trial User',
            'licensed_to' => 'Trial User',
            'company' => 'eBiz360'
        ), $stored_data);
    }
    
    public function track_usage($post_id) {
        $usage_count = get_option('siteoverlay_usage_count', 0);
        update_option('siteoverlay_usage_count', $usage_count + 1);
    }
    
    /**
     * Admin notices (non-blocking)
     */
    public function admin_notices() {
        // Only show license notices on relevant admin pages
        $screen = get_current_screen();
        if (!$screen || !in_array($screen->id, array('post', 'page', 'edit-post', 'edit-page', 'settings_page_siteoverlay-license'))) {
            return;
        }
        
        // Check for site limit error specifically
        $last_error = get_option('siteoverlay_last_error', '');
        if (strpos($last_error, 'Site limit exceeded') !== false) {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>SiteOverlay Pro - Site Limit Reached:</strong><br>';
            echo esc_html($last_error);
            echo '<br><strong>To install on this site:</strong> Uninstall SiteOverlay Pro from an existing site to free up an installation slot, then try activating again.';
            echo '<br><a href="' . admin_url('options-general.php?page=siteoverlay-license') . '">Manage your license</a> | <a href="https://siteoverlay.24hr.pro/" target="_blank">Upgrade license</a></p>';
            echo '</div>';
            return; // Show only this error if it's a site limit issue
        }

        if (!$this->has_valid_license()) {
            echo '<div class="notice notice-warning siteoverlay-license-notice">';
            echo '<p><strong>SiteOverlay Pro:</strong> No active license detected. ';
            echo '<a href="' . admin_url('options-general.php?page=siteoverlay-license') . '">Get your free trial or enter license key</a>';
            echo '</p>';
            echo '</div>';
        } else {
            $license_data = $this->get_license_data();
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
            
            if ($license_data['status'] === 'trial') {
                echo '<div class="notice notice-info siteoverlay-license-notice">';
                echo '<p><strong>SiteOverlay Pro Trial:</strong> You are using a trial license. ';
                echo '<a href="https://siteoverlay.24hr.pro/" target="_blank">Upgrade to full license</a>';
                echo '</p>';
                echo '</div>';
            }
        }
    }
}