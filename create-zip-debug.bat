@echo off
echo Creating SiteOverlay Pro Plugin Zip with Debug Functionality...
echo.

REM Remove old zip if exists
if exist "siteoverlay-pro-debug.zip" del "siteoverlay-pro-debug.zip"

REM Create new zip with all files
powershell -command "Compress-Archive -Path 'siteoverlay-pro.php', 'includes\*', 'assets\*', 'README.md', 'DEVELOPMENT_PLAN.md' -DestinationPath 'siteoverlay-pro-debug.zip' -Force"

echo.
echo Zip file created: siteoverlay-pro-debug.zip
echo.
pause 