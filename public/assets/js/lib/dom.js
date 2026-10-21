// DOM helpers. Text is always inserted with textContent (XSS-safe); no innerHTML with data.
export function h(tag, attrs = {}, ...children) {
  const el = document.createElement(tag);
  for (const [k, v] of Object.entries(attrs || {})) {
    if (v === null || v === undefined || v === false) continue;
    if (k === 'class') el.className = v;
    else if (k === 'dataset') Object.assign(el.dataset, v);
    else if (k.startsWith('on') && typeof v === 'function') el.addEventListener(k.slice(2), v);
    else if (v === true) el.setAttribute(k, '');
    else el.setAttribute(k, String(v));
  }
  for (const c of children.flat(Infinity)) {
    if (c === null || c === undefined || c === false) continue;
    el.append(c instanceof Node ? c : document.createTextNode(String(c)));
  }
  return el;
}
export const icon = (name, label) => h('i', { class: `bi bi-${name}`, 'aria-hidden': label ? null : 'true', 'aria-label': label || null });
export function clear(el) { while (el.firstChild) el.removeChild(el.firstChild); return el; }

const money = new Intl.NumberFormat('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
export function fmtMoney(v) { if (v === null || v === undefined || v === '') return 'Not available'; const n = Number(v); return (n < 0 ? '-' : '') + 'GH₵ ' + money.format(Math.abs(n)); }
export function fmtPct(v, d = 1) { return v === null || v === undefined ? 'Not available' : Number(v).toFixed(d) + '%'; }
export function fmtNum(v, d = 2) { return v === null || v === undefined ? 'Not available' : Number(v).toFixed(d); }
export function fmtDate(iso) { if (!iso) return ''; const [y, m, d] = String(iso).slice(0, 10).split('-'); return `${d}/${m}/${y}`; }
export function fmtDateTime(s) { if (!s) return ''; const t = String(s).replace('T', ' '); return fmtDate(t.slice(0, 10)) + ' ' + t.slice(11, 16); }
export function todayIso() { const d = new Date(); return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10); }

const TONES = {
  ok: ['success', 'check-circle', 'On track'], warning: ['warning', 'exclamation-triangle', 'Warning'], critical: ['danger', 'x-octagon', 'Critical'],
  information: ['info', 'info-circle', 'Information'], medium: ['warning', 'exclamation-circle', 'Medium'], high: ['danger', 'exclamation-octagon', 'High'],
};
// Status is always colour + icon + text (never colour alone).
export function badge(tone, text) {
  const [cls, ic, label] = TONES[tone] || TONES.information;
  return h('span', { class: `badge text-bg-${cls} status-badge` }, icon(ic), ' ', text || label);
}
export function scheduleBadge(a) { return badge(a.tone, a.schedule_label + (a.days_late ? ` (${a.days_late} days)` : '')); }

export function card(title, body, extra) {
  return h('section', { class: 'card shadow-sm mb-3' },
    h('div', { class: 'card-header d-flex align-items-center gap-2' }, h('h2', { class: 'h6 mb-0 flex-grow-1' }, title), extra || null),
    h('div', { class: 'card-body' }, body));
}
export function kpiCard(label, value, formula, tone) {
  return h('div', { class: 'col-6 col-md-4 col-xl-2' },
    h('div', { class: `card kpi-card h-100 border-${tone === 'critical' ? 'danger' : tone === 'warning' ? 'warning' : 'secondary-subtle'}` },
      h('div', { class: 'card-body p-2' },
        h('div', { class: 'small text-body-secondary d-flex justify-content-between' }, label,
          formula ? h('button', { type: 'button', class: 'btn btn-link p-0 small kpi-info', 'data-bs-toggle': 'tooltip', title: formula, 'aria-label': `How ${label} is calculated: ${formula}` }, icon('info-circle')) : null),
        h('div', { class: 'fs-4 fw-semibold kpi-value' }, value),
        tone ? badge(tone) : null)));
}
export function table(headers, rows, empty = 'Nothing to show yet.') {
  if (!rows.length) return h('p', { class: 'text-body-secondary mb-0' }, empty);
  // Focusable scroll region so keyboard users can scroll wide tables on phones (WCAG 2.1.1).
  return h('div', { class: 'table-responsive', tabindex: '0', role: 'region', 'aria-label': 'Table, scroll sideways for more columns' }, h('table', { class: 'table table-sm align-middle mb-0' },
    h('thead', {}, h('tr', {}, headers.map(x => h('th', { scope: 'col' }, x)))),
    h('tbody', {}, rows.map(r => h('tr', {}, r.map(c => h('td', {}, c)))))));
}
export function field(label, input, help) {
  const id = input.id || ('f-' + Math.random().toString(36).slice(2));
  input.id = id;
  if (!input.classList.contains('form-check-input')) input.classList.add(input.tagName === 'SELECT' ? 'form-select' : 'form-control');
  return h('div', { class: 'mb-3' }, h('label', { class: 'form-label', for: id }, label), input,
    h('div', { class: 'invalid-feedback', 'data-for': input.name }), help ? h('div', { class: 'form-text' }, help) : null);
}
export function select(name, options, value, placeholder) {
  return h('select', { name }, placeholder !== undefined ? h('option', { value: '' }, placeholder) : null,
    options.map(([v, l]) => h('option', { value: v, selected: String(v) === String(value ?? '') }, l)));
}
export function spinner() { return h('div', { class: 'text-center py-4' }, h('div', { class: 'spinner-border', role: 'status' }, h('span', { class: 'visually-hidden' }, 'Loading'))); }
export function emptyState(text) { return h('p', { class: 'text-body-secondary' }, text); }
