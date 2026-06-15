const CACHE = 'portoaccess-v4';
const OFFLINE_URL = '/offline';

self.addEventListener('install', (e) => {
    self.skipWaiting();
});

self.addEventListener('activate', (e) => {
    e.waitUntil(
        caches.keys().then((keys) =>
            Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))
        )
    );
    self.clients.claim();
});

self.addEventListener('fetch', (e) => {
    if (e.request.method !== 'GET') return;
    if (!e.request.url.startsWith(self.location.origin)) return;

    // For navigation requests, try network first, fall back to cache
    if (e.request.mode === 'navigate') {
        e.respondWith(
            fetch(e.request)
                .then((res) => {
                    const clone = res.clone();
                    caches.open(CACHE).then((c) => c.put(e.request, clone));
                    return res;
                })
                .catch(() => caches.match(e.request))
        );
        return;
    }

    // For assets (JS, CSS, images), cache-first
    if (/\.(js|css|woff2?|png|svg|ico)(\?|$)/.test(e.request.url)) {
        e.respondWith(
            caches.match(e.request).then(
                (cached) =>
                    cached ||
                    fetch(e.request).then((res) => {
                        const clone = res.clone();
                        caches.open(CACHE).then((c) => c.put(e.request, clone));
                        return res;
                    })
            )
        );
    }
});
