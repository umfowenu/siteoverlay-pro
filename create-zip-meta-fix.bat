@echo off
echo Creating SiteOverlay Pro Plugin Zip with Meta Box Fix...
echo.

REM Remove old zip if exists
if exist "siteoverlay-pro-meta-fix.zip" del "siteoverlay-pro-meta-fix.zip"

REM Create new zip with all files
powershell -command "Compress-Archive -Path 'siteoverlay-pro.php', 'includes\*', 'assets\*', 'README.md', 'DEVELOPMENT_PLAN.md' -DestinationPath 'siteoverlay-pro-meta-fix.zip' -Force"

echo.
echo Zip file created: siteoverlay-pro-meta-fix.zip
echo.
pause 