@echo off
cd /d "%~dp0"
set PHP=C:\xampp\php\php.exe
if not exist "%PHP%" set PHP=php
echo Precious Satin Bouquet setup. Backups go to _backup_before_pwa
"%PHP%" pwa_setup.php . --apply
echo.
echo Done. Press Ctrl+Shift+R in your browser.
pause
