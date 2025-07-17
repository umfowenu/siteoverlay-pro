# PABBLY_WEBHOOK_URL Setup Guide

## Issue Identified
Your trial license system is working correctly and generating licenses in PostgreSQL, but **no emails are being sent to AWeber** because the `PABBLY_WEBHOOK_URL` environment variable is missing from your Railway deployment.

## Current Status
- ✅ Railway API is deployed and working
- ✅ Trial licenses are generated in PostgreSQL
- ✅ Email collection records are stored
- ❌ **PABBLY_WEBHOOK_URL is missing** - no emails sent
- ❌ AWeber integration not working

## Required Fix

### Step 1: Get Your Pabbly Webhook URL
1. Log into your Pabbly Connect account
2. Go to your SiteOverlay Pro workflow
3. Find the webhook trigger URL (starts with `https://connect.pabbly.com/workflow/...`)
4. Copy the complete webhook URL

### Step 2: Add to Railway Environment Variables

**Option A: Via Railway Dashboard**
1. Go to https://railway.app/dashboard
2. Select your `siteoverlay-api-production` project
3. Go to Variables tab
4. Click "Add Variable"
5. Add:
   - **Name**: `PABBLY_WEBHOOK_URL`
   - **Value**: `https://connect.pabbly.com/workflow/sendwebhookdata/[YOUR_WEBHOOK_ID]`

**Option B: Via Railway CLI** (if installed)
```bash
railway variables set PABBLY_WEBHOOK_URL=https://connect.pabbly.com/workflow/sendwebhookdata/[YOUR_WEBHOOK_ID]
```

### Step 3: Redeploy (Automatic)
Railway will automatically redeploy your API when you add the environment variable.

## Expected Data Flow After Fix

1. **User submits trial form** → WordPress plugin
2. **Plugin calls Railway API** → `/api/request-trial` endpoint
3. **License generated** → PostgreSQL database
4. **Email data sent** → Pabbly webhook (NEW - this is currently missing)
5. **Pabbly triggers** → AWeber email automation
6. **User receives email** → With trial license key

## Test the Fix

After adding the webhook URL, test with:

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
    "requestSource": "test"
  }'
```

You should see:
- `"pabbly_status": "email_sent"` (instead of `"email_pending"`)
- Email should arrive in AWeber and be sent to the user

## Pabbly Webhook Data Format

Your Pabbly webhook will receive this data structure:
```json
{
  "email": "user@example.com",
  "license_key": "TRIAL-XXXX-XXXX-XXXX",
  "license_type": "trial",
  "customer_name": "Full Name",
  "website_url": "https://website.com",
  "site_url": "https://wordpress-site.com",
  "signup_date": "2025-01-17T12:00:00.000Z",
  "trial_expires": "2025-01-31T12:00:00.000Z",
  "aweber_list": "siteoverlay-pro",
  "aweber_tags": "trial,siteoverlay-pro,wordpress-plugin",
  "product_name": "SiteOverlay Pro",
  "trial_duration": "14 days",
  "support_email": "support@siteoverlaypro.com",
  "login_instructions": "Go to WordPress Admin → Settings → SiteOverlay License"
}
```

## Verification Steps

1. **Check Railway logs** for Pabbly webhook success messages
2. **Check AWeber** for new subscribers
3. **Test email delivery** to your own email
4. **Verify WordPress plugin** receives license via email

The system is 95% working - just needs this one environment variable to complete the email integration!