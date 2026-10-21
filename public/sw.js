/* Service worker (FR-38): caches the app shell and the last copy of read-only API data so
   site staff can open their activities without a connection. Writes are never cached; they
   go through the encrypted outbox in the page. Versioned cache; old caches are deleted. */
const VERSION = 'lubie-v0.3.0';
const SCOPE = new URL(self.registration.scope).pathname.replace(/\/$/, '');
const SHELL = [
  '/offline', '/assets/css/app.css', '/assets/js/app.js', '/assets/js/theme-init.js', '/assets/js/public.js',
  '/assets/js/lib/api.js', '/assets/js/lib/dom.js', '/assets/js/lib/outbox.js', '/assets/js/lib/drafts.js', '/assets/js/lib/poll.js', '/assets/js/lib/charts.js',
  '/assets/js/views/common.js', '/assets/js/views/site.js', '/assets/js/views/pm.js', '/assets/js/views/qs.js',
  '/assets/js/views/director.js', '/assets/js/views/client.js', '/assets/js/views/admin.js',
  '/assets/vendor/bootstrap/bootstrap.min.css', '/assets/vendor/bootstrap/bootstrap.bundle.min.js',
  '/assets/vendor/bootstrap-icons/bootstrap-icons.min.css', '/assets/vendor/bootstrap-icons/fonts/bootstrap-icons.woff2',
  '/assets/vendor/chartjs/chart.umd.min.js', '/assets/icon.svg', '/manifest.webmanifest',
].map(p => SCOPE + p);
const NO_CACHE_API = ['/api/v1/changes', '/api/v1/auth/', '/api/v1/reports', '/api/v1/admin', '/api/v1/audit', '/api/v1/backup', '/api/v1/users', '/api/v1/settings'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(VERSION).then(c => c.addAll(SHELL)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(k => k !== VERSION).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('message', e => {
  if (e.data === 'logout') e.waitUntil(caches.delete(VERSION).then(() => caches.open(VERSION)).then(c => c.addAll(SHELL)));
});

async function networkFirst(req, fallbackUrl) {
  const cache = await caches.open(VERSION);
  try {
    const res = await fetch(req);
    if (res.ok && req.method === 'GET') cache.put(req, res.clone());
    return res;
  } catch (err) {
    const hit = await cache.match(req, { ignoreSearch: false });
    if (hit) {
      const body = await hit.blob();
      const headers = new Headers(hit.headers);
      headers.set('X-Offline-Copy', '1');
      return new Response(body, { status: 200, headers });
    }
    if (fallbackUrl) return (await cache.match(fallbackUrl)) || Response.error();
    return new Response(JSON.stringify({ error: { code: 'OFFLINE', message: 'You are offline and this data was not saved on this phone yet.' } }),
      { status: 503, headers: { 'Content-Type': 'application/json' } });
  }
}

self.addEventListener('fetch', e => {
  const req = e.request;
  const url = new URL(req.url);
  if (url.origin !== location.origin || req.method !== 'GET') return; // POST/PUT/PATCH go straight to the network
  const path = url.pathname.slice(SCOPE.length) || '/';
  if (req.mode === 'navigate') { e.respondWith(networkFirst(req, SCOPE + '/offline')); return; }
  if (path.startsWith('/api/')) {
    if (NO_CACHE_API.some(p => path.startsWith(p))) return;
    e.respondWith(networkFirst(req));
    return;
  }
  if (path.startsWith('/assets/') || path === '/manifest.webmanifest') {
    e.respondWith(caches.match(req, { ignoreSearch: true }).then(hit => hit || fetch(req).then(res => {
      if (res.ok) caches.open(VERSION).then(c => c.put(req, res.clone()));
      return res;
    })));
  }
});
