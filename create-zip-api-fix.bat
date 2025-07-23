@echo off
echo Creating SiteOverlay Pro Plugin Zip with API Endpoint Fix...
echo.

REM Remove old zip if exists
if exist "siteoverlay-pro-api-fix.zip" del "siteoverlay-pro-api-fix.zip"

REM Create new zip with all files
powershell -command "Compress-Archive -Path 'siteoverlay-pro.php', 'includes\*', 'assets\*', 'README.md', 'DEVELOPMENT_PLAN.md' -DestinationPath 'siteoverlay-pro-api-fix.zip' -Force"

echo.
echo Zip file created: siteoverlay-pro-api-fix.zip
echo.
pause 