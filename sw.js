// Precious Satin Bouquet service worker. Bump V to force clients to refresh caches.
const V = 'psb-v1';
const PRECACHE = ['offline.html', 'manifest.webmanifest', 'icons/icon-192.png', 'icons/icon-512.png'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(V).then(c => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(k => k !== V).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', e => {
  const r = e.request, u = new URL(r.url);
  if (r.method !== 'GET') return;                       // never touch POST (cart, checkout, GCash, login)

  // Pages are session-based PHP: always go to the network, show offline page only if it fails.
  if (r.mode === 'navigate') {
    e.respondWith(fetch(r).catch(() => caches.match('offline.html')));
    return;
  }

  // Static assets only (css/js/images/fonts): stale-while-revalidate.
  const okHost = u.origin === location.origin || u.hostname === 'fonts.googleapis.com' || u.hostname === 'fonts.gstatic.com';
  const isAsset = /\.(css|js|png|jpe?g|gif|webp|svg|ico|woff2?|ttf)$/i.test(u.pathname) || u.hostname.startsWith('fonts.');
  if (!okHost || !isAsset || u.pathname.endsWith('/sw.js')) return;

  e.respondWith(
    caches.open(V).then(c => c.match(r).then(hit => {
      const net = fetch(r).then(res => { if (res.ok || res.type === 'opaque') c.put(r, res.clone()); return res; }).catch(() => hit);
      return hit || net;
    }))
  );
});
