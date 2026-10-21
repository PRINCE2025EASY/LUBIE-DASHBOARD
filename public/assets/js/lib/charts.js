// Chart.js wrappers: planned vs earned (S-curve) and budget vs expenditure. Colours plus
// distinct dash patterns so lines are distinguishable without colour.
const charts = new WeakMap();
const css = v => getComputedStyle(document.documentElement).getPropertyValue(v).trim();

function draw(canvas, config) {
  charts.get(canvas)?.destroy();
  const c = new window.Chart(canvas, config);
  charts.set(canvas, c);
  return c;
}
function baseOpts(yLabel) {
  const text = css('--bs-body-color') || '#333', grid = css('--bs-border-color') || '#ddd';
  return {
    responsive: true, maintainAspectRatio: false, animation: false,
    interaction: { mode: 'index', intersect: false },
    plugins: { legend: { labels: { color: text } } },
    scales: { x: { ticks: { color: text, maxTicksLimit: 8 }, grid: { color: grid } }, y: { beginAtZero: true, title: { display: true, text: yLabel, color: text }, ticks: { color: text }, grid: { color: grid } } },
  };
}
export function sCurve(canvas, series, money) {
  const labels = series.map(p => p.date.split('-').reverse().join('/'));
  const pv = series.map(p => money ? (p.pv === null ? null : Number(p.pv)) : p.pv_pct);
  const ev = series.map(p => money ? (p.ev === null ? null : Number(p.ev)) : p.ev_pct);
  return draw(canvas, {
    type: 'line',
    data: { labels, datasets: [
      { label: money ? 'Planned value (PV)' : 'Planned %', data: pv, borderColor: '#0d6efd', borderDash: [6, 4], pointRadius: 0, tension: 0.1 },
      { label: money ? 'Earned value (EV)' : 'Earned %', data: ev, borderColor: '#198754', pointRadius: 2, tension: 0.1, spanGaps: false },
    ] },
    options: baseOpts(money ? 'GH₵' : '% of project'),
  });
}
export function budgetBars(canvas, budget, byCategory) {
  const cats = Object.keys(byCategory);
  return draw(canvas, {
    type: 'bar',
    data: { labels: ['Current budget', 'Spent to date', ...cats.map(c => 'Spent: ' + c)],
      datasets: [{ label: 'GH₵', data: [Number(budget || 0), cats.reduce((a, c) => a + Number(byCategory[c]), 0), ...cats.map(c => Number(byCategory[c]))],
        backgroundColor: ['#6c757d', '#fd7e14', ...cats.map(() => '#ffc107')] }] },
    options: { ...baseOpts('GH₵'), indexAxis: 'y', plugins: { legend: { display: false } } },
  });
}
