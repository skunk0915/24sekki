const CACHE_NAME = 'koyomi-cache-v1';
const urlsToCache = [
  './',
  './index.php',
  './calendar.php',
  './functions.php',
  './css/style.css',
  './js/scripts.js',
  './manifest.json',
  './img/favicon/favicon-16.png',
  './img/favicon/favicon-32.png',
  './img/favicon/favicon-48.png',
  './img/favicon/icon-192.png',
  './img/favicon/icon-512.png',
  './img/favicon/apple-touch-icon.png',
  './img/favicon/favicon.ico'
];

// インストール時にキャッシュを設定
self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        console.log('キャッシュを開きました');
        
        // 個別にキャッシュを試み、一部が失敗しても全体が失敗しないようにする
        return Promise.all(
          urlsToCache.map(function(url) {
            return cache.add(url).catch(function(error) {
              console.log('キャッシュ失敗: ' + url + ' - ' + error.message);
              // エラーを食い止めて続行する
              return Promise.resolve();
            });
          })
        );
      })
  );
  // インストール完了時にアクティベートを強制する
  self.skipWaiting();
});

// ネットワークリクエストの傍受
self.addEventListener('fetch', function(event) {
  event.respondWith(
    caches.match(event.request)
      .then(function(response) {
        // キャッシュ内に一致するレスポンスがあればそれを返す
        if (response) {
          return response;
        }
        return fetch(event.request).then(
          function(response) {
            // 有効なレスポンスかチェック
            if(!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }

            // レスポンスを複製（レスポンスは一度しか使用できないため）
            var responseToCache = response.clone();

            caches.open(CACHE_NAME)
              .then(function(cache) {
                cache.put(event.request, responseToCache);
              });

            return response;
          }
        );
      })
    );
});

// 古いキャッシュの削除
self.addEventListener('activate', function(event) {
  var cacheWhitelist = [CACHE_NAME];
  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames.map(function(cacheName) {
          if (cacheWhitelist.indexOf(cacheName) === -1) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
});
