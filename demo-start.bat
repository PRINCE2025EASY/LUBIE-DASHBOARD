@echo off
REM Before the presentation: checks the set-up, then opens the dashboard in the browser.
cd /d "%~dp0"
"C:\xampp\php\php.exe" bin\console.php doctor
if errorlevel 1 (
  echo.
  echo Something needs attention ^(see the FIX lines above^). Is MySQL running in the XAMPP Control Panel?
  pause
  exit /b 1
)
start "" "http://localhost/lubie-dashboard/"
