const CACHE_NAME = 'koyomi-cache-v13'; // バージョン更新
const urlsToCache = [
  './css/style.css',
  './css/column-style.css',
  './js/scripts.js',
  './js/horizon-scroll.js',
  './js/config.js',
  './js/app.js',
  './manifest.json',
  './img/favicon/favicon-16.png',
  './img/favicon/favicon-32.png',
  './img/favicon/favicon-48.png',
  './img/favicon/icon-192.png',
  './img/favicon/icon-512.png',
  './img/favicon/apple-touch-icon.png',
  './img/favicon/favicon.ico'
];

// インストール時に静的アセットをキャッシュ
self.addEventListener('install', function(event) {
  console.log('サービスワーカーをインストール中...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        console.log('キャッシュを開きました: ' + CACHE_NAME);
        return Promise.all(
          urlsToCache.map(function(url) {
            return cache.add(url).catch(function(error) {
              console.log('キャッシュ失敗: ' + url + ' - ' + error.message);
              return Promise.resolve();
            });
          })
        );
      })
      .then(function() {
        return self.skipWaiting();
      })
  );
});

// ネットワークリクエストの傍受
self.addEventListener('fetch', function(event) {
  const url = new URL(event.request.url);
  const isNavigate = event.request.mode === 'navigate';
  const isPhp = url.pathname.endsWith('.php') || url.pathname.includes('.php');
  const isApi = url.pathname.includes('/api/');
  const isRoot = url.pathname === '/' || url.pathname.endsWith('/');

  // HTMLページ・PHP・APIへのリクエストは常にネットワークから最新を取得
  if (isNavigate || isPhp || isApi || isRoot) {
    event.respondWith(fetch(event.request));
    return;
  }

  // 静的アセットはキャッシュ優先
  event.respondWith(
    caches.match(event.request).then(function(response) {
      if (response) {
        return response;
      }
      return fetch(event.request).then(function(networkResponse) {
        if (!networkResponse || networkResponse.status !== 200 || networkResponse.type !== 'basic') {
          return networkResponse;
        }
        try {
          const responseToCache = networkResponse.clone();
          caches.open(CACHE_NAME).then(function(cache) {
            cache.put(event.request, responseToCache);
          });
        } catch (e) {
          console.error('キャッシュ保存エラー:', e);
        }
        return networkResponse;
      });
    })
  );
});

// 古いキャッシュの削除とアクティベート
self.addEventListener('activate', function(event) {
  console.log('サービスワーカーをアクティベート中...');
  const cacheWhitelist = [CACHE_NAME];

  event.waitUntil(
    caches.keys()
      .then(function(cacheNames) {
        return Promise.all(
          cacheNames.map(function(cacheName) {
            if (cacheWhitelist.indexOf(cacheName) === -1) {
              console.log('古いキャッシュを削除: ' + cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(function() {
        console.log('新しいサービスワーカーがすべてのクライアントを制御します');
        return self.clients.claim();
      })
  );
});

// --- ここからWeb Push通知対応 ---
self.addEventListener('push', function(event) {
  let data = {};
  try {
    data = event.data.json();
  } catch (e) {
    data = { title: '二十四節気・七十二候', body: '暦が変わりました' };
  }

  const title = data.title || '二十四節気・七十二候';
  const options = {
    body: data.body || '',
    icon: data.icon || './img/favicon/icon-192.png',
    badge: data.badge || './img/favicon/favicon-32.png',
    data: data.data || { url: './index.php' }
  };

  event.waitUntil(
    self.registration.showNotification(title, options)
  );
});

// 通知クリック時のイベント（そのときの暦のページを開く）
self.addEventListener('notificationclick', function(event) {
  event.notification.close();
  const rawUrl = (event.notification.data && event.notification.data.url) ? event.notification.data.url : './index.php';
  const urlToOpen = new URL(rawUrl, self.location.origin).href;

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(clientList) {
      for (let i = 0; i < clientList.length; i++) {
        let client = clientList[i];
        if ('focus' in client) {
          return client.focus().then(function(focusedClient) {
            const targetClient = focusedClient || client;
            if (targetClient && 'navigate' in targetClient) {
              return targetClient.navigate(urlToOpen);
            } else if (targetClient && 'postMessage' in targetClient) {
              targetClient.postMessage({ type: 'NAVIGATE', url: urlToOpen });
            }
          });
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(urlToOpen);
      }
    })
  );
});
// --- ここまでWeb Push通知対応 ---
