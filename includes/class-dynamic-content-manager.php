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
        error_log('=== SITEOVERLAY DEBUG START ===');
        
        // Try to retrieve from chunks
        $cached_content = $this->retrieve_content_chunks();
        
        if ($cached_content && is_array($cached_content) && count($cached_content) > 0) {
            error_log('SiteOverlay: Returning chunked cache (' . count($cached_content) . ' items)');
            error_log('=== SITEOVERLAY DEBUG END (CACHED) ===');
            return $cached_content;
        }
        
        // Fetch fresh content from API
        error_log('SiteOverlay: No cache, fetching fresh from API');
        $fresh_content = $this->fetch_content_from_api();
        
        if ($fresh_content && is_array($fresh_content) && count($fresh_content) > 0) {
            error_log('SiteOverlay: API returned ' . count($fresh_content) . ' items');
            
            // Store using dynamic chunking
            $storage_success = $this->store_content_chunks($fresh_content);
            error_log('SiteOverlay: Dynamic chunking result: ' . ($storage_success ? 'SUCCESS' : 'FAILED'));
            
            error_log('=== SITEOVERLAY DEBUG END (FRESH) ===');
            return $fresh_content;
        }
        
        error_log('SiteOverlay: API failed, using default content');
        error_log('=== SITEOVERLAY DEBUG END (DEFAULT) ===');
        return $this->default_content;
    }
    
    /**
     * Store content using dynamic chunking based on WordPress limits
     */
    private function store_content_chunks($content) {
        error_log('=== STORE_CONTENT_CHUNKS START ===');
        error_log('Content input: ' . (is_array($content) ? count($content) . ' items' : 'NOT ARRAY'));
        
        $expiry_time = time() + $this->cache_duration;
        
        // Test to find optimal chunk size for this hosting environment
        $optimal_chunk_size = $this->find_optimal_chunk_size($content);
        error_log("Using chunk size of {$optimal_chunk_size} items");
        
        // Calculate chunking for flat associative array
        $total_items = count($content);
        $chunk_count = ceil($total_items / $optimal_chunk_size);
        error_log("Split into {$chunk_count} chunks from {$total_items} total items");
        
        // Clear any existing chunks first
        $this->clear_all_chunks();
        error_log('Cleared existing chunks');
        
        // Store each chunk
        $stored_chunks = 0;
        $content_keys = array_keys($content);
        
        for ($i = 0; $i < $chunk_count; $i++) {
            $chunk_key = "so_cache_{$i}";
            
            // Get the keys for this chunk
            $chunk_keys = array_slice($content_keys, $i * $optimal_chunk_size, $optimal_chunk_size);
            error_log("Processing chunk {$i} with " . count($chunk_keys) . " items: " . implode(', ', $chunk_keys));
            
            // Build chunk data from original content
            $chunk_data = array();
            foreach ($chunk_keys as $key) {
                $chunk_data[$key] = $content[$key];
                error_log("Added to chunk: {$key} = " . substr($content[$key], 0, 50) . "...");
            }
            
            error_log("Chunk {$i} final data: " . count($chunk_data) . " items - " . print_r(array_keys($chunk_data), true));
            
            $result = update_option($chunk_key, $chunk_data);
            error_log("update_option({$chunk_key}) returned: " . ($result ? 'TRUE' : 'FALSE'));
            
            // Immediate verification
            $verify = get_option($chunk_key, 'NOT_FOUND');
            error_log("Immediate get_option({$chunk_key}): " . ($verify !== 'NOT_FOUND' ? 'FOUND' : 'NOT_FOUND'));
            
            if ($result) {
                $stored_chunks++;
            } else {
                error_log('STORAGE FAILED AT: update_option failed for chunk ' . $i);
                error_log("CHUNK {$i} STORAGE FAILED - ABORTING");
                $this->clear_all_chunks();
                error_log('=== STORE_CONTENT_CHUNKS END (FAILED) ===');
                return false;
            }
        }
        
        // Store metadata
        $count_result = update_option('so_cache_count', $chunk_count);
        $total_result = update_option('so_cache_total_items', count($content));
        $expiry_result = update_option('so_cache_expiry', $expiry_time);
        
        error_log("Metadata storage - count: " . ($count_result ? 'SUCCESS' : 'FAILED') . 
                  ", total: " . ($total_result ? 'SUCCESS' : 'FAILED') . 
                  ", expiry: " . ($expiry_result ? 'SUCCESS' : 'FAILED'));
        
        error_log("Successfully stored {$stored_chunks}/{$chunk_count} chunks with " . count($content) . " total items");
        error_log('=== STORE_CONTENT_CHUNKS END (SUCCESS) ===');
        return true;
    }
    
    /**
     * Find optimal chunk size by testing progressively smaller chunks
     */
    private function find_optimal_chunk_size($content) {
        error_log('=== FIND_OPTIMAL_CHUNK_SIZE START ===');
        error_log('EMERGENCY DEBUG: Raw content structure: ' . print_r($content, true));
        
        if (empty($content)) {
            error_log('Content is empty, returning 1');
            return 1;
        }
        
        $total_items = count($content);
        error_log("Testing chunk sizes for {$total_items} total items");
        
        // Test chunk sizes from largest to smallest
        $test_sizes = array(
            min($total_items, 10),
            min($total_items, 7),
            min($total_items, 5),
            min($total_items, 3),
            1
        );
        
        error_log('Will test sizes: ' . implode(', ', $test_sizes));
        
        // Get content keys for testing
        $content_keys = array_keys($content);
        error_log('EMERGENCY DEBUG: Content keys: ' . print_r($content_keys, true));
        
        foreach ($test_sizes as $size) {
            error_log("=== TESTING CHUNK SIZE: {$size} ===");
            
            // Get test keys for this chunk size
            $test_keys = array_slice($content_keys, 0, $size);
            error_log("Test keys: " . implode(', ', $test_keys));
            
            // Build test data from original content (same as fixed main method)
            $test_data = array();
            foreach ($test_keys as $key) {
                if (isset($content[$key])) {
                    $test_data[$key] = $content[$key];
                    error_log("EMERGENCY DEBUG: Added test item: {$key} = " . var_export($content[$key], true));
                } else {
                    error_log("EMERGENCY DEBUG: WARNING - Key {$key} not found in content!");
                }
            }
            
            error_log("EMERGENCY DEBUG: Final test data structure: " . print_r($test_data, true));
            error_log("Test data for size {$size}: " . count($test_data) . " items");
            
            if (count($test_data) > 0) {
                // Test if this size works
                $test_key = 'so_cache_size_test';
                error_log("EMERGENCY DEBUG: About to call update_option with key: {$test_key}");
                error_log("EMERGENCY DEBUG: Data being stored: " . serialize($test_data));
                error_log("EMERGENCY DEBUG: Serialized size: " . strlen(serialize($test_data)) . " bytes");
                
                $result = update_option($test_key, $test_data);
                error_log("EMERGENCY DEBUG: update_option result: " . var_export($result, true));
                
                if ($result) {
                    $verify = get_option($test_key, 'NOT_FOUND');
                    error_log("EMERGENCY DEBUG: get_option verification: " . ($verify !== 'NOT_FOUND' ? 'FOUND (' . count($verify) . ' items)' : 'NOT_FOUND'));
                    
                    delete_option($test_key);
                    
                    if ($verify !== 'NOT_FOUND') {
                        error_log("✅ EMERGENCY DEBUG: Size {$size} WORKS! Returning this size.");
                        error_log('=== FIND_OPTIMAL_CHUNK_SIZE END (SUCCESS) ===');
                        return $size;
                    } else {
                        error_log("❌ EMERGENCY DEBUG: Size {$size} - update_option returned TRUE but get_option failed");
                    }
                } else {
                    error_log("❌ EMERGENCY DEBUG: Size {$size} - update_option returned FALSE");
                }
            } else {
                error_log("No test data created for size {$size} - skipping");
            }
        }
        
        // Fallback to single items
        error_log("❌ EMERGENCY DEBUG: All chunk sizes failed, using fallback: 1 item");
        error_log('=== FIND_OPTIMAL_CHUNK_SIZE END (FALLBACK) ===');
        return 1;
    }
    
    /**
     * Retrieve content from chunks
     */
    private function retrieve_content_chunks() {
        $cache_count = get_option('so_cache_count', 0);
        $total_items = get_option('so_cache_total_items', 0);
        $cache_expiry = get_option('so_cache_expiry', 0);
        
        // Check expiry
        if ($cache_expiry > 0 && time() > $cache_expiry) {
            error_log('SiteOverlay: Chunked cache expired');
            $this->clear_all_chunks();
            return false;
        }
        
        if ($cache_count == 0) {
            return false;
        }
        
        // Reconstruct content from chunks
        $content = array();
        for ($i = 0; $i < $cache_count; $i++) {
            $chunk = get_option("so_cache_{$i}", false);
            if ($chunk && is_array($chunk)) {
                $content = array_merge($content, $chunk);
            } else {
                error_log("SiteOverlay: Chunk {$i} missing, invalidating cache");
                $this->clear_all_chunks();
                return false;
            }
        }
        
        if (count($content) === $total_items) {
            error_log("SiteOverlay: Successfully retrieved {$total_items} items from {$cache_count} chunks");
            return $content;
        } else {
            error_log("SiteOverlay: Item count mismatch, invalidating cache");
            $this->clear_all_chunks();
            return false;
        }
    }
    
    /**
     * Clear all cache chunks
     */
    private function clear_all_chunks() {
        $cache_count = get_option('so_cache_count', 0);
        
        // Clear chunk data
        for ($i = 0; $i < max($cache_count, 20); $i++) { // Clear up to 20 chunks to be safe
            delete_option("so_cache_{$i}");
        }
        
        // Clear metadata
        delete_option('so_cache_count');
        delete_option('so_cache_total_items');
        delete_option('so_cache_expiry');
        delete_transient('so_cache'); // Clear old transient too
        
        error_log('SiteOverlay: All cache chunks cleared');
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
        // Use the centralized clear method
        $this->clear_all_chunks();
    }
    
    /**
     * Debug API connection
     */
    public function debug_api_connection() {
        $fresh_content = $this->fetch_content_from_api();
        // Check for chunk-based cache using the centralized method
        $cached_content = $this->retrieve_content_chunks();
        
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