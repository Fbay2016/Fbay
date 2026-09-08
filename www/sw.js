/* =========================================================
   FBAY PWA CACHE
========================================================= */

const CACHE = "fbay-pwa-v2";

const FILES = [
    "fbay.html",
    "manifest.json",
    "icons/icon-192.png",
    "icons/icon-512.png",
    "download.png"
];


/* ================= INSTALL ================= */

self.addEventListener("install", event => {

    self.skipWaiting();

    event.waitUntil(
        caches.open(CACHE).then(cache => {
            return cache.addAll(FILES);
        })
    );

});


/* ================= ACTIVATE ================= */

self.addEventListener("activate", event => {

    event.waitUntil(

        caches.keys().then(cacheNames => {

            return Promise.all(

                cacheNames
                    .filter(name => name !== CACHE)
                    .map(name => caches.delete(name))

            );

        })

    );

    self.clients.claim();

});


/* =========================================================
   FETCH
========================================================= */

self.addEventListener("fetch", event => {

    // Only GET requests
    if (event.request.method !== "GET") {
        return;
    }

    const url = new URL(event.request.url);


    /* =====================================================
       NEVER CACHE LOGIN / OTP / SESSION PHP
    ===================================================== */

    if (
        url.pathname.endsWith("/auth.php") ||
        url.pathname.endsWith("/check-status.php") ||
        url.pathname.endsWith("/dashboard.php")
    ) {

        event.respondWith(
            fetch(event.request)
        );

        return;
    }


    /* =====================================================
       CACHE FIRST
    ===================================================== */

    event.respondWith(

        caches.match(event.request).then(cachedResponse => {

            if (cachedResponse) {
                return cachedResponse;
            }

            return fetch(event.request).then(response => {

                if (
                    response &&
                    response.status === 200 &&
                    url.origin === self.location.origin
                ) {

                    const responseClone = response.clone();

                    caches.open(CACHE).then(cache => {
                        cache.put(event.request, responseClone);
                    });

                }

                return response;

            });

        })

    );

});