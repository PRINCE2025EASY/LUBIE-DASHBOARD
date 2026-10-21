# Stage 4 Test Report (Iteration 1)

Version 0.4.0, 21/10/2026. All data synthetic (demo seed). Every result below was produced by
running the stated command; nothing is estimated unless marked "not run".

## 1. Environments

| Name | Where | Versions | Used for |
|---|---|---|---|
| CLOUD-PHP | Cloud workspace, PHP built-in server | PHP 8.3.6, MariaDB 10.11.14 | Unit, integration, browser, accessibility tests |
| CLOUD-APACHE | Cloud workspace, Apache with the project's .htaccess at /lubie-dashboard | Apache 2.4, PHP 8.3.6 (opcache off, as in XAMPP), MariaDB 10.11.14, least-privilege `lubie_app` account | Deployment shape, load and page timing, offline test |
| XAMPP | Your computer | Apache 2.4.58, PHP 8.2.12, MariaDB 10.4.32 (from your XAMPP readme) | Not run by me. See section 8 |

Browser: Chromium (Playwright) at 360 x 740 px, light and dark.

## 2. Results summary

| Area | Method | Result |
|---|---|---|
| Unit tests | PHPUnit, 51 tests | Pass |
| Integration tests | PHPUnit against a rebuilt `_test` database, 51 tests | Pass |
| Total | `vendor/bin/phpunit` | 102 tests, 921 assertions, 0 failures |
| Code coverage | pcov, `src/` | Lines 82.3 %, methods 71.8 %. KpiService 100 %, Csrf 100 %, SyncService 96.9 %, AlertService 91.9 %, ProgressService 90.8 %, AuthService 88.7 %, BackupService 87.0 % |
| Syntax | `php bin/lint.php`, ESLint | 0 errors |
| Browser smoke | 6 roles, 44 views, light and dark, both environments | 0 console errors, 0 failed requests, no horizontal scroll at 360 px |
| Offline | Capture offline, reload offline, reconnect | Pass (CLOUD-PHP and CLOUD-APACHE) |
| Accessibility | axe-core 4.10.2, WCAG 2.0/2.1 A and AA, 13 views x 2 themes + login | 0 violations after fixes (3 rule types found, fixed) |
| Load | ApacheBench, 200 requests, 20 concurrent, per endpoint | p95 130 to 245 ms, 0 failures (target 2 s) |
| Page load | Throttled mobile (1.6 Mbps, 150 ms RTT), 4 roles | Dashboard after login 2.08 to 2.13 s (target 3 s) |
| Security probes | 9 attack-style integration tests + header checks | Pass |
| Least privilege | All write paths and backup as `lubie_app` | Pass; audit_logs UPDATE refused |

## 3. Test suites

| Suite | Tests | Assertions | What it proves |
|---|---|---|---|
| KpiServiceTest | 23 | 65 | Thesis worked example (BAC 200,000; PV 98,000; EV 70,000; AC 80,000 gives 35.00 %, SV -28,000, SPI 0.71, CV -10,000, CPI 0.88); duration fallback; zero denominators shown as not available; only approved progress on or before the date; planned fraction edges; six schedule statuses; S-curve has no future EV |
| AlertRulesTest | 12 | 52 | Each rule A1 to A15 and A6b fires on its condition and not otherwise; budget band edges (80 %, 100 %, 110 %); A15 clears when work resumes; no rule sends SMS to a client |
| PermissionMatrixTest | 6 | 195 | Hard rules: Site Engineer has no money permission; Client limited to its summary; audit trail Administrator only; Administrator has no financial detail; separation of duties; every route has a known guard; only the token scheduler skips CSRF |
| SupportTest | 10 | 35 | Money parsing and half-up rounding in pesewas; +233 phone normalising; validation messages; CSV formula-injection guard; SQL script splitter; DD/MM/YYYY display |
| AuthSecurityTest | 12 | 71 | Unified login and redirect per role; role never taken from the browser; other role's page refused and audited; same message for unknown user and wrong password; lockout after 5 failures; IP throttle after 20; CSRF; new session ID at login; logout ends session; forced password change after reset; security headers; bcrypt cost 12 |
| AccessControlTest | 8 | 258 | Out-of-scope IDs return 404 and are audited; Site Engineer responses contain no money field; Site Engineer may create activities but cost input is ignored; Client summary is an allow-list; Administrator blocked from finance; QS cannot approve progress; deactivated user's session rejected |
| WorkflowTest | 10 | 89 | Only approved progress changes KPIs; poll token moves on save; return needs a reason; resubmission; date and % validation; stoppage raises critical alert for Director, not Client; offline sync idempotent and re-validated; budget revision supersession and A12 clearing; expenditure confirm, duplicate and void; payment cannot exceed certified; exports by role; threshold limits |
| AdminBackupTest | 7 | 65 | User creation rules; settings whitelist; SMS daily and monthly caps; offline key per user; encrypted backup, restore into empty database with matching counts and KPIs; tampered file rejected; scheduler token; audit filters |
| ProjectScheduleTest | 5 | 47 | Project create with PM auto-assignment; lifecycle transitions; completed projects closed; assignment rules and immediate access change; activity edit, archive, dependency loop refusal; milestone achieved date |
| SecurityProbeTest | 9 | 44 | SQL injection payloads inert; stored XSS escaped in reports; open redirect blocked; cookie flags; forged session ID ignored; 405 and 404; no internals in errors; mass assignment ignored; audit trail cannot be changed through the app |

Run them: `php bin/console.php test-db:reset --yes` then `vendor/bin/phpunit`.

## 4. Requirement coverage (Iteration 1)

| Requirement | Evidence |
|---|---|
| FR-01, FR-02, FR-29 Login, password, unified redirect | AuthSecurityTest (all) |
| FR-03 Role restriction | PermissionMatrixTest; AccessControlTest; AuthSecurityTest::testOtherRolePageIsRefusedAndAudited |
| FR-04 Assigned-project restriction | AccessControlTest::testOutOfScopeProjectIdsReturn404AndAreAudited; ProjectScheduleTest::testAssignmentRules |
| FR-05 User administration | AdminBackupTest::testCreateUserValidatesAndAudits; AuthSecurityTest lockout and reset tests |
| FR-06, FR-07 Projects, assignments | ProjectScheduleTest |
| FR-08 Activities (Site Engineers may create, no cost) | AccessControlTest::testSiteEngineerCanCreateActivityButCostIsIgnored; ProjectScheduleTest::testActivityEditArchiveAndDependencyLoops |
| FR-09, FR-30, FR-32 Progress, approval, delay reasons | WorkflowTest progress tests |
| FR-10, FR-11, FR-12, FR-16, FR-40 KPIs | KpiServiceTest; WorkflowTest::testOnlyApprovedProgressChangesKpis |
| FR-13 Milestones | ProjectScheduleTest::testMilestonesAchievedDateAndScope; AlertRulesTest A3, A4 |
| FR-14, FR-31 Budgets and Director approval | WorkflowTest::testBudgetRevisionWorkflowAndSeparationOfDuties |
| FR-15 Expenditure | WorkflowTest::testExpenditureNeedsConfirmationAndVoidKeepsHistory |
| FR-20 Alerts | AlertRulesTest; WorkflowTest stoppage test |
| FR-23, FR-35 Overview, Director cross-project | Browser smoke (director portfolio); access tests |
| FR-24 Refresh and last-updated | WorkflowTest (change_seq, /changes); browser smoke |
| FR-25 Audit trail | AdminBackupTest::testAuditTrailFiltersAndPagination; SecurityProbeTest::testAuditTrailCannotBeChangedThroughTheApp; least-privilege check |
| FR-26 Reports PDF and CSV | WorkflowTest::testExportsRespectRolesAndAreSafe; SecurityProbeTest XSS test; SupportTest CSV guard |
| FR-27 Client summary | AccessControlTest::testClientSummaryIsAnAllowList |
| FR-28 Valuations and payments | WorkflowTest::testCertificatePaymentCannotExceedCertified |
| FR-36, NFR-06 Backup and restore | AdminBackupTest::testEncryptedBackupRestoresWithMatchingCountsAndKpis (restore 0.14 s for demo data) |
| FR-37, FR-38 Drafts, offline | WorkflowTest::testOfflineSyncIsIdempotent; browser offline test |
| FR-41 Dark and light | Browser smoke and axe scan in both themes |
| FR-17, FR-21, FR-22 (planned later, partly built) | SPI, thresholds and SMS caps are tested (KpiServiceTest, WorkflowTest thresholds, AdminBackupTest SMS caps); real SMS not tested |

## 5. Security (OWASP Top 10, 2021)

| Risk | Control | Test |
|---|---|---|
| A01 Broken access control | Guard per route, project scope 404, field allow-lists | PermissionMatrixTest, AccessControlTest, SecurityProbeTest mass assignment |
| A02 Cryptographic failures | bcrypt cost 12; AES-256-GCM backups; AES-GCM offline queue; Secure cookie flag online | AuthSecurityTest, AdminBackupTest tamper test |
| A03 Injection | Prepared statements only; output escaping; textContent in JS; CSP without inline script | SecurityProbeTest SQL and XSS tests; inline script refused by the CSP in the browser test |
| A04 Insecure design | Separation of duties, approval workflows, confirm step for expenditure | WorkflowTest |
| A05 Misconfiguration | Security headers, .htaccess blocks, X-Powered-By removed, generic errors | AuthSecurityTest headers; Apache path checks (403 for .env, src, config, vendor, database, docs) |
| A06 Vulnerable components | Pinned versions: Dompdf 3.1.4, Bootstrap 5.3.3, Chart.js 4.4.4 | Not scanned with an advisory database (no network to one). Stage 5 CI can add `composer audit` |
| A07 Authentication failures | Lockout, IP throttle, session regeneration, idle and absolute timeouts, forced change | AuthSecurityTest |
| A08 Integrity failures | GCM authentication on backups; idempotent sync | AdminBackupTest, WorkflowTest |
| A09 Logging failures | Append-only audit trail, denied access logged | AccessControlTest, AuthSecurityTest |
| A10 SSRF | No user-supplied URLs are fetched; SMS URL comes from .env only | Code review |

## 6. Accessibility (WCAG 2.1 AA where practicable)

axe-core 4.10.2 found three rule types before fixes, all fixed and re-scanned to 0. The scan injects axe with CSP bypassed in the test browser only; the app itself keeps its strict CSP.

- color-contrast: outline buttons in dark mode and the yellow outline button in light mode.
- scrollable-region-focusable: wide tables now take keyboard focus.
- label: five inputs on the valuations page now have names.

Also checked: keyboard tab order on login and site pages; skip link; status shown with colour, icon and text together; charts use dash patterns; 44 px touch targets.

Not covered by automation: screen-reader listening test, zoom to 200 %. Recommend one manual pass in Stage 7.

## 7. Performance (NFR-02, NFR-03)

API under load, CLOUD-APACHE, 20 concurrent users, 200 requests each, opcache off:

| Endpoint | Median | p95 | Failures |
|---|---|---|---|
| PM project dashboard | 154 ms | 191 ms | 0 |
| Director portfolio | 201 ms | 245 ms | 0 |
| Client summary | 135 ms | 163 ms | 0 |
| Poll for changes | 99 ms | 130 ms | 0 |
| Site activities | 133 ms | 164 ms | 0 |

Page load on a throttled mobile network (1.6 Mbps down, 150 ms round trip), first visit:

| Role | Login page | Dashboard after login (before fix) | After fix | Reload with cache |
|---|---|---|---|---|
| Project Manager | 1.49 s | 3.22 s | 2.13 s | 0.31 s |
| Client | 1.48 s | 3.19 s | 2.09 s | 0.29 s |
| Site Engineer | 1.47 s | 3.22 s | 2.08 s | 0.28 s |
| Director | 1.47 s | 3.21 s | 2.10 s | 0.39 s |

The fix (defect D-15) loads Chart.js only when a chart is drawn, defers Bootstrap and preloads
the app modules. These timings used Apache compression. XAMPP ships with mod_deflate switched
off, so first loads there will move more data (see O-1).

## 8. Compatibility with your XAMPP

- PHP 8.2.12: the code uses no PHP 8.3-only features (scanned), and Composer is pinned to platform PHP 8.2.12.
- MariaDB 10.4.32: I could not run 10.4 itself. Downloads from mariadb.org are blocked from both my workspace and your computer's sandbox. Every schema feature used (CHECK constraints, stored generated columns with a unique key, utf8mb4) exists since MariaDB 10.2. No 10.5+ syntax (such as RETURNING) is used.
- To confirm on your machine, run `php bin/console.php doctor` after install. It checks PHP, extensions, .env keys (never their values), the database version, the 31 tables, 2 generated columns, 27 CHECK constraints and whether the audit trail is append-only.

## 9. Usability test protocol (to run with participants)

Use the demo accounts on a phone at the site office. Record time, errors and help needed.
Task wording is plain; do not show the participant where to tap.

| Task | Role | Instruction | Success when |
|---|---|---|---|
| T1 | Site Engineer | "Record that R05 is 35 % done today, delayed by rain." | Entry submitted with reason |
| T2 | Project Manager | "Approve today's progress entry, or return it if the % looks wrong." | Decision saved |
| T3 | Site Engineer | "Switch off mobile data, record progress on R04, then switch data back on." | Entry reaches the PM queue without retyping |
| T4 | Quantity Surveyor | "Record a GH₵ 2,500.00 cement purchase with voucher PV-123." | Saved after the confirm step |
| T5 | Director | "Find which project is over budget and approve or reject the waiting revision." | Correct project named; decision saved |

After the tasks, use the questionnaire items mapped to PEOU and PU in the traceability matrix.
Results table (to fill in): participant, role, task, seconds, errors, help asked, success.

## 10. Defect log

| ID | Found in | Description | Severity | Status |
|---|---|---|---|---|
| D-01 | Stage 3 sweep | Expenditure category list reached Site Engineers through /lookups | Medium (need to know) | Fixed |
| D-02 | Stage 3 browser | Reports card called a forbidden endpoint for Site Engineers | Low | Fixed |
| D-03 | Stage 3 browser | The word "null" printed on the site progress form | Low | Fixed |
| D-04 | Stage 3 browser | Offline badge could show a stale count after sync | Low | Fixed |
| D-05 | Stage 3 review | Alert texts showed ISO dates | Low (NFR-12) | Fixed |
| D-06 | Stage 3 tests | Restore check reported a false KPI mismatch (100 vs 100.0) | Medium | Fixed |
| D-07 | Stage 3 tests | New-user form hid the username error until others were fixed | Low | Fixed |
| D-08 | Stage 3 smoke | X-Powered-By header exposed the PHP version | Low | Fixed |
| D-09 | Stage 3 tests | Print view format refused | Low | Fixed |
| D-10 | Stage 3 XAMPP review | Backup would crash when openssl is off; now a clear message | Medium | Fixed |
| D-11 | Stage 4 | Wrong database password reported as "not responding" after retries | Low | Fixed |
| D-12 | Stage 4 axe | Colour contrast of outline buttons below 4.5:1 | Medium (WCAG) | Fixed |
| D-13 | Stage 4 axe | Wide tables not reachable by keyboard scrolling | Medium (WCAG) | Fixed |
| D-14 | Stage 4 axe | Five valuation inputs without accessible names | Medium (WCAG) | Fixed |
| D-15 | Stage 4 timing | Dashboard after login took 3.2 s on throttled mobile (target 3 s) | Medium (NFR-02) | Fixed, now 2.1 s |
| D-16 | Stage 6, MySQL 8 run | Backup skipped columns MySQL 8 marks DEFAULT_GENERATED (created_at and similar), so restored timestamps changed. MariaDB was not affected. New test compares table checksums after restore | High (NFR-06) | Fixed |
| D-17 | Stage 5, first CI run | Dompdf 3.1.4 has six advisories published 22/07/2026 (SVG file read, DoS, chroot bypass). Exposure was low (the app renders only its own escaped HTML, no images), but CI correctly blocked it | Medium | Fixed: Dompdf 3.1.6 |
| D-18 | Stage 6 review | Behind a host's proxy every user shared one IP, so the login IP throttle and audit IPs would be wrong | Medium | Fixed: TRUSTED_PROXY_HOPS, unit-tested |

Open items (not code defects):

| ID | Item | Action |
|---|---|---|
| O-1 | XAMPP has mod_deflate off, so responses are not compressed locally | Optional httpd.conf change (needs your approval); online hosts compress by default |
| O-2 | Native date pickers follow the browser language | Set the phone or browser to English (UK) or English (Ghana) |
| O-3 | MariaDB 10.4.32 not executed by me | Run `php bin/console.php doctor` and the install steps |
| O-4 | Windows console shows typing for the first-admin password | Make sure nobody is watching; or use the demo data |
| O-5 | openssl off in your php.ini | Backups wait for your php.ini decision |
| O-6 | Real SMS not tested | Needs a provider account (paid); stays sandbox |

## 10b. Stage 6 additions

| Check | Result |
|---|---|
| Full suite on MySQL 8.0.46 over TLS, `sql_require_primary_key=ON`, `ONLY_FULL_GROUP_BY`, app user `REQUIRE SSL` | 103 tests pass |
| Full suite on MariaDB 10.11 | 103 tests pass |
| Connection without TLS to a `REQUIRE SSL` account | Refused (expected) |
| CA certificate passed as text (`DB_SSL_CA_PEM`) | TLS 1.3 session |
| Container layout (docker/apache.conf, php.ini, entrypoint) under Apache | /health 200; .env 403; src, vendor, config, database 404; HSTS; `__Host-` cookie; client IP taken from the trusted proxy hop |
| GitHub Actions on PHP 8.2 + MariaDB 10.4.32 | Dependabot Dompdf 3.1.6 branch: all jobs green. main at 0c2ae89: failed at dependency install because of D-17 (fixed since) |
| Docker image build | Not run here (no Docker in my workspace). The new CI job builds and smoke-tests it on every push |

## 11. Not run

- Tests on your XAMPP (PHP 8.2.12, MariaDB 10.4.32).
- Dependency advisory scan (Stage 5 CI).
- Usability sessions with real participants (protocol in section 9).
- Screen reader and 200 % zoom check.
