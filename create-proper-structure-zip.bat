@echo off
echo Creating SiteOverlay Pro with proper folder structure...

REM Remove old zip if exists
if exist "siteoverlay-pro-proper.zip" del "siteoverlay-pro-proper.zip"

REM Create zip with proper folder structure
powershell -command "Compress-Archive -Path 'siteoverlay-pro.php', 'includes', 'assets', 'README.md', 'DEVELOPMENT_PLAN.md', '.cursorrules' -DestinationPath 'siteoverlay-pro-proper.zip' -Force"

echo.
echo ✅ Properly structured zip created: siteoverlay-pro-proper.zip
echo.
echo 📁 This zip contains the correct folder structure:
echo    siteoverlay-pro/
echo    ├── siteoverlay-pro.php
echo    ├── includes/
echo    │   ├── class-license-manager.php
echo    │   ├── class-site-tracker.php
echo    │   └── ...
echo    ├── assets/
echo    │   ├── css/
echo    │   │   └── admin.css
echo    │   └── js/
echo    │       └── admin.js
echo    └── README.md
echo.
echo 🚀 Upload this zip to your server and extract it!
pause 