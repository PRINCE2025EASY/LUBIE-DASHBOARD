<div class="row justify-content-center">
  <div class="col-12 col-sm-9 col-md-6 col-lg-4">
    <div class="text-center mb-4">
      <i class="bi bi-cone-striped display-5 text-warning" aria-hidden="true"></i>
      <h1 class="h4 mt-2 mb-0">Lubie Construction Works</h1>
      <p class="text-body-secondary">Project Monitoring Dashboard</p>
    </div>
    <?php if (!empty($expired)): ?>
      <div class="alert alert-info" role="status">Your session ended after a period of inactivity. Please log in again.</div>
    <?php endif; ?>
    <form id="login-form" class="card card-body shadow-sm" novalidate>
      <input type="hidden" name="next" value="<?= $e($next ?? '') ?>">
      <div class="mb-3">
        <label for="username" class="form-label">Username</label>
        <input id="username" name="username" class="form-control form-control-lg" autocomplete="username" autocapitalize="none" required maxlength="50">
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <div class="input-group">
          <input id="password" name="password" type="password" class="form-control form-control-lg" autocomplete="current-password" required maxlength="200">
          <button class="btn btn-outline-secondary" type="button" id="toggle-pw" aria-label="Show password" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
        </div>
      </div>
      <div id="login-error" class="alert alert-danger d-none" role="alert"></div>
      <button class="btn btn-primary btn-lg w-100" type="submit" id="login-btn">Log in</button>
      <p class="small text-body-secondary mt-3 mb-0">Problems logging in? Ask your administrator to reset your password.</p>
    </form>
    <p class="small text-body-secondary text-center mt-3" id="wake-msg" hidden>Waking up the server, please wait…</p>
  </div>
</div>
