/* Applies the saved light/dark preference before first paint (no inline scripts: CSP). */
(function () {
  var pref = document.documentElement.getAttribute('data-theme-pref') || 'system';
  try { pref = localStorage.getItem('lubie-theme') || pref; } catch (e) { /* storage blocked */ }
  var dark = pref === 'dark' || (pref === 'system' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
  document.documentElement.setAttribute('data-bs-theme', dark ? 'dark' : 'light');
})();
