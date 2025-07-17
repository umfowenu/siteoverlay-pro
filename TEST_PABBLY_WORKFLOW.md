# Test Pabbly Workflow After Activation

## Current Issue Found
Your Pabbly workflow is **deactivated**. That's why licenses are created but no emails are sent.

## Steps to Fix

1. **Log into Pabbly Connect**: https://connect.pabbly.com/
2. **Find your SiteOverlay Pro workflow**
3. **Click "Activate" or "Enable"** to turn it on
4. **Verify status shows "Active"**

## Test After Activation

### Test 1: Direct Pabbly Webhook
```bash
curl -X POST "https://connect.pabbly.com/workflow/sendwebhookdata/IjU3NjYwNTZhMDYzNjA0M2M1MjZjNTUzNjUxMzEi_pc" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "your-test-email@example.com",
    "license_key": "TRIAL-TEST-TEST-TEST",
    "license_type": "trial",
    "customer_name": "Test User",
    "product_name": "SiteOverlay Pro",
    "trial_duration": "14 days"
  }'
```

**Expected Response (after activation):**
```json
{"status":"success","message":"Webhook processed successfully"}
```

### Test 2: Full Trial Request
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

**Expected Response (after activation):**
```json
{
  "success": true,
  "message": "Trial license created successfully! Your 14-day trial license has been sent to your-test-email@example.com.",
  "data": {
    "email": "your-test-email@example.com",
    "customer_name": "Test User",
    "expires": "2025-07-31T06:15:00.000Z",
    "pabbly_status": "email_sent"  // ← Should change from "email_pending"
  }
}
```

## What Should Happen After Activation

1. **Pabbly workflow processes the webhook**
2. **AWeber receives new subscriber**
3. **Email is sent to user with trial license key**
4. **User can activate license in WordPress**

## Verification Steps

1. **Check Pabbly logs** for successful webhook processing
2. **Check AWeber** for new subscriber
3. **Check your test email** for license delivery
4. **Test WordPress plugin** with received license key

The system is working perfectly - just needs the Pabbly workflow activated!