@echo off
echo Creating SiteOverlay Pro Plugin Zip with Critical Railway API Fix...
echo.

REM Remove old zip if exists
if exist "siteoverlay-pro-critical-fix.zip" del "siteoverlay-pro-critical-fix.zip"

REM Create new zip with all files
powershell -command "Compress-Archive -Path 'siteoverlay-pro.php', 'includes\*', 'assets\*', 'README.md', 'DEVELOPMENT_PLAN.md' -DestinationPath 'siteoverlay-pro-critical-fix.zip' -Force"

echo.
echo Zip file created: siteoverlay-pro-critical-fix.zip
echo.
pause 