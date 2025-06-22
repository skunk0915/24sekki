const CACHE_NAME = 'koyomi-cache-v9'; // バージョン更新
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
  console.log('サービスワーカーをインストール中...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(function(cache) {
        console.log('キャッシュを開きました: ' + CACHE_NAME);
        
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
      .then(function() {
        // インストール完了時にアクティベートを強制する
        return self.skipWaiting();
      })
  );
});

// ネットワークリクエストの傍受
self.addEventListener('fetch', function(event) {
  // PHPファイルへのリクエストは常に最新を取得（キャッシュを使わない）
  if (event.request.url.match(/\.php(\?.*)?$/) || event.request.url.includes('/api/')) {
    console.log('PHPまたはAPIリクエスト - ネットワーク優先: ' + event.request.url);
    event.respondWith(
      fetch(event.request)
        .then(function(response) {
          return response;
        })
        .catch(function() {
          // オフライン時のフォールバック
          return caches.match(event.request);
        })
    );
    return;
  }
  
  // その他のリクエストはキャッシュファースト戦略
  event.respondWith(
    caches.match(event.request)
      .then(function(response) {
        // キャッシュ内に一致するレスポンスがあればそれを返す
        if (response) {
          return response;
        }
        
        // キャッシュにない場合はネットワークから取得
        return fetch(event.request).then(
          function(response) {
            // 有効なレスポンスかチェック
            if(!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }

            try {
              // レスポンスを複製（レスポンスは一度しか使用できないため）
              var responseToCache = response.clone();

              caches.open(CACHE_NAME)
                .then(function(cache) {
                  try {
                    cache.put(event.request, responseToCache);
                  } catch (cacheError) {
                    console.error('キャッシュ保存エラー:', cacheError);
                  }
                });
            } catch (cloneError) {
              console.error('レスポンス複製エラー:', cloneError);
            }

            return response;
          }
        );
      })
      .catch(function(error) {
        console.log('フェッチエラー:', error);
        // オフライン時のフォールバック処理をここに追加できます
      })
    );
});

// 古いキャッシュの削除
self.addEventListener('activate', function(event) {
});

// --- ここからWeb Push通知対応 ---
self.addEventListener('push', function (event) {
  let data = {};
  try {
    data = event.data.json();
  } catch (e) {
    data = { title: '通知', body: '暦が変わりました' };
  }
  event.waitUntil(
    self.registration.showNotification(data.title, {
      body: data.body,
      icon: '/img/favicon/icon-192.png', // 必要に応じてパス調整
    })
  );
});
// --- ここまでWeb Push通知対応 ---

self.addEventListener('activate', function(event) {
  console.log('サービスワーカーをアクティベート中...');
  var cacheWhitelist = [CACHE_NAME];
  
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
        // 新しいサービスワーカーをすべてのクライアントで即時有効化
        console.log('新しいサービスワーカーがすべてのクライアントを制御します');
        return self.clients.claim();
      })
  );
});
