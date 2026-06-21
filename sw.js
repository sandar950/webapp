const CACHE_NAME = 'thai2d3d-v2';
const urlsToCache = [
  './manifest.json'
];

// Install Event - Cache assets
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(urlsToCache).catch(err => console.log('Cache addAll error:', err));
      })
  );
  self.skipWaiting();
});

// Fetch Event - Network first for PHP and dynamic content
self.addEventListener('fetch', event => {
  event.respondWith(
    fetch(event.request).catch(function() {
      return caches.match(event.request).then(function(response) {
        return response; 
      });
    })
  );
});

// Activate Event - Clean up old caches
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
  self.clients.claim();
});

// Push Notification Event
self.addEventListener('push', event => {
  const data = event.data ? event.data.json() : {};
  const title = data.title || 'Thai 2D3D Update';
  const options = {
    body: data.body || 'You have a new message!',
    icon: 'https://via.placeholder.com/192/1a428a/FFFFFF?text=2D3D',
    badge: 'https://via.placeholder.com/192/1a428a/FFFFFF?text=2D3D',
    vibrate: [100, 50, 100],
    data: {
      url: data.url || '/'
    }
  };

  event.waitUntil(
    self.registration.showNotification(title, options)
  );
});

// Notification Click Event
self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil(
    clients.openWindow(event.notification.data.url)
  );
});
