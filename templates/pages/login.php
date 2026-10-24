<div class="login-card" role="region" aria-labelledby="login-title">
  <div class="text-center mb-3">
    <img src="<?= $e($asset('img/company-logo.png')) ?>" alt="Lubie Construction Works logo" class="login-logo" width="338" height="283">
    <h1 id="login-title" class="h4 mt-2 mb-0">Lubie Construction Works</h1>
    <p class="text-body-secondary mb-0">Project Monitoring Dashboard</p>
  </div>
  <?php if (!empty($expired)): ?>
    <div class="alert alert-info py-2" role="status">Your session ended after a period of inactivity. Please log in again.</div>
  <?php endif; ?>
  <form id="login-form" novalidate>
    <input type="hidden" name="next" value="<?= $e($next ?? '') ?>">
    <div class="mb-3">
      <label for="username" class="form-label">Username</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-person" aria-hidden="true"></i></span>
        <input id="username" name="username" class="form-control form-control-lg" autocomplete="username" autocapitalize="none" required maxlength="50">
      </div>
    </div>
    <div class="mb-3">
      <label for="password" class="form-label">Password</label>
      <div class="input-group">
        <span class="input-group-text"><i class="bi bi-lock" aria-hidden="true"></i></span>
        <input id="password" name="password" type="password" class="form-control form-control-lg" autocomplete="current-password" required maxlength="200">
        <button class="btn btn-outline-secondary" type="button" id="toggle-pw" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
      </div>
    </div>
    <div id="login-error" class="alert alert-danger d-none py-2" role="alert"></div>
    <button class="btn btn-brand btn-lg w-100" type="submit" id="login-btn">Log in</button>
    <p class="small text-body-secondary mt-3 mb-0 text-center">Problems logging in? Ask your administrator to reset your password.</p>
  </form>
  <p class="small text-body-secondary text-center mt-2 mb-0" id="wake-msg" hidden>Waking up the server, please wait…</p>
</div>
<p class="login-footer">© Lubie Construction Works · Project monitoring prototype</p>
