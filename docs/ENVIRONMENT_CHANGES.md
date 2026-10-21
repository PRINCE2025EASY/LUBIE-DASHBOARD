# Environment changes log

Every change made to your computer for this project is recorded here, with how to undo it.

| Date | Change | File or setting | Backup location | How to undo |
|---|---|---|---|---|
| 20/10/2026 | None. Stage 1 only created files inside `SYSTEM BUILDING\lubie-dashboard`. `tools/env_check.ps1` is read-only. | - | - | Delete the `lubie-dashboard` folder |
| 21/10/2026 | Created folder `C:\xampp\htdocs\lubie-dashboard` (project copy, with `vendor` for Dompdf). No existing htdocs project touched. No .env written (you create it). | `C:\xampp\htdocs\lubie-dashboard` | - | Delete the folder |
| 21/10/2026 | No change to php.ini, httpd.conf, my.ini, hosts, firewall or environment variables. | - | - | - |
| 21/10/2026 | php.ini: `;extension=openssl` to `extension=openssl` (line 942), `expose_php=On` to `Off` (398), `display_errors=On` to `Off` (506). Approved by you. | `C:\xampp\php\php.ini` | `C:\xampp\php\php.ini.bak-lubie-2026-10-21` | Copy the backup over php.ini, restart Apache |
| 21/10/2026 | Created `.env` from `.env.example` with new random APP_KEY, BACKUP_ENCRYPTION_KEY, BACKUP_TOKEN (generated on your computer, never shown). DB_PASSWORD left empty for you. | `C:\xampp\htdocs\lubie-dashboard\.env` | - | Delete `.env` |
| 21/10/2026 | php.ini line 942 set back to `;extension=openssl`: openssl was already enabled at line 984 (`extension=php_openssl.dll`), so my change loaded it twice. expose_php and display_errors changes stay. | `C:\xampp\php\php.ini` | `php.ini.bak-lubie-2026-10-21` | Copy the backup over php.ini |
| 21/10/2026 | `.env.example` in htdocs restored to the blank template (a password had been typed into it). `.env` untouched. | `C:\xampp\htdocs\lubie-dashboard\.env.example` | git history | - |
| 21/10/2026 | `vendor` in htdocs updated: Dompdf 3.1.4 to 3.1.6 (security). | `C:\xampp\htdocs\lubie-dashboard\vendor` | - | - |

