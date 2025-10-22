// /console/pwa/sw.js
const CACHE = 'vallas-admin-v1';
const CORE = [
  '/console/portal/',
  '/console/asset/css/base.css',
  '/console/asset/css/dashboard.css',
  '/console/asset/js/dashboard.js',
  '/console/pwa/manifest.json'
];
// runtime: CDN y AJAX
self.addEventListener('install', e=>{
  self.skipWaiting();
  e.waitUntil(caches.open(CACHE).then(c=>c.addAll(CORE)));
});
self.addEventListener('activate', e=>{
  e.waitUntil(caches.keys().then(keys=>Promise.all(keys.map(k=>k!==CACHE && caches.delete(k)))));
  self.clients.claim();
});
self.addEventListener('fetch', e=>{
  const req = e.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);

  // API: network-first
  if (url.pathname === '/console/portal/ajax/dashboard.php') {
    e.respondWith(
      fetch(req).then(res=>{
        const clone = res.clone();
        caches.open(CACHE).then(c=>c.put(req, clone));
        return res;
      }).catch(()=>caches.match(req))
    );
    return;
  }

  // CDN Chart.js: cache-first
  if (url.hostname.includes('cdn.jsdelivr.net')) {
    e.respondWith(
      caches.match(req).then(hit=>hit||fetch(req).then(res=>{
        const clone = res.clone();
        caches.open(CACHE).then(c=>c.put(req, clone));
        return res;
      }))
    );
    return;
  }

  // Otros estáticos bajo /console: cache-first
  if (url.pathname.startsWith('/console/')) {
    e.respondWith(
      caches.match(req).then(hit=>hit||fetch(req).then(res=>{
        const clone = res.clone();
        caches.open(CACHE).then(c=>c.put(req, clone));
        return res;
      }).catch(()=>caches.match('/console/portal/')))
    );
  }
});
