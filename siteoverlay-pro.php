<?php
/**
 * Plugin Name: SiteOverlay Pro
 * Plugin URI: https://siteoverlaypro.com
 * Description: Overlay any website over your ranked pages for monetization
 * Version: 2.0.0
 * Author: SiteOverlay Pro Team
 * Author URI: https://siteoverlaypro.com
 * License: GPLv2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: siteoverlay-pro
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('SITEOVERLAY_RR_VERSION', '2.0.0');
define('SITEOVERLAY_RR_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('SITEOVERLAY_RR_PLUGIN_URL', plugin_dir_url(__FILE__));

class SiteOverlay_Rank_Rent {
    
    private $license_manager;
    
    public function __construct() {
        // Initialize after WordPress loads
        add_action('init', array($this, 'init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        
        // Initialize license manager (ENHANCEMENT MODULE)
        $this->init_license_manager();
    }
    
    /**
     * Initialize license manager enhancement
     */
    private function init_license_manager() {
        $license_file = SITEOVERLAY_RR_PLUGIN_PATH . 'includes/class-license-manager.php';
        if (file_exists($license_file)) {
            require_once $license_file;
            $this->license_manager = new SiteOverlay_License_Manager();
        }
    }
    
    public function init() {
        // CORE FUNCTIONALITY LOADS IMMEDIATELY - NO BLOCKING (SACRED)
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_overlay_data'));
        add_action('wp_head', array($this, 'display_overlay'));
        
        // CORE AJAX handlers - immediate, no license blocking (SACRED)
        add_action('wp_ajax_siteoverlay_save_overlay', array($this, 'ajax_save_overlay'));
        add_action('wp_ajax_siteoverlay_remove_overlay', array($this, 'ajax_remove_overlay'));
        add_action('wp_ajax_siteoverlay_preview_overlay', array($this, 'ajax_preview_overlay'));
    }
    
    public function add_meta_box() {
        // CORE META BOX - NEVER MODIFY LAYOUT OR APPEARANCE (SACRED)
        $screens = array('post', 'page');
        foreach ($screens as $screen) {
            add_meta_box(
                'siteoverlay-rank-rent-meta',
                'SiteOverlay Pro - Overlay Settings',
                array($this, 'meta_box_callback'),
                $screen,
                'side',
                'high'
            );
        }
    }

    public function meta_box_callback($post) {
        // CORE META BOX LAYOUT - PRESERVE EXACTLY AS IS (SACRED)
        wp_nonce_field('siteoverlay_overlay_nonce', 'siteoverlay_overlay_nonce');
        $current_url = get_post_meta($post->ID, '_siteoverlay_overlay_url', true);
        
        // Get license status for display (non-blocking)
        $license_status = $this->get_license_status();
        ?>
        <div id="siteoverlay-overlay-container">
            <!-- Logo Section (CORE) -->
            <div style="text-align: center; padding: 10px 0; background: white;">
                <img src="https://page1.genspark.site/v1/base64_upload/fe1edd2c48ac954784b3e58ed66b0764" alt="SiteOverlay Pro" style="max-width: 100%; height: auto;" />
            </div>
            
            <!-- License Status Display (ENHANCEMENT - non-blocking) -->
            <?php if ($license_status['active']): ?>
                <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 8px; text-align: center; font-size: 12px; margin-bottom: 10px;">
                    ✓ <strong>License Active</strong><br>
                    Type: <?php echo esc_html($license_status['type']); ?><br>
                    👁 <?php echo get_post_meta($post->ID, '_siteoverlay_overlay_views', true) ?: '0'; ?> views
                </div>
            <?php else: ?>
                <div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 8px; text-align: center; color: #721c24; font-size: 12px; margin-bottom: 10px;">
                    ✗ <strong>No Valid License</strong><br>
                    <a href="<?php echo admin_url('options-general.php?page=siteoverlay-license'); ?>" style="color: #721c24; text-decoration: underline;">Get License Key</a>
                </div>
            <?php endif; ?>
            
            <!-- CORE OVERLAY CONTROLS (SACRED - NEVER MODIFY) -->
            <div class="siteoverlay-overlay-controls">
                <?php if ($current_url): ?>
                    <div class="siteoverlay-current-overlay" style="margin-bottom: 15px;">
                        <strong>Current Overlay:</strong><br>
                        <span style="font-size: 12px; color: #666; word-break: break-all;"><?php echo esc_html($current_url); ?></span>
                    </div>
                    
                    <div class="siteoverlay-overlay-actions" style="margin-bottom: 15px;">
                        <button type="button" class="button button-small" onclick="editOverlay()" style="margin-right: 5px;">✏️ Edit</button>
                        <button type="button" class="button button-small" onclick="previewOverlay()" style="margin-right: 5px;">👁 Preview</button>
                        <button type="button" class="button button-small" onclick="removeOverlay()" style="color: #dc3545;">🗑 Remove</button>
                    </div>
                <?php else: ?>
                    <p style="color: #666; margin-bottom: 15px;">No overlay set for this page.</p>
                <?php endif; ?>
                
                <div id="siteoverlay-url-input" style="<?php echo $current_url ? 'display: none;' : ''; ?>">
                    <label for="overlay_url"><strong>Overlay URL:</strong></label>
                    <input type="url" 
                           id="overlay_url" 
                           name="overlay_url" 
                           value="<?php echo esc_attr($current_url); ?>" 
                           placeholder="https://example.com" 
                           style="width: 100%; margin: 8px 0;" />
                    
                    <div style="margin-top: 10px;">
                        <button type="button" class="button button-primary" onclick="saveOverlay()" style="margin-right: 5px;">💾 Save</button>
                        <button type="button" class="button" onclick="cancelEdit()">❌ Cancel</button>
                    </div>
                </div>
            </div>
            
            <div id="siteoverlay-status" style="margin-top: 10px; padding: 8px; border-radius: 4px; display: none;"></div>
        </div>
        
        <script>
        // CORE JAVASCRIPT FUNCTIONS (SACRED - NEVER MODIFY)
        function editOverlay() {
            document.getElementById('siteoverlay-url-input').style.display = 'block';
            document.querySelector('.siteoverlay-overlay-actions').style.display = 'none';
        }
        
        function cancelEdit() {
            document.getElementById('siteoverlay-url-input').style.display = 'none';
            if (document.querySelector('.siteoverlay-current-overlay')) {
                document.querySelector('.siteoverlay-overlay-actions').style.display = 'block';
            }
        }
        
        function saveOverlay() {
            const url = document.getElementById('overlay_url').value.trim();
            if (!url) {
                showStatus('Please enter a valid URL', 'error');
                return;
            }
            
            showStatus('Saving overlay...', 'info');
            
            // CORE AJAX CALL (SACRED - IMMEDIATE, NON-BLOCKING)
            jQuery.post(ajaxurl, {
                action: 'siteoverlay_save_overlay',
                post_id: <?php echo $post->ID; ?>,
                overlay_url: url,
                nonce: '<?php echo wp_create_nonce('siteoverlay_overlay_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    showStatus('Overlay saved successfully!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showStatus(response.data || 'Failed to save overlay', 'error');
                }
            }).fail(function() {
                showStatus('Connection error. Please try again.', 'error');
            });
        }
        
        function removeOverlay() {
            if (!confirm('Remove overlay from this page?')) return;
            
            showStatus('Removing overlay...', 'info');
            
            jQuery.post(ajaxurl, {
                action: 'siteoverlay_remove_overlay',
                post_id: <?php echo $post->ID; ?>,
                nonce: '<?php echo wp_create_nonce('siteoverlay_overlay_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    showStatus('Overlay removed successfully!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showStatus(response.data || 'Failed to remove overlay', 'error');
                }
            });
        }
        
        function previewOverlay() {
            const currentUrl = '<?php echo esc_js($current_url); ?>';
            if (currentUrl) {
                window.open(currentUrl, '_blank');
            }
        }
        
        function showStatus(message, type) {
            const statusDiv = document.getElementById('siteoverlay-status');
            const colors = {
                success: { bg: '#d4edda', text: '#155724', border: '#c3e6cb' },
                error: { bg: '#f8d7da', text: '#721c24', border: '#f5c6cb' },
                info: { bg: '#d1ecf1', text: '#0c5460', border: '#bee5eb' }
            };
            
            const color = colors[type] || colors.info;
            statusDiv.style.backgroundColor = color.bg;
            statusDiv.style.color = color.text;
            statusDiv.style.border = '1px solid ' + color.border;
            statusDiv.innerHTML = message;
            statusDiv.style.display = 'block';
        }
        </script>
        <?php
    }
    
    // CORE AJAX HANDLERS - FAST, NON-BLOCKING (SACRED - NEVER MODIFY)
    public function ajax_save_overlay() {
        check_ajax_referer('siteoverlay_overlay_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        $overlay_url = esc_url_raw($_POST['overlay_url']);
        
        if (!$overlay_url) {
            wp_send_json_error('Invalid URL');
            return;
        }
        
        // CORE FUNCTIONALITY: Save immediately without license blocking
        update_post_meta($post_id, '_siteoverlay_overlay_url', $overlay_url);
        
        // Track usage (non-blocking)
        $this->track_overlay_usage($post_id, 'save');
        
        wp_send_json_success('Overlay saved successfully');
    }
    
    public function ajax_remove_overlay() {
        check_ajax_referer('siteoverlay_overlay_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        
        // CORE FUNCTIONALITY: Remove immediately without license blocking
        delete_post_meta($post_id, '_siteoverlay_overlay_url');
        
        // Track usage (non-blocking)
        $this->track_overlay_usage($post_id, 'remove');
        
        wp_send_json_success('Overlay removed successfully');
    }
    
    public function ajax_preview_overlay() {
        check_ajax_referer('siteoverlay_overlay_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        $overlay_url = get_post_meta($post_id, '_siteoverlay_overlay_url', true);
        
        if ($overlay_url) {
            wp_send_json_success(array('url' => $overlay_url));
        } else {
            wp_send_json_error('No overlay URL found');
        }
    }
    
    // CORE OVERLAY DISPLAY - IMMEDIATE LOADING (SACRED - NEVER MODIFY)
    public function display_overlay() {
        if (is_admin()) return;
        
        global $post;
        if (!$post) return;
        
        $overlay_url = get_post_meta($post->ID, '_siteoverlay_overlay_url', true);
        if (!$overlay_url) return;
        
        // CORE OVERLAY DISPLAY: Immediate, no license dependency
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Create overlay iframe
            const overlay = document.createElement('iframe');
            overlay.src = '<?php echo esc_js($overlay_url); ?>';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                border: none;
                z-index: 999999;
                background: white;
            `;
            
            document.body.appendChild(overlay);
            
            // Track view (non-blocking)
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=siteoverlay_track_view&post_id=<?php echo $post->ID; ?>&nonce=<?php echo wp_create_nonce('siteoverlay_track'); ?>'
            }).catch(() => {}); // Silent fail - don't block overlay
        });
        </script>
        <?php
    }
    
    /**
     * Track overlay usage (non-blocking)
     */
    private function track_overlay_usage($post_id, $action) {
        $views = get_post_meta($post_id, '_siteoverlay_overlay_views', true) ?: 0;
        if ($action === 'view') {
            update_post_meta($post_id, '_siteoverlay_overlay_views', $views + 1);
        }
    }
    
    /**
     * Get license status (non-blocking)
     */
    private function get_license_status() {
        if ($this->license_manager && $this->license_manager->has_valid_license()) {
            $license_data = $this->license_manager->get_license_data();
            return array(
                'active' => true,
                'type' => ucfirst(str_replace('_', ' ', $license_data['license_type'] ?? 'Unknown'))
            );
        }
        
        return array('active' => false, 'type' => 'None');
    }
    
    /**
     * Save overlay data (post save hook)
     */
    public function save_overlay_data($post_id) {
        if (!isset($_POST['siteoverlay_overlay_nonce']) || 
            !wp_verify_nonce($_POST['siteoverlay_overlay_nonce'], 'siteoverlay_overlay_nonce')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;
        
        if (isset($_POST['overlay_url'])) {
            $overlay_url = esc_url_raw($_POST['overlay_url']);
            if ($overlay_url) {
                update_post_meta($post_id, '_siteoverlay_overlay_url', $overlay_url);
            } else {
                delete_post_meta($post_id, '_siteoverlay_overlay_url');
            }
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function admin_scripts($hook) {
        if ($hook === 'post.php' || $hook === 'post-new.php') {
            wp_enqueue_script('jquery');
        }
    }
    
    /**
     * Track overlay views
     */
    public function __construct_ajax_handlers() {
        add_action('wp_ajax_siteoverlay_track_view', array($this, 'ajax_track_view'));
        add_action('wp_ajax_nopriv_siteoverlay_track_view', array($this, 'ajax_track_view'));
    }
    
    public function ajax_track_view() {
        if (!wp_verify_nonce($_POST['nonce'], 'siteoverlay_track')) return;
        
        $post_id = intval($_POST['post_id']);
        $this->track_overlay_usage($post_id, 'view');
        
        wp_die();
    }
}

// Initialize the plugin
new SiteOverlay_Rank_Rent();

// Initialize AJAX handlers
add_action('wp_ajax_siteoverlay_track_view', function() {
    if (!wp_verify_nonce($_POST['nonce'], 'siteoverlay_track')) return;
    $post_id = intval($_POST['post_id']);
    $views = get_post_meta($post_id, '_siteoverlay_overlay_views', true) ?: 0;
    update_post_meta($post_id, '_siteoverlay_overlay_views', $views + 1);
    wp_die();
});

add_action('wp_ajax_nopriv_siteoverlay_track_view', function() {
    if (!wp_verify_nonce($_POST['nonce'], 'siteoverlay_track')) return;
    $post_id = intval($_POST['post_id']);
    $views = get_post_meta($post_id, '_siteoverlay_overlay_views', true) ?: 0;
    update_post_meta($post_id, '_siteoverlay_overlay_views', $views + 1);
    wp_die();
});