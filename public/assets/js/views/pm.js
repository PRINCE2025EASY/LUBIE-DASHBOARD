// Project Manager views: projects, overview, approvals, activities, milestones, team,
// project thresholds, project set-up and status. Budgets/cost are read-only plus revision request.
import { get, post, patch, put, del, formData, showErrors } from '../lib/api.js';
import { h, clear, card, table, field, select, scheduleBadge, fmtMoney, fmtPct, fmtDate, spinner, badge, icon } from '../lib/dom.js';
import { projectList, projectOverview, pageHeader, tabs, watch, statusLabel } from './common.js';
import { toast } from '../app.js';
import { budgetsCard, requestRevisionForm } from './qs.js';

const pmTabs = (id, active) => tabs(id, active, [['overview', 'Overview'], ['approvals', 'Approvals'], ['activities', 'Activities'], ['milestones', 'Milestones'],
  ['progress', 'Record progress'], ['cost', 'Cost'], ['team', 'Team'], ['settings', 'Settings']]);

export const routes = {
  '/': app => projectList(app, { actions: [h('a', { class: 'btn btn-primary', href: '#/new-project' }, icon('plus-lg'), ' New project')] }),
  '/new-project': app => projectForm(app, null),
  '/project/:id/overview': (app, id) => projectOverview(app, id, { tabsFor: pmTabs }),
  '/project/:id/approvals': approvals,
  '/project/:id/activities': activitiesView,
  '/project/:id/milestones': milestonesView,
  '/project/:id/progress': (app, id) => { location.hash = `#/project/${id}/activities`; },
  '/project/:id/cost': costView,
  '/project/:id/team': teamView,
  '/project/:id/settings': settingsView,
};

async function approvals(app, id) {
  clear(app).append(spinner());
  const q = await get(`/projects/${id}/progress-queue`);
  clear(app).append(pageHeader('Progress waiting for approval'), pmTabs(id, 'approvals'));
  if (!q.length) { app.append(h('p', { class: 'text-body-secondary' }, 'Nothing is waiting for your approval.')); }
  for (const r of q) {
    const reason = h('input', { class: 'form-control', name: 'return_reason', placeholder: 'Reason for returning', maxlength: 300 });
    const msg = h('div', { class: 'small text-danger' });
    const act = async (path, body) => {
      try { await post(path, body); toast('Saved.'); approvals(app, id); } catch (e) { msg.textContent = e.message; }
    };
    app.append(h('div', { class: 'card mb-2' }, h('div', { class: 'card-body' },
      h('div', { class: 'd-flex justify-content-between flex-wrap gap-2' }, h('strong', {}, `${r.activity_code} ${r.activity_name}`),
        r.raises_critical ? badge('critical', r.delay_reason) : (r.delay_reason ? badge('warning', r.delay_reason) : null)),
      h('div', { class: 'small' }, `${fmtPct(r.percent_complete, 0)} on ${fmtDate(r.record_date)} (last approved ${fmtPct(r.last_approved_pct, 0)}) · by ${r.submitted_by_name}`),
      r.remarks ? h('div', { class: 'small fst-italic' }, r.remarks) : null,
      h('div', { class: 'd-flex flex-wrap gap-2 mt-2' },
        h('button', { class: 'btn btn-success', type: 'button', onclick: () => act(`/progress/${r.id}/approve`) }, icon('check-lg'), ' Approve'),
        h('div', { class: 'input-group flex-grow-1' }, reason, h('button', { class: 'btn btn-outline-warning', type: 'button', onclick: () => act(`/progress/${r.id}/return`, { return_reason: reason.value }) }, 'Return'))),
      msg)));
  }
  watch(id, -1, () => approvals(app, id));
}

async function activitiesView(app, id) {
  clear(app).append(spinner());
  const acts = await get(`/projects/${id}/activities`);
  clear(app).append(pageHeader('Activities'), pmTabs(id, 'activities'));
  app.append(card('Activities', table(['Activity', 'Planned', 'Budgeted cost', 'Approved %', 'Status', ''], acts.map(a => [
    `${a.code} ${a.name}`, `${fmtDate(a.planned_start)} – ${fmtDate(a.planned_finish)}`, a.budgeted_cost === null ? 'Not priced' : fmtMoney(a.budgeted_cost),
    fmtPct(a.pct_complete, 0), scheduleBadge(a),
    h('div', { class: 'd-flex gap-1' },
      h('a', { class: 'btn btn-sm btn-primary', href: `#/project/${id}/activities?update=${a.id}`, onclick: e => { e.preventDefault(); progressForm(app, id, a); } }, 'Progress'),
      h('button', { class: 'btn btn-sm btn-outline-secondary', type: 'button', onclick: () => activityForm(app, id, a) }, 'Edit'))]))));
  activityForm(app, id, null, true);
}

function activityForm(app, id, a, inline = false) {
  const form = h('form', { class: 'card card-body', novalidate: true },
    h('h2', { class: 'h6' }, a ? `Edit ${a.code}` : 'Add activity'),
    h('div', { class: 'row' },
      h('div', { class: 'col-md-3' }, field('Code', h('input', { name: 'code', value: a?.code || '', maxlength: 20 }))),
      h('div', { class: 'col-md-9' }, field('Name', h('input', { name: 'name', value: a?.name || '', maxlength: 150 })))),
    h('div', { class: 'row' },
      h('div', { class: 'col-md-4' }, field('Planned start', h('input', { name: 'planned_start', type: 'date', value: a?.planned_start || '' }))),
      h('div', { class: 'col-md-4' }, field('Planned finish', h('input', { name: 'planned_finish', type: 'date', value: a?.planned_finish || '' }))),
      h('div', { class: 'col-md-4' }, field('Budgeted cost (GH₵)', h('input', { name: 'budgeted_cost', inputmode: 'decimal', value: a?.budgeted_cost ?? '' }), 'Leave empty if not priced.'))),
    h('div', { class: 'form-msg' }),
    h('div', { class: 'd-flex gap-2' }, h('button', { class: 'btn btn-primary', type: 'submit' }, a ? 'Save changes' : 'Add activity'),
      a ? h('button', { class: 'btn btn-outline-danger', type: 'button', onclick: async () => {
        const reason = window.prompt('Reason for archiving this activity?'); if (!reason) return;
        try { await post(`/activities/${a.id}/archive`, { reason }); toast('Activity archived.'); activitiesView(app, id); } catch (e) { showErrors(form, e); }
      } }, 'Archive') : null));
  form.addEventListener('submit', async e => {
    e.preventDefault();
    try {
      if (a) await patch(`/activities/${a.id}`, formData(form)); else await post(`/projects/${id}/activities`, formData(form));
      toast('Saved.'); activitiesView(app, id);
    } catch (err) { showErrors(form, err); }
  });
  if (inline) app.append(form); else { app.prepend(form); form.scrollIntoView({ behavior: 'smooth' }); }
}

function progressForm(app, id, a) {
  get('/lookups').then(lk => {
    const form = h('form', { class: 'card card-body mb-3', novalidate: true }, h('h2', { class: 'h6' }, `Record progress: ${a.code}`),
      field('Date', h('input', { name: 'record_date', type: 'date', value: new Date().toISOString().slice(0, 10) })),
      field('% complete', h('input', { name: 'percent_complete', type: 'number', min: 0, max: 100, step: '0.5' })),
      field('Delay reason', select('delay_reason_id', lk.delay_reasons.map(r => [r.id, r.label]), '', 'No delay')),
      field('Remarks', h('input', { name: 'remarks', maxlength: 500 })), h('div', { class: 'form-msg' }),
      h('button', { class: 'btn btn-primary', type: 'submit' }, 'Submit'));
    form.addEventListener('submit', async e => {
      e.preventDefault();
      const b = formData(form); if (!b.delay_reason_id) delete b.delay_reason_id;
      try { await post(`/activities/${a.id}/progress`, b); toast('Submitted. Approve it in Approvals.'); activitiesView(app, id); } catch (err) { showErrors(form, err); }
    });
    app.prepend(form);
  });
}

async function milestonesView(app, id) {
  clear(app).append(spinner());
  const [ms, acts] = await Promise.all([get(`/projects/${id}/milestones`), get(`/projects/${id}/activities`)]);
  clear(app).append(pageHeader('Milestones'), pmTabs(id, 'milestones'));
  const stateBadge = m => ({ achieved: badge('ok', 'Achieved'), overdue: badge('critical', 'Overdue'), upcoming: badge('information', 'Upcoming') }[m.state]);
  app.append(card('Milestones', table(['Milestone', 'Due', 'Achieved', 'State', ''], ms.map(m => [m.name, fmtDate(m.due_date), fmtDate(m.achieved_date), stateBadge(m),
    m.achieved_date ? '' : h('button', { class: 'btn btn-sm btn-success', type: 'button', onclick: async () => {
      try { await patch(`/milestones/${m.id}`, { achieved_date: new Date().toISOString().slice(0, 10) }); toast('Marked achieved.'); milestonesView(app, id); } catch (e) { toast(e.message, 'danger'); }
    } }, 'Mark achieved today')]))));
  const form = h('form', { class: 'card card-body', novalidate: true }, h('h2', { class: 'h6' }, 'Add milestone'),
    field('Name', h('input', { name: 'name', maxlength: 150 })), field('Due date', h('input', { name: 'due_date', type: 'date' })),
    field('Linked activity (optional)', select('activity_id', acts.map(a => [a.id, `${a.code} ${a.name}`]), '', 'None')),
    h('div', { class: 'form-msg' }), h('button', { class: 'btn btn-primary', type: 'submit' }, 'Add milestone'));
  form.addEventListener('submit', async e => {
    e.preventDefault(); const b = formData(form); if (!b.activity_id) delete b.activity_id;
    try { await post(`/projects/${id}/milestones`, b); toast('Milestone added.'); milestonesView(app, id); } catch (err) { showErrors(form, err); }
  });
  app.append(form);
}

async function costView(app, id) {
  clear(app).append(spinner());
  const d = await get(`/projects/${id}/dashboard`);
  clear(app).append(pageHeader('Cost'), pmTabs(id, 'cost'),
    h('p', {}, `Budget used ${fmtPct(d.kpis.budget_consumed_pct)} · CPI ${d.kpis.cpi ?? 'Not available'} · Actual cost ${fmtMoney(d.kpis.ac)}`),
    await budgetsCard(id), requestRevisionForm(id, () => costView(app, id)));
}

async function teamView(app, id) {
  clear(app).append(spinner());
  const [team, candidates] = await Promise.all([get(`/projects/${id}/assignments`), get(`/projects/${id}/assignable-users`)]);
  clear(app).append(pageHeader('Team'), pmTabs(id, 'team'),
    card('Assigned users', table(['Name', 'Role', ''], team.map(t => [t.full_name, t.role_name,
      h('button', { class: 'btn btn-sm btn-outline-danger', type: 'button', onclick: async () => {
        if (!window.confirm(`Remove ${t.full_name} from this project?`)) return;
        try { await del(`/projects/${id}/assignments/${t.user_id}`); teamView(app, id); } catch (e) { toast(e.message, 'danger'); }
      } }, 'Remove')]))));
  const form = h('form', { class: 'card card-body', novalidate: true }, h('h2', { class: 'h6' }, 'Assign a user'),
    field('User', select('user_id', candidates.map(u => [u.id, `${u.full_name} (${u.role_name})`]), '', 'Choose a user')),
    h('div', { class: 'form-msg' }), h('button', { class: 'btn btn-primary', type: 'submit' }, 'Assign'));
  form.addEventListener('submit', async e => {
    e.preventDefault();
    try { await post(`/projects/${id}/assignments`, formData(form)); toast('Assigned.'); teamView(app, id); } catch (err) { showErrors(form, err); }
  });
  app.append(form);
}

async function settingsView(app, id) {
  clear(app).append(spinner());
  const [p, th] = await Promise.all([get(`/projects/${id}`), get(`/thresholds?project_id=${id}`)]);
  clear(app).append(pageHeader('Project settings'), pmTabs(id, 'settings'));
  const LABELS = { budget_warning_pct: 'Budget warning at % used', budget_overrun_sms_pct: 'Critical overrun SMS above budget by %', cpi_min: 'Warn when CPI below',
    spi_min: 'Warn when SPI below', milestone_due_days: 'Milestone due-soon warning (days)', stale_data_days: 'Out-of-date warning after (days)', delay_sms_days: 'Delay SMS after (days late)' };
  app.append(card('Alert thresholds for this project', table(['Threshold', "Director's limit", 'Company value', 'This project', ''], th.map(t => {
    const inp = h('input', { class: 'form-control form-control-sm', value: t.project ?? '', placeholder: String(t.global), inputmode: 'decimal', 'aria-label': LABELS[t.key] });
    return [LABELS[t.key] || t.key, `${t.min_allowed ?? '–'} to ${t.max_allowed ?? '–'}`, String(t.global), inp,
      h('button', { class: 'btn btn-sm btn-outline-primary', type: 'button', onclick: async () => {
        try { await put(`/projects/${id}/thresholds/${t.key}`, { value: inp.value === '' ? null : inp.value }); toast('Threshold saved.'); } catch (e) { toast(e.message, 'danger'); }
      } }, 'Save')];
  }))));
  const status = h('div', { class: 'd-flex flex-wrap gap-2' }, ...({ planning: ['active', 'archived'], active: ['on_hold', 'completed'], on_hold: ['active', 'archived'], completed: ['archived', 'active'] }[p.status] || [])
    .map(s => h('button', { class: 'btn btn-outline-secondary', type: 'button', onclick: async () => {
      if (!window.confirm(`Change project status to ${statusLabel(s)}?`)) return;
      try { await post(`/projects/${id}/status`, { status: s }); toast('Status changed.'); settingsView(app, id); } catch (e) { toast(e.message, 'danger'); }
    } }, `Mark ${statusLabel(s)}`)));
  app.append(card(`Project status: ${statusLabel(p.status)}`, status));
  projectForm(app, p, true);
}

export function projectForm(app, p, append = false) {
  const form = h('form', { class: 'card card-body', novalidate: true }, h('h2', { class: 'h6' }, p ? 'Project details' : 'New project'),
    h('div', { class: 'row' },
      h('div', { class: 'col-md-3' }, field('Code', h('input', { name: 'code', value: p?.code || '', maxlength: 20 }))),
      h('div', { class: 'col-md-9' }, field('Name', h('input', { name: 'name', value: p?.name || '', maxlength: 150 })))),
    h('div', { class: 'row' },
      h('div', { class: 'col-md-4' }, field('Type', h('input', { name: 'project_type', value: p?.project_type || '', maxlength: 60, list: 'ptypes' }))),
      h('div', { class: 'col-md-4' }, field('Location', h('input', { name: 'location', value: p?.location || '', maxlength: 150 }))),
      h('div', { class: 'col-md-4' }, field('Client', h('input', { name: 'client_name', value: p?.client_name || '', maxlength: 150 })))),
    h('datalist', { id: 'ptypes' }, ['Road and drainage', 'Building', 'Building (classroom)', 'Bridge', 'Water and sanitation'].map(t => h('option', { value: t }))),
    h('div', { class: 'row' },
      h('div', { class: 'col-md-3' }, field('Contract sum (GH₵)', h('input', { name: 'contract_sum', value: p?.contract_sum || '', inputmode: 'decimal' }))),
      h('div', { class: 'col-md-3' }, field('Planned start', h('input', { name: 'planned_start', type: 'date', value: p?.planned_start || '' }))),
      h('div', { class: 'col-md-3' }, field('Planned finish', h('input', { name: 'planned_finish', type: 'date', value: p?.planned_finish || '' }))),
      h('div', { class: 'col-md-3' }, field('Payment period (days)', h('input', { name: 'payment_period_days', value: p?.payment_period_days ?? 30, inputmode: 'numeric' })))),
    h('div', { class: 'form-msg' }), h('button', { class: 'btn btn-primary', type: 'submit' }, p ? 'Save details' : 'Create project'));
  form.addEventListener('submit', async e => {
    e.preventDefault();
    try {
      if (p) { await patch(`/projects/${p.id}`, formData(form)); toast('Saved.'); }
      else { const r = await post('/projects', formData(form)); toast('Project created.'); location.hash = `#/project/${r.id}/activities`; }
    } catch (err) { showErrors(form, err); }
  });
  if (!append) clear(app).append(pageHeader(p ? 'Project details' : 'New project'));
  app.append(form);
}
