@echo off
REM One-time (or reset) set-up of the LOCAL presentation database on XAMPP.
REM Start Apache and MySQL in the XAMPP Control Panel first.
cd /d "%~dp0"
"C:\xampp\php\php.exe" bin\console.php demo:install --owner=root
echo.
"C:\xampp\php\php.exe" bin\console.php doctor
pause
