// Application shell: session info, theme, logout, alert bell, network status, service worker,
// hash router per role. Every permission is enforced on the server; the UI only hides clutter.
import { get, post, setCsrf, base } from './lib/api.js';
import { h, clear, badge, fmtDateTime } from './lib/dom.js';
import * as outbox from './lib/outbox.js';
import { clearAllDrafts } from './lib/drafts.js';

const roleModules = { site: './views/site.js', pm: './views/pm.js', qs: './views/qs.js', director: './views/director.js', client: './views/client.js', admin: './views/admin.js' };
export const state = { me: null, refresh: 60, poller: null };

function toast(text, tone = 'success') {
  const t = h('div', { class: `toast align-items-center text-bg-${tone} border-0`, role: 'status' },
    h('div', { class: 'd-flex' }, h('div', { class: 'toast-body' }, text), h('button', { type: 'button', class: 'btn-close btn-close-white me-2 m-auto', 'data-bs-dismiss': 'toast', 'aria-label': 'Close' })));
  document.getElementById('toasts')?.append(t);
  new window.bootstrap.Toast(t, { delay: 4000 }).show();
  t.addEventListener('hidden.bs.toast', () => t.remove());
}
export { toast };

export function banner(text, tone = 'warning') {
  const b = document.getElementById('banner');
  if (!b) return;
  if (!text) { b.classList.add('d-none'); return; }
  b.className = `alert alert-${tone} rounded-0 mb-0`; b.textContent = text;
}

function setupTheme() {
  document.getElementById('theme-btn')?.addEventListener('click', async () => {
    const dark = document.documentElement.getAttribute('data-bs-theme') !== 'dark';
    document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
    try { localStorage.setItem('lubie-theme', dark ? 'dark' : 'light'); } catch (e) { /* ignore */ }
    window.dispatchEvent(new Event('theme-changed'));
    try { await post('/auth/theme', { theme: dark ? 'dark' : 'light' }); } catch (e) { /* offline: kept locally */ }
  });
}

async function logout() {
  const pending = await outbox.count().catch(() => 0);
  if (pending && !window.confirm(`${pending} entries on this phone are not sent yet. Logging out deletes them. Log out anyway?`)) return;
  try { await post('/auth/logout'); } catch (e) { /* still clear local data */ }
  await outbox.wipe();
  clearAllDrafts();
  navigator.serviceWorker?.controller?.postMessage('logout');
  location.href = base + '/login';
}

export async function refreshAlerts() {
  const body = document.getElementById('alerts-body');
  if (!body) return;
  clear(body).append(h('p', {}, 'Loading…'));
  try {
    const list = await get('/alerts?status=open');
    clear(body);
    if (!list.length) { body.append(h('p', { class: 'text-body-secondary' }, 'No open alerts.')); return; }
    for (const a of list) {
      const item = h('div', { class: `border rounded p-2 mb-2 ${a.status === 'delivered' ? 'border-primary' : ''}` },
        h('div', { class: 'd-flex justify-content-between gap-2' }, badge(a.severity, a.severity[0].toUpperCase() + a.severity.slice(1)), h('small', { class: 'text-body-secondary' }, a.project_code)),
        h('div', { class: 'fw-semibold mt-1' }, a.title), h('div', { class: 'small' }, a.description),
        h('div', { class: 'small text-body-secondary mt-1' }, 'Raised ' + fmtDateTime(a.created_at) + (a.sms_status !== 'none' ? ` · SMS: ${a.sms_status}` : '')),
        a.status !== 'acknowledged' ? h('button', { class: 'btn btn-sm btn-outline-secondary mt-2', type: 'button', onclick: async e => {
          e.target.disabled = true; await post(`/alerts/${a.recipient_id}/acknowledge`); refreshAlerts(); } }, 'Acknowledge') : h('span', { class: 'small text-success' }, ' Acknowledged'));
      body.append(item);
      if (a.status === 'delivered') post(`/alerts/${a.recipient_id}/read`).catch(() => {});
    }
  } catch (e) { clear(body).append(h('p', { class: 'text-danger' }, e.message)); }
}

function setupAlerts() {
  const btn = document.getElementById('alerts-btn');
  if (!btn) return;
  const panel = new window.bootstrap.Offcanvas('#alerts-panel');
  btn.addEventListener('click', () => { panel.show(); refreshAlerts(); });
}
export function setUnread(n) {
  const el = document.getElementById('alerts-count');
  if (!el) return;
  el.textContent = n > 99 ? '99+' : String(n);
  el.classList.toggle('d-none', !n);
  el.setAttribute('aria-label', `${n} unread alerts`);
}

function setupNetwork() {
  const st = document.getElementById('net-status');
  let seq = 0;
  const update = async () => {
    const mine = ++seq;
    const n = await outbox.count().catch(() => 0);
    if (mine !== seq) return; // a newer update started; ignore this stale count
    if (!navigator.onLine) { st.className = 'badge text-bg-warning'; st.textContent = `Offline${n ? ` · ${n} waiting` : ''}`; }
    else if (n) { st.className = 'badge text-bg-info'; st.textContent = `${n} waiting to sync`; }
    else { st.className = 'badge d-none'; st.textContent = ''; }
  };
  window.addEventListener('online', async () => { banner(''); await outbox.sync().then(r => r.sent && toast(`${r.sent} saved entries sent.`)).catch(() => {}); update(); window.dispatchEvent(new Event('data-changed')); });
  window.addEventListener('offline', () => { banner('You are offline. You can view saved pages and record progress; it will be sent when the connection returns.'); update(); });
  window.addEventListener('outbox-changed', update);
  update();
  if (navigator.onLine) outbox.sync().then(r => r.sent && toast(`${r.sent} saved entries sent.`)).catch(() => {});
}

async function registerSw() {
  if (!('serviceWorker' in navigator)) return;
  try { await navigator.serviceWorker.register(base + '/sw.js', { scope: base + '/' }); } catch (e) { /* not available over plain http on LAN */ }
}

async function route(mod) {
  const app = document.getElementById('app');
  const hash = location.hash.replace(/^#/, '') || '/';
  state.poller?.stop();
  state.poller = null;
  for (const [pattern, view] of Object.entries(mod.routes)) {
    const re = new RegExp('^' + pattern.replace(/:\w+/g, '([^/]+)') + '$');
    const m = hash.match(re);
    if (m) {
      try { await view(app, ...m.slice(1)); }
      catch (e) { clear(app).append(h('div', { class: 'alert alert-danger' }, e.message || 'Could not load this page.')); }
      app.focus?.();
      return;
    }
  }
  location.hash = '#/';
}

async function boot() {
  setupTheme();
  document.getElementById('logout-btn')?.addEventListener('click', logout);
  const app = document.getElementById('app');
  try {
    state.me = await get('/auth/me');
  } catch (e) {
    if (e.network) banner('You are offline. Showing saved pages where available.');
    state.me = JSON.parse(sessionStorage.getItem('lubie-me') || 'null');
    if (!state.me) { if (app) clear(app).append(h('div', { class: 'alert alert-warning' }, 'You are offline and this page has not been opened before on this device.')); return; }
  }
  try { sessionStorage.setItem('lubie-me', JSON.stringify({ ...state.me, csrf: undefined, outbox_key: undefined })); } catch (e) { /* ignore */ }
  setCsrf(state.me.csrf);
  state.refresh = state.me.refresh_seconds || 60;
  outbox.rememberKey(state.me.outbox_key);
  setupAlerts();
  setupNetwork();
  registerSw();
  if (document.getElementById('password-form')) { (await import('./views/password.js')).mount(); return; }
  if (!app) return;
  const mod = await import(roleModules[app.dataset.role]);
  window.addEventListener('hashchange', () => route(mod));
  route(mod);
}
boot();
