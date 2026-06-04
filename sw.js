// sw.js — Service Worker para Sensor.IO PWA
const CACHE = 'sensorio-v1';

// Recursos a cachear para funcionar offline (página de error)
const OFFLINE = [
  '/offline.html'
];

// Instalar: cachear página offline
self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(CACHE).then(c => c.addAll(OFFLINE))
  );
  self.skipWaiting();
});

// Activar: limpiar caches viejos
self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys =>
      Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))
    )
  );
  self.clients.claim();
});

// Fetch: network-first (siempre datos frescos), fallback offline
self.addEventListener('fetch', e => {
  // Solo interceptar peticiones GET al mismo origen
  if (e.request.method !== 'GET') return;

  e.respondWith(
    fetch(e.request)
      .catch(() => caches.match('/offline.html'))
  );
});