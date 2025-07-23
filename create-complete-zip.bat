@echo off
echo Creating Complete SiteOverlay Pro Zip with All Folders...
echo.

REM Remove old zip if exists
if exist "siteoverlay-pro-complete.zip" del "siteoverlay-pro-complete.zip"

REM Create new zip with all folders
powershell -command "Compress-Archive -Path 'siteoverlay-pro.php', 'includes', 'assets', 'README.md', 'DEVELOPMENT_PLAN.md' -DestinationPath 'siteoverlay-pro-complete.zip' -Force"

echo.
echo Complete zip created: siteoverlay-pro-complete.zip
echo.
pause 