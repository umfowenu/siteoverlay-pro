<?php
// Optional overlay enhancement - scrollbar management
class SiteOverlay_Overlay_Enhancement {
    
    public function __construct() {
        add_action('wp_head', array($this, 'add_overlay_enhancements'), 5);
    }
    
    public function add_overlay_enhancements() {
        // Only add if overlay exists
        if (is_admin() || !is_singular()) return;
        
        global $post;
        if (!$post) return;
        
        $overlay_url = get_post_meta($post->ID, '_siteoverlay_overlay_url', true);
        if (!$overlay_url) return;
        
        // Add enhancement CSS/JS for professional overlay
        ?>
        <style>
        body.siteoverlay-active {
            overflow: hidden !important;
        }
        </style>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Wait for overlay to load, then hide parent scrollbars
            setTimeout(function() {
                if (document.getElementById('siteoverlay-overlay-frame')) {
                    document.body.classList.add('siteoverlay-active');
                }
            }, 100);
        });
        </script>
        <?php
    }
}

// Initialize enhancement
new SiteOverlay_Overlay_Enhancement();
?> 