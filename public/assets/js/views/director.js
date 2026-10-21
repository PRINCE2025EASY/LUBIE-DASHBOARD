// Director views: cross-project portfolio, project view (read-only), approvals of budget
// revisions and threshold proposals, global thresholds. No audit trail (decision 21/10/2026).
import { get, post, put } from '../lib/api.js';
import { h, clear, card, table, fmtMoney, fmtPct, fmtNum, fmtDate, fmtDateTime, spinner, badge, icon } from '../lib/dom.js';
import { projectOverview, pageHeader, tabs, watch, reportsCard, statusLabel } from './common.js';
import { budgetsCard } from './qs.js';
import { toast } from '../app.js';

const dirTabs = (id, active) => tabs(id, active, [['overview', 'Overview'], ['cost', 'Cost and payments']]);

export const routes = {
  '/': portfolio,
  '/approvals': approvals,
  '/thresholds': thresholds,
  '/project/:id/overview': (app, id) => projectOverview(app, id, { tabsFor: dirTabs }),
  '/project/:id/cost': costView,
};

function nav(active) {
  return h('ul', { class: 'nav nav-pills mb-3' }, [['/', 'Portfolio'], ['/approvals', 'Approvals'], ['/thresholds', 'Company thresholds']].map(([p, l]) =>
    h('li', { class: 'nav-item' }, h('a', { class: `nav-link ${p === active ? 'active' : ''}`, href: '#' + p }, l))));
}

async function portfolio(app) {
  clear(app).append(spinner());
  const rows = await get('/dashboard/portfolio');
  clear(app).append(pageHeader('Portfolio of projects'), nav('/'));
  app.append(card('All projects', table(['Project', 'Status', 'Complete', 'Planned', 'SV', 'CV', 'SPI', 'CPI', 'Budget used', 'Delayed', 'Next milestone', 'Alerts', ''],
    rows.map(p => [h('a', { href: `#/project/${p.id}/overview` }, `${p.code} ${p.name}`), statusLabel(p.status), fmtPct(p.completion_pct), fmtPct(p.planned_pct),
      fmtMoney(p.sv), fmtMoney(p.cv), fmtNum(p.spi), fmtNum(p.cpi), fmtPct(p.budget_consumed_pct), String(p.delayed_count),
      p.next_milestone ? `${p.next_milestone.name} (${fmtDate(p.next_milestone.due_date)})` : '–', String(p.open_high_alerts),
      badge(p.tone, { ok: 'On track', warning: 'Watch', critical: 'Needs action' }[p.tone])]), 'No projects yet.')));
  watch(null, -1, () => portfolio(app));
}

async function approvals(app) {
  clear(app).append(spinner());
  const [budgets, proposals] = await Promise.all([get('/budgets/pending'), get('/threshold-proposals')]);
  clear(app).append(pageHeader('Waiting for your decision'), nav('/approvals'));
  const decide = async (path, body) => { try { await post(path, body); toast('Decision saved.'); approvals(app); } catch (e) { toast(e.message, 'danger'); } };
  app.append(card('Budget revisions', budgets.length ? h('div', {}, budgets.map(b => {
    const note = h('input', { class: 'form-control', placeholder: 'Note (required to reject)', maxlength: 300 });
    return h('div', { class: 'border rounded p-2 mb-2' }, h('strong', {}, `${b.project_code} v${b.version_no}: ${fmtMoney(b.amount)}`),
      h('div', { class: 'small' }, `Current ${fmtMoney(b.current_amount)} · requested by ${b.requested_by_name} on ${fmtDateTime(b.requested_at)}`), h('div', { class: 'small fst-italic' }, b.reason),
      h('div', { class: 'd-flex flex-wrap gap-2 mt-2' }, note,
        h('button', { class: 'btn btn-success', type: 'button', onclick: () => decide(`/budgets/${b.id}/approve`, { note: note.value }) }, icon('check-lg'), ' Approve'),
        h('button', { class: 'btn btn-outline-danger', type: 'button', onclick: () => decide(`/budgets/${b.id}/reject`, { note: note.value }) }, 'Reject')));
  })) : h('p', { class: 'text-body-secondary mb-0' }, 'No budget revisions are waiting.')));
  app.append(card('Threshold proposals from QS', table(['Project', 'Threshold', 'Proposed', 'By', ''], proposals.map(t => [t.project_code || 'Company-wide', t.setting_key, String(t.proposed_value), t.proposed_by_name,
    h('div', { class: 'd-flex gap-1' }, h('button', { class: 'btn btn-sm btn-success', type: 'button', onclick: () => decide(`/threshold-proposals/${t.id}/decide`, { approve: true }) }, 'Approve'),
      h('button', { class: 'btn btn-sm btn-outline-danger', type: 'button', onclick: () => decide(`/threshold-proposals/${t.id}/decide`, { approve: false }) }, 'Reject'))]), 'No proposals are waiting.')));
}

async function thresholds(app) {
  clear(app).append(spinner());
  const th = await get('/thresholds');
  clear(app).append(pageHeader('Company thresholds and limits'), nav('/thresholds'), h('p', {}, 'Project Managers can set project values only inside the minimum and maximum you set here.'));
  app.append(card('Global thresholds', table(['Threshold', 'Value', 'Minimum', 'Maximum', ''], th.map(t => {
    const v = h('input', { class: 'form-control form-control-sm', value: t.global ?? '', 'aria-label': 'Value ' + t.key });
    const mn = h('input', { class: 'form-control form-control-sm', value: t.min_allowed ?? '', 'aria-label': 'Minimum ' + t.key });
    const mx = h('input', { class: 'form-control form-control-sm', value: t.max_allowed ?? '', 'aria-label': 'Maximum ' + t.key });
    return [t.key.replaceAll('_', ' '), v, mn, mx, h('button', { class: 'btn btn-sm btn-outline-primary', type: 'button', onclick: async () => {
      try { await put(`/thresholds/global/${t.key}`, { value: v.value, min_allowed: mn.value, max_allowed: mx.value }); toast('Saved.'); } catch (e) { toast(e.message, 'danger'); }
    } }, 'Save')];
  }))));
}

async function costView(app, id) {
  clear(app).append(spinner());
  const [d, certs, b] = await Promise.all([get(`/projects/${id}/dashboard`), get(`/projects/${id}/certificates`), budgetsCard(id)]);
  clear(app).append(pageHeader(`${d.project.code} cost and payments`), dirTabs(id, 'cost'), b,
    card('Valuations and payments', table(['Certificate', 'Submitted', 'Certified', 'Paid', 'Outstanding', 'Status'], certs.map(c => [c.cert_no, fmtDate(c.submitted_date),
      fmtMoney(c.amount_certified), fmtMoney(c.paid), fmtMoney(c.outstanding), c.overdue ? badge('critical', 'Overdue') : c.status])), 'No certificates.'), reportsCard(id));
  watch(id, d.change_seq, () => costView(app, id));
}
