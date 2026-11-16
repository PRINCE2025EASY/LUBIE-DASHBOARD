// Labour, materials, equipment and material requests (FR-18, FR-19, FR-33).
// One screen for every staff role; the server decides what each role may see and do
// (`can` flags), and money fields only arrive for Director, PM and QS.
import { get, post, patch, formData, showErrors, showOk } from '../lib/api.js';
import { h, clear, card, table, field, select, badge, fmtMoney, fmtDate, fmtDateTime, todayIso, spinner, icon } from '../lib/dom.js';
import * as outbox from '../lib/outbox.js';
import { pageHeader, lastUpdated, watch, confirmDialog } from './common.js';
import { toast } from '../app.js';

const uuid = () => (crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => { const r = crypto.getRandomValues(new Uint8Array(1))[0] % 16; return (c === 'x' ? r : (r & 3) | 8).toString(16); }));
const qty = v => (v === null || v === undefined ? '' : Number(v).toLocaleString('en-GB', { maximumFractionDigits: 2 }));
const EQUIP = { working: ['ok', 'Working'], faulty: ['critical', 'Faulty'], idle: ['information', 'Idle'], off_site: ['information', 'Off site'] };
const equipBadge = s => badge(...(EQUIP[s] || ['information', s || 'Unknown']));
const REQ = { requested: ['warning', 'Waiting for PM'], approved: ['information', 'Approved'], delivered: ['ok', 'Delivered'], cancelled: ['information', 'Cancelled'] };
const reqBadge = s => badge(...(REQ[s] || ['information', s]));
const MOVE = { received: 'Received', used: 'Used', attendance: 'Attendance', hours: 'Hours' };

/** Render the resources page. `tabs` draws the role's project tabs with this page active. */
export async function resourcesView(app, id, { tabs, me } = {}) {
  clear(app).append(spinner());
  const [d, acts] = await Promise.all([get(`/projects/${id}/resources`), get(`/projects/${id}/activities`).catch(() => [])]);
  const money = d.materials.concat(d.labour, d.equipment).some(r => 'unit_rate' in r);
  const rec = d.can.record && d.open;
  const panel = h('div', { id: 'res-panel' });
  const rerender = () => resourcesView(app, id, { tabs, me });
  const ctx = { id, d, acts, panel, rerender, money };

  clear(app).append(pageHeader(`${d.project.code} ${d.project.name}`), tabs ? tabs(id, 'resources') : null, lastUpdated(d.last_updated));
  if (d.project.is_demo) app.append(h('div', { class: 'alert alert-secondary py-1 small' }, 'Demonstration figures: quantities and rates are illustrative.'));
  if (!d.open) app.append(h('div', { class: 'alert alert-info' }, icon('lock'), ' This project is closed. Records can be viewed but not changed.'));

  const s = d.summary;
  app.append(h('div', { class: 'row g-2 mb-3' },
    chip('Materials below reorder level', s.low_stock, s.low_stock ? 'warning' : 'ok'),
    chip('Faulty equipment', s.faulty_equipment, s.faulty_equipment ? 'critical' : 'ok'),
    chip('Workers recorded today', qty(s.workers_today), null),
    chip('Open material requests', s.open_requests, s.open_requests ? 'information' : null)));

  app.append(panel);

  // Materials
  const mHead = ['Material', 'Unit', 'Received', 'Used', 'Balance', 'Reorder level'].concat(money ? ['Rate', 'Value used'] : []).concat(rec ? [''] : []);
  const mActions = m => [btn('box-arrow-in-down', 'Received', () => usageForm(ctx, m, 'received')), btn('box-arrow-up', 'Used', () => usageForm(ctx, m, 'used')),
    btn('cart-plus', 'Request', () => requestForm(ctx, m)), btn('pencil', 'Edit', () => editForm(ctx, m), 'btn-outline-secondary')];
  app.append(card('Materials', responsive(table(mHead, d.materials.map(m => [
    h('span', {}, m.name, m.planned_qty !== null ? h('div', { class: 'small text-body-secondary' }, `Planned ${qty(m.planned_qty)} ${m.unit}`) : null),
    m.unit, qty(m.received), qty(m.used),
    m.low ? badge('warning', `${qty(m.balance)} low`) : h('strong', {}, qty(m.balance)),
    m.reorder_level === null ? '' : qty(m.reorder_level),
    ...(money ? [m.unit_rate === null ? 'Not set' : fmtMoney(m.unit_rate), m.value_used === null ? '' : fmtMoney(m.value_used)] : []),
    ...(rec ? [h('div', { class: 'd-flex flex-wrap gap-1' }, mActions(m))] : []),
  ]), 'No materials recorded for this project yet.'), d.materials, m => itemCard(m.name, [
    h('span', {}, 'In store: ', m.low ? badge('warning', `${qty(m.balance)} ${m.unit}, below reorder level ${qty(m.reorder_level)}`) : h('strong', {}, `${qty(m.balance)} ${m.unit}`)),
    `Received ${qty(m.received)} · Used ${qty(m.used)}` + (m.reorder_level !== null && !m.low ? ` · Reorder at ${qty(m.reorder_level)}` : ''),
    money ? `Rate ${m.unit_rate === null ? 'not set' : fmtMoney(m.unit_rate)} · Value used ${m.value_used === null ? 'n/a' : fmtMoney(m.value_used)}` : null,
  ], rec ? mActions(m) : null)), rec ? addBtn(ctx, 'material') : null));

  // Labour
  const lHead = ['Labour', 'Today', 'Last 7 days', 'Total person-days'].concat(money ? ['Daily rate', 'Cost to date'] : []).concat(rec ? [''] : []);
  const lActions = l => [btn('person-check', 'Attendance', () => usageForm(ctx, l, 'attendance')), btn('pencil', 'Edit', () => editForm(ctx, l), 'btn-outline-secondary')];
  app.append(card('Labour', responsive(table(lHead, d.labour.map(l => [
    l.name, qty(l.today_qty), qty(l.week_qty), qty(l.person_days),
    ...(money ? [l.unit_rate === null ? 'Not set' : fmtMoney(l.unit_rate), l.value_used === null ? '' : fmtMoney(l.value_used)] : []),
    ...(rec ? [h('div', { class: 'd-flex flex-wrap gap-1' }, lActions(l))] : []),
  ]), 'No labour groups recorded yet.'), d.labour, l => itemCard(l.name, [
    h('span', {}, 'Today: ', h('strong', {}, qty(l.today_qty)), ` · Last 7 days ${qty(l.week_qty)} · Total ${qty(l.person_days)} person-days`),
    money ? `Daily rate ${l.unit_rate === null ? 'not set' : fmtMoney(l.unit_rate)} · Cost ${l.value_used === null ? 'n/a' : fmtMoney(l.value_used)}` : null,
  ], rec ? lActions(l) : null)), rec ? addBtn(ctx, 'labour') : null));

  // Equipment
  const eHead = ['Equipment', 'Status', 'Hours used'].concat(money ? ['Hourly rate', 'Cost to date'] : []).concat(rec ? [''] : []);
  const eActions = e => [btn('clock', 'Hours', () => usageForm(ctx, e, 'hours')), btn('wrench', 'Status', () => statusForm(ctx, e)), btn('pencil', 'Edit', () => editForm(ctx, e), 'btn-outline-secondary')];
  app.append(card('Equipment', responsive(table(eHead, d.equipment.map(e => [
    e.name, equipBadge(e.equipment_status), qty(e.hours),
    ...(money ? [e.unit_rate === null ? 'Not set' : fmtMoney(e.unit_rate), e.value_used === null ? '' : fmtMoney(e.value_used)] : []),
    ...(rec ? [h('div', { class: 'd-flex flex-wrap gap-1' }, eActions(e))] : []),
  ]), 'No equipment recorded yet.'), d.equipment, e => itemCard(e.name, [
    h('span', {}, equipBadge(e.equipment_status), ` · ${qty(e.hours)} hours used`),
    money ? `Hourly rate ${e.unit_rate === null ? 'not set' : fmtMoney(e.unit_rate)} · Cost ${e.value_used === null ? 'n/a' : fmtMoney(e.value_used)}` : null,
  ], rec ? eActions(e) : null)), rec ? addBtn(ctx, 'equipment') : null));

  // Material requests
  const myId = me?.id;
  app.append(card('Material requests', table(['Material', 'Quantity', 'Needed by', 'Status', 'Requested by', 'Remarks', ''], d.requests.map(r => {
    const actions = h('div', { class: 'd-flex flex-wrap gap-1' });
    if (d.open && d.can.decide && r.status === 'requested' && r.requested_by !== myId) actions.append(btn('check-lg', 'Approve', () => decide(ctx, r, 'approve'), 'btn-success'));
    if (d.open && rec && r.status === 'approved') actions.append(btn('truck', 'Delivered', () => deliverForm(ctx, r)));
    if (d.open && ((d.can.decide && ['requested', 'approved'].includes(r.status)) || (rec && r.status === 'requested' && r.requested_by === myId))) {
      actions.append(btn('x-lg', 'Cancel', () => decide(ctx, r, 'cancel'), 'btn-outline-danger'));
    }
    return [r.resource_name, `${qty(r.quantity)} ${r.unit}`, fmtDate(r.required_by), reqBadge(r.status),
      h('span', {}, r.requested_by_name, h('div', { class: 'small text-body-secondary' }, fmtDateTime(r.requested_at))), r.remarks || '', actions];
  }), 'No material requests yet.')));

  // Recent entries
  app.append(card('Recent entries', table(['Date', 'Item', 'Entry', 'Quantity', 'Activity', 'By', 'Remarks'], d.recent.map(m => [
    fmtDate(m.usage_date), m.resource_name, MOVE[m.movement] || m.movement, `${qty(m.quantity)} ${m.unit}`, m.activity_code || '', m.recorded_by_name, m.remarks || '',
  ]), 'Nothing recorded yet.')));

  watch(id, d.change_seq, () => { if (!panel.querySelector('form')) rerender(); });
}

/** Table on wide screens; one card per item on phones (360 px), with the actions full width. */
function responsive(tableEl, items, render) {
  if (!items.length) return tableEl;
  const list = h('div', { class: 'd-md-none' }, items.map(render));
  return h('div', {}, h('div', { class: 'd-none d-md-block' }, tableEl), list);
}

function itemCard(title, lines, actions) {
  return h('div', { class: 'border rounded p-2 mb-2' },
    h('div', { class: 'fw-semibold' }, title),
    lines.filter(Boolean).map(l => h('div', { class: 'small' }, l)),
    actions ? h('div', { class: 'd-flex flex-wrap gap-2 mt-2 res-actions' }, actions) : null);
}

function chip(label, value, tone) {
  return h('div', { class: 'col-6 col-md-3' }, h('div', { class: `card h-100 border-${tone === 'critical' ? 'danger' : tone === 'warning' ? 'warning' : 'secondary-subtle'}` },
    h('div', { class: 'card-body p-2' }, h('div', { class: 'small text-body-secondary' }, label), h('div', { class: 'fs-4 fw-semibold' }, String(value)), tone && tone !== 'ok' ? badge(tone) : null)));
}

function btn(ic, label, onclick, cls = 'btn-outline-primary') {
  return h('button', { type: 'button', class: `btn btn-sm ${cls}`, onclick }, icon(ic), ' ', label);
}

function addBtn(ctx, category) {
  const label = { material: 'Add material', labour: 'Add labour group', equipment: 'Add equipment' }[category];
  return h('button', { type: 'button', class: 'btn btn-sm btn-primary', onclick: () => itemForm(ctx, category) }, icon('plus-lg'), ' ', label);
}

/** Show one form in the panel at the top of the page and scroll to it. */
function openPanel(ctx, title, form) {
  const close = h('button', { type: 'button', class: 'btn-close', 'aria-label': 'Close', onclick: () => clear(ctx.panel) });
  clear(ctx.panel).append(h('section', { class: 'card border-primary shadow-sm mb-3' },
    h('div', { class: 'card-header d-flex align-items-center' }, h('h2', { class: 'h6 mb-0 flex-grow-1' }, title), close),
    h('div', { class: 'card-body' }, form)));
  ctx.panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  form.querySelector('input:not([type=hidden]), select')?.focus();
}

const activityOptions = acts => acts.filter(a => a.status === 'active').map(a => [a.id, `${a.code} ${a.name}`]);

/** Record received / used materials, labour attendance or equipment hours. Works offline. */
function usageForm(ctx, item, movement) {
  const titles = { received: `Record ${item.name} received`, used: `Record ${item.name} used`, attendance: `Record attendance: ${item.name}`, hours: `Record hours: ${item.name}` };
  const qLabel = { received: `Quantity received (${item.unit})`, used: `Quantity used (${item.unit})`, attendance: 'Number of workers present', hours: 'Hours worked (0 to 24)' }[movement];
  const help = movement === 'used' ? `In store now: ${qty(item.balance)} ${item.unit}.` : movement === 'received' ? 'Use the delivery note or waybill.' : '';
  const form = h('form', { novalidate: true },
    h('input', { type: 'hidden', name: 'movement', value: movement }),
    field('Date', h('input', { name: 'usage_date', type: 'date', value: todayIso(), max: todayIso(), required: true })),
    field(qLabel, h('input', { name: 'quantity', type: 'number', min: 0, step: movement === 'attendance' ? '1' : '0.01', inputmode: 'decimal', required: true }), help),
    movement !== 'received' ? field('Activity (optional)', select('activity_id', activityOptions(ctx.acts), '', 'Not linked to an activity')) : null,
    field('Remarks', h('input', { name: 'remarks', maxlength: 300, placeholder: movement === 'received' ? 'e.g. delivery note number' : '' })),
    h('div', { class: 'form-msg' }),
    h('button', { class: 'btn btn-primary w-100', type: 'submit' }, 'Save'));
  openPanel(ctx, titles[movement], form);
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const b = form.querySelector('button[type=submit]'); b.disabled = true;
    const body = formData(form);
    if (!body.activity_id) delete body.activity_id;
    body.client_uuid = uuid();
    const send = async extra => post(`/resources/${item.id}/usage`, { ...body, ...extra });
    try {
      await send({});
      toast('Saved.'); clear(ctx.panel); ctx.rerender();
    } catch (err) {
      if (err.code === 'NEGATIVE_BALANCE' && confirmDialog(err.message + '\n\nRecord it anyway?')) {
        try { await send({ confirm_negative: true }); toast('Saved. The balance is now below zero; check the store count.'); clear(ctx.panel); ctx.rerender(); return; }
        catch (err2) { showErrors(form, err2); b.disabled = false; return; }
      }
      if (err.network && await outbox.available()) {
        await outbox.enqueue({ client_uuid: body.client_uuid, type: 'usage', resource_id: item.id, body, label: `${item.name}: ${MOVE[movement]} ${body.quantity} ${item.unit} on ${fmtDate(body.usage_date)}` });
        showOk(form, 'No connection. Saved on this phone; it will be sent automatically when the connection returns.');
        setTimeout(() => clear(ctx.panel), 1800);
      } else { showErrors(form, err); b.disabled = false; }
    }
  });
}

/** Change equipment status (faulty raises alert A14). Works offline. */
function statusForm(ctx, item) {
  const form = h('form', { novalidate: true },
    field('Status', select('equipment_status', Object.entries(EQUIP).map(([k, [, l]]) => [k, l]), item.equipment_status)),
    field('Remarks', h('input', { name: 'remarks', maxlength: 300, placeholder: 'e.g. hydraulic leak, mechanic called' })),
    h('div', { class: 'form-msg' }), h('button', { class: 'btn btn-primary w-100', type: 'submit' }, 'Save status'));
  openPanel(ctx, `Status of ${item.name}`, form);
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const body = formData(form);
    try { await post(`/resources/${item.id}/equipment-status`, body); toast(body.equipment_status === 'faulty' ? 'Saved. The Project Manager is alerted.' : 'Saved.'); clear(ctx.panel); ctx.rerender(); }
    catch (err) {
      if (err.network && await outbox.available()) {
        await outbox.enqueue({ client_uuid: uuid(), type: 'equipment_status', resource_id: item.id, body, label: `${item.name}: status ${EQUIP[body.equipment_status]?.[1] || body.equipment_status}` });
        showOk(form, 'No connection. Saved on this phone; it will be sent automatically.');
        setTimeout(() => clear(ctx.panel), 1800);
      } else showErrors(form, err);
    }
  });
}

function requestForm(ctx, item) {
  const form = h('form', { novalidate: true },
    field(`Quantity needed (${item.unit})`, h('input', { name: 'quantity', type: 'number', min: 0, step: '0.01', inputmode: 'decimal', required: true }), `In store now: ${qty(item.balance)} ${item.unit}.`),
    field('Needed by', h('input', { name: 'required_by', type: 'date', min: todayIso(), required: true })),
    field('Remarks', h('input', { name: 'remarks', maxlength: 300, placeholder: 'e.g. for the drain lining' })),
    h('div', { class: 'form-msg' }), h('button', { class: 'btn btn-primary w-100', type: 'submit' }, 'Send request to the Project Manager'));
  openPanel(ctx, `Request ${item.name}`, form);
  form.addEventListener('submit', async e => {
    e.preventDefault();
    try { await post(`/projects/${ctx.id}/material-requests`, { ...formData(form), resource_id: item.id }); toast('Request sent.'); clear(ctx.panel); ctx.rerender(); }
    catch (err) { showErrors(form, err); }
  });
}

function deliverForm(ctx, r) {
  const form = h('form', { novalidate: true },
    field(`Quantity delivered (${r.unit})`, h('input', { name: 'quantity', type: 'number', min: 0, step: '0.01', value: r.quantity, required: true }), 'This quantity is added to the store balance.'),
    field('Date delivered', h('input', { name: 'usage_date', type: 'date', value: todayIso(), max: todayIso(), required: true })),
    field('Remarks', h('input', { name: 'remarks', maxlength: 200, placeholder: 'e.g. waybill number' })),
    h('div', { class: 'form-msg' }), h('button', { class: 'btn btn-primary w-100', type: 'submit' }, 'Mark delivered'));
  openPanel(ctx, `Delivery of ${r.resource_name}`, form);
  form.addEventListener('submit', async e => {
    e.preventDefault();
    try { await post(`/material-requests/${r.id}/deliver`, formData(form)); toast('Delivered and added to the store.'); clear(ctx.panel); ctx.rerender(); }
    catch (err) { showErrors(form, err); }
  });
}

async function decide(ctx, r, action) {
  try {
    if (action === 'approve') {
      if (!confirmDialog(`Approve the request for ${qty(r.quantity)} ${r.unit} of ${r.resource_name}?`)) return;
      await post(`/material-requests/${r.id}/approve`, {});
      toast('Request approved.');
    } else {
      const reason = window.prompt('Reason for cancelling this request:');
      if (reason === null) return;
      await post(`/material-requests/${r.id}/cancel`, { reason });
      toast('Request cancelled.');
    }
    ctx.rerender();
  } catch (err) { toast(err.message, 'danger'); }
}

/** Add a material, labour group or piece of equipment. Rates only for the Project Manager. */
function itemForm(ctx, category) {
  const units = ['bag', 't', 'm3', 'm2', 'm', 'pcs', 'kg', 'litre', 'trip'];
  const rates = ctx.d.can.rates;
  const rateLabel = { material: 'Unit price (GH₵, optional)', labour: 'Daily rate per worker (GH₵, optional)', equipment: 'Hourly rate (GH₵, optional)' }[category];
  const form = h('form', { novalidate: true },
    h('input', { type: 'hidden', name: 'category', value: category }),
    field('Name', h('input', { name: 'name', maxlength: 120, required: true, placeholder: { material: 'e.g. Cement (50 kg bag)', labour: 'e.g. Steel benders', equipment: 'e.g. Water tanker' }[category] })),
    category === 'material' ? field('Unit', select('unit', units.map(u => [u, u]), 'bag')) : null,
    category === 'material' ? field('Planned quantity (optional)', h('input', { name: 'planned_qty', type: 'number', min: 0, step: '0.01' })) : null,
    category === 'material' ? field('Reorder level (optional)', h('input', { name: 'reorder_level', type: 'number', min: 0, step: '0.01' }), 'An alert is raised when the balance falls below this level.') : null,
    category === 'equipment' ? field('Status', select('equipment_status', Object.entries(EQUIP).map(([k, [, l]]) => [k, l]), 'working')) : null,
    rates ? field(rateLabel, h('input', { name: 'unit_rate', type: 'number', min: 0, step: '0.01', inputmode: 'decimal' })) : h('p', { class: 'small text-body-secondary' }, 'The Project Manager adds prices and rates.'),
    h('div', { class: 'form-msg' }), h('button', { class: 'btn btn-primary w-100', type: 'submit' }, 'Add'));
  openPanel(ctx, { material: 'Add material', labour: 'Add labour group', equipment: 'Add equipment' }[category], form);
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const body = formData(form);
    for (const k of ['planned_qty', 'reorder_level', 'unit_rate']) if (body[k] === '') delete body[k];
    try { await post(`/projects/${ctx.id}/resources`, body); toast('Added.'); clear(ctx.panel); ctx.rerender(); }
    catch (err) { showErrors(form, err); }
  });
}

/** Edit an item: planned quantity and reorder level (PM and Site Engineer); rate and archive (PM only). */
function editForm(ctx, item) {
  const rates = ctx.d.can.rates;
  const form = h('form', { novalidate: true },
    field('Name', h('input', { name: 'name', maxlength: 120, value: item.name, required: true })),
    item.category === 'material' ? field('Planned quantity', h('input', { name: 'planned_qty', type: 'number', min: 0, step: '0.01', value: item.planned_qty ?? '' })) : null,
    item.category === 'material' ? field('Reorder level', h('input', { name: 'reorder_level', type: 'number', min: 0, step: '0.01', value: item.reorder_level ?? '' })) : null,
    rates ? field('Rate (GH₵)', h('input', { name: 'unit_rate', type: 'number', min: 0, step: '0.01', value: item.unit_rate ?? '' })) : null,
    rates ? h('div', { class: 'form-check mb-3' }, h('input', { class: 'form-check-input', type: 'checkbox', name: 'archive', id: `arch-${item.id}` }),
      h('label', { class: 'form-check-label', for: `arch-${item.id}` }, 'Archive this item (hide it; history is kept)')) : null,
    h('div', { class: 'form-msg' }), h('button', { class: 'btn btn-primary w-100', type: 'submit' }, 'Save changes'));
  openPanel(ctx, `Edit ${item.name}`, form);
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const b = formData(form);
    const body = { name: b.name };
    for (const k of ['planned_qty', 'reorder_level', 'unit_rate']) if (k in b) body[k] = b[k] === '' ? null : b[k];
    if (b.archive) body.status = 'archived';
    try { await patch(`/resources/${item.id}`, body); toast('Saved.'); clear(ctx.panel); ctx.rerender(); }
    catch (err) { showErrors(form, err); }
  });
}
