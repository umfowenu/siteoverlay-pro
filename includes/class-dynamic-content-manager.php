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
        'training_url' => 'https://siteoverlay.24hr.pro/training',
        // Meta box dynamic content (used in both admin page and meta boxes)
        'metabox_boost_title' => '🚀 Boost Your SEO Rankings',
        'metabox_boost_subtitle' => 'Get Xagio - The #1 SEO Tool for Rank & Rent Success',
        'metabox_button_text' => 'Get Xagio Now',
        'metabox_disclaimer' => 'Affiliate Link - We earn a commission at no cost to you'
    );
    
    public function __construct() {
        // No hooks needed - this is a utility class
    }
    
    /**
     * API Key to Display Key Mapping
     * Maps Railway API keys to plugin display keys
     */
    private function get_key_mapping() {
        return array(
            // API Key => Display Key (now using metabox_* keys for consistency)
            'preview_title_text' => 'metabox_boost_title',
            'preview_button_text' => 'metabox_button_text',
            'preview_subtitle_text' => 'metabox_boost_subtitle',
            'metabox_title_text' => 'metabox_boost_title',
            'metabox_subtitle_text' => 'metabox_boost_subtitle',
            'metabox_button_text' => 'metabox_button_text',
            'metabox_disclaimer_text' => 'metabox_disclaimer',
            // Direct mappings (no translation needed)
            'xagio_affiliate_url' => 'xagio_affiliate_url',
            'support_url' => 'support_url',
            'training_url' => 'training_url',
            'upgrade_message' => 'upgrade_message'
        );
    }
    
    /**
     * Apply key mapping to convert API keys to display keys
     */
    private function apply_key_mapping($api_content) {
        if (!is_array($api_content)) {
            return $api_content;
        }
        
        $mapping = $this->get_key_mapping();
        $mapped_content = array();
        
        error_log('SiteOverlay: KEY MAPPING DEBUG - Original API keys: ' . implode(', ', array_keys($api_content)));
        
        foreach ($api_content as $api_key => $value) {
            if (isset($mapping[$api_key])) {
                $display_key = $mapping[$api_key];
                $mapped_content[$display_key] = $value;
                error_log("SiteOverlay: MAPPED {$api_key} -> {$display_key} = " . substr($value, 0, 50) . "...");
            } else {
                // Keep unmapped keys as-is
                $mapped_content[$api_key] = $value;
                error_log("SiteOverlay: UNMAPPED (kept as-is) {$api_key} = " . substr($value, 0, 50) . "...");
            }
        }
        
        error_log('SiteOverlay: KEY MAPPING DEBUG - Final display keys: ' . implode(', ', array_keys($mapped_content)));
        return $mapped_content;
    }

    /**
     * Get dynamic content with DEEP DEBUGGING
     */
    public function get_dynamic_content() {
        error_log('=== SITEOVERLAY DEBUG START ===');
        
        // Try to retrieve from chunks
        $cached_content = $this->retrieve_content_chunks();
        
        if ($cached_content && is_array($cached_content) && count($cached_content) > 0) {
            error_log('SiteOverlay: Retrieved chunked cache (' . count($cached_content) . ' items)');
            
            // Apply key mapping to cached content
            $mapped_content = $this->apply_key_mapping($cached_content);
            error_log('SiteOverlay: After key mapping (' . count($mapped_content) . ' items)');
            error_log('=== SITEOVERLAY DEBUG END (CACHED) ===');
            return $mapped_content;
        }
        
        // Fetch fresh content from API
        error_log('SiteOverlay: No cache, fetching fresh from API');
        $fresh_content = $this->fetch_content_from_api();
        
        if ($fresh_content && is_array($fresh_content) && count($fresh_content) > 0) {
            error_log('SiteOverlay: API returned ' . count($fresh_content) . ' items');
            
            // Store original API content using dynamic chunking (preserve API keys for caching)
            $storage_success = $this->store_content_chunks($fresh_content);
            if ($storage_success) {
                $stored_items = get_option('so_cache_stored_items', 0);
                error_log('SiteOverlay: Dynamic chunking SUCCESS - stored ' . $stored_items . '/' . count($fresh_content) . ' items');
            } else {
                error_log('SiteOverlay: Dynamic chunking FAILED - no items stored');
            }
            
            // Apply key mapping to fresh content for display
            $mapped_fresh_content = $this->apply_key_mapping($fresh_content);
            error_log('SiteOverlay: Applied key mapping to fresh content (' . count($mapped_fresh_content) . ' display keys)');
            
            error_log('=== SITEOVERLAY DEBUG END (FRESH) ===');
            return $mapped_fresh_content;
        }
        
        error_log('SiteOverlay: API failed, using default content');
        
        // 🚨 DEBUG: Log default content being returned
        error_log('DEFAULT CONTENT DEBUG: Original defaults: ' . print_r($this->default_content, true));
        
        // Apply key mapping to default content for consistency
        $mapped_default_content = $this->apply_key_mapping($this->default_content);
        // Merge with original defaults to ensure all display keys are available
        $final_default_content = array_merge($this->default_content, $mapped_default_content);
        
        error_log('SiteOverlay: Applied key mapping to default content (' . count($final_default_content) . ' total keys)');
        error_log('DEFAULT CONTENT DEBUG: Final default keys: ' . implode(', ', array_keys($final_default_content)));
        error_log('=== SITEOVERLAY DEBUG END (DEFAULT) ===');
        return $final_default_content;
    }
    
    /**
     * Store content using dynamic chunking based on WordPress limits
     * Now handles partial failures gracefully - continues processing even if some chunks fail
     */
    private function store_content_chunks($content) {
        error_log('=== STORE_CONTENT_CHUNKS START ===');
        error_log('Content input: ' . (is_array($content) ? count($content) . ' items' : 'NOT ARRAY'));
        
        // 🚨 DEBUG: Log exact content being stored to identify dummy data source
        if (is_array($content)) {
            error_log('STORAGE DEBUG: Input content keys: ' . implode(', ', array_keys($content)));
            foreach ($content as $key => $value) {
                error_log("STORAGE DEBUG: {$key} = " . substr($value, 0, 100) . "...");
            }
        }
        
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
        
        // Store each chunk - track successful and failed chunks
        $stored_chunks = 0;
        $successful_chunks = array();
        $failed_chunks = array();
        $content_keys = array_keys($content);
        $stored_items_count = 0;
        
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
            
            if ($result && $verify !== 'NOT_FOUND') {
                $stored_chunks++;
                $successful_chunks[] = $i;
                $stored_items_count += count($chunk_data);
                error_log("✅ CHUNK {$i} STORED SUCCESSFULLY ({$stored_items_count} items total so far)");
            } else {
                $failed_chunks[] = $i;
                error_log("❌ CHUNK {$i} STORAGE FAILED - CONTINUING WITH OTHER CHUNKS");
                // Continue processing instead of aborting
            }
        }
        
        error_log("Chunk storage summary: {$stored_chunks}/{$chunk_count} successful");
        error_log("Successful chunks: " . implode(', ', $successful_chunks));
        error_log("Failed chunks: " . implode(', ', $failed_chunks));
        
        // Only store metadata if we have at least one successful chunk
        if ($stored_chunks > 0) {
            // Store metadata about successful chunks
            $count_result = update_option('so_cache_count', $chunk_count);
            $total_result = update_option('so_cache_total_items', $total_items);
            $expiry_result = update_option('so_cache_expiry', $expiry_time);
            $successful_result = update_option('so_cache_successful_chunks', $successful_chunks);
            $stored_items_result = update_option('so_cache_stored_items', $stored_items_count);
            
            error_log("Metadata storage - count: " . ($count_result ? 'SUCCESS' : 'FAILED') . 
                      ", total: " . ($total_result ? 'SUCCESS' : 'FAILED') . 
                      ", expiry: " . ($expiry_result ? 'SUCCESS' : 'FAILED') .
                      ", successful_chunks: " . ($successful_result ? 'SUCCESS' : 'FAILED') .
                      ", stored_items: " . ($stored_items_result ? 'SUCCESS' : 'FAILED'));
            
            error_log("PARTIAL SUCCESS: Stored {$stored_chunks}/{$chunk_count} chunks with {$stored_items_count}/{$total_items} items");
            error_log('=== STORE_CONTENT_CHUNKS END (PARTIAL SUCCESS) ===');
            return true; // Return success for partial storage
        } else {
            error_log("COMPLETE FAILURE: No chunks could be stored");
            error_log('=== STORE_CONTENT_CHUNKS END (COMPLETE FAILURE) ===');
            return false;
        }
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
     * Now handles partial chunk storage gracefully
     */
    private function retrieve_content_chunks() {
        $cache_count = get_option('so_cache_count', 0);
        $total_items = get_option('so_cache_total_items', 0);
        $cache_expiry = get_option('so_cache_expiry', 0);
        $successful_chunks = get_option('so_cache_successful_chunks', array());
        $stored_items_count = get_option('so_cache_stored_items', 0);
        
        // Check expiry
        if ($cache_expiry > 0 && time() > $cache_expiry) {
            error_log('SiteOverlay: Chunked cache expired');
            $this->clear_all_chunks();
            return false;
        }
        
        if ($cache_count == 0 || empty($successful_chunks)) {
            error_log('SiteOverlay: No cache chunks available');
            return false;
        }
        
        error_log("SiteOverlay: Attempting to retrieve from chunks: " . implode(', ', $successful_chunks));
        
        // Reconstruct content from successful chunks only
        $content = array();
        $retrieved_chunks = 0;
        $missing_chunks = array();
        
        foreach ($successful_chunks as $chunk_index) {
            $chunk = get_option("so_cache_{$chunk_index}", false);
            if ($chunk && is_array($chunk)) {
                // 🚨 DEBUG: Log individual chunk content
                error_log("CHUNK {$chunk_index} DEBUG: Keys = " . implode(', ', array_keys($chunk)));
                foreach ($chunk as $key => $value) {
                    error_log("CHUNK {$chunk_index} DEBUG: {$key} = " . substr($value, 0, 50) . "...");
                }
                
                $content = array_merge($content, $chunk);
                $retrieved_chunks++;
                error_log("SiteOverlay: Successfully retrieved chunk {$chunk_index} with " . count($chunk) . " items");
            } else {
                $missing_chunks[] = $chunk_index;
                error_log("SiteOverlay: Chunk {$chunk_index} is missing (was supposed to be successful)");
            }
        }
        
        if ($retrieved_chunks > 0) {
            $actual_items = count($content);
            error_log("SiteOverlay: PARTIAL SUCCESS - Retrieved {$retrieved_chunks}/" . count($successful_chunks) . " chunks with {$actual_items} items");
            
            // 🚨 DEBUG: Log what's actually being retrieved from cache
            error_log('CACHE RETRIEVAL DEBUG: Retrieved content keys: ' . implode(', ', array_keys($content)));
            foreach ($content as $key => $value) {
                error_log("CACHE RETRIEVAL DEBUG: {$key} = " . substr($value, 0, 100) . "...");
            }
            
            if (!empty($missing_chunks)) {
                error_log("SiteOverlay: Missing chunks: " . implode(', ', $missing_chunks));
            }
            
            // Return partial content even if some chunks are missing
            // This allows the plugin to work with 9/14 items instead of 0/14
            return $content;
        } else {
            error_log("SiteOverlay: No chunks could be retrieved, invalidating cache");
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
        
        // Clear all metadata (including new partial storage metadata)
        delete_option('so_cache_count');
        delete_option('so_cache_total_items');
        delete_option('so_cache_expiry');
        delete_option('so_cache_successful_chunks');
        delete_option('so_cache_stored_items');
        delete_transient('so_cache'); // Clear old transient too
        
        error_log('SiteOverlay: All cache chunks and metadata cleared');
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
            // 🚨 DEBUG: Log raw API response to identify dummy data source
            error_log('SiteOverlay: RAW API RESPONSE: ' . print_r($data, true));
            
            // Convert Railway API format to plugin format
            $formatted_content = array();
            foreach ($data['content'] as $key => $item) {
                if (isset($item['value'])) {
                    $formatted_content[$key] = $item['value'];
                    error_log("SiteOverlay: API PARSING - {$key} = " . substr($item['value'], 0, 50) . "...");
                }
            }
            
            error_log('SiteOverlay: FORMATTED API CONTENT KEYS: ' . implode(', ', array_keys($formatted_content)));
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
            // 🚨 DEBUG: Log raw cURL API response to identify dummy data source
            error_log('SiteOverlay: RAW cURL API RESPONSE: ' . print_r($data, true));
            
            $formatted_content = array();
            foreach ($data['content'] as $key => $item) {
                if (isset($item['value'])) {
                    $formatted_content[$key] = $item['value'];
                    error_log("SiteOverlay: cURL API PARSING - {$key} = " . substr($item['value'], 0, 50) . "...");
                }
            }
            
            error_log('SiteOverlay: cURL FORMATTED API CONTENT KEYS: ' . implode(', ', array_keys($formatted_content)));
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
        
        // Get partial storage statistics
        $cache_stats = array(
            'total_chunks' => get_option('so_cache_count', 0),
            'successful_chunks' => get_option('so_cache_successful_chunks', array()),
            'stored_items' => get_option('so_cache_stored_items', 0),
            'total_items' => get_option('so_cache_total_items', 0)
        );
        
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
            'cache_stats' => $cache_stats,
            'cache_test' => $cache_test_result,
            'default_content' => $this->default_content
        );
    }
}