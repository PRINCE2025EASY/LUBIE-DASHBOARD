import { post, formData, showErrors, setCsrf } from '../lib/api.js';
export function mount() {
  const f = document.getElementById('password-form');
  f.querySelectorAll('input').forEach(i => { i.classList.add('form-control'); i.after(Object.assign(document.createElement('div'), { className: 'invalid-feedback' })); i.nextSibling.dataset.for = i.name; });
  f.querySelector('.form-msg').className = 'form-msg';
  f.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = f.querySelector('button[type=submit]'); btn.disabled = true;
    try { const r = await post('/auth/password', formData(f)); setCsrf(r.csrf); location.href = r.redirect; }
    catch (err) { showErrors(f, err); btn.disabled = false; }
  });
}
