# Lubie Construction Works: Project Monitoring Dashboard

MSc IT research prototype (University of Cape Coast). A mobile-first PHP and MySQL dashboard
for one construction company: projects, activities, progress with Project Manager approval,
cost-weighted KPIs and earned value, budgets, expenditure, valuations and payments, rule-based
alerts, reports, audit trail, encrypted backups, and offline progress capture.

All data in this repository is synthetic. No real people, projects or amounts.

## Stack

PHP 8.2+, MariaDB 10.4+ or MySQL 8, plain PHP (no framework), Bootstrap 5.3, Chart.js 4,
Dompdf 3. Vendor front-end files are in `public/assets/vendor`.

## Quick start

See `docs/INSTALL_XAMPP.md`.

## Roles

Administrator, Director, Project Manager, Quantity Surveyor, Site Engineer/Supervisor, Client.
One login page; the server decides each dashboard. Site Engineers never see money. Clients see
one summary page per project. The Administrator manages users and system set-up but has no
financial detail. Only the Administrator sees the audit trail.

## Tests

    composer install
    php bin/console.php test-db:reset --yes    # needs DB_TEST_NAME ending in _test
    vendor/bin/phpunit

## Documents

- `docs/stage1/`: findings, issues, assumptions, MoSCoW, requirements, traceability
- `docs/DESIGN_SPECIFICATION.md`, `docs/DATA_DICTIONARY.md`, `docs/USE_CASE_SPECIFICATIONS.md`
- `docs/diagrams/`: draw.io files and previews
- `PROJECT_STATE.md`: current stage, decisions, checks, open items
