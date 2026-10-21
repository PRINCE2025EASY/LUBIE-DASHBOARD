// Client: one simple screen per project (overall %, milestone timeline, payment card).
// The API sends only client-safe fields; nothing internal reaches this page.
import { get, base } from '../lib/api.js';
import { h, clear, card, fmtMoney, fmtPct, spinner, icon } from '../lib/dom.js';
import { pageHeader, lastUpdated, milestoneList, watch } from './common.js';

export const routes = {
  '/': async app => {
    clear(app).append(spinner());
    const projects = await get('/projects');
    if (projects.length === 1) { location.hash = `#/project/${projects[0].id}`; return; }
    clear(app).append(pageHeader('My projects'), h('div', { class: 'list-group' }, projects.map(p => h('a', { class: 'list-group-item list-group-item-action py-3', href: `#/project/${p.id}` }, `${p.code} ${p.name}`))));
    if (!projects.length) app.append(h('p', {}, 'No project is linked to your account yet.'));
  },
  '/project/:id': summary,
};

async function summary(app, id) {
  clear(app).append(spinner());
  const s = await get(`/client/summary?project_id=${id}`);
  const pct = s.completion_pct ?? 0;
  clear(app).append(pageHeader(`${s.project.code} ${s.project.name}`), lastUpdated(s.last_updated));
  app.append(card('Overall progress', h('div', {},
    h('div', { class: 'progress client-progress', role: 'progressbar', 'aria-label': 'Overall progress', 'aria-valuenow': pct, 'aria-valuemin': 0, 'aria-valuemax': 100 },
      h('div', { class: 'progress-bar', dataset: { w: String(Math.round(pct)) } }, fmtPct(s.completion_pct, 0))),
    h('p', { class: 'small text-body-secondary mt-2 mb-0' }, `Planned by today: ${fmtPct(s.planned_pct, 0)}. Progress counts only work approved by the Project Manager.`))));
  app.querySelectorAll('.progress-bar[data-w]').forEach(b => { b.style.width = b.dataset.w + '%'; });
  if (s.notices.length) app.append(card('Notices', h('ul', { class: 'mb-0' }, s.notices.map(n => h('li', {}, h('strong', {}, n.title), ' ', n.description)))));
  app.append(card('Milestones', milestoneList(s.milestones)));
  const p = s.payments;
  app.append(card('Payments', h('dl', { class: 'row mb-0' },
    ...[['Contract sum', p.contract_sum], ['Certified to date', p.certified], ['Paid to date', p.paid], ['Outstanding', p.outstanding]]
      .flatMap(([l, v]) => [h('dt', { class: 'col-5 fw-normal' }, l), h('dd', { class: 'col-7 text-end fw-semibold text-nowrap' }, fmtMoney(v))]))));
  app.append(h('a', { class: 'btn btn-outline-primary', href: `${base}/api/v1/reports/client-summary?project_id=${id}&format=pdf` }, icon('file-earmark-pdf'), ' Download summary (PDF)'));
  watch(id, s.change_seq, () => summary(app, id));
}
