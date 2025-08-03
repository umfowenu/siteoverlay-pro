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
     * Get dynamic content with DEEP DEBUGGING
     */
    public function get_dynamic_content() {
        $cache_key = 'so_cache';
        $cache_expiry_key = 'so_cache_expiry';
        
        error_log('=== SITEOVERLAY DEBUG START ===');
        
        // Use options table directly (READ FROM CHUNKS)
        $cache_count = get_option('so_cache_count', 0);
        $cache_expiry = get_option('so_cache_expiry', 0);
        
        error_log('STEP 1 - Cache check: Found ' . $cache_count . ' chunks');
        error_log('STEP 1 - Expiry: ' . $cache_expiry . ' (current time: ' . time() . ')');
        
        if ($cache_count > 0) {
            // Reconstruct content from chunks
            $cached_content = array();
            for ($i = 0; $i < $cache_count; $i++) {
                $chunk = get_option('so_cache_' . $i, false);
                if ($chunk && is_array($chunk)) {
                    $cached_content = array_merge($cached_content, $chunk);
                } else {
                    // If any chunk is missing, invalidate entire cache
                    error_log('STEP 2 - Chunk ' . $i . ' missing, invalidating cache');
                    $cached_content = false;
                    break;
                }
            }
        } else {
            $cached_content = false;
        }
        
        // Check if cache is expired
        if ($cached_content && time() > $cache_expiry) {
            error_log('STEP 2 - Cache EXPIRED, clearing chunks');
            $cached_content = false;
            // Clear all chunks
            for ($i = 0; $i < $cache_count; $i++) {
                delete_option('so_cache_' . $i);
            }
            delete_option('so_cache_count');
            delete_option('so_cache_expiry');
        }
        
        // Return cached content if available
        if ($cached_content !== false && is_array($cached_content) && count($cached_content) > 0) {
            error_log('STEP 3 - Returning cached content (' . count($cached_content) . ' items)');
            error_log('=== SITEOVERLAY DEBUG END (CACHED) ===');
            return $cached_content;
        }
        
        // Fetch fresh content from API
        error_log('STEP 4 - No valid cache, fetching from API');
        $fresh_content = $this->fetch_content_from_api();
        
        if ($fresh_content && is_array($fresh_content) && count($fresh_content) > 0) {
            error_log('STEP 5 - API SUCCESS: Got ' . count($fresh_content) . ' items');
            
            // Cache using options table (SPLIT INTO CHUNKS)
            $expiry_time = time() + $this->cache_duration;
            
            // Split content into chunks of 5 items each
            $chunks = array_chunk($fresh_content, 5, true);
            $chunk_count = count($chunks);
            
            error_log('STEP 6 - Splitting ' . count($fresh_content) . ' items into ' . $chunk_count . ' chunks');
            
            // Store each chunk separately
            $all_chunks_stored = true;
            for ($i = 0; $i < $chunk_count; $i++) {
                $chunk_key = 'so_cache_' . $i;
                $chunk_result = update_option($chunk_key, $chunks[$i]);
                error_log('STEP 7.' . $i . ' - Chunk ' . $i . ' storage: ' . ($chunk_result ? 'SUCCESS' : 'FAILED'));
                if (!$chunk_result) {
                    $all_chunks_stored = false;
                }
            }
            
            // Store metadata
            $count_result = update_option('so_cache_count', $chunk_count);
            $expiry_result = update_option('so_cache_expiry', $expiry_time);
            
            error_log('STEP 8 - Metadata storage - Count: ' . ($count_result ? 'SUCCESS' : 'FAILED') . ', Expiry: ' . ($expiry_result ? 'SUCCESS' : 'FAILED'));
            
            // Verify chunk storage
            if ($all_chunks_stored) {
                error_log('STEP 9 - All chunks stored successfully');
                
                // Verify by reconstructing
                $verify_content = array();
                for ($i = 0; $i < $chunk_count; $i++) {
                    $verify_chunk = get_option('so_cache_' . $i, false);
                    if ($verify_chunk && is_array($verify_chunk)) {
                        $verify_content = array_merge($verify_content, $verify_chunk);
                    }
                }
                error_log('STEP 10 - Verification: Reconstructed ' . count($verify_content) . ' items from chunks');
            } else {
                error_log('STEP 9 - Some chunks failed to store');
            }
            
            error_log('=== SITEOVERLAY DEBUG END (FRESH) ===');
            return $fresh_content;
        }
        
        // Fallback to default content if API fails
        error_log('STEP 12 - API FAILED, using default content');
        error_log('=== SITEOVERLAY DEBUG END (DEFAULT) ===');
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
     * Clear content cache (options table version)
     */
    public function clear_cache() {
        // Clear chunk-based cache
        $cache_count = get_option('so_cache_count', 0);
        
        // Clear all chunks
        for ($i = 0; $i < $cache_count; $i++) {
            delete_option('so_cache_' . $i);
        }
        
        // Clear metadata
        delete_option('so_cache_count');
        delete_option('so_cache_expiry');
        
        // Clear legacy cache (if any)
        delete_transient('so_cache');
        delete_option('so_cache');
        
        error_log('SiteOverlay: Cache cleared (' . $cache_count . ' chunks cleared)');
    }
    
    /**
     * Debug API connection
     */
    public function debug_api_connection() {
        $fresh_content = $this->fetch_content_from_api();
        // Check for chunk-based cache
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
        
        // Test cache setting
        $cache_test_result = 'SKIPPED';
        if ($fresh_content) {
            $test_cache_key = 'siteoverlay_test_cache';
            $test_cache_set = set_transient($test_cache_key, array('test' => 'value'), 60);
            $test_cache_get = get_transient($test_cache_key);
            delete_transient($test_cache_key);
            $cache_test_result = ($test_cache_set && $test_cache_get) ? 'WORKING' : 'FAILED';
        }
        
        return array(
            'api_url' => $this->api_base_url . '/dynamic-content',
            'timeout' => $this->api_timeout,
            'fresh_content' => $fresh_content,
            'cached_content' => $cached_content,
            'cache_test' => $cache_test_result,
            'default_content' => $this->default_content
        );
    }
}