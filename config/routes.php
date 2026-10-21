<?php

declare(strict_types=1);

use Lubie\Controllers\AdminController as Adm;
use Lubie\Controllers\AuthController as Auth;
use Lubie\Controllers\CostController as Cost;
use Lubie\Controllers\DashboardController as Dash;
use Lubie\Controllers\HealthController as Health;
use Lubie\Controllers\PageController as Page;
use Lubie\Controllers\ProgressController as Prog;
use Lubie\Controllers\ProjectController as Proj;
use Lubie\Controllers\ReportController as Rep;
use Lubie\Controllers\ThresholdController as Thr;
use Lubie\Http\Router;

/*
 * Every route declares its guard (deny by default). Guard syntax:
 *   public | auth | perm:<permission>   plus optional flags: ,pwchange (allowed while a
 *   password change is pending) ,nocsrf (token-authenticated machine endpoint only).
 * Project scope is enforced inside services on every ID.
 */
return static function (Router $r): void {
    $v = '/api/v1';
    // Pages
    $r->add('GET', '/', [Auth::class, 'home'], 'public');
    $r->add('GET', '/login', [Auth::class, 'loginPage'], 'public');
    $r->add('GET', '/password', [Auth::class, 'passwordPage'], 'auth,pwchange');
    $r->add('GET', '/offline', [Page::class, 'offline'], 'public');
    foreach (['admin', 'director', 'pm', 'qs', 'site', 'client'] as $role) {
        $r->add('GET', "/{$role}", [Page::class, 'role'], 'auth');
    }
    $r->add('GET', '/health', [Health::class, 'health'], 'public');

    // Authentication
    $r->add('POST', "$v/auth/login", [Auth::class, 'login'], 'public');
    $r->add('POST', "$v/auth/logout", [Auth::class, 'logout'], 'auth,pwchange');
    $r->add('GET', "$v/auth/me", [Auth::class, 'me'], 'auth,pwchange');
    $r->add('POST', "$v/auth/password", [Auth::class, 'changePassword'], 'perm:password.change,pwchange');
    $r->add('POST', "$v/auth/theme", [Auth::class, 'theme'], 'auth');
    $r->add('GET', "$v/lookups", [Dash::class, 'lookups'], 'auth');

    // Users and administration
    $r->add('GET', "$v/users", [Adm::class, 'users'], 'perm:users.manage');
    $r->add('GET', "$v/roles", [Adm::class, 'roles'], 'perm:users.manage');
    $r->add('POST', "$v/users", [Adm::class, 'createUser'], 'perm:users.manage');
    $r->add('PATCH', "$v/users/{id}", [Adm::class, 'updateUser'], 'perm:users.manage');
    $r->add('POST', "$v/users/{id}/reset-password", [Adm::class, 'resetPassword'], 'perm:users.manage');
    $r->add('POST', "$v/users/{id}/unlock", [Adm::class, 'unlock'], 'perm:users.manage');
    $r->add('GET', "$v/settings", [Adm::class, 'settings'], 'perm:settings.manage');
    $r->add('PATCH', "$v/settings/{key}", [Adm::class, 'updateSetting'], 'perm:settings.manage');
    $r->add('POST', "$v/settings/sms/test", [Adm::class, 'testSms'], 'perm:settings.manage');
    $r->add('GET', "$v/audit", [Adm::class, 'audit'], 'perm:audit.view');
    $r->add('GET', "$v/admin/backups", [Adm::class, 'backups'], 'perm:backup.manage');
    $r->add('POST', "$v/admin/backups", [Adm::class, 'createBackup'], 'perm:backup.manage');
    $r->add('POST', "$v/backup/run", [Adm::class, 'scheduledBackup'], 'public,nocsrf');
    $r->add('GET', "$v/dashboard/admin", [Dash::class, 'admin'], 'perm:dashboard.admin');

    // Projects and assignments
    $r->add('GET', "$v/projects", [Proj::class, 'index'], 'perm:projects.view');
    $r->add('POST', "$v/projects", [Proj::class, 'store'], 'perm:projects.manage');
    $r->add('GET', "$v/projects/{id}", [Proj::class, 'show'], 'perm:projects.view');
    $r->add('PATCH', "$v/projects/{id}", [Proj::class, 'update'], 'perm:projects.manage');
    $r->add('POST', "$v/projects/{id}/status", [Proj::class, 'status'], 'perm:projects.manage');
    $r->add('GET', "$v/projects/{id}/assignments", [Proj::class, 'assignments'], 'perm:assignments.manage');
    $r->add('POST', "$v/projects/{id}/assignments", [Proj::class, 'assign'], 'perm:assignments.manage');
    $r->add('GET', "$v/projects/{id}/assignable-users", [Proj::class, 'assignable'], 'perm:assignments.manage');
    $r->add('DELETE', "$v/projects/{id}/assignments/{userId}", [Proj::class, 'unassign'], 'perm:assignments.manage');

    // Activities and milestones
    $r->add('GET', "$v/projects/{id}/activities", [Proj::class, 'activities'], 'perm:activities.view');
    $r->add('POST', "$v/projects/{id}/activities", [Proj::class, 'storeActivity'], 'perm:activities.manage');
    $r->add('PATCH', "$v/activities/{id}", [Proj::class, 'updateActivity'], 'perm:activities.manage');
    $r->add('POST', "$v/activities/{id}/archive", [Proj::class, 'archiveActivity'], 'perm:activities.cost');
    $r->add('PUT', "$v/activities/{id}/dependencies", [Proj::class, 'dependencies'], 'perm:activities.cost');
    $r->add('GET', "$v/projects/{id}/milestones", [Proj::class, 'milestones'], 'perm:milestones.view');
    $r->add('POST', "$v/projects/{id}/milestones", [Proj::class, 'storeMilestone'], 'perm:milestones.manage');
    $r->add('PATCH', "$v/milestones/{id}", [Proj::class, 'updateMilestone'], 'perm:milestones.manage');

    // Progress, approval, offline sync
    $r->add('GET', "$v/activities/{id}/progress", [Prog::class, 'history'], 'perm:progress.view');
    $r->add('POST', "$v/activities/{id}/progress", [Prog::class, 'submit'], 'perm:progress.submit');
    $r->add('GET', "$v/projects/{id}/progress-queue", [Prog::class, 'queue'], 'perm:progress.approve');
    $r->add('GET', "$v/projects/{id}/my-progress", [Prog::class, 'mine'], 'perm:progress.submit');
    $r->add('POST', "$v/progress/{id}/approve", [Prog::class, 'approve'], 'perm:progress.approve');
    $r->add('POST', "$v/progress/{id}/return", [Prog::class, 'returnEntry'], 'perm:progress.approve');
    $r->add('POST', "$v/sync", [Prog::class, 'sync'], 'perm:sync.submit');

    // Cost and payments
    $r->add('GET', "$v/projects/{id}/budgets", [Cost::class, 'budgets'], 'perm:cost.view');
    $r->add('POST', "$v/projects/{id}/budgets", [Cost::class, 'requestBudget'], 'perm:budget.request');
    $r->add('POST', "$v/budgets/{id}/submit", [Cost::class, 'submitBudget'], 'perm:budget.request');
    $r->add('POST', "$v/budgets/{id}/approve", [Cost::class, 'approveBudget'], 'perm:budget.approve');
    $r->add('POST', "$v/budgets/{id}/reject", [Cost::class, 'rejectBudget'], 'perm:budget.approve');
    $r->add('GET', "$v/budgets/pending", [Cost::class, 'pendingBudgets'], 'perm:budget.approve');
    $r->add('GET', "$v/projects/{id}/expenditures", [Cost::class, 'expenditures'], 'perm:cost.view');
    $r->add('POST', "$v/projects/{id}/expenditures", [Cost::class, 'recordExpenditure'], 'perm:expenditure.record');
    $r->add('POST', "$v/expenditures/{id}/void", [Cost::class, 'voidExpenditure'], 'perm:expenditure.record');
    $r->add('GET', "$v/projects/{id}/certificates", [Cost::class, 'certificates'], 'perm:cost.view');
    $r->add('POST', "$v/projects/{id}/certificates", [Cost::class, 'createCertificate'], 'perm:certificates.record');
    $r->add('POST', "$v/certificates/{id}/certify", [Cost::class, 'certify'], 'perm:certificates.record');
    $r->add('POST', "$v/certificates/{id}/payments", [Cost::class, 'pay'], 'perm:certificates.record');

    // Dashboards, polling, alerts
    $r->add('GET', "$v/projects/{id}/dashboard", [Dash::class, 'project'], 'perm:dashboard.project');
    $r->add('GET', "$v/dashboard/portfolio", [Dash::class, 'portfolio'], 'perm:dashboard.portfolio');
    $r->add('GET', "$v/client/summary", [Dash::class, 'client'], 'perm:client.summary');
    $r->add('GET', "$v/changes", [Dash::class, 'changes'], 'auth');
    $r->add('GET', "$v/alerts", [Dash::class, 'alerts'], 'perm:alerts.view');
    $r->add('POST', "$v/alerts/{id}/read", [Dash::class, 'readAlert'], 'perm:alerts.view');
    $r->add('POST', "$v/alerts/{id}/acknowledge", [Dash::class, 'ackAlert'], 'perm:alerts.view');

    // Thresholds
    $r->add('GET', "$v/thresholds", [Thr::class, 'index'], 'perm:thresholds.view');
    $r->add('PUT', "$v/thresholds/global/{key}", [Thr::class, 'setGlobal'], 'perm:thresholds.global');
    $r->add('PUT', "$v/projects/{id}/thresholds/{key}", [Thr::class, 'setProject'], 'perm:thresholds.project');
    $r->add('POST', "$v/threshold-proposals", [Thr::class, 'propose'], 'perm:thresholds.propose');
    $r->add('GET', "$v/threshold-proposals", [Thr::class, 'proposals'], 'perm:thresholds.decide');
    $r->add('POST', "$v/threshold-proposals/{id}/decide", [Thr::class, 'decide'], 'perm:thresholds.decide');

    // Reports
    $r->add('GET', "$v/reports", [Rep::class, 'types'], 'perm:reports.export');
    $r->add('GET', "$v/reports/{type}", [Rep::class, 'export'], 'perm:reports.export');
};
