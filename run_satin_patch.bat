@echo off
cd /d "%~dp0"
set PHP=C:\xampp\php\php.exe
if not exist "%PHP%" set PHP=php
"%PHP%" satin_only_patch2.php
echo.
echo Now press Ctrl+Shift+R in your browser.
pause