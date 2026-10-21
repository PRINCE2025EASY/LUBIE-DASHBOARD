<div class="row justify-content-center">
  <div class="col-12 col-md-6 col-lg-5">
    <h1 class="h4">Change password</h1>
    <?php if ($user && $user->mustChangePassword): ?>
      <div class="alert alert-info">For security, choose a new password before you continue.</div>
    <?php endif; ?>
    <form id="password-form" class="card card-body" novalidate>
      <div class="mb-3"><label class="form-label" for="current_password">Current password</label>
        <input class="form-control" type="password" id="current_password" name="current_password" autocomplete="current-password" required></div>
      <div class="mb-3"><label class="form-label" for="new_password">New password</label>
        <input class="form-control" type="password" id="new_password" name="new_password" autocomplete="new-password" required aria-describedby="pw-help">
        <div id="pw-help" class="form-text">At least 10 characters, with letters and numbers.</div></div>
      <div class="mb-3"><label class="form-label" for="confirm_password">Repeat new password</label>
        <input class="form-control" type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required></div>
      <div class="form-msg"></div>
      <button class="btn btn-primary btn-lg" type="submit">Save new password</button>
    </form>
  </div>
</div>
