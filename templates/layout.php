<?php
/** @var \Lubie\Security\AuthUser|null $user */
$theme = $user?->theme ?? 'system';
?>
<!doctype html>
<html lang="en-GB" data-bs-theme="light" data-theme-pref="<?= $e($theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= $e($csrf) ?>">
<meta name="base-path" content="<?= $e($basePath) ?>">
<meta name="role" content="<?= $e($user?->role ?? '') ?>">
<meta name="chart-src" content="<?= $e($asset('vendor/chartjs/chart.umd.min.js')) ?>">
<title><?= $e($title ?? 'Dashboard') ?> · Lubie Project Monitoring</title>
<link rel="manifest" href="<?= $e($url('/manifest.webmanifest')) ?>">
<link rel="icon" href="<?= $e($url('/assets/icon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= $e($asset('vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= $e($asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
<link rel="stylesheet" href="<?= $e($asset('css/app.css')) ?>">
<script src="<?= $e($asset('js/theme-init.js')) ?>"></script>
<?php foreach (['lib/api.js', 'lib/dom.js', 'lib/outbox.js', 'lib/drafts.js', 'lib/poll.js', 'views/common.js'] as $m): ?>
<link rel="modulepreload" href="<?= $e($url('/assets/js/' . $m)) ?>">
<?php endforeach; ?>
</head>
<body>
<a class="visually-hidden-focusable" href="#main">Skip to content</a>
<nav class="navbar navbar-expand bg-body border-bottom sticky-top app-nav">
  <div class="container-fluid">
    <a class="navbar-brand fw-semibold" href="<?= $e($url('/')) ?>"><i class="bi bi-cone-striped" aria-hidden="true"></i> Lubie</a>
    <span class="navbar-text small d-none d-sm-inline text-truncate"><?= $e($user?->fullName ?? '') ?> · <?= $e($title ?? '') ?></span>
    <div class="ms-auto d-flex align-items-center gap-1">
      <span id="net-status" class="badge text-bg-secondary d-none" role="status"></span>
      <?php if ($user && $user->role !== 'admin'): ?>
      <button class="btn btn-outline-secondary btn-touch position-relative" id="alerts-btn" type="button" aria-label="Alerts">
        <i class="bi bi-bell" aria-hidden="true"></i><span id="alerts-count" class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger d-none">0</span>
      </button>
      <?php endif; ?>
      <button class="btn btn-outline-secondary btn-touch" id="theme-btn" type="button" aria-label="Switch dark or light mode"><i class="bi bi-circle-half" aria-hidden="true"></i></button>
      <div class="dropdown">
        <button class="btn btn-outline-secondary btn-touch" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu"><i class="bi bi-person-circle" aria-hidden="true"></i></button>
        <ul class="dropdown-menu dropdown-menu-end">
          <li><span class="dropdown-item-text small"><?= $e($user?->fullName ?? '') ?></span></li>
          <li><a class="dropdown-item" href="<?= $e($url('/password')) ?>">Change password</a></li>
          <li><button class="dropdown-item" id="logout-btn" type="button">Log out</button></li>
        </ul>
      </div>
    </div>
  </div>
</nav>
<div id="banner" class="alert alert-warning rounded-0 mb-0 d-none" role="alert"></div>
<main id="main" class="container-fluid py-3"><?= $content ?></main>
<div class="offcanvas offcanvas-end" tabindex="-1" id="alerts-panel" aria-labelledby="alerts-title">
  <div class="offcanvas-header"><h2 class="offcanvas-title h5" id="alerts-title">Alerts</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button></div>
  <div class="offcanvas-body" id="alerts-body"></div>
</div>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toasts" aria-live="polite"></div>
<script defer src="<?= $e($asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script type="module" src="<?= $e($asset('js/app.js')) ?>"></script>
</body>
</html>
