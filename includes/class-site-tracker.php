<?php
/**
 * SiteOverlay Pro Site Tracker
 * 
 * CONSTITUTIONAL COMPLIANCE:
 * - All site tracking runs in background (non-blocking)
 * - Never blocks core plugin functionality
 * - Graceful degradation when API is unavailable
 * - Site registration is enhancement only
 */

if (!defined('ABSPATH')) {
    exit;
}

class SiteOverlay_Site_Tracker {
    
    private $api_base_url = 'https://siteoverlay-api-production.up.railway.app/api';
    private $api_timeout = 10; // Short timeout for non-blocking operations
    
    public function __construct() {
        // CONSTITUTIONAL RULE: Background operations only
        add_action('wp_loaded', array($this, 'maybe_register_site'), 30);
        
        // Daily heartbeat (wp_cron)
        add_action('init', array($this, 'schedule_heartbeat'));
        add_action('siteoverlay_daily_heartbeat', array($this, 'daily_heartbeat'));
        
        // Note: Plugin activation/deactivation hooks are registered in main plugin file
        
        // Admin interface enhancements
        add_action('admin_init', array($this, 'add_site_usage_display'));
    }
    
    /**
     * CONSTITUTIONAL RULE: Background site registration (non-blocking)
     */
    public function maybe_register_site() {
        // Only register if we have a valid license
        if (!$this->has_valid_license()) {
            return;
        }
        
        // Check if site is already registered (avoid repeated calls)
        $last_registration = get_transient('siteoverlay_site_registered');
        if ($last_registration) {
            return;
        }
        
        // Register site in background
        $this->register_site_background();
        
        // Cache registration status for 24 hours
        set_transient('siteoverlay_site_registered', time(), 24 * HOUR_IN_SECONDS);
    }
    
    /**
     * Background site registration (non-blocking)
     */
    private function register_site_background() {
        // CONSTITUTIONAL RULE: Avoid circular dependencies
        if (!class_exists('SiteOverlay_License_Manager')) {
            return;
        }
        
        $license_manager = new SiteOverlay_License_Manager();
        $license_key = $license_manager->get_license_key();
        
        if (empty($license_key)) {
            return;
        }
        
        $site_data = array(
            'wp_version' => get_bloginfo('version'),
            'plugin_version' => SITEOVERLAY_RR_VERSION,
            'php_version' => PHP_VERSION,
            'theme' => get_option('stylesheet'),
            'site_name' => get_bloginfo('name'),
            'admin_email' => get_bloginfo('admin_email'),
            'site_language' => get_bloginfo('language'),
            'registration_time' => current_time('mysql')
        );
        
        $body = array(
            'licenseKey' => $license_key,
            'siteUrl' => get_site_url(),
            'siteData' => $site_data
        );
        
        // Non-blocking API call
        wp_remote_post($this->api_base_url . '/register-site-usage', array(
            'timeout' => $this->api_timeout,
            'blocking' => false,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($body)
        ));
        
        // Log registration attempt (for debugging)
        error_log('SiteOverlay: Background site registration attempted for: ' . get_site_url());
    }
    
    /**
     * Plugin activation handler
     */
    public function on_plugin_activation() {
        // Clear registration cache to force re-registration
        delete_transient('siteoverlay_site_registered');
        
        // Schedule heartbeat
        $this->schedule_heartbeat();
        
        // CONSTITUTIONAL RULE: Never block activation for license/API issues
        // Registration will happen in background during wp_loaded
    }
    
    /**
     * Plugin deactivation handler
     */
    public function on_plugin_deactivation() {
        // Clear scheduled events
        wp_clear_scheduled_hook('siteoverlay_daily_heartbeat');
        
        // Attempt to unregister site (non-blocking)
        $this->unregister_site_background();
    }
    
    /**
     * Background site unregistration (non-blocking)
     */
    private function unregister_site_background() {
        // CONSTITUTIONAL RULE: Avoid circular dependencies
        if (!class_exists('SiteOverlay_License_Manager')) {
            return;
        }
        
        $license_manager = new SiteOverlay_License_Manager();
        $license_key = $license_manager->get_license_key();
        
        if (empty($license_key)) {
            return;
        }
        
        $body = array(
            'licenseKey' => $license_key,
            'siteUrl' => get_site_url()
        );
        
        // Non-blocking API call
        wp_remote_post($this->api_base_url . '/unregister-site-usage', array(
            'timeout' => $this->api_timeout,
            'blocking' => false,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($body)
        ));
    }
    
    /**
     * Schedule daily heartbeat
     */
    public function schedule_heartbeat() {
        if (!wp_next_scheduled('siteoverlay_daily_heartbeat')) {
            wp_schedule_event(time(), 'daily', 'siteoverlay_daily_heartbeat');
        }
    }
    
    /**
     * Daily heartbeat to verify site registration
     */
    public function daily_heartbeat() {
        // Only send heartbeat if licensed
        if (!$this->has_valid_license()) {
            return;
        }
        
        // Clear registration cache to force refresh
        delete_transient('siteoverlay_site_registered');
        
        // Re-register site (updates last_seen timestamp)
        $this->register_site_background();
        
        // Fetch current usage statistics (non-blocking)
        $this->fetch_usage_stats_background();
    }
    
    /**
     * Fetch usage statistics in background (non-blocking)
     */
    private function fetch_usage_stats_background() {
        // CONSTITUTIONAL RULE: Avoid circular dependencies
        if (!class_exists('SiteOverlay_License_Manager')) {
            return;
        }
        
        $license_manager = new SiteOverlay_License_Manager();
        $license_key = $license_manager->get_license_key();
        
        if (empty($license_key)) {
            return;
        }
        
        // Non-blocking request for usage stats
        $response = wp_remote_get($this->api_base_url . '/license-usage/' . $license_key, array(
            'timeout' => $this->api_timeout,
            'blocking' => false
        ));
        
        // Note: Since this is non-blocking, we can't process the response here
        // The stats will be fetched when needed in the admin interface
    }
    
    /**
     * Get current site usage statistics (blocking call for admin display)
     */
    public function get_usage_stats() {
        // CONSTITUTIONAL RULE: Avoid circular dependencies
        if (!class_exists('SiteOverlay_License_Manager')) {
            return array(
                'sites_used' => 0,
                'site_limit' => 0,
                'sites' => array()
            );
        }
        
        $license_manager = new SiteOverlay_License_Manager();
        $license_key = $license_manager->get_license_key();
        
        if (empty($license_key)) {
            return array(
                'sites_used' => 0,
                'site_limit' => 0,
                'sites' => array()
            );
        }
        
        // Check cache first
        $cached_stats = get_transient('siteoverlay_usage_stats');
        if ($cached_stats) {
            return $cached_stats;
        }
        
        // Fetch from API (blocking call for admin display)
        $response = wp_remote_get($this->api_base_url . '/license-usage/' . $license_key, array(
            'timeout' => $this->api_timeout,
            'headers' => array('Content-Type' => 'application/json')
        ));
        
        if (is_wp_error($response)) {
            return array(
                'sites_used' => 0,
                'site_limit' => 0,
                'sites' => array(),
                'error' => 'Unable to fetch usage statistics'
            );
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !$data['success']) {
            return array(
                'sites_used' => 0,
                'site_limit' => 0,
                'sites' => array(),
                'error' => 'Invalid response from server'
            );
        }
        
        $stats = $data['data'];
        
        // Cache for 1 hour
        set_transient('siteoverlay_usage_stats', $stats, HOUR_IN_SECONDS);
        
        return $stats;
    }
    
    /**
     * Generate unique site signature
     */
    public function generate_site_signature() {
        $site_data = array(
            get_site_url(),
            ABSPATH,
            wp_salt('auth'),
            wp_salt('secure_auth')
        );
        
        return substr(hash('sha256', implode('|', $site_data)), 0, 32);
    }
    
    /**
     * Check if site has valid license
     */
    private function has_valid_license() {
        if (!class_exists('SiteOverlay_License_Manager')) {
            return false;
        }
        
        $license_manager = new SiteOverlay_License_Manager();
        return $license_manager->has_valid_license();
    }
    
    /**
     * Add site usage display to admin interface
     */
    public function add_site_usage_display() {
        // Add to license page only
        add_action('siteoverlay_license_page_content', array($this, 'display_site_usage'));
    }
    
    /**
     * Display site usage statistics on license page
     */
    public function display_site_usage() {
        if (!$this->has_valid_license()) {
            return;
        }
        
        $stats = $this->get_usage_stats();
        ?>
        <div class="siteoverlay-usage-stats" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa;">
            <h3><?php _e('Site Usage Statistics', 'siteoverlay-rr'); ?></h3>
            
            <?php if (isset($stats['error'])): ?>
                <p style="color: #d63638;"><?php echo esc_html($stats['error']); ?></p>
            <?php else: ?>
                <p>
                    <strong><?php _e('Sites Used:', 'siteoverlay-rr'); ?></strong> 
                    <?php echo esc_html($stats['sites_used']); ?>
                    <?php if ($stats['site_limit'] > 0): ?>
                        / <?php echo esc_html($stats['site_limit']); ?>
                    <?php else: ?>
                        (<?php _e('Unlimited', 'siteoverlay-rr'); ?>)
                    <?php endif; ?>
                </p>
                
                <?php if (!empty($stats['sites'])): ?>
                    <h4><?php _e('Registered Sites:', 'siteoverlay-rr'); ?></h4>
                    <ul style="margin-left: 20px;">
                        <?php foreach ($stats['sites'] as $site): ?>
                            <li>
                                <strong><?php echo esc_html($site['site_domain']); ?></strong>
                                <br>
                                <small>
                                    <?php _e('Registered:', 'siteoverlay-rr'); ?> <?php echo esc_html(date('M j, Y', strtotime($site['registered_at']))); ?>
                                    | <?php _e('Last Seen:', 'siteoverlay-rr'); ?> <?php echo esc_html(date('M j, Y', strtotime($site['last_seen']))); ?>
                                    <?php if ($site['site_url'] === get_site_url()): ?>
                                        <strong>(<?php _e('This Site', 'siteoverlay-rr'); ?>)</strong>
                                    <?php endif; ?>
                                </small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                
                <?php if ($stats['site_limit'] > 0 && $stats['sites_used'] >= $stats['site_limit']): ?>
                    <div style="color: #d63638; font-weight: bold; margin-top: 10px;">
                        ⚠️ <?php _e('Site limit reached. Please upgrade your license to add more sites.', 'siteoverlay-rr'); ?>
                    </div>
                <?php elseif ($stats['site_limit'] > 0): ?>
                    <div style="color: #007cba; margin-top: 10px;">
                        ✅ <?php printf(__('You can add %d more sites.', 'siteoverlay-rr'), $stats['site_limit'] - $stats['sites_used']); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }
} 