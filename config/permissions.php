<?php

declare(strict_types=1);

/*
 * Role permission matrix (Design Specification s6). Data, not code: tests iterate over it.
 * Project scope (assigned projects only; Director all projects read) is checked separately
 * by Authorizer::requireProject() on every ID.
 * Roles: admin, director, pm, qs, site, client.
 */
return [
    'password.change'      => ['admin', 'director', 'pm', 'qs', 'site', 'client'],
    'alerts.view'          => ['director', 'pm', 'qs', 'site', 'client'],

    'users.manage'         => ['admin'],
    'settings.manage'      => ['admin'],
    'audit.view'           => ['admin'],
    'backup.manage'        => ['admin'],
    'dashboard.admin'      => ['admin'],

    'projects.view'        => ['admin', 'director', 'pm', 'qs', 'site', 'client'],
    'projects.manage'      => ['admin', 'pm'],
    'assignments.manage'   => ['admin', 'pm'],

    'activities.view'      => ['director', 'pm', 'qs', 'site'],
    'activities.manage'    => ['pm', 'site'],
    'activities.cost'      => ['pm'],
    'milestones.view'      => ['director', 'pm', 'qs', 'site', 'client'],
    'milestones.manage'    => ['pm'],

    'progress.view'        => ['director', 'pm', 'qs', 'site'],
    'progress.submit'      => ['pm', 'site'],
    'progress.approve'     => ['pm'],
    'sync.submit'          => ['pm', 'site'],

    'cost.view'            => ['director', 'pm', 'qs'],
    'budget.request'       => ['pm', 'qs'],
    'budget.approve'       => ['director'],
    'expenditure.record'   => ['qs'],
    'certificates.record'  => ['qs'],

    'resources.view'       => ['director', 'pm', 'qs', 'site'],
    'resources.record'     => ['pm', 'site'],
    'requests.decide'      => ['pm'],

    'dashboard.project'    => ['director', 'pm', 'qs', 'site'],
    'dashboard.portfolio'  => ['director'],
    'client.summary'       => ['client'],

    'thresholds.view'      => ['director', 'pm', 'qs'],
    'thresholds.global'    => ['director'],
    'thresholds.project'   => ['pm'],
    'thresholds.propose'   => ['qs'],
    'thresholds.decide'    => ['director'],

    'reports.export'       => ['admin', 'director', 'pm', 'qs', 'client'],
];
