// Site Engineer views: assigned activities, one-screen progress update (online or offline),
// submission status, add activity (no cost fields: the API never sends or accepts them).
import { get, post, formData, showErrors, showOk } from '../lib/api.js';
import { h, clear, card, table, field, select, scheduleBadge, fmtPct, fmtDate, fmtDateTime, todayIso, spinner, badge, icon } from '../lib/dom.js';
import * as outbox from '../lib/outbox.js';
import { bindDraft } from '../lib/drafts.js';
import { projectList, projectOverview, pageHeader, tabs, lastUpdated, watch } from './common.js';
import { toast } from '../app.js';

const siteTabs = (id, active) => tabs(id, active, [['activities', 'My activities'], ['overview', 'Overview'], ['submissions', 'My submissions'], ['new-activity', 'Add activity']]);
const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => { const r = crypto.getRandomValues(new Uint8Array(1))[0] % 16; return (c === 'x' ? r : (r & 3) | 8).toString(16); }));
const statusBadge = s => ({ submitted: badge('information', 'Submitted, waiting for PM'), approved: badge('ok', 'Approved'), returned: badge('warning', 'Returned') }[s] || badge('information', s));

export const routes = {
  '/': app => projectList(app, { onOpen: p => `#/project/${p.id}/activities` }),
  '/project/:id/activities': activities,
  '/project/:id/overview': (app, id) => projectOverview(app, id, { tabsFor: siteTabs }),
  '/project/:id/submissions': submissions,
  '/project/:id/new-activity': newActivity,
  '/project/:id/update/:aid': updateForm,
  '/outbox': outboxView,
};

async function activities(app, id) {
  clear(app).append(spinner());
  const [d, mine] = await Promise.all([get(`/projects/${id}/dashboard`), get(`/projects/${id}/my-progress`).catch(() => [])]);
  const latestMine = {};
  for (const m of mine) if (!latestMine[m.activity_id]) latestMine[m.activity_id] = m;
  clear(app).append(pageHeader(`${d.project.code} ${d.project.name}`), siteTabs(id, 'activities'), lastUpdated(d.last_updated));
  await outboxNotice(app);
  const list = h('div', { class: 'list-group mb-3' });
  for (const a of d.activities) {
    const m = latestMine[a.id];
    list.append(h('a', { class: 'list-group-item list-group-item-action py-3', href: `#/project/${id}/update/${a.id}` },
      h('div', { class: 'd-flex justify-content-between gap-2 flex-wrap' }, h('strong', {}, `${a.code} ${a.name}`), scheduleBadge(a)),
      h('div', { class: 'small mt-1' }, `Planned now ${fmtPct(a.planned_pct, 0)} · Approved ${fmtPct(a.pct_complete, 0)} · Finish ${fmtDate(a.planned_finish)}`),
      m ? h('div', { class: 'small mt-1' }, 'Your last entry: ', statusBadge(m.status), ` ${fmtPct(m.percent_complete, 0)} on ${fmtDate(m.record_date)}`, m.return_reason ? ` · Reason: ${m.return_reason}` : '') : null,
      h('span', { class: 'btn btn-primary btn-sm mt-2' }, icon('pencil-square'), ' Update progress')));
  }
  if (!d.activities.length) list.append(h('div', { class: 'list-group-item text-body-secondary' }, 'No activities yet.'));
  app.append(list);
  watch(id, d.change_seq, () => activities(app, id));
}

async function outboxNotice(app) {
  const rows = await outbox.list().catch(() => []);
  const queued = rows.filter(r => r.state === 'queued').length, rejected = rows.filter(r => r.state === 'rejected').length;
  if (queued || rejected) {
    app.append(h('div', { class: `alert ${rejected ? 'alert-warning' : 'alert-info'}` }, icon('phone'), ` ${queued} entries saved on this phone are waiting to be sent.`,
      rejected ? ` ${rejected} need attention.` : '', ' ', h('a', { href: '#/outbox' }, 'View')));
  }
}

async function updateForm(app, id, aid) {
  clear(app).append(spinner());
  const [acts, lookups] = await Promise.all([get(`/projects/${id}/activities`), get('/lookups')]);
  const a = acts.find(x => String(x.id) === String(aid));
  if (!a) throw new Error('Activity not found.');
  const returned = (await get(`/projects/${id}/my-progress`).catch(() => [])).find(m => String(m.activity_id) === String(aid) && m.status === 'returned');
  const form = h('form', { class: 'card card-body', novalidate: true });
  const pct = h('input', { name: 'percent_complete', type: 'number', min: 0, max: 100, step: '0.5', inputmode: 'decimal', required: true, value: '' });
  form.append(...[
    h('p', { class: 'mb-2' }, scheduleBadge(a), ` Planned now ${fmtPct(a.planned_pct, 0)} · Last approved ${fmtPct(a.pct_complete, 0)}`),
    returned ? h('div', { class: 'alert alert-warning small' }, `Your entry of ${fmtDate(returned.record_date)} was returned: ${returned.return_reason || ''}. This entry will replace it.`) : null,
    h('input', { type: 'hidden', name: 'resubmission_of', value: returned ? returned.id : '' }),
    field('Date of work', h('input', { name: 'record_date', type: 'date', value: todayIso(), max: todayIso(), required: true })),
    field('Percentage complete (0 to 100)', pct, 'Total for the activity so far, not just today.'),
    field('Delay reason (if any)', select('delay_reason_id', lookups.delay_reasons.map(r => [r.id, r.label + (r.raises_critical ? ' (alerts management)' : '')]), '', 'No delay')),
    field('Remarks', h('textarea', { name: 'remarks', rows: 2, maxlength: 500 })),
    h('div', { class: 'form-msg' }),
    h('button', { class: 'btn btn-primary btn-lg w-100', type: 'submit' }, 'Submit for approval')].filter(Boolean));  // native append would print null
  const clearDraft = bindDraft(form, `progress-${aid}`);
  clear(app).append(pageHeader(`${a.code} ${a.name}`), h('p', {}, h('a', { href: `#/project/${id}/activities` }, icon('arrow-left'), ' Back to activities')), form);
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = form.querySelector('button[type=submit]'); btn.disabled = true;
    const body = formData(form);
    for (const k of ['delay_reason_id', 'resubmission_of']) if (!body[k]) delete body[k];
    body.client_uuid = uuid();
    try {
      await post(`/activities/${aid}/progress`, body);
      clearDraft();
      toast('Submitted. Your PM will approve or return it.');
      location.hash = `#/project/${id}/activities`;
    } catch (err) {
      if (err.network && await outbox.available()) {
        await outbox.enqueue({ client_uuid: body.client_uuid, type: 'progress', activity_id: Number(aid), body, label: `${a.code} ${body.percent_complete}% on ${fmtDate(body.record_date)}` });
        clearDraft();
        showOk(form, 'No connection. Saved on this phone; it will be sent automatically when the connection returns.');
        setTimeout(() => { location.hash = `#/project/${id}/activities`; }, 1500);
      } else {
        showErrors(form, err);
        btn.disabled = false;
      }
    }
  });
}

async function submissions(app, id) {
  clear(app).append(spinner());
  const mine = await get(`/projects/${id}/my-progress`);
  clear(app).append(pageHeader('My submissions'), siteTabs(id, 'submissions'),
    card('Latest entries', table(['Date', '%', 'Status', 'Note'], mine.map(m => [fmtDate(m.record_date), fmtPct(m.percent_complete, 0), statusBadge(m.status),
      m.return_reason || (m.reviewed_at ? 'Reviewed ' + fmtDateTime(m.reviewed_at) : '')]), 'You have not submitted progress on this project yet.')));
  watch(id, -1, () => submissions(app, id));
}

async function newActivity(app, id) {
  const form = h('form', { class: 'card card-body', novalidate: true },
    field('Activity code', h('input', { name: 'code', maxlength: 20, required: true }), 'Short code, e.g. R07.'),
    field('Activity name', h('input', { name: 'name', maxlength: 150, required: true })),
    field('Planned start', h('input', { name: 'planned_start', type: 'date', required: true })),
    field('Planned finish', h('input', { name: 'planned_finish', type: 'date', required: true })),
    h('p', { class: 'small text-body-secondary' }, 'The Project Manager adds the budgeted cost.'),
    h('div', { class: 'form-msg' }), h('button', { class: 'btn btn-primary', type: 'submit' }, 'Add activity'));
  clear(app).append(pageHeader('Add activity'), siteTabs(id, 'new-activity'), form);
  form.addEventListener('submit', async e => {
    e.preventDefault();
    try { await post(`/projects/${id}/activities`, formData(form)); toast('Activity added.'); location.hash = `#/project/${id}/activities`; }
    catch (err) { showErrors(form, err); }
  });
}

async function outboxView(app) {
  const rows = await outbox.list();
  clear(app).append(pageHeader('Saved on this phone'),
    h('p', {}, navigator.onLine ? h('button', { class: 'btn btn-primary', type: 'button', onclick: async () => { const r = await outbox.sync(); toast(`${r.sent} sent.`); outboxView(app); } }, 'Send now') : 'You are offline. Entries will be sent when the connection returns.'),
    table(['Entry', 'Saved', 'State', ''], rows.map(r => [r.label, fmtDateTime(r.queued_at.replace('T', ' ')), r.state === 'rejected' ? badge('warning', 'Needs attention: ' + r.error) : badge('information', 'Waiting'),
      r.state === 'rejected' ? h('button', { class: 'btn btn-sm btn-outline-danger', type: 'button', onclick: async () => { await outbox.discard(r.client_uuid); outboxView(app); } }, 'Discard') : '']), 'Nothing is waiting.'));
}

