# Pabbly → AWeber Integration Debug Guide

## Current Status
- ✅ Railway API is working
- ✅ Licenses are created in PostgreSQL
- ✅ Pabbly webhook receives data (returns "Response Accepted")
- ❌ **No subscriber added to AWeber**
- ❌ **Pabbly dashboard shows no webhook response**

## Issue Analysis

The problem is likely in the **Pabbly workflow configuration** or **AWeber integration setup** within Pabbly.

## Data Being Sent to Pabbly

Your Railway API sends this exact data structure to Pabbly:

```json
{
  "email": "user@example.com",
  "license_key": "TRIAL-XXXX-XXXX-XXXX",
  "license_type": "trial",
  "customer_name": "Full Name",
  "website_url": "https://user-website.com",
  "site_url": "https://wordpress-site.com",
  "signup_date": "2025-07-17T14:20:39.725Z",
  "trial_expires": "2025-07-31T14:20:39.725Z",
  "aweber_list": "siteoverlay-pro",
  "aweber_tags": "trial,siteoverlay-pro,wordpress-plugin",
  "product_name": "SiteOverlay Pro",
  "trial_duration": "14 days",
  "support_email": "support@siteoverlaypro.com",
  "login_instructions": "Go to WordPress Admin → Settings → SiteOverlay License"
}
```

## Debugging Steps

### 1. Check Pabbly Workflow Configuration

**In your Pabbly Connect dashboard:**

1. **Go to your SiteOverlay Pro workflow**
2. **Check the webhook trigger step:**
   - Verify it's set to receive JSON data
   - Check if there are any field mapping issues
   - Look for error messages in the webhook logs

3. **Check the AWeber action step:**
   - Verify AWeber connection is active
   - Check if the list name matches: `siteoverlay-pro`
   - Verify field mappings (email, name, tags)
   - Check for any authentication errors

### 2. Test Webhook Data Reception

**Add a simple action before AWeber** to debug:

1. **Add a "Send Email" or "Webhook Response" action**
2. **Configure it to send you the received data**
3. **Test the workflow to see what data Pabbly receives**

### 3. Check AWeber Integration

**In your AWeber account:**

1. **Verify the list exists:** `siteoverlay-pro`
2. **Check API connection status** in Pabbly
3. **Look for any error logs** in AWeber
4. **Test manual subscriber addition** to verify list is working

### 4. Pabbly Workflow Troubleshooting

**Common issues and fixes:**

#### Issue A: Webhook Not Triggering
- **Check webhook URL** in Railway matches Pabbly
- **Verify workflow is active** (you've done this)
- **Check for rate limiting** or quota issues

#### Issue B: Data Mapping Problems
- **Field names must match exactly**
- **Check for required fields** in AWeber action
- **Verify data types** (strings, dates, etc.)

#### Issue C: AWeber Authentication
- **Reconnect AWeber** in Pabbly integrations
- **Check API permissions** in AWeber
- **Verify list permissions** for the API key

### 5. Enhanced Debugging

**Add logging to your Pabbly workflow:**

1. **Add a "Code" step** before AWeber action
2. **Log the received data:**
   ```javascript
   console.log('Received webhook data:', input);
   return input;
   ```
3. **Check Pabbly execution logs** for this output

### 6. Test with Manual Data

**Test your Pabbly workflow manually:**

1. **Go to your workflow in Pabbly**
2. **Click "Test" or "Run"**
3. **Input sample data:**
   ```json
   {
     "email": "test@example.com",
     "customer_name": "Test User",
     "license_key": "TRIAL-TEST-TEST-TEST"
   }
   ```
4. **See if AWeber receives the subscriber**

## Quick Fix Attempts

### Option 1: Simplify the Workflow
**Create a minimal test workflow:**
1. **Webhook trigger** (same URL)
2. **AWeber add subscriber** (just email and name)
3. **Test with minimal data**

### Option 2: Check Required Fields
**Ensure AWeber action has all required fields:**
- Email (required)
- Name (often required)
- List name (required)

### Option 3: Verify List Name
**Double-check the AWeber list name:**
- In AWeber: Go to Lists → Check exact name
- In Pabbly: Ensure list name matches exactly
- Common issue: Case sensitivity or spaces

## Testing Commands

**Test the webhook directly:**
```bash
curl -X POST "https://connect.pabbly.com/workflow/sendwebhookdata/IjU3NjYwNTZhMDYzNjA0M2M1MjZjNTUzNjUxMzEi_pc" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "your-test-email@example.com",
    "customer_name": "Test User",
    "license_key": "TRIAL-TEST-TEST-TEST"
  }'
```

**Test full trial request:**
```bash
curl -X POST https://siteoverlay-api-production.up.railway.app/api/request-trial \
  -H "Content-Type: application/json" \
  -d '{
    "full_name": "Test User",
    "email": "your-test-email@example.com",
    "siteUrl": "https://test.com",
    "siteTitle": "Test Site",
    "wpVersion": "6.0",
    "pluginVersion": "1.0",
    "requestSource": "debug"
  }'
```

## Next Steps

1. **Check Pabbly execution logs** for your workflow
2. **Verify AWeber list configuration**
3. **Test with minimal data** to isolate the issue
4. **Check AWeber API connection** in Pabbly
5. **Add debugging steps** to your workflow

The webhook is working - the issue is in the Pabbly → AWeber connection or configuration.