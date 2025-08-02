@echo off
echo Creating SiteOverlay Pro Final Production Zip with All Assets...
echo.

REM Remove old zip if exists
if exist "siteoverlay-pro-final.zip" del "siteoverlay-pro-final.zip"

REM Create new zip with ALL files including assets
powershell -command "Compress-Archive -Path 'siteoverlay-pro.php', 'includes\*', 'assets\*', 'README.md', 'DEVELOPMENT_PLAN.md' -DestinationPath 'siteoverlay-pro-final.zip' -Force"

echo.
echo Final Production Zip created: siteoverlay-pro-final.zip
echo This includes all CSS and JS files needed for full functionality.
echo.
pause 