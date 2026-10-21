# Corrected master requirements list

Base wording: thesis Table 3.4 (FR-01 to FR-27) and Table 3.5 (NFR-01 to NFR-10). FR-26 wording corrected; FR-04 and FR-21 amended to the reconciled baseline. FR-28 onward are new, stable IDs. Table 3.8 numbering (which diverges from FR-23) is retired; see the Issues Register for the old-to-new map.

## Functional requirements

| ID | Title | The system shall... | Priority | Override | Iteration |
|---|---|---|---|---|---|
| FR-01 | Authenticate and log out | The system shall authenticate users with a username and password and allow secure logout. | Must | O1 (rule also met) | 1 |
| FR-02 | Change and reset password | The system shall allow users to change their password and allow the administrator to reset passwords, storing passwords only as salted hashes. | Must | O1 | 1 |
| FR-03 | Role-based function restriction | The system shall restrict functions according to the user's role (Administrator, Director, Project Manager, Quantity Surveyor, Site Engineer, Client), enforced on every API request. | Must | O1 (rule also met) | 1 |
| FR-04 | Assigned-project restriction | The system shall restrict each user to the projects assigned to that user, except the Director (read access to all projects) and the Administrator (no project editing duties). | Must | O1 (rule also met) | 1 |
| FR-05 | User administration | The system shall allow the administrator to create, deactivate and edit user accounts and roles, with secure first-administrator provisioning and no public self-registration. | Must | O1 (access control) | 1 |
| FR-06 | Project management | The system shall allow the project manager to create and update projects (code, name, type, location, client, contract sum, planned and actual dates, contractual payment period, status: planning, active, on hold, completed, archived). | Must | O2 | 1 |
| FR-07 | Assign users to projects | The system shall allow the administrator or project manager to assign users to projects. | Must | O1 | 1 |
| FR-08 | Activities | The system shall allow activities to be created and updated with planned start and finish dates, actual dates, budgeted cost and optional dependencies. The PM and site engineers create and edit activities on assigned projects (decision 21/10/2026); only the PM sets budgeted cost, because site engineers never see cost data. | Must | O2 | 1 |
| FR-09 | Record progress | The system shall allow site engineers and project managers to record dated progress (percentage complete, 0 to 100) with remarks for an activity. | Must | O2 | 1 |
| FR-10 | Project completion % | The system shall calculate project completion percentage using budgeted-cost weighting (EV / BAC x 100), falling back to duration weighting, clearly labelled, when activity costs are absent. | Must | O2 + O3 | 1 |
| FR-11 | Planned versus earned value | The system shall calculate and display planned value against earned value over time (planned versus actual progress). | Must | Rule (D2); O2 (E6) | 1 |
| FR-12 | Schedule status and delayed activities | The system shall determine each activity's schedule status (not started, late start, in progress, delayed, completed, completed late) from its dates and progress and list delayed activities. | Must | Rule (D3); O2 + O3 (D5) | 1 |
| FR-13 | Milestones | The system shall allow milestones to be defined and marked as achieved, and display upcoming, achieved and overdue milestones. | Must | Rule (D3); O2 (D4, E7) | 1 |
| FR-14 | Approved budget and revisions | The system shall allow the quantity surveyor to record the approved project budget and its revisions (draft, pending approval, approved, rejected, superseded), never overwriting the baseline. | Must | O2 | 1 |
| FR-15 | Expenditure | The system shall allow the quantity surveyor to record expenditure entries against a project and, optionally, an activity. | Must | Rule (D7); O2 (E8) | 1 |
| FR-16 | Budget versus expenditure, CV, CPI | The system shall display budget against expenditure, budget consumed, budget variance, cost variance and cost performance index. | Must | O3 | 1 |
| FR-17 | SV and SPI | The system shall calculate and display schedule variance and schedule performance index. | Should | No questionnaire item; thesis priority kept | 2 |
| FR-18 | Labour and materials | The system shall allow labour and material records (attendance, quantities received and used, balance) to be entered and summarised per project. | Should | - | 2 |
| FR-19 | Equipment status | The system shall allow equipment records (equipment on site and status) to be entered per project, with an in-app alert when equipment is reported faulty (A14). | Could | - | 3 |
| FR-20 | In-app alerts | The system shall generate in-app alerts for delayed activities, late starts, upcoming and overdue milestones, budget thresholds, poor cost or schedule performance, overdue certificates, stale data, pending approvals and work stoppage or safety incidents (rules A1 to A15), with severity, read, acknowledge and resolve states and de-duplication. | Must | O3 (D12, D13, E15); O3 (E14 to Should) | 1 (A1-A7, A9, A11-A13, A15); 2 (A8 with FR-17); 3 (A10, A14) |
| FR-21 | Two-level alert thresholds | The system shall let the Director set global threshold limits, the PM set project thresholds within those limits, and the QS propose cost thresholds for Director approval; the Administrator maintains system settings. | Should | - | 2 |
| FR-22 | SMS for critical and high alerts | The system shall send SMS notifications to the responsible users for Critical and High alerts (A1 > 14 days, A4, A6b > 10% overrun, A9, A12, A15), with a per-user daily cap and an optional monthly cap, through a gateway adapter (log-only sandbox and HTTP adapter). | Could | O3 (+1) and O4 (-1) | 2 (first item after Should) |
| FR-23 | Consolidated overview | The system shall present indicators through KPI cards (with formula tooltips), charts, progress bars and tables on a consolidated project overview. | Must | Rule (E11, E17); O3 (E12) | 1 |
| FR-24 | Refresh and last-updated stamp | The system shall refresh dashboard data automatically at a configurable interval (60 s default) and display the date and time of the latest update on every dashboard and KPI view. | Must | O3 (D14) | 1 |
| FR-25 | Audit trail | The system shall record an append-only audit trail of logins, failed logins, logouts, record creation, updates, deletions and archives, role and assignment changes, threshold changes, approvals and rejections, exports, backups and restores, and 403 attempts. | Must | O1 (rule also met) | 1 |
| FR-26 | Export reports (corrected wording) | The system shall export reports in PDF and CSV formats (project status, delayed activities, milestone status, expenditure listing, budget status, cost-variance summary, valuations and payments, resources, alerts, audit for the Administrator, and a one-page client progress and payment summary), limited to the data the user may view. | Must | - | 1 (PDF + CSV core); 2 (remaining reports) |
| FR-27 | Client read-only summary | The system shall provide clients with a read-only summary of progress, schedule, milestones, budget status and payment status (contract sum, certified, paid) for their own projects only. | Must | - | 1 |
| FR-28 | Valuations and payment certificates | The system shall record payment certificates (valuation amount, amount certified, certification date) and payment transactions (partial and multiple), and show outstanding balance and status (submitted, certified, part-paid, paid, overdue). | Must | O3 | 1 |
| FR-29 | Unified login and role-based redirect | The system shall provide one login page for all users and redirect each user, from trusted server records, to the dashboard of their role; another role's URL returns 403 and is audit-logged. | Must | O1 | 1 |
| FR-30 | PM approval of progress | The system shall require the project manager to approve or return (with reason) progress entries; only approved entries count towards KPIs; the submitter sees confirmation and current status. | Must | - | 1 |
| FR-31 | Director approval of budget revisions | The system shall require the Director to approve or reject budget revisions requested by the PM or QS before they become current. | Must | - | 1 |
| FR-32 | Delay-reason dropdown | The system shall offer a delay-reason list with each progress entry, including 'Work stoppage' and 'Safety incident', which raise a Critical alert (A15). | Must | O2 (A15 in FR-20 depends on it); interview-derived | 1 |
| FR-33 | Material requests and store balance | The system shall provide a simple material request tracker (requested, approved, delivered, cancelled) and show store balances with reorder levels. | Should | Interview-derived | 2 |
| FR-34 | Material reorder alert | The system shall raise an in-app alert (A10) when a material balance falls below its reorder level. | Could | - | 3 |
| FR-35 | Director cross-project view | The system shall give the Director a consolidated cross-project comparison (% complete, CV, SV, delayed activities, upcoming milestones, open alerts) for all projects without assignment. | Must | - | 1 |
| FR-36 | Backup and restore function | The system shall provide a daily full logical backup through an administrator function and a token-protected endpoint for an external scheduler, compressed and encrypted, delivered off the host, with 7 daily and 4 weekly retention and a documented, tested restore. | Must | O1 | 1 |
| FR-37 | Form resilience and draft autosave | The system shall keep form input on failure, state clearly that data were not saved, and autosave unsaved drafts in the browser. | Could | Delivered with FR-38 | 1 (part of FR-38) |
| FR-38 | Offline operation with synchronisation | The system shall work offline as an installable web app: cached app shell and last-synced read-only views, offline capture of progress, labour, material and equipment entries (progress in Iteration 1; the others as their modules arrive) in an encrypted local queue, and automatic synchronisation with server-side validation when the connection returns. | Must | Owner directive (O5), decision 21/10/2026 | 1 |
| FR-39 | Progress photo | The system shall allow one optional photo per progress entry, compressed in the browser to about 200 KB, type-checked and stored in the database or external object storage. | Could | - | 3 |
| FR-40 | Days remaining to planned completion | The system shall show the number of days remaining to the planned completion date on the project KPI cards. | Could | Single response | 3 |
| FR-41 | Dark and light theme | The system shall offer a dark/light mode toggle that remembers the user's choice. | Must | O5 owner directive | 1 |

## Non-functional requirements

| ID | Title | The system shall... | Priority | Override | Iteration |
|---|---|---|---|---|---|
| NFR-01 | Security | Use HTTPS; hash passwords; use prepared statements; encode output; protect state-changing requests with CSRF tokens; manage sessions securely (HttpOnly, Secure, SameSite, regeneration, idle timeout); throttle failed logins; security headers; least-privilege DB account; secrets outside web root and Git. OWASP Top 10 and ASVS Level 1. | Must | O1 | 1 |
| NFR-02 | Performance (completed) | API summary calls respond within 2 s at the 95th percentile and full pages load within 3 s on a throttled mobile network with 20 concurrent users. | Must | Thesis priority | 1 |
| NFR-03 | Low bandwidth | Minimise data transferred: cache static assets, compress responses, return summary data on refresh, pause refresh when the tab is hidden, avoid large images. | Must | Thesis priority | 1 |
| NFR-04 | Responsive, mobile-first | Display and function correctly on smartphones from 360 px width, tablets and desktops, with large touch targets. | Must | Thesis priority | 1 |
| NFR-05 | Usability | Use plain construction labels, consistent layout and short forms with dropdowns and defaults; require no more than basic computer skills; WCAG 2.1 AA where practicable. | Must | Rule met | 1 |
| NFR-06 | Backup and recovery (completed) | Back up the database daily, keep an off-site copy, and restore within RTO 4 hours with at most 24 hours of data loss (RPO). | Must | O1 | 1 |
| NFR-07 | Data integrity | Validate inputs (types, ranges, date order, 0 to 100 %, non-negative amounts); enforce foreign keys, unique pairs, one current budget; use transactions; archive instead of delete; standard units and formats; extra validation for single-source fields. | Must | Thesis priority | 1 |
| NFR-08 | Data-entry efficiency | Pre-fill dates and defaults; allow progress update in one screen per activity. | Should | Thesis priority | 1 |
| NFR-09 | Availability | Host the application off-site so site power interruptions affect only users' devices; report clearly when a request fails; handle cold starts. | Should | Thesis priority | 1 |
| NFR-10 | Configurability | Store thresholds, refresh interval and SMS caps as parameters, not in code. | Should | Thesis priority | 1 |
| NFR-11 | Host-agnostic, stateless deployment | Run unchanged on XAMPP and a free container host: database sessions, environment configuration, no persistent local disk, no cron dependence, TLS database connections. | Must | O5 owner directive | 1 |
| NFR-12 | Local conventions | British English; GH₵ with two decimals stored as DECIMAL; Africa/Accra time; dates shown DD/MM/YYYY and stored ISO; phone numbers +233. | Must | O5 owner directive | 1 |
| NFR-13 | Data protection | Use synthetic data only in development, testing and demonstration; document considerations of Ghana's Data Protection Act, 2012 (Act 843) for any later move to real data. | Must | O1 | 1 |

## Won't Have (this version)

| ID | Item | Evidence | Decision |
|---|---|---|---|
| W-01 | WhatsApp notification | F1 mean 2.80 (6/15); thesis s1.10 delimitation | Won't (rule and scope) |
| W-02 | Separate native mobile app | F3 mean 3.40 (8/15); thesis s1.8 | Won't (scope); offline web app can be installed |
| W-03 | GPS tracking of equipment or vehicles | F4 mean 4.60 (13/15); thesis s1.8 | Won't (scope) despite rule Must; future work |
| W-04 | Predictive analytics of delays or overruns | F5 mean 4.67 (14/15); thesis s1.8 | Won't (scope) despite rule Must; future work |
| W-05 | Forecast final cost / EAC | QS-01, QS-02 (Interview Table 5, 14); thesis s2.2.7 | Won't (scope) |
| W-06 | Quality, inspection and safety module | SE-02, PM-02 (Interview Table 14) | Won't; only stoppage/safety delay reasons (FR-32) |
| W-07 | IoT, sensors, drones, BIM | Thesis s1.8; no participant asked | Won't (scope) |
| W-08 | Accounting or ERP integration | Thesis s1.8 | Won't (scope) |
| W-09 | E-mail notification | Thesis s1.8 | Won't (scope) |
| W-10 | Multi-company use | Thesis s1.8 | Won't (scope) |
| W-11 | 'Value addition at management level' | D21 open text, 1 response, meaning unclear | Won't until clarified |
| W-12 | Fallback channel when the system is down | CL-01, CL-03, CL-04, CL-05 | Operating procedure, not a feature |

Counts: Must 41, Should 7, Could 6, Won't 12.

Old-to-new ID map (Table 3.8 -> master): FR-23 payment certificates -> FR-28; FR-24 overview -> FR-23; FR-25 refresh -> FR-24; FR-26 audit -> FR-25; FR-27 export -> FR-26; FR-28 client summary -> FR-27. FR-01 to FR-22 unchanged.
