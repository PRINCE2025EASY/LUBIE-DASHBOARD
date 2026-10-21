// Near-real-time refresh (FR-24): asks /changes for a change counter every interval while the
// tab is visible; full data are fetched only when something changed. Backoff on failure.
import { get } from './api.js';

export function startPolling({ projectId = null, seq = -1, interval = 60, onChange, onStatus }) {
  let timer = null, failures = 0, lastSeq = seq, stopped = false;
  const tick = async () => {
    if (stopped) return;
    if (document.visibilityState !== 'visible') { schedule(); return; }
    try {
      const q = new URLSearchParams({ since: String(lastSeq) });
      if (projectId) q.set('project_id', projectId);
      const r = await get('/changes?' + q.toString(), { timeout: 10000 });
      failures = 0;
      onStatus?.({ ok: true, checked: r.server_time, unread: r.unread_alerts });
      if (r.changed && lastSeq !== -1) await onChange?.(r);
      lastSeq = r.seq;
    } catch (e) {
      failures++;
      onStatus?.({ ok: false, error: e, waking: e.status === 503 });
    }
    schedule();
  };
  const schedule = () => {
    clearTimeout(timer);
    const delay = failures ? Math.min(600, interval * 2 ** Math.min(failures, 4)) : interval;
    timer = setTimeout(tick, delay * 1000);
  };
  const onVis = () => { if (document.visibilityState === 'visible') { clearTimeout(timer); tick(); } };
  document.addEventListener('visibilitychange', onVis);
  tick();
  return { stop() { stopped = true; clearTimeout(timer); document.removeEventListener('visibilitychange', onVis); }, setSeq(s) { lastSeq = s; } };
}
