@echo off
echo Creating SiteOverlay Pro Plugin Zip with Elegant UX...
echo.

REM Remove old zip if exists
if exist "siteoverlay-pro-elegant-ux.zip" del "siteoverlay-pro-elegant-ux.zip"

REM Create new zip with all files
powershell -command "Compress-Archive -Path 'siteoverlay-pro.php', 'includes\*', 'assets\*', 'README.md', 'DEVELOPMENT_PLAN.md' -DestinationPath 'siteoverlay-pro-elegant-ux.zip' -Force"

echo.
echo Zip file created: siteoverlay-pro-elegant-ux.zip
echo.
pause 