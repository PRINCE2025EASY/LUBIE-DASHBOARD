// Offline outbox (FR-38): entries are encrypted with AES-GCM and stored in IndexedDB until
// they are sent to /api/v1/sync. The key comes from the server for this user (HMAC of APP_KEY),
// is kept in sessionStorage for this tab, and is cleared at logout together with the outbox.
import { post } from './api.js';

const DB = 'lubie-outbox', STORE = 'items';
let keyPromise = null;

function idb() {
  return new Promise((resolve, reject) => {
    const r = indexedDB.open(DB, 1);
    r.onupgradeneeded = () => r.result.createObjectStore(STORE, { keyPath: 'client_uuid' });
    r.onsuccess = () => resolve(r.result);
    r.onerror = () => reject(r.error);
  });
}
async function tx(mode, fn) {
  const db = await idb();
  return new Promise((resolve, reject) => {
    const t = db.transaction(STORE, mode);
    const out = fn(t.objectStore(STORE));
    t.oncomplete = () => resolve(out?.result ?? out);
    t.onerror = () => reject(t.error);
  });
}
const b64 = buf => btoa(String.fromCharCode(...new Uint8Array(buf)));
const unb64 = s => Uint8Array.from(atob(s), c => c.charCodeAt(0));

export function rememberKey(rawB64) {
  if (!rawB64) return;
  try { sessionStorage.setItem('lubie-ok', rawB64); } catch (e) { /* private mode */ }
  keyPromise = null;
}
async function key() {
  let raw = null;
  try { raw = sessionStorage.getItem('lubie-ok'); } catch (e) { /* blocked */ }
  if (!raw || !window.crypto?.subtle) return null;
  keyPromise ??= crypto.subtle.importKey('raw', unb64(raw), 'AES-GCM', false, ['encrypt', 'decrypt']);
  return keyPromise;
}
export async function available() { return !!(window.indexedDB && await key()); }

export async function enqueue(item) {
  const k = await key();
  if (!k) throw new Error('Offline saving is not ready on this phone. Connect once to enable it.');
  const iv = crypto.getRandomValues(new Uint8Array(12));
  const data = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, k, new TextEncoder().encode(JSON.stringify(item)));
  await tx('readwrite', s => s.put({ client_uuid: item.client_uuid, iv: b64(iv), data: b64(data), queued_at: new Date().toISOString(), state: 'queued', label: item.label || '' }));
  window.dispatchEvent(new Event('outbox-changed'));
}
export async function list() { return (await tx('readonly', s => s.getAll())) || []; }
export async function count() { return (await list()).filter(i => i.state === 'queued').length; }

async function decrypt(row) {
  const k = await key();
  if (!k) return null;
  const plain = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: unb64(row.iv) }, k, unb64(row.data));
  return JSON.parse(new TextDecoder().decode(plain));
}

let syncing = false;
// Send queued items; keep rejected ones in a "needs attention" state with the server's reason.
export async function sync() {
  if (syncing || !navigator.onLine) return { sent: 0 };
  syncing = true;
  try {
    const rows = (await list()).filter(r => r.state === 'queued').slice(0, 50);
    if (!rows.length) return { sent: 0 };
    const items = [];
    for (const r of rows) { const it = await decrypt(r); if (it) items.push(it); }
    if (!items.length) return { sent: 0 };
    const results = await post('/sync', { items }, { timeout: 30000 });
    let sent = 0;
    for (const res of results) {
      if (res.status >= 200 && res.status < 300) { await tx('readwrite', s => s.delete(res.client_uuid)); sent++; }
      else if (res.status >= 400 && res.status < 500) {
        await tx('readwrite', s => { const g = s.get(res.client_uuid); g.onsuccess = () => { if (g.result) s.put({ ...g.result, state: 'rejected', error: res.error || 'Rejected' }); }; });
      }
    }
    window.dispatchEvent(new Event('outbox-changed'));
    return { sent };
  } finally { syncing = false; }
}
export async function discard(uuid) { await tx('readwrite', s => s.delete(uuid)); window.dispatchEvent(new Event('outbox-changed')); }
export async function wipe() {
  try { sessionStorage.removeItem('lubie-ok'); } catch (e) { /* ignore */ }
  keyPromise = null;
  try { await tx('readwrite', s => s.clear()); } catch (e) { /* no db */ }
}
