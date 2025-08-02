<?php
/**
 * Dynamic Content Manager for SiteOverlay Pro
 * 
 * Fetches and caches dynamic content from Railway API
 * Provides fallback content when API is unavailable
 * 
 * @package SiteOverlay_Pro
 * @since 2.0.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SiteOverlay_Dynamic_Content_Manager {
    
    private $api_base_url = 'https://siteoverlay-api-production.up.railway.app/api';
    private $api_timeout = 10; // seconds
    private $cache_duration = 3600; // 1 hour in seconds
    
    private $default_content = array(
        'upgrade_message' => 'Upgrade to unlock all SiteOverlay Pro features',
        'xagio_affiliate_url' => 'https://xagio.net/?ref=siteoverlay',
        'support_url' => 'https://siteoverlay.24hr.pro/support',
        'training_url' => 'https://siteoverlay.24hr.pro/training'
    );
    
    public function __construct() {
        // No hooks needed - this is a utility class
    }
    
    /**
     * Get dynamic content with caching
     */
    public function get_dynamic_content() {
        $cache_key = 'siteoverlay_dynamic_content';
        $cached_content = get_transient($cache_key);
        
        if ($cached_content !== false) {
            return $cached_content;
        }
        
        // Fetch fresh content from API
        $fresh_content = $this->fetch_content_from_api();
        
        if ($fresh_content) {
            // Cache successful API response
            set_transient($cache_key, $fresh_content, $this->cache_duration);
            return $fresh_content;
        }
        
        // Fallback to default content if API fails
        return $this->default_content;
    }
    
    /**
     * Fetch content from Railway API
     */
    private function fetch_content_from_api() {
        $request_data = array(
            'plugin_version' => SITEOVERLAY_RR_VERSION,
            'site_url' => get_site_url(),
            'license_type' => $this->get_current_license_type()
        );
        
        $response = wp_remote_post($this->api_base_url . '/dynamic-content', array(
            'timeout' => $this->api_timeout,
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode($request_data),
            'blocking' => true,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            error_log('SiteOverlay Dynamic Content API Error: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('SiteOverlay Dynamic Content API returned code: ' . $response_code);
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['success']) && $data['success'] && isset($data['content'])) {
            return $data['content'];
        }
        
        return false;
    }
    
    /**
     * Get current license type for API context
     */
    private function get_current_license_type() {
        $license_status = get_option('siteoverlay_license_status', 'unlicensed');
        return $license_status;
    }
    
    /**
     * Get upgrade message
     */
    public function get_upgrade_message() {
        $content = $this->get_dynamic_content();
        return isset($content['upgrade_message']) ? $content['upgrade_message'] : $this->default_content['upgrade_message'];
    }
    
    /**
     * Get Xagio affiliate URL
     */
    public function get_xagio_affiliate_url() {
        $content = $this->get_dynamic_content();
        return isset($content['xagio_affiliate_url']) ? $content['xagio_affiliate_url'] : $this->default_content['xagio_affiliate_url'];
    }
    
    /**
     * Get support URL
     */
    public function get_support_url() {
        $content = $this->get_dynamic_content();
        return isset($content['support_url']) ? $content['support_url'] : $this->default_content['support_url'];
    }
    
    /**
     * Get training URL
     */
    public function get_training_url() {
        $content = $this->get_dynamic_content();
        return isset($content['training_url']) ? $content['training_url'] : $this->default_content['training_url'];
    }
    
    /**
     * Clear content cache
     */
    public function clear_cache() {
        delete_transient('siteoverlay_dynamic_content');
    }
    
    /**
     * Debug API connection
     */
    public function debug_api_connection() {
        $fresh_content = $this->fetch_content_from_api();
        
        return array(
            'api_url' => $this->api_base_url . '/dynamic-content',
            'timeout' => $this->api_timeout,
            'fresh_content' => $fresh_content,
            'cached_content' => get_transient('siteoverlay_dynamic_content'),
            'default_content' => $this->default_content
        );
    }
}