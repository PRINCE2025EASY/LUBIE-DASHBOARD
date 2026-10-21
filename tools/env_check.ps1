# Lubie Dashboard - Stage 1 environment check (read-only).
# It changes nothing on your computer. It writes env_check_result.txt next to this script.
# Run in PowerShell:  powershell -ExecutionPolicy Bypass -File .\env_check.ps1
# Optional: pass a different XAMPP folder:  .\env_check.ps1 -Xampp "D:\xampp"

param([string]$Xampp = "C:\xampp")

$out = Join-Path $PSScriptRoot "env_check_result.txt"
function Say($t) { $t | Tee-Object -FilePath $out -Append }
"" | Set-Content $out

Say "=== Lubie env check  $(Get-Date -Format 'dd/MM/yyyy HH:mm') ==="
Say "Windows: $((Get-CimInstance Win32_OperatingSystem).Caption) $((Get-CimInstance Win32_OperatingSystem).Version)"

$php = Join-Path $Xampp "php\php.exe"
$mysql = Join-Path $Xampp "mysql\bin\mysql.exe"

if (Test-Path $php) {
  Say "`n--- php -v ---";            & $php -v 2>&1 | ForEach-Object { Say $_ }
  Say "`n--- php -m ---";            & $php -m 2>&1 | ForEach-Object { Say $_ }
  Say "`n--- php.ini in use ---";    & $php --ini 2>&1 | ForEach-Object { Say $_ }
  Say "`n--- required extensions ---"
  $mods = (& $php -m) | ForEach-Object { $_.Trim().ToLower() }
  foreach ($e in "pdo_mysql","mbstring","openssl","curl","gd","zip","intl","sodium","fileinfo") {
    Say ("{0,-10} {1}" -f $e, $(if ($mods -contains $e) {"OK"} else {"MISSING"}))
  }
  Say "`n--- key ini values ---"
  & $php -r "foreach(['date.timezone','memory_limit','upload_max_filesize','post_max_size','max_execution_time','display_errors','curl.cainfo','openssl.cafile','opcache.enable'] as `$k){echo `$k.' = '.var_export(ini_get(`$k),true).PHP_EOL;}" 2>&1 | ForEach-Object { Say $_ }
} else { Say "PHP not found at $php" }

if (Test-Path $mysql) {
  Say "`n--- mysql --version ---"; & $mysql --version 2>&1 | ForEach-Object { Say $_ }
} else { Say "mysql.exe not found at $mysql" }

$httpd = Join-Path $Xampp "apache\bin\httpd.exe"
if (Test-Path $httpd) {
  Say "`n--- Apache ---"; & $httpd -v 2>&1 | ForEach-Object { Say $_ }
  $conf = Join-Path $Xampp "apache\conf\httpd.conf"
  if (Test-Path $conf) {
    $rw = Select-String -Path $conf -Pattern "^\s*#?\s*LoadModule rewrite_module" | Select-Object -First 1
    Say "mod_rewrite line: $($rw.Line.Trim())"
  }
}

foreach ($tool in "composer","git","docker","code","node") {
  $c = Get-Command $tool -ErrorAction SilentlyContinue
  if ($c) {
    $v = switch ($tool) { "composer" {& composer -V 2>&1} "git" {& git --version} "docker" {& docker --version 2>&1} "code" {(& code --version 2>&1 | Select-Object -First 1)} "node" {& node --version} }
    Say ("{0,-9} {1}" -f $tool, ($v -join ' '))
  } else { Say ("{0,-9} not found" -f $tool) }
}

Say "`n--- ports 80 / 443 / 3306 in use ---"
Get-NetTCPConnection -State Listen -ErrorAction SilentlyContinue | Where-Object { $_.LocalPort -in 80,443,3306 } |
  ForEach-Object { Say ("port {0} pid {1} {2}" -f $_.LocalPort, $_.OwningProcess, (Get-Process -Id $_.OwningProcess -ErrorAction SilentlyContinue).ProcessName) }

Say "`n--- htdocs folders (names only) ---"
$ht = Join-Path $Xampp "htdocs"
if (Test-Path $ht) { Get-ChildItem $ht -Directory | ForEach-Object { Say $_.Name } }

Say "`nDone. Result saved to $out"
