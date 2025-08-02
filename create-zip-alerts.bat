@echo off
echo Creating SiteOverlay Pro Plugin Zip with Alert Improvements...
echo.

REM Remove old zip if exists
if exist "siteoverlay-pro-alerts.zip" del "siteoverlay-pro-alerts.zip"

REM Create new zip with all files
powershell -command "Compress-Archive -Path 'siteoverlay-pro.php', 'includes\*', 'assets\*', 'README.md', 'DEVELOPMENT_PLAN.md' -DestinationPath 'siteoverlay-pro-alerts.zip' -Force"

echo.
echo Zip file created: siteoverlay-pro-alerts.zip
echo.
pause 