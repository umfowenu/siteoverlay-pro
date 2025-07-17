# Complete Fixes Package

## Issue Summary
1. ✅ **API Endpoint**: Fixed `/start-trial` → `/request-trial`
2. ❌ **WordPress Plugin**: May need cache clearing or additional fixes
3. ❌ **Test Data Cleanup**: Need to remove test licenses from PostgreSQL
4. ❌ **AWeber Reputation**: Need to avoid fake email domains in testing

## Fix 1: WordPress Plugin Update

**File**: `includes/class-license-manager.php`
**Action**: Replace the entire `ajax_request_trial()` function

```php
/**
 * FIXED: Email-based trial request - DO NOT RETURN LICENSE KEY
 */
public function ajax_request_trial() {
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['nonce'] ?? '', 'siteoverlay_license_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $full_name = sanitize_text_field($_POST['full_name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $website = esc_url_raw($_POST['website'] ?? '');
    
    if (empty($full_name)) {
        wp_send_json_error('Please enter your full name');
        return;
    }
    
    if (empty($email) || !is_email($email)) {
        wp_send_json_error('Please enter a valid email address');
        return;
    }
    
    // Clear any caches
    wp_cache_flush();
    
    // Send trial request to Railway API WITH NAME
    $trial_data = array(
        'full_name' => $full_name,
        'email' => $email,
        'website' => $website,
        'siteUrl' => get_site_url(),
        'siteTitle' => get_bloginfo('name'),
        'wpVersion' => get_bloginfo('version'),
        'pluginVersion' => SITEOVERLAY_RR_VERSION,
        'requestSource' => 'plugin_admin'
    );
    
    // Log the request for debugging
    error_log('SiteOverlay Trial Request: ' . json_encode($trial_data));
    
    $response = wp_remote_post($this->api_base_url . '/request-trial', array(
        'timeout' => $this->api_timeout,
        'headers' => array('Content-Type' => 'application/json'),
        'body' => json_encode($trial_data),
        'blocking' => true,
        'sslverify' => true
    ));
    
    if (is_wp_error($response)) {
        error_log('SiteOverlay Trial Error: ' . $response->get_error_message());
        wp_send_json_error('Connection error: ' . $response->get_error_message());
        return;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    // Log the response for debugging
    error_log('SiteOverlay Trial Response Code: ' . $response_code);
    error_log('SiteOverlay Trial Response Body: ' . $body);
    
    $data = json_decode($body, true);
    
    if ($response_code === 200 && $data && isset($data['success'])) {
        if ($data['success']) {
            // FIXED: Do NOT return license key - force email verification
            wp_send_json_success(array(
                'message' => 'Trial license has been sent to your email address. Please check your inbox and enter the license key below to activate your 14-day trial.',
                'email' => $email,
                'full_name' => $full_name,
                'debug_info' => array(
                    'pabbly_status' => $data['data']['pabbly_status'] ?? 'unknown',
                    'api_response' => $data['success'] ? 'success' : 'failed'
                )
                // REMOVED: 'license_key' => $data['data']['license_key'] ?? null
            ));
        } else {
            wp_send_json_error($data['message']);
        }
    } else {
        wp_send_json_error('Failed to process trial request. Response code: ' . $response_code);
    }
}
```

## Fix 2: Database Cleanup Script

**Action**: Run this SQL to clean up test licenses

```sql
-- Clean up test licenses (BE CAREFUL!)
DELETE FROM email_collection WHERE 
  email LIKE '%@example.com' 
  OR email LIKE '%test%'
  OR license_key LIKE 'TRIAL-TEST-%'
  OR license_key LIKE 'TRIAL-1234-%'
  OR license_key LIKE 'TRIAL-DEBUG-%'
  OR license_key LIKE 'TRIAL-ALT-%'
  OR license_key LIKE 'TRIAL-REAL-%';

DELETE FROM site_usage WHERE 
  license_key LIKE 'TRIAL-TEST-%'
  OR license_key LIKE 'TRIAL-1234-%'
  OR license_key LIKE 'TRIAL-DEBUG-%'
  OR license_key LIKE 'TRIAL-ALT-%'
  OR license_key LIKE 'TRIAL-REAL-%';

DELETE FROM licenses WHERE 
  customer_email LIKE '%@example.com' 
  OR customer_email LIKE '%test%'
  OR license_key LIKE 'TRIAL-TEST-%'
  OR license_key LIKE 'TRIAL-1234-%'
  OR license_key LIKE 'TRIAL-DEBUG-%'
  OR license_key LIKE 'TRIAL-ALT-%'
  OR license_key LIKE 'TRIAL-REAL-%';

-- Check what's left
SELECT license_key, customer_email, customer_name, created_at 
FROM licenses 
WHERE license_type = 'trial' 
ORDER BY created_at DESC 
LIMIT 10;
```

## Fix 3: AWeber Cleanup

**Action**: Remove test subscribers from AWeber

1. **Go to AWeber dashboard**
2. **Go to Subscribers → All Subscribers**
3. **Search and remove these test emails:**
   - `test@example.com`
   - `testuser@gmail.com`
   - `realtest@gmail.com`
   - `plugin.test@gmail.com`
   - `debug.real@gmail.com`
   - `marius.test@gmail.com`
   - Any other test emails

4. **Also check "Suppressed" list** and remove test emails there too

## Fix 4: Testing Protocol (No More Fake Emails!)

**For Future Testing**: Only use REAL email addresses that you control:

✅ **Good for testing:**
- `your-email+test1@gmail.com`
- `your-email+test2@gmail.com`
- `marius+siteoverlay@shaw.ca`

❌ **Bad for testing (hurts AWeber reputation):**
- `test@example.com`
- `fake@fake.com`
- `nonexistent@domain.com`

## Fix 5: WordPress Plugin Cache Clear

**After updating the plugin code:**

```php
// Add this to wp-config.php temporarily
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Then check /wp-content/debug.log for the trial request logs
```

## Verification Steps

1. **Update WordPress plugin** with the new `ajax_request_trial()` function
2. **Clear all caches** (WordPress, browser, CDN if any)
3. **Clean up PostgreSQL** with the SQL script
4. **Clean up AWeber** by removing test subscribers
5. **Test with a real email** you control (use `+test` variations)
6. **Check WordPress debug.log** for the request/response logs
7. **Check Pabbly execution logs** for any errors
8. **Check AWeber** for new subscriber

## Expected Result

After all fixes:
- ✅ WordPress plugin calls correct endpoint
- ✅ License created in PostgreSQL with name/email
- ✅ Pabbly webhook triggered successfully
- ✅ AWeber receives subscriber
- ✅ User receives trial license email
- ✅ No test data cluttering the system

This should resolve all the issues you mentioned!