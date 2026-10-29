// Service worker minimal agar aplikasi dapat dipasang (PWA). Tidak menyimpan data transaksi di cache.
self.addEventListener('install', (event) => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => {});
