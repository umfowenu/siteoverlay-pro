# SiteOverlay Pro - WordPress Plugin

**Version:** 2.0.0  
**Author:** SiteOverlay Pro Team  
**License:** GPLv2 or later  
**Requires WordPress:** 5.0+  
**Tested up to:** 6.3  
**Requires PHP:** 7.2+

## ðŸŽ¯ Overview

SiteOverlay Pro is a professional WordPress plugin designed for rank & rent entrepreneurs to overlay any website over their ranked pages for monetization. This plugin provides a clean, fast, and reliable solution with advanced licensing, analytics, and customer intelligence features.

## âœ¨ Key Features

### ðŸš€ Core Functionality (Sacred - Never Modified)
- **Instant Overlay Loading**: Overlays load immediately without any blocking dependencies
- **Clean Admin Interface**: Intuitive meta box with clear active/inactive states
- **Fast AJAX Operations**: Save and remove overlays without page reloads
- **Non-blocking Architecture**: Core functionality never waits for license validation

### ðŸ’¼ Professional Licensing System
- **Two-Tier Pricing**: Professional ($35/month, 5 sites) and Unlimited ($297 lifetime)
- **14-Day Free Trial**: Extended trial period with email notifications
- **Railway API Integration**: Cloud-based license validation and management
- **Background Validation**: License checks never block core functionality

### ðŸ“Š Advanced Analytics & Intelligence
- **Usage Tracking**: Monitor overlay creation, views, and removals
- **Customer Intelligence**: Site data collection and analysis
- **Performance Metrics**: Track plugin usage and user engagement
- **Admin Dashboard**: Comprehensive analytics interface

### ðŸ“§ Email Collection System
- **Lead Capture**: Built-in email collection with multiple sources
- **Autoresponder Integration**: MailChimp, ConvertKit, and Sendster support
- **Email Management**: Admin interface for managing collected emails
- **Export Functionality**: CSV export of email lists

### ðŸ”— Xagio Integration
- **Affiliate Promotion**: Strategic Xagio SEO tool promotion
- **Click Tracking**: Monitor affiliate link performance
- **Dynamic Content**: Real-time updates from Railway API
- **Revenue Generation**: Additional income stream for plugin users

## ðŸ—ï¸ Architecture

### Sacred Core Files (Never Modify)
```
siteoverlay-pro.php           # Main plugin file with non-blocking initialization
includes/overlay-display.php  # Pure overlay rendering (not created yet)
assets/css/admin.css         # Clean interface styling
assets/js/admin.js           # AJAX functionality without license blocking
```

### Enhancement Modules (Modify Freely)
```
includes/class-license-manager.php    # Background license validation
includes/class-email-capture.php     # Email collection system
includes/class-affiliate-manager.php  # Dynamic promotional content
includes/class-analytics-tracker.php  # Usage statistics
```

## ðŸš€ Installation

### Method 1: WordPress Admin Upload
1. Download the plugin ZIP file
2. Go to **Plugins > Add New > Upload Plugin**
3. Select the ZIP file and click **Install Now**
4. Click **Activate Plugin**

### Method 2: Manual Installation
1. Extract the ZIP file
2. Upload the `siteoverlay-pro` folder to `/wp-content/plugins/`
3. Activate the plugin through the WordPress admin

### Method 3: WP-CLI
```bash
wp plugin install siteoverlay-pro.zip --activate
```

## âš™ï¸ Configuration

### 1. License Activation
- Go to **Settings > SiteOverlay Pro**
- Enter your license key or start a 14-day free trial
- The plugin works immediately - license validation runs in background

### 2. Basic Usage
1. Edit any post or page
2. Find the **SiteOverlay Pro** meta box in the sidebar
3. Enter the URL you want to overlay
4. Click **Save Overlay**
5. View your post/page to see the overlay in action

### 3. Advanced Settings
- **Email Collection**: Configure autoresponder integration
- **Analytics**: View usage statistics and performance metrics
- **Affiliate Settings**: Manage Xagio promotion settings

## ðŸ”§ Technical Requirements

### Server Requirements
- **WordPress**: 5.0 or higher
- **PHP**: 7.2 or higher
- **MySQL**: 5.6 or higher
- **SSL Certificate**: Recommended for secure API communication

### Recommended Hosting
- **Memory Limit**: 256MB or higher
- **Execution Time**: 60 seconds or higher
- **cURL**: Required for API communication
- **JSON**: Required for data processing

## ðŸŽ¨ Customization

### CSS Customization
The plugin includes clean, professional styling that can be customized:

```css
/* Customize meta box appearance */
#siteoverlay-container {
    /* Your custom styles */
}

/* Customize overlay display */
#siteoverlay-iframe {
    /* Your custom styles */
}
```

### Hook Integration
```php
// Add custom content after overlay stats
add_action('siteoverlay_meta_box_after_stats', 'my_custom_content');

// Track custom events
do_action('siteoverlay_track_event', 'custom_event', $data);
```

## ðŸ”Œ API Integration

### Railway License Server
The plugin integrates with a Railway-hosted license server for:
- License validation and activation
- Customer intelligence collection
- Analytics and usage tracking
- Dynamic content updates

### Stripe Webhook Integration
- Automatic license generation on purchase
- Payment processing and validation
- Customer data synchronization

## ðŸ“Š Analytics & Reporting

### Available Metrics
- **Overlay Creation**: Track when overlays are created
- **Overlay Views**: Monitor overlay performance
- **User Engagement**: Analyze user behavior patterns
- **License Usage**: Monitor license utilization

### Admin Dashboard
Access comprehensive analytics at **Settings > Analytics**:
- Overview statistics
- Top performing posts
- Recent activity logs
- User engagement metrics

## ðŸ›¡ï¸ Security Features

### Data Protection
- **Nonce Verification**: All AJAX requests are secured
- **Capability Checks**: Proper user permission validation
- **SQL Injection Protection**: Parameterized database queries
- **XSS Prevention**: All output is properly escaped

### License Security
- **Secure API Communication**: HTTPS-only API calls
- **License Key Encryption**: Secure storage of license data
- **Site Validation**: Prevent unauthorized usage

## ðŸ”„ Updates & Maintenance

### Automatic Updates
- Plugin updates are delivered through WordPress admin
- License validation ensures update eligibility
- Backup recommendations before major updates

### Database Maintenance
The plugin automatically creates and maintains database tables:
- `wp_siteoverlay_emails` - Email collection storage
- `wp_siteoverlay_analytics` - Usage analytics data

## ðŸ†˜ Troubleshooting

### Common Issues

#### Overlay Not Loading
1. Check if overlay URL is valid and accessible
2. Verify no JavaScript errors in browser console
3. Ensure target site allows iframe embedding

#### License Validation Issues
1. Check internet connection
2. Verify license key is correct
3. Ensure Railway API server is accessible

#### AJAX Errors
1. Check WordPress admin-ajax.php is accessible
2. Verify nonce validation is working
3. Check for plugin conflicts

### Debug Mode
Enable WordPress debug mode for detailed error logging:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## ðŸ¤ Support

### Documentation
- Plugin documentation: [https://siteoverlaypro.com/docs](https://siteoverlaypro.com/docs)
- Video tutorials: [https://siteoverlaypro.com/tutorials](https://siteoverlaypro.com/tutorials)
- FAQ: [https://siteoverlaypro.com/faq](https://siteoverlaypro.com/faq)

### Support Channels
- **Email Support**: support@siteoverlaypro.com
- **Priority Support**: Available for licensed users
- **Community Forum**: [https://siteoverlaypro.com/community](https://siteoverlaypro.com/community)

## ðŸ“„ License

This plugin is licensed under the GPLv2 or later license.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
```

## ðŸš€ Changelog

### Version 2.0.0
- **Complete architecture rebuild** following sacred core principles
- **Non-blocking initialization** - overlay loads immediately
- **Railway API integration** for cloud-based license management
- **Advanced analytics system** with customer intelligence
- **Professional email collection** with autoresponder integration
- **Xagio affiliate integration** for additional revenue streams
- **Enhanced security features** and performance optimizations
- **Clean, professional UI** with improved user experience

### Version 1.0.0
- Initial release as eBiz Rank & Rent Plugin
- Basic overlay functionality
- Simple license system

## ðŸ”® Roadmap

### Upcoming Features
- **Advanced Overlay Options**: Custom CSS, animations, and effects
- **Multi-language Support**: Internationalization for global users
- **White-label Options**: Custom branding for agencies
- **Advanced Analytics**: Conversion tracking and ROI analysis
- **Integration Expansions**: Additional affiliate partnerships

### Performance Improvements
- **Caching System**: Reduce API calls and improve speed
- **CDN Integration**: Faster asset delivery
- **Database Optimization**: Enhanced query performance

---

**SiteOverlay Pro** - Professional rank & rent solutions for WordPress

For more information, visit [https://siteoverlaypro.com](https://siteoverlaypro.com)
