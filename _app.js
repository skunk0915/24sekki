// PWAのPush通知購読登録用スクリプト
const publicVapidKey = 'BD6V2tlGjASgOHE4p-I9ndImCi-E9Ii62eHu0ugfg5kt0ufuIPyJYmOCKJz8095OxPlEFHOtntQ2EKHesq83AfI';

async function subscribeUser() {
  if ('serviceWorker' in navigator && 'PushManager' in window) {
    const registration = await navigator.serviceWorker.register('./service-worker.js');

    const permission = await Notification.requestPermission();
    if (permission !== 'granted') {
      alert('通知を許可してください');
      return;
    }

    const subscription = await registration.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: urlBase64ToUint8Array(publicVapidKey)
    });

    // PHPに購読情報を送信
    await fetch('./subscribe.php', {
      method: 'POST',
      body: JSON.stringify(subscription),
      headers: { 'Content-Type': 'application/json' },
    });
    alert('プッシュ通知の購読が完了しました');
  }
}

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
  const rawData = atob(base64);
  return Uint8Array.from([...rawData].map(char => char.charCodeAt(0)));
}

// ページロード時に購読登録ボタンを自動設置（必要に応じて調整）
document.addEventListener('DOMContentLoaded', () => {
  if (!('serviceWorker' in navigator && 'PushManager' in window)) return;
  let btn = document.getElementById('push-subscribe-btn');
  if (!btn) {
    btn = document.createElement('button');
    btn.id = 'push-subscribe-btn';
    btn.textContent = 'プッシュ通知を有効化';
    document.body.appendChild(btn);
  }
  btn.onclick = subscribeUser;
});
