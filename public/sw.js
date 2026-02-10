// ============================================================
// dion.coach Service Worker — Offline Evaluation Support
// ============================================================

const CACHE_VERSION = 'v1';
const APP_SHELL_CACHE = 'dion-appshell-' + CACHE_VERSION;
const PAGE_CACHE = 'dion-pages-' + CACHE_VERSION;
const API_CACHE = 'dion-api-' + CACHE_VERSION;

// App shell resources (cache-first)
const APP_SHELL_URLS = [
    '/css/theme.css',
    '/css/camps.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
    '/js/offline-manager.js',
];

// ============================================================
// INSTALL — Pre-cache app shell
// ============================================================
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(APP_SHELL_CACHE)
            .then(cache => cache.addAll(APP_SHELL_URLS))
            .then(() => self.skipWaiting())
    );
});

// ============================================================
// ACTIVATE — Clean old caches
// ============================================================
self.addEventListener('activate', (event) => {
    const currentCaches = [APP_SHELL_CACHE, PAGE_CACHE, API_CACHE];
    event.waitUntil(
        caches.keys().then(names =>
            Promise.all(
                names
                    .filter(name => name.startsWith('dion-') && !currentCaches.includes(name))
                    .map(name => caches.delete(name))
            )
        ).then(() => self.clients.claim())
    );
});

// ============================================================
// FETCH — Routing strategies
// ============================================================
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Only handle GET requests for caching
    if (event.request.method !== 'GET') return;

    // Strategy: App Shell (cache-first)
    if (isAppShellRequest(url, event.request)) {
        event.respondWith(cacheFirst(event.request, APP_SHELL_CACHE));
        return;
    }

    // Strategy: Evaluate page (stale-while-revalidate)
    if (isEvaluatePage(url)) {
        event.respondWith(staleWhileRevalidate(event.request, PAGE_CACHE));
        return;
    }

    // Strategy: API (network-first)
    if (isApiRequest(url)) {
        event.respondWith(networkFirst(event.request, API_CACHE));
        return;
    }
});

// ============================================================
// URL Matchers
// ============================================================
function isAppShellRequest(url, request) {
    // CSS, JS files (local or CDN)
    if (url.pathname.endsWith('.css') || url.pathname.endsWith('.js')) {
        return url.pathname !== '/sw.js'; // Don't cache the SW itself
    }
    // CDN resources
    if (url.hostname.includes('cdn.jsdelivr.net')) return true;
    return false;
}

function isEvaluatePage(url) {
    return /^\/camps\/\d+\/evaluate$/.test(url.pathname);
}

function isApiRequest(url) {
    return url.pathname.startsWith('/api/');
}

// ============================================================
// Caching Strategies
// ============================================================

// Cache-first: Use cache, fallback to network
async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) return cached;

    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch (e) {
        return new Response('Offline', { status: 503 });
    }
}

// Stale-while-revalidate: Return cache immediately, update in background
async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    const fetchPromise = fetch(request).then(response => {
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    }).catch(() => null);

    return cached || (await fetchPromise) || new Response('Hors ligne — Veuillez préparer le camp pour utilisation hors-ligne.', {
        status: 503,
        headers: { 'Content-Type': 'text/html; charset=utf-8' }
    });
}

// Network-first: Try network, fallback to cache
async function networkFirst(request, cacheName) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch (e) {
        const cached = await caches.match(request);
        if (cached) return cached;
        return new Response(JSON.stringify({ error: 'offline' }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

// ============================================================
// Message handler — for "prepare offline" commands from client
// ============================================================
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'CACHE_PAGE') {
        const url = event.data.url;
        caches.open(PAGE_CACHE).then(cache => {
            return fetch(url, { credentials: 'same-origin' }).then(response => {
                if (response.ok) {
                    cache.put(url, response);
                    event.source.postMessage({ type: 'CACHE_PAGE_DONE', url, ok: true });
                } else {
                    event.source.postMessage({ type: 'CACHE_PAGE_DONE', url, ok: false });
                }
            });
        }).catch(() => {
            event.source.postMessage({ type: 'CACHE_PAGE_DONE', url: url, ok: false });
        });
    }
});
