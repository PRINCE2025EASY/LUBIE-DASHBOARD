<!doctype html>
<html lang="en-GB" data-bs-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= $e($csrf) ?>">
<meta name="base-path" content="<?= $e($basePath) ?>">
<title><?= $e($title ?? 'Lubie') ?> · Lubie Project Monitoring</title>
<link rel="manifest" href="<?= $e($url('/manifest.webmanifest')) ?>">
<link rel="icon" href="<?= $e($url('/assets/icon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= $e($asset('vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= $e($asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
<link rel="stylesheet" href="<?= $e($asset('css/app.css')) ?>">
<script src="<?= $e($asset('js/theme-init.js')) ?>"></script>
</head>
<body class="bg-body-tertiary">
<main class="container py-4 public-main"><?= $content ?></main>
<script type="module" src="<?= $e($asset('js/public.js')) ?>"></script>
</body>
</html>
