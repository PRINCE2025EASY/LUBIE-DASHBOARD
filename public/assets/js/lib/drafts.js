// Draft autosave (FR-37): keeps unsent form input in this browser only. Not offline mode.
const KEY = 'lubie-draft:';
export function bindDraft(form, id) {
  const k = KEY + id;
  try {
    const saved = JSON.parse(localStorage.getItem(k) || 'null');
    if (saved) for (const [n, v] of Object.entries(saved)) { const el = form.elements[n]; if (el && el.type !== 'hidden') el.value = v; }
  } catch (e) { /* storage blocked */ }
  form.addEventListener('input', () => {
    const o = {};
    for (const el of form.elements) if (el.name && el.type !== 'hidden' && el.type !== 'submit') o[el.name] = el.value;
    try { localStorage.setItem(k, JSON.stringify(o)); } catch (e) { /* ignore */ }
  });
  return () => { try { localStorage.removeItem(k); } catch (e) { /* ignore */ } };
}
export function clearAllDrafts() {
  try { Object.keys(localStorage).filter(k => k.startsWith(KEY)).forEach(k => localStorage.removeItem(k)); } catch (e) { /* ignore */ }
}
