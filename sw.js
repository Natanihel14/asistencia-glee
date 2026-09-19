const CACHE_NAME = 'glee-cache-v2';
const urlsToCache = [
  '/assets/css/style.css',
  '/assets/img/logo.png',
  '/assets/img/bg-login.jpg'
];

self.addEventListener('install', event => {
  self.skipWaiting();
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(urlsToCache);
      })
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cache => {
          if (cache !== CACHE_NAME) {
            return caches.delete(cache);
          }
        })
      );
    })
  );
});

self.addEventListener('fetch', event => {
  // Para las peticiones a archivos PHP o a la raiz, ir SIEMPRE a la red primero (Network First).
  // Si no hay red, intenta sacar algo del cache.
  if (event.request.mode === 'navigate' || event.request.url.includes('.php')) {
    event.respondWith(
      fetch(event.request).catch(() => caches.match(event.request))
    );
    return;
  }

  // Para assets (CSS, IMG), ir al cache primero (Cache First)
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        return response || fetch(event.request);
      })
  );
});
