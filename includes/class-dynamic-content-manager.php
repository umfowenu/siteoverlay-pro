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
        
        // Add debug logging
        error_log('SiteOverlay: Attempting API call to: ' . $this->api_base_url . '/dynamic-content');
        error_log('SiteOverlay: Request headers: ' . print_r(array(
            'X-Software-Type' => 'wordpress_plugin',
            'User-Agent' => 'SiteOverlay-Pro-Plugin/2.0.1'
        ), true));
        
        $response = wp_remote_get($this->api_base_url . '/dynamic-content', array(
            'timeout' => $this->api_timeout,
            'headers' => array(
                'X-Software-Type' => 'wordpress_plugin',
                'User-Agent' => 'SiteOverlay-Pro-Plugin/2.0.1'
            ),
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            error_log('SiteOverlay: API call failed with WP_Error: ' . $response->get_error_message());
            error_log('SiteOverlay: WP_Error code: ' . $response->get_error_code());
            return false;
        } else {
            error_log('SiteOverlay: API call successful, response code: ' . wp_remote_retrieve_response_code($response));
            error_log('SiteOverlay: Response body length: ' . strlen(wp_remote_retrieve_body($response)));
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('SiteOverlay Dynamic Content API returned code: ' . $response_code);
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['success']) && $data['success'] && isset($data['content'])) {
            // Convert Railway API format to plugin format
            $formatted_content = array();
            foreach ($data['content'] as $key => $item) {
                if (isset($item['value'])) {
                    $formatted_content[$key] = $item['value'];
                }
            }
            return $formatted_content;
        }
        
        // Try cURL fallback if wp_remote_get failed
        error_log('SiteOverlay: wp_remote_get failed, trying cURL fallback');
        return $this->fetch_content_from_api_curl();
    }
    
    /**
     * Fallback API fetch using cURL if wp_remote_get fails
     */
    private function fetch_content_from_api_curl() {
        if (!function_exists('curl_init')) {
            error_log('SiteOverlay: cURL not available');
            return false;
        }
        
        $url = $this->api_base_url . '/dynamic-content';
        error_log('SiteOverlay: Trying cURL fallback to: ' . $url);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->api_timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'X-Software-Type: wordpress_plugin',
            'User-Agent: SiteOverlay-Pro-Plugin/2.0.1'
        ));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For SSL issues
        
        $body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log('SiteOverlay: cURL error: ' . $error);
            return false;
        }
        
        if ($http_code !== 200) {
            error_log('SiteOverlay: cURL returned HTTP ' . $http_code);
            return false;
        }
        
        error_log('SiteOverlay: cURL success, response length: ' . strlen($body));
        $data = json_decode($body, true);
        
        if (isset($data['success']) && $data['success'] && isset($data['content'])) {
            $formatted_content = array();
            foreach ($data['content'] as $key => $item) {
                if (isset($item['value'])) {
                    $formatted_content[$key] = $item['value'];
                }
            }
            return $formatted_content;
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