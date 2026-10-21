# Assumptions Register

Each item is a default the build will use until you confirm or change it. "Source" says where the default comes from.

| ID | Assumption / default | Source | Needs your confirmation |
|---|---|---|---|
| A-01 | Sample: questionnaire n = 15 (Director 1, PM 3, SE 4, QS 2, Client 5); interviews n = 14. | CSV; interview analysis; your s15 note | No (stated by you) |
| A-02 | Six system roles: Administrator, Director, Project Manager, Quantity Surveyor, Site Engineer (includes Site Supervisor), Client. No "Authorised staff". | Master prompt s4.1, your s15 note | No |
| A-03 | CSV item numbering is authoritative for all evidence references. | Master prompt s4.4 | No |
| A-04 | MoSCoW: thesis Table 3.6 rule plus overrides O1 to O6 as defined in 04_MoSCoW_Recalculation.md. Requirement priority = highest final priority of its mapped items. | Thesis; master prompt s4.5 | Yes: O5 (owner directive) and the "highest item" rule are new |
| A-05 | Director: read access to all projects without assignment; approves revisions and global thresholds; no data entry; no audit-trail access (decided 21/10/2026). Administrator: users, settings, SMS, backups, audit trail; no project editing; no financial detail beyond portfolio counts. | Master prompt s4.6, s7; your answer | Decided |
| A-06 | PM and SE create and edit activities on assigned projects (decided 21/10/2026). Only the PM sets budgeted cost and creates milestones; the API ignores and never returns cost fields for SE. SE also records progress, delay reasons, labour, materials, equipment. | Your answer; hard rule s7 | Decided |
| A-07 | SMS: Critical and High only; per-user daily cap 5 and optional monthly cap; sandbox adapter by default; Arkesel-compatible HTTP adapter. No SMS to clients; clients get in-app notices only (decided 21/10/2026). | Interviews; master prompt s4.7; your answer | Yes: cap values |
| A-08 | SMS recalculates to Could (E24 3.47, E25 3.07; +1 interviews, -1 external service). It is delivered first after the Should items, in Iteration 2, matching your stage plan. | Recalculation | Yes |
| A-09 | Targets: API summary 2 s p95; page load 3 s on throttled mobile; 20 concurrent users; RPO 24 h; RTO 4 h; refresh 60 s. | Master prompt s4.9; interviews | Yes |
| A-10 | PDF: Dompdf (pinned) with a print-stylesheet fallback. | Master prompt s4.9 | Yes |
| A-11 | PHP target: the lower of your XAMPP version and the host version, minimum 8.1. Not assumed until env_check output arrives. | Master prompt s5 | Pending your output |
| A-12 | Completion %: cost-weighted when any activity has a budgeted cost (unpriced activities tracked by status only); duration-weighted and labelled when none do. Only approved progress counts. | Thesis s2.2.7; master prompt s9 | Yes |
| A-13 | Budget alerts: A5 at 80%; A6 above 100% (in-app); A6b above 110% (SMS). CPI and SPI warning at 0.90; milestone due-soon 7 days; stale data 7 days; delay SMS above 14 days. | Thesis Table 3.11; DIR-01 | Yes (defaults are configurable) |
| A-14 | Delay reasons include "Work stoppage" and "Safety incident", which raise A15 (Critical, SMS). No safety module. | Master prompt s4.12 | No |
| A-15 | Progress photos: Could, one per entry, ~200 KB, stored in DB or object storage, hidden from clients by default. | Master prompt s4.13 | Yes: client visibility |
| A-16 | Offline: installable web app (service worker) with cached shell and read views, encrypted local queue for SE entries, idempotent sync, server re-validation; proposed Should, Iteration 2. Draft autosave (FR-37) merges into it. Q1 not answered on 21/10/2026, so this default stands. | Your s8.17; F2; SE-01, SE-04 | Default applied |
| A-17 | Dark/light toggle is Must as an owner directive (Bootstrap colour modes). | Master prompt s0 | No |
| A-18 | Synthetic data only: three demo projects shaped like P1 road and drainage, P2 building, P3 classroom block; fictional users; labelled DEMO. | Master prompt s4.15 | No |
| A-19 | Session idle timeout 20 minutes; lockout after 5 failed logins for 15 minutes; password minimum 10 characters. | Security defaults (ASVS L1) | Yes |
| A-20 | Client payment card shows contract sum, total certified, total paid, outstanding; no certificate line detail beyond dates and status. | Interviews s4.2; D22 | Yes |
| A-21 | MVP scope is large (40 Must requirements, many small). If week 4 shows slippage, Iteration 1 keeps all O1 items and core KPIs, and moves FR-26 secondary reports to Iteration 2. | Thesis Table 3.1 plan | Noted risk |
| A-22 | Project folder for code and docs: `lubie-dashboard` inside your SYSTEM BUILDING folder for now; moved to `C:\xampp\htdocs\lubie-dashboard` in Stage 3 with your approval. | Working protocol | Yes |
