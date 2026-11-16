# API reference

Generated from `config/routes.php` and `config/permissions.php` by `tools/gen_api_reference.php`. Do not edit by hand.

All API paths start with `/api/v1`. Requests and responses are JSON. State-changing requests need the `X-CSRF-Token` header from `GET /api/v1/auth/me`. Errors use one format: `{"error": {"code", "message", "request_id", "fields"}}` with 401 (not logged in), 403 (role), 404 (unknown or out-of-scope ID), 409 (conflict), 422 (validation), 429 (throttled), 503 (database waking).

Project scope is checked inside the services: an ID outside the user's projects returns 404, never 403, so IDs cannot be probed.

| Method | Path | Handler | Who | Notes |
|---|---|---|---|---|
| GET | `/` | AuthController::home | Anyone |  |
| GET | `/login` | AuthController::loginPage | Anyone |  |
| GET | `/password` | AuthController::passwordPage | Any logged-in user | allowed during forced password change |
| GET | `/offline` | PageController::offline | Anyone |  |
| GET | `/admin` | PageController::role | Any logged-in user |  |
| GET | `/director` | PageController::role | Any logged-in user |  |
| GET | `/pm` | PageController::role | Any logged-in user |  |
| GET | `/qs` | PageController::role | Any logged-in user |  |
| GET | `/site` | PageController::role | Any logged-in user |  |
| GET | `/client` | PageController::role | Any logged-in user |  |
| GET | `/health` | HealthController::health | Anyone |  |
| POST | `/api/v1/auth/login` | AuthController::login | Anyone |  |
| POST | `/api/v1/auth/logout` | AuthController::logout | Any logged-in user | allowed during forced password change |
| GET | `/api/v1/auth/me` | AuthController::me | Any logged-in user | allowed during forced password change |
| POST | `/api/v1/auth/password` | AuthController::changePassword | Admin, Director, PM, QS, Site, Client (`password.change`) | allowed during forced password change |
| POST | `/api/v1/auth/theme` | AuthController::theme | Any logged-in user |  |
| GET | `/api/v1/lookups` | DashboardController::lookups | Any logged-in user |  |
| GET | `/api/v1/users` | AdminController::users | Admin (`users.manage`) |  |
| GET | `/api/v1/roles` | AdminController::roles | Admin (`users.manage`) |  |
| POST | `/api/v1/users` | AdminController::createUser | Admin (`users.manage`) |  |
| PATCH | `/api/v1/users/{id}` | AdminController::updateUser | Admin (`users.manage`) |  |
| POST | `/api/v1/users/{id}/reset-password` | AdminController::resetPassword | Admin (`users.manage`) |  |
| POST | `/api/v1/users/{id}/unlock` | AdminController::unlock | Admin (`users.manage`) |  |
| GET | `/api/v1/settings` | AdminController::settings | Admin (`settings.manage`) |  |
| PATCH | `/api/v1/settings/{key}` | AdminController::updateSetting | Admin (`settings.manage`) |  |
| POST | `/api/v1/settings/sms/test` | AdminController::testSms | Admin (`settings.manage`) |  |
| GET | `/api/v1/audit` | AdminController::audit | Admin (`audit.view`) |  |
| GET | `/api/v1/admin/backups` | AdminController::backups | Admin (`backup.manage`) |  |
| POST | `/api/v1/admin/backups` | AdminController::createBackup | Admin (`backup.manage`) |  |
| POST | `/api/v1/backup/run` | AdminController::scheduledBackup | Anyone | Bearer BACKUP_TOKEN, no session |
| GET | `/api/v1/dashboard/admin` | DashboardController::admin | Admin (`dashboard.admin`) |  |
| GET | `/api/v1/projects` | ProjectController::index | Admin, Director, PM, QS, Site, Client (`projects.view`) |  |
| POST | `/api/v1/projects` | ProjectController::store | Admin, PM (`projects.manage`) |  |
| GET | `/api/v1/projects/{id}` | ProjectController::show | Admin, Director, PM, QS, Site, Client (`projects.view`) |  |
| PATCH | `/api/v1/projects/{id}` | ProjectController::update | Admin, PM (`projects.manage`) |  |
| POST | `/api/v1/projects/{id}/status` | ProjectController::status | Admin, PM (`projects.manage`) |  |
| GET | `/api/v1/projects/{id}/assignments` | ProjectController::assignments | Admin, PM (`assignments.manage`) |  |
| POST | `/api/v1/projects/{id}/assignments` | ProjectController::assign | Admin, PM (`assignments.manage`) |  |
| GET | `/api/v1/projects/{id}/assignable-users` | ProjectController::assignable | Admin, PM (`assignments.manage`) |  |
| DELETE | `/api/v1/projects/{id}/assignments/{userId}` | ProjectController::unassign | Admin, PM (`assignments.manage`) |  |
| GET | `/api/v1/projects/{id}/activities` | ProjectController::activities | Director, PM, QS, Site (`activities.view`) |  |
| POST | `/api/v1/projects/{id}/activities` | ProjectController::storeActivity | PM, Site (`activities.manage`) |  |
| PATCH | `/api/v1/activities/{id}` | ProjectController::updateActivity | PM, Site (`activities.manage`) |  |
| POST | `/api/v1/activities/{id}/archive` | ProjectController::archiveActivity | PM (`activities.cost`) |  |
| PUT | `/api/v1/activities/{id}/dependencies` | ProjectController::dependencies | PM (`activities.cost`) |  |
| GET | `/api/v1/projects/{id}/milestones` | ProjectController::milestones | Director, PM, QS, Site, Client (`milestones.view`) |  |
| POST | `/api/v1/projects/{id}/milestones` | ProjectController::storeMilestone | PM (`milestones.manage`) |  |
| PATCH | `/api/v1/milestones/{id}` | ProjectController::updateMilestone | PM (`milestones.manage`) |  |
| GET | `/api/v1/activities/{id}/progress` | ProgressController::history | Director, PM, QS, Site (`progress.view`) |  |
| POST | `/api/v1/activities/{id}/progress` | ProgressController::submit | PM, Site (`progress.submit`) |  |
| GET | `/api/v1/projects/{id}/progress-queue` | ProgressController::queue | PM (`progress.approve`) |  |
| GET | `/api/v1/projects/{id}/my-progress` | ProgressController::mine | PM, Site (`progress.submit`) |  |
| POST | `/api/v1/progress/{id}/approve` | ProgressController::approve | PM (`progress.approve`) |  |
| POST | `/api/v1/progress/{id}/return` | ProgressController::returnEntry | PM (`progress.approve`) |  |
| POST | `/api/v1/sync` | ProgressController::sync | PM, Site (`sync.submit`) |  |
| GET | `/api/v1/projects/{id}/budgets` | CostController::budgets | Director, PM, QS (`cost.view`) |  |
| POST | `/api/v1/projects/{id}/budgets` | CostController::requestBudget | PM, QS (`budget.request`) |  |
| POST | `/api/v1/budgets/{id}/submit` | CostController::submitBudget | PM, QS (`budget.request`) |  |
| POST | `/api/v1/budgets/{id}/approve` | CostController::approveBudget | Director (`budget.approve`) |  |
| POST | `/api/v1/budgets/{id}/reject` | CostController::rejectBudget | Director (`budget.approve`) |  |
| GET | `/api/v1/budgets/pending` | CostController::pendingBudgets | Director (`budget.approve`) |  |
| GET | `/api/v1/projects/{id}/expenditures` | CostController::expenditures | Director, PM, QS (`cost.view`) |  |
| POST | `/api/v1/projects/{id}/expenditures` | CostController::recordExpenditure | QS (`expenditure.record`) |  |
| POST | `/api/v1/expenditures/{id}/void` | CostController::voidExpenditure | QS (`expenditure.record`) |  |
| GET | `/api/v1/projects/{id}/certificates` | CostController::certificates | Director, PM, QS (`cost.view`) |  |
| POST | `/api/v1/projects/{id}/certificates` | CostController::createCertificate | QS (`certificates.record`) |  |
| POST | `/api/v1/certificates/{id}/certify` | CostController::certify | QS (`certificates.record`) |  |
| POST | `/api/v1/certificates/{id}/payments` | CostController::pay | QS (`certificates.record`) |  |
| GET | `/api/v1/projects/{id}/resources` | ResourceController::index | Director, PM, QS, Site (`resources.view`) |  |
| POST | `/api/v1/projects/{id}/resources` | ResourceController::store | PM, Site (`resources.record`) |  |
| PATCH | `/api/v1/resources/{id}` | ResourceController::update | PM, Site (`resources.record`) |  |
| GET | `/api/v1/resources/{id}/usage` | ResourceController::history | Director, PM, QS, Site (`resources.view`) |  |
| POST | `/api/v1/resources/{id}/usage` | ResourceController::usage | PM, Site (`resources.record`) |  |
| POST | `/api/v1/resources/{id}/equipment-status` | ResourceController::equipmentStatus | PM, Site (`resources.record`) |  |
| POST | `/api/v1/projects/{id}/material-requests` | ResourceController::createRequest | PM, Site (`resources.record`) |  |
| POST | `/api/v1/material-requests/{id}/approve` | ResourceController::approveRequest | PM (`requests.decide`) |  |
| POST | `/api/v1/material-requests/{id}/cancel` | ResourceController::cancelRequest | PM, Site (`resources.record`) |  |
| POST | `/api/v1/material-requests/{id}/deliver` | ResourceController::deliverRequest | PM, Site (`resources.record`) |  |
| GET | `/api/v1/projects/{id}/dashboard` | DashboardController::project | Director, PM, QS, Site (`dashboard.project`) |  |
| GET | `/api/v1/dashboard/portfolio` | DashboardController::portfolio | Director (`dashboard.portfolio`) |  |
| GET | `/api/v1/client/summary` | DashboardController::client | Client (`client.summary`) |  |
| GET | `/api/v1/changes` | DashboardController::changes | Any logged-in user |  |
| GET | `/api/v1/alerts` | DashboardController::alerts | Director, PM, QS, Site, Client (`alerts.view`) |  |
| POST | `/api/v1/alerts/{id}/read` | DashboardController::readAlert | Director, PM, QS, Site, Client (`alerts.view`) |  |
| POST | `/api/v1/alerts/{id}/acknowledge` | DashboardController::ackAlert | Director, PM, QS, Site, Client (`alerts.view`) |  |
| GET | `/api/v1/thresholds` | ThresholdController::index | Director, PM, QS (`thresholds.view`) |  |
| PUT | `/api/v1/thresholds/global/{key}` | ThresholdController::setGlobal | Director (`thresholds.global`) |  |
| PUT | `/api/v1/projects/{id}/thresholds/{key}` | ThresholdController::setProject | PM (`thresholds.project`) |  |
| POST | `/api/v1/threshold-proposals` | ThresholdController::propose | QS (`thresholds.propose`) |  |
| GET | `/api/v1/threshold-proposals` | ThresholdController::proposals | Director (`thresholds.decide`) |  |
| POST | `/api/v1/threshold-proposals/{id}/decide` | ThresholdController::decide | Director (`thresholds.decide`) |  |
| GET | `/api/v1/reports` | ReportController::types | Admin, Director, PM, QS, Client (`reports.export`) |  |
| GET | `/api/v1/reports/{type}` | ReportController::export | Admin, Director, PM, QS, Client (`reports.export`) |  |

93 routes.
