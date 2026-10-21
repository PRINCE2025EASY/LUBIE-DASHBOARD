// Views shared by staff roles: project list, project dashboard, reports, milestones, alerts.
import { get, base } from '../lib/api.js';
import { h, clear, card, kpiCard, table, badge, scheduleBadge, fmtMoney, fmtPct, fmtNum, fmtDate, fmtDateTime, spinner, icon } from '../lib/dom.js';
import { startPolling } from '../lib/poll.js';
import { sCurve, budgetBars } from '../lib/charts.js';
import { state, setUnread, banner } from '../app.js';

export const statusTone = s => ({ active: 'ok', planning: 'information', on_hold: 'warning', completed: 'ok', archived: 'information' }[s] || 'information');
export const statusLabel = s => ({ active: 'Active', planning: 'Planning', on_hold: 'On hold', completed: 'Completed', archived: 'Archived' }[s] || s);

export function pageHeader(title, ...actions) {
  return h('div', { class: 'd-flex flex-wrap align-items-center gap-2 mb-3' }, h('h1', { class: 'h4 mb-0 flex-grow-1' }, title), ...actions);
}
export function lastUpdated(at, checked) {
  return h('p', { class: 'small text-body-secondary mb-2 last-updated' }, icon('clock-history'), ' Data last updated: ', h('strong', {}, fmtDateTime(at) || 'never'),
    checked ? ` · Checked ${fmtDateTime(checked)}` : '');
}
export function tabs(projectId, active, items) {
  return h('ul', { class: 'nav nav-pills flex-nowrap overflow-auto mb-3 project-tabs' }, items.map(([key, label]) =>
    h('li', { class: 'nav-item' }, h('a', { class: `nav-link text-nowrap ${key === active ? 'active' : ''}`, href: `#/project/${projectId}/${key}`, 'aria-current': key === active ? 'page' : null }, label))));
}

/** Watch one project (or all) and re-render on change. */
export function watch(projectId, seq, rerender) {
  state.poller?.stop();
  state.poller = startPolling({
    projectId, seq, interval: state.refresh,
    onChange: () => rerender(),
    onStatus: s => {
      if (s.ok) { setUnread(s.unread); banner(''); document.querySelectorAll('.checked-at').forEach(e => { e.textContent = 'Checked ' + fmtDateTime(s.checked); }); }
      else if (s.waking) banner('Waking up the server… retrying.', 'info');
      else if (navigator.onLine) banner('Connection problem. Showing the last data received; retrying automatically.');
    },
  });
}

export async function projectList(app, { title = 'My projects', onOpen, actions = [] } = {}) {
  clear(app).append(spinner());
  const projects = await get('/projects');
  clear(app).append(pageHeader(title, ...actions));
  if (!projects.length) { app.append(h('p', { class: 'text-body-secondary' }, 'No projects are assigned to you yet. Ask your Project Manager or Administrator.')); return; }
  const grid = h('div', { class: 'row g-3' });
  for (const p of projects) {
    grid.append(h('div', { class: 'col-12 col-md-6 col-xl-4' }, h('a', { class: 'card h-100 text-decoration-none project-card', href: onOpen ? onOpen(p) : `#/project/${p.id}/overview` },
      h('div', { class: 'card-body' },
        h('div', { class: 'd-flex justify-content-between gap-2' }, h('span', { class: 'fw-semibold' }, p.code), badge(statusTone(p.status), statusLabel(p.status))),
        h('div', { class: 'mt-1' }, p.name), h('div', { class: 'small text-body-secondary' }, p.location),
        h('div', { class: 'small mt-2' }, 'Planned ', fmtDate(p.planned_start), ' to ', fmtDate(p.planned_finish)),
        p.is_demo ? h('span', { class: 'badge text-bg-secondary mt-2' }, 'Demonstration data') : null))));
  }
  app.append(grid);
  watch(null, -1, () => projectList(app, { title, onOpen, actions }));
}

const FORMULAS = {
  completion: 'Completion = EV / BAC x 100, using budgeted-cost weighting and only PM-approved progress.',
  planned: 'Planned % = PV / BAC x 100. Each activity is planned to progress evenly between its planned start and finish.',
  spi: 'SPI = EV / PV. Below 1.00 means behind plan. Not shown when PV is 0.',
  cpi: 'CPI = EV / AC. Below 1.00 means the work done cost more than budgeted. Not shown when AC is 0.',
  consumed: 'Budget consumed = actual cost (AC) / current approved budget x 100.',
  currency: 'Days since the last approved progress entry on this project.',
};

/** Project overview dashboard (FR-23). Money widgets only render when the API sends money fields. */
export async function projectOverview(app, id, { tabsFor } = {}) {
  clear(app).append(spinner());
  const d = await get(`/projects/${id}/dashboard`);
  const k = d.kpis, money = 'cpi' in k;
  clear(app);
  app.append(pageHeader(`${d.project.code} ${d.project.name}`, badge(statusTone(d.project.status), statusLabel(d.project.status))));
  if (tabsFor) app.append(tabsFor(id, 'overview'));
  app.append(lastUpdated(d.last_updated, null), h('p', { class: 'small text-body-secondary checked-at' }, 'Checked ' + fmtDateTime(d.checked_at)));
  if (d.project.is_demo) app.append(h('div', { class: 'alert alert-secondary py-1 small' }, 'Demonstration data only. Not a real project.'));
  const tone = (v, warn = 0.9) => v === null || v === undefined ? null : (v < warn ? 'warning' : 'ok');
  const kpis = h('div', { class: 'row g-2 mb-3' },
    kpiCard('Complete', fmtPct(k.completion_pct), FORMULAS.completion + (k.weighting === 'duration' ? ' No activity costs: weighted by duration.' : '')),
    kpiCard('Planned to date', fmtPct(k.planned_pct), FORMULAS.planned),
    kpiCard('SPI', fmtNum(k.spi), FORMULAS.spi, tone(k.spi)),
    money ? kpiCard('CPI', fmtNum(k.cpi), FORMULAS.cpi, tone(k.cpi)) : null,
    money ? kpiCard('Budget used', fmtPct(k.budget_consumed_pct), FORMULAS.consumed, k.budget_consumed_pct === null ? null : k.budget_consumed_pct > 100 ? 'critical' : k.budget_consumed_pct >= 80 ? 'warning' : 'ok') : null,
    kpiCard('Delayed activities', String(k.delayed_count), 'Activities past their planned finish and under 100%.', k.delayed_count ? 'critical' : 'ok'),
    kpiCard('Days since update', k.data_currency_days === null ? 'No approved progress' : String(k.data_currency_days), FORMULAS.currency, k.data_currency_days > 7 ? 'warning' : null),
    kpiCard('Days to planned finish', String(k.days_to_planned_finish), 'Planned finish date minus today. Negative means past the planned finish.'),
  );
  app.append(kpis);
  if (money) {
    app.append(h('div', { class: 'row g-2 mb-3 small' }, ...[['BAC', k.bac], ['PV', k.pv], ['EV', k.ev], ['AC', k.ac], ['SV', k.sv], ['CV', k.cv], ['Budget', k.current_budget], ['Budget variance', k.budget_variance]]
      .map(([l, v]) => h('div', { class: 'col-6 col-md-3' }, h('div', { class: 'border rounded p-2' }, h('div', { class: 'text-body-secondary' }, l), h('div', { class: 'fw-semibold' }, fmtMoney(v)))))));
  }
  const row = h('div', { class: 'row g-3' });
  const lineCanvas = h('canvas', { 'aria-label': 'Planned versus earned progress chart', role: 'img' });
  row.append(h('div', { class: 'col-12 col-xl-7' }, card('Planned versus earned', h('div', { class: 'chart-box' }, lineCanvas))));
  if (money) {
    const barCanvas = h('canvas', { 'aria-label': 'Budget versus expenditure chart', role: 'img' });
    row.append(h('div', { class: 'col-12 col-xl-5' }, card('Budget versus expenditure', h('div', { class: 'chart-box' }, barCanvas))));
    requestAnimationFrame(() => budgetBars(barCanvas, k.current_budget, d.expenditure_by_category || {}));
  }
  app.append(row);
  requestAnimationFrame(() => sCurve(lineCanvas, d.series, money));
  app.append(h('div', { class: 'row g-3' },
    h('div', { class: 'col-12 col-lg-6' }, card('Delayed activities', table(['Activity', 'Planned finish', 'Days late', 'Approved %'],
      d.delayed.map(a => [`${a.code} ${a.name}`, fmtDate(a.planned_finish), String(a.days_late), fmtPct(a.pct, 0)]), 'No delayed activities.'))),
    h('div', { class: 'col-12 col-lg-6' }, card('Milestones', milestoneList(d.milestones)))));
  app.append(card('Activities', table(['Activity', 'Planned', 'Planned %', 'Approved %', 'Status'],
    d.activities.map(a => [`${a.code} ${a.name}`, `${fmtDate(a.planned_start)} – ${fmtDate(a.planned_finish)}`, fmtPct(a.planned_pct, 0), fmtPct(a.pct_complete, 0), scheduleBadge(a)]))));
  app.append(card('Open alerts on this project', d.alerts.length ? h('ul', { class: 'list-unstyled mb-0' }, d.alerts.map(a => h('li', { class: 'mb-2' }, badge(a.severity, a.severity), ' ', h('strong', {}, a.title), h('div', { class: 'small' }, a.description))))
    : h('p', { class: 'text-body-secondary mb-0' }, 'No open alerts for you on this project.')));
  if (state.me?.permissions?.includes('reports.export')) app.append(reportsCard(id));
  watch(id, d.change_seq, () => projectOverview(app, id, { tabsFor }));
}

export function milestoneList(ms) {
  if (!ms.length) return h('p', { class: 'text-body-secondary mb-0' }, 'No milestones.');
  const tone = { achieved: ['ok', 'Achieved'], overdue: ['critical', 'Overdue'], upcoming: ['information', 'Upcoming'] };
  return h('ol', { class: 'list-unstyled milestone-timeline mb-0' }, ms.map(m => h('li', { class: 'd-flex gap-2 align-items-start mb-2' },
    badge(...tone[m.state]), h('div', {}, h('div', { class: 'fw-semibold' }, m.name),
      h('div', { class: 'small text-body-secondary' }, 'Due ', fmtDate(m.due_date), m.achieved_date ? ` · achieved ${fmtDate(m.achieved_date)}` : '')))));
}

const REPORT_LABELS = { status: 'Project status', delayed: 'Delayed activities', milestones: 'Milestones', expenditure: 'Expenditure listing', budget: 'Budget status',
  'cost-variance': 'Cost variance summary', payments: 'Valuations and payments', alerts: 'Alerts', 'client-summary': 'Client summary', audit: 'Audit trail' };
export function reportsCard(projectId) {
  const body = h('div', {}, h('p', { class: 'small text-body-secondary' }, 'Loading reports…'));
  get('/reports').then(types => {
    clear(body);
    if (!types.length) { body.append(h('p', { class: 'text-body-secondary mb-0' }, 'No reports for your role.')); return; }
    body.append(h('div', { class: 'd-flex flex-wrap gap-2' }, types.filter(t => t !== 'audit').map(t => h('div', { class: 'btn-group' },
      h('a', { class: 'btn btn-sm btn-outline-primary', href: `${base}/api/v1/reports/${t}?project_id=${projectId}&format=pdf` }, icon('file-earmark-pdf'), ' ', REPORT_LABELS[t]),
      h('a', { class: 'btn btn-sm btn-outline-secondary', href: `${base}/api/v1/reports/${t}?project_id=${projectId}&format=csv`, 'aria-label': `${REPORT_LABELS[t]} as CSV` }, 'CSV')))));
  }).catch(e => { clear(body).append(h('p', { class: 'text-danger' }, e.message)); });
  return card('Reports', body);
}

export function confirmDialog(text) { return window.confirm(text); }
