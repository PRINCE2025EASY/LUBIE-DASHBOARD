@echo off
REM Load the presentation data into the CLOUD database (Aiven), then check it.
REM Uses the Aiven settings saved in .env.before-demo. Your local .env is not changed.
cd /d "%~dp0"
"C:\xampp\php\php.exe" bin\console.php cloud:load-data --owner=avnadmin --env-file=.env.before-demo
if errorlevel 1 goto end
echo.
"C:\xampp\php\php.exe" bin\console.php doctor --env-file=.env.before-demo
:end
pause
