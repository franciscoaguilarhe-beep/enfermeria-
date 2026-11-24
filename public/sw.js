const CACHE_NAME = 'srae-static-v1';
const STATIC_ASSETS = [
  './assets/css/patients.css',
  './assets/css/configuracion.css',
  './assets/css/app.css',
  './assets/js/app.js',
  './assets/images/icon-192.png',
  './assets/images/icon-512.png',
  './manifest.json'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => cache.addAll(STATIC_ASSETS))
      .catch(() => {})
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(keys => Promise.all(
        keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  const isStaticAsset = /\.(css|js|png|jpg|jpeg|svg|ico|woff2?)$/i.test(url.pathname);
  const isApiOrPhp = /\.php$/i.test(url.pathname) || url.pathname.includes('/api/');

  // Network-First estricto para páginas PHP y APIs clínicas (nunca servir datos obsoletos)
  if (isApiOrPhp || !isStaticAsset) {
    event.respondWith(
      fetch(req).catch(() => caches.match(req))
    );
    return;
  }

  // Caché ligero con refresco en segundo plano para assets estáticos
  event.respondWith(
    caches.match(req).then(cached => {
      const networkFetch = fetch(req).then(response => {
        if (response && response.status === 200 && response.type === 'basic') {
          const clone = response.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(req, clone)).catch(() => {});
        }
        return response;
      }).catch(() => cached);

      return cached || networkFetch;
    })
  );
});
