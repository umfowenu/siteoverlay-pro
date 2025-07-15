<?php
/**
 * SiteOverlay Pro License Manager
 * Handles license validation, trial management, and API communication
 * 
 * CONSTITUTIONAL COMPLIANCE:
 * - All license validation runs in background (non-blocking)
 * - Core overlay functionality never depends on license status
 * - Enhancement module only - does not modify core plugin files
 */

if (!defined('ABSPATH')) {
    exit;
}

class SiteOverlay_License_Manager {
    
    private $api_base_url;
    private $license_key;
    private $license_data;
    
    public function __construct() {
        $this->api_base_url = 'https://siteoverlay-api-production.up.railway.app/api';
        $this->license_key = get_option('siteoverlay_license_key', '');
        
        // Admin hooks (enhancement only)
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Background license validation (non-blocking)
        add_action('wp_loaded', array($this, 'background_license_check'), 20);
        
        // AJAX handlers for admin functionality
        add_action('wp_ajax_siteoverlay_validate_license', array($this, 'ajax_validate_license'));
        add_action('wp_ajax_siteoverlay_deactivate_license', array($this, 'ajax_deactivate_license'));
    }
    
    /**
     * Add admin menu (Settings > SiteOverlay License)
     */
    public function admin_menu() {
        add_options_page(
            'SiteOverlay Pro License',
            'SiteOverlay License',
            'manage_options',
            'siteoverlay-license',
            array($this, 'license_page')
        );
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        register_setting('siteoverlay_license', 'siteoverlay_license_key');
        
        // Handle manual license key submission
        if (isset($_POST['siteoverlay_license_action']) && $_POST['siteoverlay_license_action'] === 'activate') {
            if (wp_verify_nonce($_POST['siteoverlay_license_nonce'], 'siteoverlay_license_action')) {
                $license_key = sanitize_text_field($_POST['license_key']);
                if (!empty($license_key)) {
                    $this->activate_license($license_key);
                }
            }
        }
    }
    
    /**
     * Background license validation (non-blocking)
     * CONSTITUTIONAL RULE: Never blocks core functionality
     */
    public function background_license_check() {
        if (empty($this->license_key)) {
            return;
        }
        
        // Check if we need to validate (cache for 6 hours)
        $last_check = get_transient('siteoverlay_license_last_check');
        if ($last_check && (time() - $last_check) < 21600) {
            return;
        }
        
        // Background validation with short timeout
        $this->validate_license_background();
        set_transient('siteoverlay_license_last_check', time(), 21600);
    }
    
    /**
     * License page display
     */
    public function license_page() {
        echo '<div class="wrap">';
        echo '<div class="siteoverlay-admin-page">';
        
        // Header
        echo '<div class="siteoverlay-admin-header">';
        echo '<h1>SiteOverlay Pro - License Manager</h1>';
        echo '<p class="subtitle">Manage your license, start trials, and view system information</p>';
        echo '</div>';
        
        // Current license status
        if ($this->has_valid_license()) {
            $this->display_license_status();
        } else {
            $this->display_no_license_status();
        }
        
        // Trial request form (only show if no valid license)
        if (!$this->has_valid_license()) {
            $this->display_trial_request_form();
        }
        
        // License key management
        $this->display_license_management();
        
        // System information
        $this->display_system_information();
        
        echo '</div>'; // .siteoverlay-admin-page
        echo '</div>'; // .wrap
    }
    
    /**
     * Display current license status (when valid license exists)
     */
    private function display_license_status() {
        $license_data = $this->get_license_data();
        
        echo '<div class="license-status-card active">';
        echo '<div class="license-status-header">';
        echo '<h2 class="license-status-title">✅ License Active</h2>';
        echo '<span class="license-badge active">' . ucfirst($license_data['license_type']) . '</span>';
        echo '</div>';
        
        echo '<div class="license-details">';
        echo '<div class="license-detail-item">';
        echo '<span class="license-detail-label">License Key</span>';
        echo '<span class="license-detail-value">' . esc_html($this->license_key) . '</span>';
        echo '</div>';
        
        if (!empty($license_data['customer_name']) && $license_data['customer_name'] !== 'Trial User') {
            echo '<div class="license-detail-item">';
            echo '<span class="license-detail-label">Licensed To</span>';
            echo '<span class="license-detail-value">' . esc_html($license_data['customer_name']) . '</span>';
            echo '</div>';
        }
        
        echo '<div class="license-detail-item">';
        echo '<span class="license-detail-label">License Type</span>';
        echo '<span class="license-detail-value">' . ucfirst(str_replace('_', ' ', $license_data['license_type'])) . '</span>';
        echo '</div>';
        
        if ($license_data['status'] === 'trial') {
            echo '<div class="license-detail-item">';
            echo '<span class="license-detail-label">Trial Expires</span>';
            echo '<span class="license-detail-value">' . esc_html($license_data['expires'] ?? 'Unknown') . '</span>';
            echo '</div>';
        }
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Display no license status
     */
    private function display_no_license_status() {
        echo '<div class="license-status-card invalid">';
        echo '<div class="license-status-header">';
        echo '<h2 class="license-status-title">❌ No Active License</h2>';
        echo '<span class="license-badge expired">Unlicensed</span>';
        echo '</div>';
        echo '<p style="margin: 15px 0;">You need a valid license to use SiteOverlay Pro. Request a free trial below or enter your license key.</p>';
        echo '</div>';
    }
    
    /**
     * Display trial request form
     */
    private function display_trial_request_form() {
        echo '<div class="trial-request-section" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 25px; margin-bottom: 20px;">';
        echo '<div class="trial-form-header" style="text-align: center; margin-bottom: 25px;">';
        echo '<h2 style="color: #2271b1; margin: 0 0 10px 0; font-size: 22px;">🚀 Get Your Free 14-Day Trial</h2>';
        echo '<p style="color: #666; margin: 0; font-size: 14px;">Enter your details below and we\'ll send your license key to your email</p>';
        echo '</div>';

        echo '<form id="siteoverlay-trial-form" style="max-width: 400px; margin: 0 auto;">';

        echo '<div style="margin-bottom: 20px;">';
        echo '<label for="trial-name" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Full Name *</label>';
        echo '<input type="text" id="trial-name" name="full_name" required style="width: 100%; padding: 12px 16px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;" placeholder="Enter your full name">';
        echo '</div>';

        echo '<div style="margin-bottom: 20px;">';
        echo '<label for="trial-email" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Email Address *</label>';
        echo '<input type="email" id="trial-email" name="email" required style="width: 100%; padding: 12px 16px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;" placeholder="Enter your email address">';
        echo '<small style="color: #666; font-size: 12px;">Your trial license will be sent to this email</small>';
        echo '</div>';

        echo '<div style="margin-bottom: 20px;">';
        echo '<label for="trial-website" style="display: block; margin-bottom: 8px; font-weight: 600; color: #333;">Website URL (Optional)</label>';
        echo '<input type="url" id="trial-website" name="website" style="width: 100%; padding: 12px 16px; border: 2px solid #ddd; border-radius: 6px; font-size: 14px;" placeholder="https://yourwebsite.com">';
        echo '</div>';

        echo '<button id="request-trial-btn" type="button" style="width: 100%; padding: 15px 24px; background: #2271b1; color: #fff; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background-color 0.3s;">Get My Trial License</button>';

        echo '<div id="trial-response" style="margin-top: 20px; padding: 15px; border-radius: 6px; display: none;"></div>';
        echo '</form>';

        echo '<div style="text-align: center; margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">';
        echo '<p style="color: #888; font-size: 13px; margin: 0;">✅ No credit card required • ✅ Full features included • ✅ Cancel anytime</p>';
        echo '</div>';

        echo '</div>';

        // Add the JavaScript for trial form
        $this->add_trial_form_javascript();
    }
    
    /**
     * Display license key management section
     */
    private function display_license_management() {
        echo '<div class="license-key-section">';
        echo '<h2>🔑 License Key Management</h2>';
        
        echo '<form method="post" action="">';
        wp_nonce_field('siteoverlay_license_action', 'siteoverlay_license_nonce');
        echo '<input type="hidden" name="siteoverlay_license_action" value="activate">';
        
        echo '<div class="license-form-group">';
        echo '<label for="license_key">License Key:</label>';
        echo '<input type="text" id="license_key" name="license_key" value="' . esc_attr($this->license_key) . '" class="license-key-input" placeholder="Enter your license key here" ' . ($this->has_valid_license() ? 'readonly' : '') . '>';
        echo '</div>';
        
        echo '<div class="license-actions">';
        if ($this->has_valid_license()) {
            echo '<button type="button" class="license-btn secondary" onclick="enableLicenseEdit()">✏️ Edit</button>';
            echo '<button type="button" class="license-btn primary" onclick="validateLicense()">🔄 Revalidate</button>';
            echo '<button type="button" class="license-btn danger" onclick="deactivateLicense()">❌ Deactivate</button>';
        } else {
            echo '<button type="submit" class="license-btn primary">✅ Activate License</button>';
            echo '<button type="button" class="license-btn secondary" onclick="validateLicense()">🔍 Validate</button>';
        }
        echo '</div>';
        
        echo '</form>';
        echo '<div id="license-response" style="margin-top: 15px; display: none;"></div>';
        echo '</div>';
        
        // Add license management JavaScript
        $this->add_license_management_javascript();
    }
    
    /**
     * Display system information
     */
    private function display_system_information() {
        echo '<div class="system-info-section" style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 25px; margin-bottom: 20px;">';
        echo '<h2>ℹ️ System Information</h2>';
        
        echo '<table class="widefat" style="margin-top: 15px;">';
        echo '<tbody>';
        
        echo '<tr>';
        echo '<td style="width: 200px;"><strong>Plugin Version:</strong></td>';
        echo '<td>' . SITEOVERLAY_RR_VERSION . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><strong>WordPress Version:</strong></td>';
        echo '<td>' . get_bloginfo('version') . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><strong>Site URL:</strong></td>';
        echo '<td>' . get_site_url() . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><strong>API Status:</strong></td>';
        echo '<td><span id="api-status">🔍 Checking...</span></td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><strong>Last Validated:</strong></td>';
        echo '<td>' . ($this->get_last_validation_time() ?: 'Never') . '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<td><strong>Validation Source:</strong></td>';
        echo '<td>Railway API</td>';
        echo '</tr>';
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
        
        // Add API status check JavaScript
        $this->add_api_status_javascript();
    }
    
    /**
     * Add trial form JavaScript
     */
    private function add_trial_form_javascript() {
        echo '<script>
        jQuery(document).ready(function($) {
            function requestTrial() {
                const fullName = $("#trial-name").val().trim();
                const email = $("#trial-email").val().trim();
                const website = $("#trial-website").val().trim();
                const $button = $("#request-trial-btn");
                const $response = $("#trial-response");
                
                // Clear previous response
                $response.hide();
                
                // Validation
                if (!fullName) {
                    showResponse("Please enter your full name", "error");
                    $("#trial-name").focus();
                    return;
                }
                
                if (!email) {
                    showResponse("Please enter your email address", "error");
                    $("#trial-email").focus();
                    return;
                }
                
                // Email format validation
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    showResponse("Please enter a valid email address", "error");
                    $("#trial-email").focus();
                    return;
                }
                
                // Show loading state
                $button.prop("disabled", true).text("Sending Trial License...");
                showResponse("📧 Processing your trial request...", "info");
                
                // Send to Railway API
                $.ajax({
                    url: "' . $this->api_base_url . '/request-trial",
                    type: "POST",
                    contentType: "application/json",
                    data: JSON.stringify({
                        full_name: fullName,
                        email: email,
                        website: website,
                        siteUrl: window.location.origin,        // CHANGED: site_url → siteUrl
                        siteTitle: document.title,              // CHANGED: site_title → siteTitle  
                        wpVersion: "' . get_bloginfo('version') . '",      // CHANGED: wp_version → wpVersion
                        pluginVersion: "' . SITEOVERLAY_RR_VERSION . '",   // CHANGED: plugin_version → pluginVersion
                        userAgent: navigator.userAgent,         // CHANGED: user_agent → userAgent
                        requestSource: "plugin_admin"           // CHANGED: request_source → requestSource
                    }),
                    success: function(data) {
                        if (data.success) {
                            showResponse("✅ " + data.message + "<br><br><strong>Please check your email (including spam folder) for your license key!</strong>", "success");
                            
                            // Clear the form
                            $("#trial-name, #trial-email, #trial-website").val("");
                            
                            // Refresh page after 5 seconds to check for new license
                            setTimeout(function() {
                                showResponse("🔄 Refreshing page to check for your new license...", "info");
                                setTimeout(() => location.reload(), 2000);
                            }, 3000);
                        } else {
                            showResponse("❌ " + (data.message || "Failed to process trial request. Please try again."), "error");
                        }
                    },
                    error: function(xhr, status, error) {
                        let errorMessage = "Connection error. Please check your internet connection and try again.";
                        
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        } else if (xhr.status === 0) {
                            errorMessage = "Unable to connect to server. Please check your internet connection.";
                        } else if (xhr.status >= 500) {
                            errorMessage = "Server error. Please try again in a few minutes.";
                        }
                        
                        showResponse("❌ " + errorMessage, "error");
                        console.error("Trial request error:", { status, error, response: xhr.responseText });
                    },
                    complete: function() {
                        $button.prop("disabled", false).text("Get My Trial License");
                    }
                });
            }
            
            function showResponse(message, type) {
                const $response = $("#trial-response");
                const colors = {
                    success: { bg: "#d4edda", text: "#155724", border: "#c3e6cb" },
                    error: { bg: "#f8d7da", text: "#721c24", border: "#f5c6cb" },
                    info: { bg: "#d1ecf1", text: "#0c5460", border: "#bee5eb" }
                };
                
                const color = colors[type] || colors.info;
                $response.css({
                    "background-color": color.bg,
                    "color": color.text,
                    "border": "1px solid " + color.border
                }).html(message).show();
                
                // Scroll to response
                $("html, body").animate({
                    scrollTop: $response.offset().top - 100
                }, 500);
            }
            
            // Handle form submission
            $("#request-trial-btn").on("click", function(e) {
                e.preventDefault();
                requestTrial();
            });
            
            // Handle Enter key press
            $("#trial-name, #trial-email, #trial-website").on("keypress", function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    requestTrial();
                }
            });
            
            // Real-time email validation
            $("#trial-email").on("blur", function() {
                const email = $(this).val().trim();
                if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    $(this).css("border-color", "#dc3545");
                } else {
                    $(this).css("border-color", "#ddd");
                }
            });
        });
        </script>';
    }
    
    /**
     * Add license management JavaScript
     */
    private function add_license_management_javascript() {
        echo '<script>
        function enableLicenseEdit() {
            document.getElementById("license_key").readOnly = false;
            document.getElementById("license_key").focus();
        }
        
        function validateLicense() {
            const licenseKey = document.getElementById("license_key").value.trim();
            if (!licenseKey) {
                showLicenseResponse("Please enter a license key", "error");
                return;
            }
            
            showLicenseResponse("🔍 Validating license...", "info");
            
            jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "siteoverlay_validate_license",
                    license_key: licenseKey,
                    nonce: "' . wp_create_nonce('siteoverlay_ajax') . '"
                },
                success: function(response) {
                    if (response.success) {
                        showLicenseResponse("✅ " + response.data.message, "success");
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showLicenseResponse("❌ " + response.data.message, "error");
                    }
                },
                error: function() {
                    showLicenseResponse("❌ Connection error. Please try again.", "error");
                }
            });
        }
        
        function deactivateLicense() {
            if (!confirm("Are you sure you want to deactivate this license?")) {
                return;
            }
            
            showLicenseResponse("🔄 Deactivating license...", "info");
            
            jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: {
                    action: "siteoverlay_deactivate_license",
                    nonce: "' . wp_create_nonce('siteoverlay_ajax') . '"
                },
                success: function(response) {
                    if (response.success) {
                        showLicenseResponse("✅ License deactivated successfully", "success");
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showLicenseResponse("❌ " + response.data.message, "error");
                    }
                },
                error: function() {
                    showLicenseResponse("❌ Connection error. Please try again.", "error");
                }
            });
        }
        
        function showLicenseResponse(message, type) {
            const responseDiv = document.getElementById("license-response");
            const colors = {
                success: { bg: "#d4edda", text: "#155724", border: "#c3e6cb" },
                error: { bg: "#f8d7da", text: "#721c24", border: "#f5c6cb" },
                info: { bg: "#d1ecf1", text: "#0c5460", border: "#bee5eb" }
            };
            
            const color = colors[type] || colors.info;
            responseDiv.style.backgroundColor = color.bg;
            responseDiv.style.color = color.text;
            responseDiv.style.border = "1px solid " + color.border;
            responseDiv.style.padding = "12px";
            responseDiv.style.borderRadius = "6px";
            responseDiv.innerHTML = message;
            responseDiv.style.display = "block";
        }
        </script>';
    }
    
    /**
     * Add API status check JavaScript
     */
    private function add_api_status_javascript() {
        echo '<script>
        jQuery(document).ready(function($) {
            // Test API connection on page load
            testApiConnection();
            
            function testApiConnection() {
                $.ajax({
                    url: "' . $this->api_base_url . '/health",
                    type: "GET",
                    timeout: 5000,
                    success: function(response) {
                        if (response && response.status === "ok") {
                            $("#api-status").html("<span style=\"color: #28a745;\">✅ Connected</span>");
                        } else {
                            $("#api-status").html("<span style=\"color: #ffc107;\">⚠️ Limited</span>");
                        }
                    },
                    error: function() {
                        $("#api-status").html("<span style=\"color: #dc3545;\">❌ Offline</span>");
                    }
                });
            }
        });
        </script>';
    }
    
    /**
     * AJAX handler for license validation
     */
    public function ajax_validate_license() {
        if (!wp_verify_nonce($_POST['nonce'], 'siteoverlay_ajax')) {
            wp_die('Security check failed');
        }
        
        $license_key = sanitize_text_field($_POST['license_key']);
        $result = $this->activate_license($license_key);
        
        wp_send_json($result);
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
     * Activate license key
     */
    private function activate_license($license_key) {
        $response = wp_remote_post($this->api_base_url . '/validate-license', array(
            'timeout' => 10,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'licenseKey' => $license_key,
                'siteUrl' => get_site_url(),
                'action' => 'activate',
                'pluginVersion' => SITEOVERLAY_RR_VERSION,
                'wpVersion' => get_bloginfo('version')
            ))
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'data' => array('message' => 'Connection error: ' . $response->get_error_message())
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && $data['success']) {
            update_option('siteoverlay_license_key', $license_key);
            update_option('siteoverlay_license_data', $data['data']);
            update_option('siteoverlay_license_validated', current_time('mysql'));
            
            $this->license_key = $license_key;
            $this->license_data = $data['data'];
            
            return array(
                'success' => true,
                'data' => array('message' => 'License activated successfully!')
            );
        } else {
            return array(
                'success' => false,
                'data' => array('message' => $data['message'] ?? 'License validation failed')
            );
        }
    }
    
    /**
     * Deactivate current license
     */
    private function deactivate_license() {
        if (empty($this->license_key)) {
            return array(
                'success' => false,
                'data' => array('message' => 'No license to deactivate')
            );
        }
        
        $response = wp_remote_post($this->api_base_url . '/validate-license', array(
            'timeout' => 10,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'licenseKey' => $this->license_key,
                'siteUrl' => get_site_url(),
                'action' => 'deactivate'
            ))
        ));
        
        // Clear local license data regardless of API response
        delete_option('siteoverlay_license_key');
        delete_option('siteoverlay_license_data');
        delete_option('siteoverlay_license_validated');
        
        $this->license_key = '';
        $this->license_data = null;
        
        return array(
            'success' => true,
            'data' => array('message' => 'License deactivated successfully')
        );
    }
    
    /**
     * Background license validation (non-blocking)
     */
    private function validate_license_background() {
        if (empty($this->license_key)) {
            return;
        }
        
        $response = wp_remote_post($this->api_base_url . '/validate-license', array(
            'timeout' => 5,
            'blocking' => false,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array(
                'licenseKey' => $this->license_key,
                'siteUrl' => get_site_url(),
                'action' => 'check',
                'pluginVersion' => SITEOVERLAY_RR_VERSION
            ))
        ));
        
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($data && isset($data['success'])) {
                if ($data['success']) {
                    update_option('siteoverlay_license_data', $data['data']);
                    update_option('siteoverlay_license_validated', current_time('mysql'));
                } else {
                    // License invalid - clear it
                    delete_option('siteoverlay_license_key');
                    delete_option('siteoverlay_license_data');
                }
            }
        }
    }
    
    /**
     * Check if current license is valid
     */
    public function has_valid_license() {
        if (empty($this->license_key)) {
            return false;
        }
        
        $license_data = get_option('siteoverlay_license_data');
        if (!$license_data) {
            return false;
        }
        
        return isset($license_data['status']) && 
               in_array($license_data['status'], array('active', 'trial'));
    }
    
    /**
     * Get license data
     */
    public function get_license_data() {
        if ($this->license_data) {
            return $this->license_data;
        }
        
        $this->license_data = get_option('siteoverlay_license_data', array(
            'status' => 'invalid',
            'license_type' => 'none',
            'customer_name' => '',
            'expires' => ''
        ));
        
        return $this->license_data;
    }
    
    /**
     * Get last validation time
     */
    private function get_last_validation_time() {
        $validated = get_option('siteoverlay_license_validated');
        if ($validated) {
            return date('F j, Y g:i A', strtotime($validated));
        }
        return null;
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
        
        if (!$this->has_valid_license()) {
            echo '<div class="notice notice-warning siteoverlay-license-notice">';
            echo '<p><strong>SiteOverlay Pro:</strong> No active license detected. ';
            echo '<a href="' . admin_url('options-general.php?page=siteoverlay-license') . '">Get your free trial or enter license key</a>';
            echo '</p>';
            echo '</div>';
        } else {
            $license_data = $this->get_license_data();
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