/*
 * Traspaso's service worker: shows the nightly push notifications and
 * opens the café when one is tapped. It doesn't cache pages; the game
 * needs the server anyway.
 */

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) =>
    event.waitUntil(self.clients.claim()),
);

self.addEventListener('push', (event) => {
    let message = { title: 'Traspaso', body: '', url: '/games/latest' };

    try {
        message = { ...message, ...event.data.json() };
    } catch {
        // A push without a JSON payload still shows something.
    }

    event.waitUntil(
        self.registration.showNotification(message.title, {
            body: message.body,
            icon: '/icons/icon-192.png',
            badge: '/icons/icon-192.png',
            tag: message.tag,
            renotify: Boolean(message.tag),
            data: { url: message.url },
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = new URL(
        event.notification.data?.url ?? '/games/latest',
        self.location.origin,
    ).href;

    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((windows) => {
                const open = windows.find((w) => w.url === url) ?? windows[0];

                return open
                    ? open.focus().then((w) => w.navigate(url))
                    : self.clients.openWindow(url);
            }),
    );
});
