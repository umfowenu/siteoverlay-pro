/**
 * SiteOverlay Pro - Enhanced Admin JavaScript
 * Handles core AJAX operations and enhanced license features
 * FOLLOWS CONSTITUTIONAL RULES - Core functionality preserved, enhancements added
 */

jQuery(document).ready(function($) {
    
    // CORE VARIABLES (SACRED - DO NOT MODIFY)
    var postId = siteoverlay_ajax.post_id;
    var ajaxUrl = siteoverlay_ajax.ajax_url;
    var nonce = siteoverlay_ajax.nonce;
    
    // CORE OVERLAY OPERATIONS (SACRED - NEVER MODIFY THESE)
    
    // Save overlay (CORE - FAST, NON-BLOCKING)
    $('#save-overlay, #edit-overlay').on('click', function(e) {
        e.preventDefault();
        saveOverlay();
    });
    
    // Remove overlay (CORE - IMMEDIATE)
    $('#remove-overlay').on('click', function(e) {
        e.preventDefault();
        
        if (confirm('Are you sure you want to remove this overlay?')) {
            removeOverlay();
        }
    });
    
    // Preview overlay (CORE)
    $('#preview-overlay').on('click', function(e) {
        e.preventDefault();
        previewOverlay();
    });
    
    // Enter key support for overlay URL (CORE)
    $('#siteoverlay-overlay-url').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            saveOverlay();
        }
    });
    
    // CORE AJAX FUNCTIONS (SACRED - PRESERVE EXACTLY)
    
    function saveOverlay() {
        var overlayUrl = $('#siteoverlay-overlay-url').val().trim();
        
        if (!overlayUrl) {
            alert('Please enter an overlay URL');
            return;
        }
        
        // Validate URL format
        if (!isValidUrl(overlayUrl)) {
            alert('Please enter a valid URL (e.g., https://example.com)');
            return;
        }
        
        // IMMEDIATE SAVE - NO BLOCKING (SACRED)
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'siteoverlay_save_overlay',
                nonce: nonce,
                post_id: postId,
                overlay_url: overlayUrl
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Overlay saved successfully!', 'success');
                    // Refresh the page to show updated interface
                    location.reload();
                } else {
                    showNotice('Error: ' + (response.data || 'Failed to save overlay'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotice('Connection error: ' + error, 'error');
                console.error('Save overlay error:', xhr.responseText);
            }
        });
    }
    
    function removeOverlay() {
        // IMMEDIATE REMOVAL (SACRED)
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'siteoverlay_remove_overlay',
                nonce: nonce,
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Overlay removed successfully!', 'success');
                    // Refresh to show clean interface
                    location.reload();
                } else {
                    showNotice('Error: ' + (response.data || 'Failed to remove overlay'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotice('Connection error: ' + error, 'error');
                console.error('Remove overlay error:', xhr.responseText);
            }
        });
    }
    
    function previewOverlay() {
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'siteoverlay_preview_overlay',
                nonce: nonce,
                post_id: postId
            },
            success: function(response) {
                if (response.success && response.data.url) {
                    // Open preview in new window
                    window.open(response.data.url, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
                } else {
                    showNotice('No overlay URL to preview', 'warning');
                }
            },
            error: function(xhr, status, error) {
                showNotice('Preview failed: ' + error, 'error');
                console.error('Preview error:', xhr.responseText);
            }
        });
    }
    
    // ENHANCEMENT FEATURES (SAFE TO MODIFY)
    
    // Newsletter signup (ENHANCEMENT)
    $('#subscribe-newsletter').on('click', function(e) {
        e.preventDefault();
        
        var email = $('#newsletter-email').val().trim();
        if (!email) {
            alert('Please enter your email address');
            return;
        }
        
        if (!isValidEmail(email)) {
            alert('Please enter a valid email address');
            return;
        }
        
        var $btn = $(this);
        var originalText = $btn.text();
        $btn.text('Subscribing...').prop('disabled', true);
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'siteoverlay_newsletter_signup',
                nonce: nonce,
                email: email
            },
            success: function(response) {
                if (response.success) {
                    showNotice('✅ ' + response.data, 'success');
                    $('#newsletter-email').val(''); // Clear email field
                } else {
                    showNotice('❌ ' + (response.data || 'Signup failed'), 'error');
                }
            },
            error: function(xhr, status, error) {
                showNotice('❌ Connection error: ' + error, 'error');
                console.error('Newsletter signup error:', xhr.responseText);
            },
            complete: function() {
                $btn.text(originalText).prop('disabled', false);
            }
        });
    });
    
    // Enter key support for newsletter email
    $('#newsletter-email').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            $('#subscribe-newsletter').click();
        }
    });
    
    // ENHANCED UI INTERACTIONS (ENHANCEMENT)
    
    // Auto-focus overlay URL field when editing
    $('#edit-overlay').on('click', function() {
        setTimeout(function() {
            $('#siteoverlay-overlay-url').focus();
        }, 100);
    });
    
    // Real-time URL validation feedback
    $('#siteoverlay-overlay-url').on('input', function() {
        var url = $(this).val().trim();
        var $field = $(this);
        
        // Remove previous validation classes
        $field.removeClass('url-valid url-invalid');
        
        if (url.length > 0) {
            if (isValidUrl(url)) {
                $field.addClass('url-valid');
                $field.css('border-color', '#28a745');
            } else {
                $field.addClass('url-invalid');
                $field.css('border-color', '#dc3545');
            }
        } else {
            $field.css('border-color', '');
        }
    });
    
    // Overlay URL suggestions (ENHANCEMENT)
    $('#siteoverlay-overlay-url').on('focus', function() {
        var $field = $(this);
        var currentValue = $field.val().trim();
        
        // If field is empty, show placeholder examples
        if (!currentValue) {
            showUrlSuggestions();
        }
    });
    
    function showUrlSuggestions() {
        // Create suggestions dropdown (only if doesn't exist)
        if ($('#url-suggestions').length === 0) {
            var suggestions = [
                'https://example.com',
                'https://yourlandingpage.com',
                'https://yourbusiness.com',
                'https://yourservice.com'
            ];
            
            var $dropdown = $('<div id="url-suggestions" style="position: absolute; background: white; border: 1px solid #ddd; border-radius: 4px; padding: 10px; margin-top: 5px; font-size: 11px; color: #666; z-index: 1000; box-shadow: 0 2px 5px rgba(0,0,0,0.1);"></div>');
            $dropdown.html('<div style="font-weight: bold; margin-bottom: 5px;">💡 Example URLs:</div>' + suggestions.map(url => '<div style="cursor: pointer; padding: 2px 0; hover: background: #f0f0f0;" data-url="' + url + '">' + url + '</div>').join(''));
            
            $('#siteoverlay-overlay-url').after($dropdown);
            
            // Handle suggestion clicks
            $dropdown.on('click', '[data-url]', function() {
                var selectedUrl = $(this).data('url');
                $('#siteoverlay-overlay-url').val(selectedUrl).focus();
                $dropdown.remove();
            });
            
            // Remove dropdown when clicking outside
            $(document).on('click.url-suggestions', function(e) {
                if (!$(e.target).closest('#siteoverlay-overlay-url, #url-suggestions').length) {
                    $('#url-suggestions').remove();
                    $(document).off('click.url-suggestions');
                }
            });
        }
    }
    
    // HELPER FUNCTIONS (UTILITY)
    
    function isValidUrl(string) {
        try {
            var url = new URL(string);
            return url.protocol === 'http:' || url.protocol === 'https:';
        } catch (_) {
            return false;
        }
    }
    
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function showNotice(message, type) {
        type = type || 'info';
        
        // Remove existing notices
        $('.siteoverlay-notice').remove();
        
        // Create notice element
        var noticeClass = 'notice notice-' + type;
        if (type === 'success') noticeClass += ' notice-success';
        if (type === 'error') noticeClass += ' notice-error';
        if (type === 'warning') noticeClass += ' notice-warning';
        
        var $notice = $('<div class="' + noticeClass + ' siteoverlay-notice is-dismissible" style="margin: 10px 0; padding: 10px; font-size: 12px;"><p>' + message + '</p></div>');
        
        // Insert notice
        $('#siteoverlay-overlay-container').prepend($notice);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Manual dismiss functionality
        $notice.on('click', '.notice-dismiss', function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        });
    }
    
    // ENHANCED ANALYTICS (ENHANCEMENT)
    
    // Track overlay interactions for analytics
    function trackInteraction(action, data) {
        // Only track if analytics is enabled
        if (typeof siteoverlay_ajax.analytics_enabled !== 'undefined' && siteoverlay_ajax.analytics_enabled) {
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'siteoverlay_track_interaction',
                    nonce: nonce,
                    interaction_type: action,
                    interaction_data: JSON.stringify(data || {}),
                    post_id: postId
                },
                success: function(response) {
                    // Silent tracking - no user feedback needed
                },
                error: function() {
                    // Silent fail - don't disrupt user experience
                }
            });
        }
    }
    
    // Track when overlay is saved
    $(document).ajaxSuccess(function(event, xhr, settings) {
        if (settings.data && settings.data.indexOf('action=siteoverlay_save_overlay') !== -1) {
            trackInteraction('overlay_saved', {
                url: $('#siteoverlay-overlay-url').val(),
                timestamp: new Date().toISOString()
            });
        }
    });
    
    // Track when overlay is removed
    $(document).ajaxSuccess(function(event, xhr, settings) {
        if (settings.data && settings.data.indexOf('action=siteoverlay_remove_overlay') !== -1) {
            trackInteraction('overlay_removed', {
                timestamp: new Date().toISOString()
            });
        }
    });
    
    // ACCESSIBILITY ENHANCEMENTS (ENHANCEMENT)
    
    // Keyboard navigation improvements
    $('#siteoverlay-overlay-container').on('keydown', function(e) {
        // Escape key to clear focus
        if (e.which === 27) {
            $(e.target).blur();
        }
        
        // Tab navigation enhancement
        if (e.which === 9) {
            var focusableElements = $('#siteoverlay-overlay-container').find('input, button, a').filter(':visible');
            var currentIndex = focusableElements.index(e.target);
            
            if (e.shiftKey) {
                // Shift+Tab - go backwards
                if (currentIndex === 0) {
                    e.preventDefault();
                    focusableElements.last().focus();
                }
            } else {
                // Tab - go forwards  
                if (currentIndex === focusableElements.length - 1) {
                    e.preventDefault();
                    focusableElements.first().focus();
                }
            }
        }
    });
    
    // PERFORMANCE OPTIMIZATIONS (ENHANCEMENT)
    
    // Debounced URL validation
    var urlValidationTimeout;
    $('#siteoverlay-overlay-url').on('input', function() {
        clearTimeout(urlValidationTimeout);
        var $field = $(this);
        
        urlValidationTimeout = setTimeout(function() {
            // Perform validation after user stops typing
            var url = $field.val().trim();
            if (url && isValidUrl(url)) {
                // Pre-validate URL accessibility (optional)
                checkUrlAccessibility(url);
            }
        }, 1000);
    });
    
    function checkUrlAccessibility(url) {
        // Optional: Check if URL is accessible
        // This is non-blocking and doesn't affect core functionality
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'siteoverlay_check_url',
                nonce: nonce,
                url: url
            },
            timeout: 5000,
            success: function(response) {
                if (response.success) {
                    // URL is accessible
                    $('#siteoverlay-overlay-url').attr('title', '✅ URL is accessible');
                } else {
                    // URL might not be accessible
                    $('#siteoverlay-overlay-url').attr('title', '⚠️ URL might not be accessible');
                }
            },
            error: function() {
                // Silent fail - don't affect user experience
            }
        });
    }
    
    // INITIALIZATION COMPLETE
    
    // Log successful initialization (for debugging)
    if (console && console.log) {
        console.log('SiteOverlay Pro Admin JS loaded successfully');
        console.log('Post ID:', postId);
        console.log('Core functionality preserved, enhancements active');
    }
    
    // Show initialization success (only in debug mode)
    if (typeof siteoverlay_ajax.debug_mode !== 'undefined' && siteoverlay_ajax.debug_mode) {
        showNotice('SiteOverlay Pro initialized successfully', 'success');
    }
    
    // ENHANCED ERROR HANDLING (ENHANCEMENT)
    
    // Global AJAX error handler for SiteOverlay requests
    $(document).ajaxError(function(event, xhr, settings, thrownError) {
        // Only handle our plugin's AJAX requests
        if (settings.data && settings.data.indexOf('siteoverlay_') !== -1) {
            console.error('SiteOverlay AJAX Error:', {
                url: settings.url,
                data: settings.data,
                status: xhr.status,
                statusText: xhr.statusText,
                responseText: xhr.responseText,
                error: thrownError
            });
            
            // Show user-friendly error message
            if (xhr.status === 0) {
                showNotice('❌ Connection lost. Please check your internet connection.', 'error');
            } else if (xhr.status >= 500) {
                showNotice('❌ Server error. Please try again or contact support.', 'error');
            } else if (xhr.status === 403) {
                showNotice('❌ Permission denied. Please refresh the page and try again.', 'error');
            }
        }
    });
    
    // Heartbeat API integration for connection monitoring
    if (typeof wp !== 'undefined' && wp.heartbeat) {
        // Monitor connection status
        $(document).on('heartbeat-tick', function(event, data) {
            if (data.siteoverlay_status) {
                // Connection is healthy
                $('.siteoverlay-connection-status').removeClass('offline').addClass('online');
            }
        });
        
        $(document).on('heartbeat-connection-lost', function() {
            showNotice('⚠️ Connection lost. Changes may not be saved.', 'warning');
            $('.siteoverlay-connection-status').removeClass('online').addClass('offline');
        });
        
        $(document).on('heartbeat-connection-restored', function() {
            showNotice('✅ Connection restored.', 'success');
            $('.siteoverlay-connection-status').removeClass('offline').addClass('online');
        });
    }
});

// EXTERNAL API INTEGRATION HELPERS (ENHANCEMENT)

// Function to test Railway API connection
function testRailwayApiConnection() {
    return fetch('https://siteoverlay-api-production.up.railway.app/api/health')
        .then(response => response.json())
        .then(data => {
            return data.status === 'ok';
        })
        .catch(error => {
            console.error('Railway API connection test failed:', error);
            return false;
        });
}

// Function to validate license with Railway API (for future use)
function validateLicenseWithRailway(licenseKey) {
    return fetch('https://siteoverlay-api-production.up.railway.app/api/validate-license', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            licenseKey: licenseKey,
            siteUrl: window.location.origin,
            action: 'check'
        })
    })
    .then(response => response.json())
    .catch(error => {
        console.error('License validation failed:', error);
        return { success: false, message: 'Connection error' };
    });
}

// Make functions available globally for other scripts
window.SiteOverlayPro = {
    testApiConnection: testRailwayApiConnection,
    validateLicense: validateLicenseWithRailway
};