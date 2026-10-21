// Login page: posts credentials, never a role (the server decides the dashboard).
import { post } from './lib/api.js';
const f = document.getElementById('login-form');
if (f) {
  const pw = document.getElementById('password');
  const tg = document.getElementById('toggle-pw');
  tg.addEventListener('click', () => {
    const show = pw.type === 'password'; pw.type = show ? 'text' : 'password';
    tg.setAttribute('aria-pressed', String(show)); tg.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    tg.firstElementChild.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
  });
  f.addEventListener('submit', async e => {
    e.preventDefault();
    const err = document.getElementById('login-error'); err.classList.add('d-none');
    const btn = document.getElementById('login-btn'); btn.disabled = true; btn.textContent = 'Checking…';
    const slow = setTimeout(() => { document.getElementById('wake-msg').hidden = false; }, 4000);
    try {
      const r = await post('/auth/login', { username: f.username.value, password: f.password.value, next: f.next.value }, { timeout: 60000 });
      location.href = r.redirect;
    } catch (ex) {
      err.textContent = ex.status === 403 && ex.code === 'CSRF_FAILED' ? 'The page expired. Reload and try again.' : ex.message;
      err.classList.remove('d-none'); f.password.value = ''; f.password.focus();
    } finally { clearTimeout(slow); document.getElementById('wake-msg').hidden = true; btn.disabled = false; btn.textContent = 'Log in'; }
  });
}
