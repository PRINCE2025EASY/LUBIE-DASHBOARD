// API client: CSRF header, single JSON error format, timeouts, "waking server" handling.
const meta = n => document.querySelector(`meta[name="${n}"]`)?.content || '';
export const base = meta('base-path');
let csrf = meta('csrf-token');
export const setCsrf = t => { if (t) csrf = t; };

export class ApiError extends Error {
  constructor(status, body) {
    super(body?.error?.message || `Request failed (${status})`);
    this.status = status; this.code = body?.error?.code; this.fields = body?.error?.fields || {}; this.network = status === 0;
  }
}

export async function api(method, path, body, { timeout = 15000, raw = false } = {}) {
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), timeout);
  const opts = { method, headers: { Accept: 'application/json' }, credentials: 'same-origin', signal: ctrl.signal };
  if (method !== 'GET') { opts.headers['X-CSRF-Token'] = csrf; }
  if (body !== undefined) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
  let res;
  try { res = await fetch(base + '/api/v1' + path, opts); }
  catch (e) { throw new ApiError(0, { error: { message: 'No connection. Your data was not saved.' } }); }
  finally { clearTimeout(timer); }
  if (raw) return res;
  if (res.status === 204) return null;
  let json = null;
  try { json = await res.json(); } catch (e) { /* non-JSON */ }
  if (res.status === 401 && !path.startsWith('/auth/login')) { location.href = base + '/login?expired=1&next=' + encodeURIComponent(location.pathname); throw new ApiError(401, json); }
  if (json?.error?.code === 'PASSWORD_CHANGE_REQUIRED') { location.href = base + '/password'; }
  if (!res.ok) throw new ApiError(res.status, json);
  return json;
}
export const get = (p, o) => api('GET', p, undefined, o).then(r => r?.data ?? r);
export const post = (p, b, o) => api('POST', p, b ?? {}, o).then(r => r?.data ?? r);
export const patch = (p, b) => api('PATCH', p, b).then(r => r?.data ?? r);
export const put = (p, b) => api('PUT', p, b).then(r => r?.data ?? r);
export const del = p => api('DELETE', p).then(r => r?.data ?? r);

// Show field errors under inputs; keep the user's input (form resilience, FR-37).
export function showErrors(form, err) {
  form.querySelectorAll('.is-invalid').forEach(e => e.classList.remove('is-invalid'));
  const box = form.querySelector('.form-msg');
  for (const [name, msg] of Object.entries(err.fields || {})) {
    const input = form.querySelector(`[name="${name}"]`);
    const fb = form.querySelector(`.invalid-feedback[data-for="${name}"]`);
    if (input) input.classList.add('is-invalid');
    if (fb) fb.textContent = msg;
  }
  if (box) {
    box.className = 'form-msg alert alert-danger';
    box.textContent = (err.network ? '' : 'Not saved. ') + err.message;
  }
}
export function showOk(form, text) {
  form.querySelectorAll('.is-invalid').forEach(e => e.classList.remove('is-invalid'));
  const box = form.querySelector('.form-msg');
  if (box) { box.className = 'form-msg alert alert-success'; box.textContent = text; }
}
export function formData(form) {
  const o = {};
  for (const el of form.elements) {
    if (!el.name || el.disabled) continue;
    if (el.type === 'checkbox') o[el.name] = el.checked; else o[el.name] = el.value;
  }
  return o;
}
