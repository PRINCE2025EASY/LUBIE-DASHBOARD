// Quantity Surveyor views: cost dashboard, budget versions and revision requests,
// expenditure (record, list, void), valuation certificates and payments, threshold proposals.
import { get, post, formData, showErrors } from '../lib/api.js';
import { h, clear, card, table, field, select, fmtMoney, fmtPct, fmtNum, fmtDate, fmtDateTime, todayIso, spinner, badge } from '../lib/dom.js';
import { projectList, projectOverview, pageHeader, tabs, watch, reportsCard } from './common.js';
import { toast } from '../app.js';

const qsTabs = (id, active) => tabs(id, active, [['cost', 'Cost'], ['overview', 'Overview'], ['expenditure', 'Expenditure'], ['certificates', 'Valuations'], ['budget', 'Budget'], ['thresholds', 'Thresholds']]);
const BUDGET_TONE = { approved: ['ok', 'Approved (current)'], pending: ['high', 'Waiting for Director'], draft: ['information', 'Draft'], rejected: ['warning', 'Rejected'], superseded: ['information', 'Superseded'] };

export const routes = {
  '/': app => projectList(app, { onOpen: p => `#/project/${p.id}/cost` }),
  '/project/:id/cost': cost,
  '/project/:id/overview': (app, id) => projectOverview(app, id, { tabsFor: qsTabs }),
  '/project/:id/expenditure': expenditure,
  '/project/:id/certificates': certificates,
  '/project/:id/budget': budget,
  '/project/:id/thresholds': thresholds,
};

async function cost(app, id) {
  clear(app).append(spinner());
  const d = await get(`/projects/${id}/dashboard`);
  const k = d.kpis;
  clear(app).append(pageHeader(`${d.project.code} cost`), qsTabs(id, 'cost'));
  app.append(h('div', { class: 'row g-2 mb-3' }, ...[
    ['Current budget', fmtMoney(k.current_budget)], ['Actual cost (AC)', fmtMoney(k.ac)], ['Budget used', fmtPct(k.budget_consumed_pct)],
    ['Budget variance', fmtMoney(k.budget_variance)], ['Earned value (EV)', fmtMoney(k.ev)], ['Cost variance (CV)', fmtMoney(k.cv)], ['CPI', fmtNum(k.cpi)],
    ['Certified', fmtMoney(d.certificates?.certified)], ['Paid', fmtMoney(d.certificates?.paid)],
  ].map(([l, v]) => h('div', { class: 'col-6 col-md-4 col-xl-3' }, h('div', { class: 'border rounded p-2 h-100' }, h('div', { class: 'small text-body-secondary' }, l), h('div', { class: 'fs-5 fw-semibold' }, v))))));
  app.append(card('Spent by category', table(['Category', 'Amount'], Object.entries(d.expenditure_by_category || {}).map(([c, v]) => [c, fmtMoney(v)]), 'No expenditure yet.')));
  app.append(reportsCard(id));
  watch(id, d.change_seq, () => cost(app, id));
}

async function expenditure(app, id, page = 1) {
  clear(app).append(spinner());
  const [res, lk, acts] = await Promise.all([get(`/projects/${id}/expenditures?per_page=25&page=${page}`, {}), get('/lookups'), get(`/projects/${id}/activities`)]);
  const rows = res.data ?? res;
  clear(app).append(pageHeader('Expenditure'), qsTabs(id, 'expenditure'));
  const form = h('form', { class: 'card card-body mb-3', novalidate: true }, h('h2', { class: 'h6' }, 'Record expenditure'),
    h('div', { class: 'row' },
      h('div', { class: 'col-md-3' }, field('Date', h('input', { name: 'expense_date', type: 'date', value: todayIso(), max: todayIso() }))),
      h('div', { class: 'col-md-3' }, field('Amount (GH₵)', h('input', { name: 'amount', inputmode: 'decimal' }))),
      h('div', { class: 'col-md-3' }, field('Category', select('category_id', lk.expenditure_categories.map(c => [c.id, c.label]), '', 'Choose'))),
      h('div', { class: 'col-md-3' }, field('Activity (optional)', select('activity_id', acts.map(a => [a.id, `${a.code} ${a.name}`]), '', 'Project level')))),
    h('div', { class: 'row' },
      h('div', { class: 'col-md-6' }, field('Description', h('input', { name: 'description', maxlength: 300 }))),
      h('div', { class: 'col-md-3' }, field('Voucher / receipt no.', h('input', { name: 'reference_no', maxlength: 50 }))),
      h('div', { class: 'col-md-3' }, field('Supplier', h('input', { name: 'supplier_name', maxlength: 150 })))),
    h('div', { class: 'form-check mb-3' }, h('input', { class: 'form-check-input', type: 'checkbox', name: 'confirmed', id: 'exp-confirm' }),
      h('label', { class: 'form-check-label', for: 'exp-confirm' }, 'I have checked the amount and date against the voucher.'), h('div', { class: 'invalid-feedback', 'data-for': 'confirmed' })),
    h('div', { class: 'form-msg' }), h('button', { class: 'btn btn-primary', type: 'submit' }, 'Save expenditure'));
  form.addEventListener('submit', async e => {
    e.preventDefault(); const b = formData(form); if (!b.activity_id) delete b.activity_id;
    try { await post(`/projects/${id}/expenditures`, b); toast('Expenditure saved.'); expenditure(app, id); } catch (err) { showErrors(form, err); }
  });
  app.append(form, card('Entries', table(['Date', 'Category', 'Description', 'Ref', 'Amount', 'Status', ''], rows.map(x => [fmtDate(x.expense_date), x.category_label, x.description, x.reference_no || '',
    fmtMoney(x.amount), x.status === 'voided' ? badge('warning', 'Void: ' + (x.void_reason || '')) : badge('ok', 'Active'),
    x.status === 'active' ? h('button', { class: 'btn btn-sm btn-outline-danger', type: 'button', onclick: async () => {
      const reason = window.prompt('Reason for voiding this entry?'); if (!reason) return;
      try { await post(`/expenditures/${x.id}/void`, { void_reason: reason }); toast('Entry voided.'); expenditure(app, id, page); } catch (e2) { toast(e2.message, 'danger'); }
    } }, 'Void') : '']), 'No expenditure recorded.')));
  const meta = res.meta;
  if (meta && meta.total > meta.per_page) {
    app.append(h('nav', {}, h('div', { class: 'd-flex gap-2' },
      page > 1 ? h('button', { class: 'btn btn-outline-secondary', type: 'button', onclick: () => expenditure(app, id, page - 1) }, 'Previous') : null,
      page * meta.per_page < meta.total ? h('button', { class: 'btn btn-outline-secondary', type: 'button', onclick: () => expenditure(app, id, page + 1) }, 'Next') : null)));
  }
}

async function certificates(app, id) {
  clear(app).append(spinner());
  const certs = await get(`/projects/${id}/certificates`);
  clear(app).append(pageHeader('Valuations and payments'), qsTabs(id, 'certificates'));
  const stat = c => c.overdue ? badge('critical', 'Overdue') : ({ paid: badge('ok', 'Paid'), part_paid: badge('warning', 'Part-paid'), certified: badge('information', 'Certified'), submitted: badge('information', 'Submitted') }[c.status]);
  for (const c of certs) {
    const actions = h('div', { class: 'd-flex flex-wrap gap-2 mt-2' });
    if (c.status === 'submitted') {
      const f = h('form', { class: 'd-flex flex-wrap gap-2 align-items-end', novalidate: true },
        h('div', {}, h('label', { class: 'form-label small' }, 'Amount certified'), h('input', { class: 'form-control form-control-sm', name: 'amount_certified', inputmode: 'decimal' }), h('div', { class: 'invalid-feedback', 'data-for': 'amount_certified' })),
        h('div', {}, h('label', { class: 'form-label small' }, 'Date certified'), h('input', { class: 'form-control form-control-sm', name: 'certified_date', type: 'date', value: todayIso() }), h('div', { class: 'invalid-feedback', 'data-for': 'certified_date' })),
        h('button', { class: 'btn btn-sm btn-primary', type: 'submit' }, 'Certify'), h('div', { class: 'form-msg w-100' }));
      f.addEventListener('submit', async e => { e.preventDefault(); try { await post(`/certificates/${c.id}/certify`, formData(f)); toast('Certified.'); certificates(app, id); } catch (err) { showErrors(f, err); } });
      actions.append(f);
    } else if (c.status !== 'paid') {
      const f = h('form', { class: 'd-flex flex-wrap gap-2 align-items-end', novalidate: true },
        h('div', {}, h('label', { class: 'form-label small' }, 'Payment received'), h('input', { class: 'form-control form-control-sm', name: 'amount', inputmode: 'decimal', placeholder: c.outstanding }), h('div', { class: 'invalid-feedback', 'data-for': 'amount' })),
        h('div', {}, h('label', { class: 'form-label small' }, 'Date paid'), h('input', { class: 'form-control form-control-sm', name: 'paid_date', type: 'date', value: todayIso() }), h('div', { class: 'invalid-feedback', 'data-for': 'paid_date' })),
        h('div', {}, h('label', { class: 'form-label small' }, 'Bank / receipt ref'), h('input', { class: 'form-control form-control-sm', name: 'reference_no', maxlength: 50 }), h('div', { class: 'invalid-feedback', 'data-for': 'reference_no' })),
        h('button', { class: 'btn btn-sm btn-success', type: 'submit' }, 'Record payment'), h('div', { class: 'form-msg w-100' }));
      f.addEventListener('submit', async e => { e.preventDefault(); try { await post(`/certificates/${c.id}/payments`, formData(f)); toast('Payment recorded.'); certificates(app, id); } catch (err) { showErrors(f, err); } });
      actions.append(f);
    }
    app.append(h('div', { class: 'card mb-2' }, h('div', { class: 'card-body' },
      h('div', { class: 'd-flex justify-content-between flex-wrap gap-2' }, h('strong', {}, c.cert_no), stat(c)),
      h('div', { class: 'small' }, `Submitted ${fmtDate(c.submitted_date)} · Valuation ${fmtMoney(c.valuation_amount)} · Certified ${fmtMoney(c.amount_certified)} · Paid ${fmtMoney(c.paid)} · Outstanding ${fmtMoney(c.outstanding)}`),
      c.payments.length ? h('div', { class: 'small text-body-secondary' }, 'Payments: ' + c.payments.map(p => `${fmtDate(p.paid_date)} ${fmtMoney(p.amount)} (${p.reference_no})`).join('; ')) : null,
      actions)));
  }
  const form = h('form', { class: 'card card-body', novalidate: true }, h('h2', { class: 'h6' }, 'New valuation certificate'),
    h('div', { class: 'row' }, h('div', { class: 'col-md-4' }, field('Certificate no.', h('input', { name: 'cert_no', maxlength: 20, placeholder: 'IPC-04' }))),
      h('div', { class: 'col-md-4' }, field('Valuation amount (GH₵)', h('input', { name: 'valuation_amount', inputmode: 'decimal' }))),
      h('div', { class: 'col-md-4' }, field('Date submitted', h('input', { name: 'submitted_date', type: 'date', value: todayIso() })))),
    field('Remarks', h('input', { name: 'remarks', maxlength: 300 })), h('div', { class: 'form-msg' }), h('button', { class: 'btn btn-primary', type: 'submit' }, 'Add certificate'));
  form.addEventListener('submit', async e => { e.preventDefault(); try { await post(`/projects/${id}/certificates`, formData(form)); toast('Certificate added.'); certificates(app, id); } catch (err) { showErrors(form, err); } });
  app.append(form);
}

export async function budgetsCard(id) {
  const b = await get(`/projects/${id}/budgets`);
  return card('Budget versions', table(['Version', 'Amount', 'Status', 'Reason', 'Requested', 'Decision'], b.versions.map(v => [
    'v' + v.version_no, fmtMoney(v.amount), badge(...BUDGET_TONE[v.status]), v.reason || '', `${v.requested_by_name}, ${fmtDateTime(v.requested_at)}`,
    v.decided_at ? `${v.decided_by_name}, ${fmtDateTime(v.decided_at)}${v.decision_note ? ': ' + v.decision_note : ''}` : '']), 'No budget recorded yet.'));
}
export function requestRevisionForm(id, after) {
  const form = h('form', { class: 'card card-body', novalidate: true }, h('h2', { class: 'h6' }, 'Request a budget revision (or the first baseline)'),
    field('New total budget (GH₵)', h('input', { name: 'amount', inputmode: 'decimal' })), field('Reason', h('textarea', { name: 'reason', rows: 2, maxlength: 300 })),
    h('p', { class: 'small text-body-secondary' }, 'The Director must approve it before it becomes the current budget. The old version is kept.'),
    h('div', { class: 'form-msg' }), h('button', { class: 'btn btn-primary', type: 'submit' }, 'Send for Director approval'));
  form.addEventListener('submit', async e => { e.preventDefault(); try { await post(`/projects/${id}/budgets`, { ...formData(form), submit: true }); toast('Sent to the Director.'); after(); } catch (err) { showErrors(form, err); } });
  return form;
}
async function budget(app, id) {
  clear(app).append(spinner());
  const c = await budgetsCard(id);
  clear(app).append(pageHeader('Budget'), qsTabs(id, 'budget'), c, requestRevisionForm(id, () => budget(app, id)));
}

async function thresholds(app, id) {
  clear(app).append(spinner());
  const th = await get(`/thresholds?project_id=${id}`);
  clear(app).append(pageHeader('Cost thresholds'), qsTabs(id, 'thresholds'), h('p', {}, 'You can propose cost thresholds. The Director approves them.'));
  const COST = ['budget_warning_pct', 'budget_overrun_sms_pct', 'cpi_min'];
  app.append(card('Propose a change', table(['Threshold', 'Effective now', 'Proposed value', ''], th.filter(t => COST.includes(t.key)).map(t => {
    const inp = h('input', { class: 'form-control form-control-sm', inputmode: 'decimal', 'aria-label': 'Proposed value for ' + t.key });
    return [t.key.replaceAll('_', ' '), String(t.effective), inp, h('button', { class: 'btn btn-sm btn-outline-primary', type: 'button', onclick: async () => {
      try { await post('/threshold-proposals', { project_id: id, setting_key: t.key, value: inp.value }); toast('Proposal sent to the Director.'); } catch (e) { toast(e.message, 'danger'); }
    } }, 'Propose')];
  }))));
}

