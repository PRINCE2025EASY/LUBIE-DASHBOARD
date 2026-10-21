// Administrator views: system summary, users, settings, audit trail, backups, and project
// set-up and team assignments. No budgets, expenditure or payments (financial detail is not
// an automatic Administrator right).
import { api, get, post, patch, del, formData, showErrors, showOk } from '../lib/api.js';
import { h, clear, card, table, field, select, fmtDateTime, spinner, badge, icon } from '../lib/dom.js';
import { pageHeader, statusLabel } from './common.js';
import { projectForm } from './pm.js';
import { toast } from '../app.js';

export const routes = {
  '/': summary,
  '/users': users,
  '/users/new': app => userForm(app, null),
  '/users/:id': (app, id) => userForm(app, Number(id)),
  '/projects': projects,
  '/projects/new': app => { projectForm(app, null); app.prepend(nav('/projects')); },
  '/projects/:id/team': team,
  '/project/:id/activities': (app, id) => { location.hash = `#/projects/${id}/team`; },
  '/settings': settings,
  '/audit': audit,
  '/backups': backups,
};

const NAV = [['/', 'Summary'], ['/users', 'Users'], ['/projects', 'Projects'], ['/settings', 'Settings'], ['/audit', 'Audit trail'], ['/backups', 'Backups']];
function nav(active) {
  return h('ul', { class: 'nav nav-pills flex-wrap mb-3' }, NAV.map(([p, l]) =>
    h('li', { class: 'nav-item' }, h('a', { class: `nav-link ${p === active ? 'active' : ''}`, href: '#' + p, 'aria-current': p === active ? 'page' : null }, l))));
}
const userStatus = s => ({ active: badge('ok', 'Active'), locked: badge('critical', 'Locked'), deactivated: badge('information', 'Deactivated') }[s] || s);
const kb = n => (n === null || n === undefined ? '' : `${(Number(n) / 1024).toFixed(0)} KB`);

async function summary(app) {
  clear(app).append(spinner());
  const s = await get('/dashboard/admin');
  clear(app).append(pageHeader('System administration'), nav('/'));
  const counts = rows => table(['Item', 'Count'], rows);
  app.append(h('div', { class: 'row g-3' },
    h('div', { class: 'col-md-6' }, card('Projects by status', counts(s.projects_by_status.map(r => [statusLabel(r.status), String(r.n)])))),
    h('div', { class: 'col-md-6' }, card('Users by role', counts(s.users_by_role.map(r => [`${r.role} (${r.status})`, String(r.n)])))),
    h('div', { class: 'col-md-6' }, card('Open alerts', counts(s.open_alerts_by_severity.map(r => [r.severity, String(r.n)])))),
    h('div', { class: 'col-md-6' }, card('Health', h('dl', { class: 'row mb-0' },
      h('dt', { class: 'col-7 fw-normal' }, 'Locked accounts'), h('dd', { class: 'col-5 text-end' }, s.locked_users ? badge('critical', String(s.locked_users)) : '0'),
      h('dt', { class: 'col-7 fw-normal' }, 'Last backup'),
      h('dd', { class: 'col-5 text-end' }, s.last_backup ? `${fmtDateTime(s.last_backup.started_at)} (${s.last_backup.status})` : badge('warning', 'None yet')))))));
  app.append(card('Set-up checks', table(['Check', 'Result', 'Detail'], (s.checks || []).map(([label, ok, detail]) => [label, ok ? badge('ok', 'OK') : badge('warning', 'Action needed'), detail]))));
}

async function users(app) {
  clear(app).append(spinner());
  const list = await get('/users');
  clear(app).append(pageHeader('Users', h('a', { class: 'btn btn-primary btn-touch', href: '#/users/new' }, icon('person-plus'), ' New user')), nav('/users'));
  app.append(card('All users', table(['Name', 'Username', 'Role', 'Phone', 'Status', 'Last login', ''], list.map(u => [
    u.full_name, u.username, u.role_name, u.phone_e164 || '', userStatus(u.status), fmtDateTime(u.last_login_at),
    h('div', { class: 'd-flex flex-wrap gap-1' },
      h('a', { class: 'btn btn-sm btn-outline-primary', href: `#/users/${u.id}` }, 'Edit'),
      u.status === 'locked' ? h('button', { class: 'btn btn-sm btn-outline-warning', type: 'button', onclick: async () => {
        try { await post(`/users/${u.id}/unlock`); toast('Account unlocked.'); users(app); } catch (e) { toast(e.message, 'danger'); }
      } }, 'Unlock') : null)]), 'No users yet.')));
}

async function userForm(app, id) {
  clear(app).append(spinner());
  const [roles, all] = await Promise.all([get('/roles'), id ? get('/users') : Promise.resolve([])]);
  const u = id ? all.find(x => x.id === id) : null;
  if (id && !u) { clear(app).append(nav('/users'), h('div', { class: 'alert alert-warning' }, 'User not found.')); return; }
  clear(app).append(pageHeader(u ? `Edit ${u.full_name}` : 'New user'), nav('/users'));
  const form = h('form', { class: 'card card-body mb-3', novalidate: true, autocomplete: 'off' },
    u ? null : field('Username', h('input', { name: 'username', maxlength: 50, autocapitalize: 'none' }), 'Lower-case letters, numbers, dot, dash or underscore.'),
    field('Full name', h('input', { name: 'full_name', value: u?.full_name || '', maxlength: 120 })),
    field('Role', select('role', roles.map(r => [r.code, r.name]), u?.role_code || '', 'Choose a role')),
    field('Phone', h('input', { name: 'phone', value: u?.phone_e164 || '', inputmode: 'tel', placeholder: '+233XXXXXXXXX' }), 'Used for SMS alerts. Format +233.'),
    field('Email', h('input', { name: 'email', type: 'email', value: u?.email || '', maxlength: 190 })),
    u ? field('Status', select('status', [['active', 'Active'], ['deactivated', 'Deactivated']], u.status === 'locked' ? 'active' : u.status)) : null,
    u ? null : field('Temporary password', h('input', { name: 'temporary_password', type: 'password', autocomplete: 'new-password' }),
      'Give it to the user in person. They must change it at first login.'),
    h('div', { class: 'form-msg' }), h('button', { class: 'btn btn-primary btn-touch', type: 'submit' }, u ? 'Save changes' : 'Create user'));
  form.addEventListener('submit', async e => {
    e.preventDefault();
    try {
      if (u) { await patch(`/users/${u.id}`, formData(form)); toast('User saved.'); location.hash = '#/users'; }
      else { await post('/users', formData(form)); toast('User created.'); location.hash = '#/users'; }
    } catch (err) { showErrors(form, err); }
  });
  app.append(form);
  if (!u) return;
  const reset = h('form', { class: 'card card-body', novalidate: true, autocomplete: 'off' }, h('h2', { class: 'h6' }, 'Reset password'),
    field('New temporary password', h('input', { name: 'temporary_password', type: 'password', autocomplete: 'new-password' }),
      'This signs the user out everywhere. They must choose a new password at next login.'),
    h('div', { class: 'form-msg' }), h('button', { class: 'btn btn-outline-danger btn-touch', type: 'submit' }, 'Reset password'));
  reset.addEventListener('submit', async e => {
    e.preventDefault();
    if (!window.confirm(`Reset the password for ${u.full_name}?`)) return;
    try { await post(`/users/${u.id}/reset-password`, formData(reset)); reset.reset(); showOk(reset, 'Password reset. Tell the user in person.'); } catch (err) { showErrors(reset, err); }
  });
  app.append(reset);
}

async function projects(app) {
  clear(app).append(spinner());
  const list = await get('/projects');
  clear(app).append(pageHeader('Project set-up', h('a', { class: 'btn btn-primary btn-touch', href: '#/projects/new' }, icon('plus-lg'), ' New project')), nav('/projects'),
    h('p', {}, 'You set up projects and teams here. Budgets and payments stay with the QS, Project Manager and Director.'));
  app.append(card('Projects', table(['Project', 'Status', ''], list.map(p => [`${p.code} ${p.name}`, statusLabel(p.status),
    h('a', { class: 'btn btn-sm btn-outline-primary', href: `#/projects/${p.id}/team` }, 'Team')]), 'No projects yet.')));
}

async function team(app, id) {
  clear(app).append(spinner());
  const [p, members, candidates] = await Promise.all([get(`/projects/${id}`), get(`/projects/${id}/assignments`), get(`/projects/${id}/assignable-users`)]);
  clear(app).append(pageHeader(`${p.code} team`), nav('/projects'));
  app.append(card('Assigned users', table(['Name', 'Role', ''], members.map(t => [t.full_name, t.role_name,
    h('button', { class: 'btn btn-sm btn-outline-danger', type: 'button', onclick: async () => {
      if (!window.confirm(`Remove ${t.full_name} from this project?`)) return;
      try { await del(`/projects/${id}/assignments/${t.user_id}`); team(app, id); } catch (e) { toast(e.message, 'danger'); }
    } }, 'Remove')]), 'Nobody is assigned yet.')));
  const form = h('form', { class: 'card card-body', novalidate: true }, h('h2', { class: 'h6' }, 'Assign a user'),
    field('User', select('user_id', candidates.map(c => [c.id, `${c.full_name} (${c.role_name})`]), '', 'Choose a user')),
    h('div', { class: 'form-msg' }), h('button', { class: 'btn btn-primary btn-touch', type: 'submit' }, 'Assign'));
  form.addEventListener('submit', async e => {
    e.preventDefault();
    try { await post(`/projects/${id}/assignments`, formData(form)); toast('Assigned.'); team(app, id); } catch (err) { showErrors(form, err); }
  });
  app.append(form);
}

const SETTING_LABELS = {
  refresh_interval_seconds: 'Dashboard refresh (seconds)', session_idle_minutes: 'Sign out after idle (minutes)', lockout_threshold: 'Lock account after failed logins',
  lockout_minutes: 'Lock duration (minutes)', password_min_length: 'Minimum password length', sms_enabled: 'SMS alerts on', sms_daily_cap_per_user: 'SMS per user per day',
  sms_monthly_cap_total: 'SMS per month (all users)', offline_enabled: 'Offline mode on',
};
async function settings(app) {
  clear(app).append(spinner());
  const s = await get('/settings');
  const byKey = Object.fromEntries(s.settings.map(x => [x.setting_key, x.value]));
  clear(app).append(pageHeader('System settings'), nav('/settings'));
  app.append(card('Settings', table(['Setting', 'Value', ''], s.editable.map(key => {
    const isBool = key === 'sms_enabled' || key === 'offline_enabled';
    const inp = isBool ? select('value', [['1', 'On'], ['0', 'Off']], byKey[key] ?? '0') : h('input', { name: 'value', value: byKey[key] ?? '', inputmode: 'numeric' });
    inp.classList.add(isBool ? 'form-select' : 'form-control', isBool ? 'form-select-sm' : 'form-control-sm');
    inp.setAttribute('aria-label', SETTING_LABELS[key] || key);
    return [SETTING_LABELS[key] || key, inp, h('button', { class: 'btn btn-sm btn-outline-primary', type: 'button', onclick: async () => {
      try { await patch(`/settings/${key}`, { value: inp.value }); toast('Setting saved.'); } catch (e) { toast(e.fields?.value || e.message, 'danger'); }
    } }, 'Save')];
  }))));
  const sms = h('form', { class: 'card card-body', novalidate: true }, h('h2', { class: 'h6' }, `Test SMS (provider: ${s.sms_provider})`),
    s.sms_provider === 'sandbox' ? h('p', { class: 'small' }, 'Sandbox mode. No message reaches a phone. Set a real provider in .env to send SMS.') : null,
    field('Phone', h('input', { name: 'phone', inputmode: 'tel', placeholder: '+233XXXXXXXXX' })),
    h('div', { class: 'form-msg' }), h('button', { class: 'btn btn-outline-primary btn-touch', type: 'submit' }, 'Send test'));
  sms.addEventListener('submit', async e => {
    e.preventDefault();
    try { const r = await post('/settings/sms/test', formData(sms)); showOk(sms, `${r.accepted ? 'Accepted by provider.' : 'Provider refused: ' + (r.error || 'unknown error')} ${r.note}`); } catch (err) { showErrors(sms, err); }
  });
  app.append(sms);
}

async function audit(app, page = 1, filters = {}) {
  clear(app).append(spinner());
  const q = new URLSearchParams({ ...filters, page: String(page), per_page: '25' });
  const res = await api('GET', '/audit?' + q.toString());
  clear(app).append(pageHeader('Audit trail'), nav('/audit'));
  const f = h('form', { class: 'row g-2 align-items-end mb-3' },
    h('div', { class: 'col-6 col-md-2' }, field('Username', h('input', { name: 'user', value: filters.user || '' }))),
    h('div', { class: 'col-6 col-md-2' }, field('Action', h('input', { name: 'action', value: filters.action || '', list: 'audit-actions' }))),
    h('div', { class: 'col-6 col-md-2' }, field('Record type', h('input', { name: 'entity', value: filters.entity || '' }))),
    h('div', { class: 'col-6 col-md-2' }, field('From', h('input', { name: 'from', type: 'date', value: filters.from || '' }))),
    h('div', { class: 'col-6 col-md-2' }, field('To', h('input', { name: 'to', type: 'date', value: filters.to || '' }))),
    h('div', { class: 'col-6 col-md-2 mb-3' }, h('button', { class: 'btn btn-primary w-100', type: 'submit' }, 'Filter')),
    h('datalist', { id: 'audit-actions' }, ['login', 'login_failed', 'logout', 'create', 'update', 'approve', 'reject', 'access_denied', 'settings_change', 'backup'].map(a => h('option', { value: a }))));
  f.addEventListener('submit', e => {
    e.preventDefault();
    const d = Object.fromEntries(Object.entries(formData(f)).filter(([, v]) => v !== '' && v !== null));
    audit(app, 1, d);
  });
  app.append(f);
  const changes = r => {
    const o = r.old_values ? JSON.parse(r.old_values) : null;
    const n = r.new_values ? JSON.parse(r.new_values) : null;
    if (!o && !n) return '';
    const keys = [...new Set([...Object.keys(o || {}), ...Object.keys(n || {})])];
    return h('div', { class: 'small' }, keys.map(k => h('div', {}, `${k}: ${o?.[k] ?? '–'} → ${n?.[k] ?? '–'}`)));
  };
  app.append(card(`Entries (${res.meta.total})`, table(['When', 'User', 'Action', 'Record', 'Change', 'IP'], res.data.map(r => [
    fmtDateTime(r.created_at), r.username || r.username_attempted || 'System', r.action, `${r.entity_type}${r.entity_id ? ' #' + r.entity_id : ''}`, changes(r), r.ip_address || '']), 'No entries match.')));
  const pages = Math.max(1, Math.ceil(res.meta.total / res.meta.per_page));
  app.append(h('div', { class: 'd-flex gap-2 align-items-center' },
    h('button', { class: 'btn btn-outline-secondary', type: 'button', disabled: page <= 1, onclick: () => audit(app, page - 1, filters) }, 'Previous'),
    h('span', {}, `Page ${page} of ${pages}`),
    h('button', { class: 'btn btn-outline-secondary', type: 'button', disabled: page >= pages, onclick: () => audit(app, page + 1, filters) }, 'Next')));
}

async function backups(app) {
  clear(app).append(spinner());
  const log = await get('/admin/backups');
  clear(app).append(pageHeader('Backups'), nav('/backups'));
  const btn = h('button', { class: 'btn btn-primary btn-touch', type: 'button' }, icon('download'), ' Create and download backup');
  const msg = h('div', { class: 'small mt-2', role: 'status' });
  btn.addEventListener('click', async () => {
    btn.disabled = true; msg.textContent = 'Creating backup. Keep this page open.';
    try {
      const res = await api('POST', '/admin/backups', {}, { raw: true, timeout: 120000 });
      if (!res.ok) { let j = null; try { j = await res.json(); } catch (e) { /* ignore */ } throw new Error(j?.error?.message || `Backup failed (${res.status})`); }
      const name = (res.headers.get('Content-Disposition') || '').match(/filename="?([^";]+)"?/)?.[1] || 'lubie-backup.sql.gz.enc';
      const blob = await res.blob();
      const a = h('a', { href: URL.createObjectURL(blob), download: name });
      document.body.append(a); a.click(); a.remove();
      setTimeout(() => URL.revokeObjectURL(a.href), 10000);
      msg.textContent = `Saved ${name}. SHA-256 ${res.headers.get('X-Backup-SHA256') || ''}. Store it off this computer.`;
      setTimeout(() => backups(app), 1500);
    } catch (e) { msg.textContent = e.message; toast(e.message, 'danger'); }
    finally { btn.disabled = false; }
  });
  app.append(card('Manual backup', h('div', {}, h('p', {}, 'The file is encrypted with the backup key in .env. Without that key nobody can read it, including you.'), btn, msg)));
  app.append(card('Backup history', table(['Started', 'Type', 'Status', 'File', 'Size', 'By'], log.map(b => [fmtDateTime(b.started_at), b.kind,
    b.status === 'succeeded' ? badge('ok', 'Succeeded') : b.status === 'failed' ? badge('critical', 'Failed') : badge('warning', 'Started'),
    b.file_name || '', kb(b.byte_size), b.username || 'Scheduler']), 'No backups yet.')));
}
