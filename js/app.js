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

// ページロード時に購読登録ボタンを自動設置＆状態に応じて文言切替
document.addEventListener('DOMContentLoaded', () => {
  if (!('serviceWorker' in navigator && 'PushManager' in window)) return;
  let btn = document.getElementById('push-subscribe-btn');
  if (!btn) {
    btn = document.createElement('button');
    btn.id = 'push-subscribe-btn';
    // 初期テキストは後でセット
    const area = document.getElementById('push-btn-area');
    if (area) {
      area.appendChild(btn);
    } else {
      document.body.appendChild(btn);
    }
  }
  updatePushBtnText();
  btn.onclick = togglePushSubscription;
});

async function updatePushBtnText() {
  const btn = document.getElementById('push-subscribe-btn');
  if (!btn) return;
  if (!('serviceWorker' in navigator && 'PushManager' in window)) {
    btn.textContent = 'プッシュ非対応';
    btn.disabled = true;
    return;
  }
  const registration = await navigator.serviceWorker.getRegistration();
  if (!registration) {
    btn.textContent = '暦が変わったら通知する';
    return;
  }
  const subscription = await registration.pushManager.getSubscription();
  if (subscription) {
    btn.textContent = '暦が変わっても通知しない';
  } else {
    btn.textContent = '暦が変わったら通知する';
  }
}

async function togglePushSubscription() {
  const registration = await navigator.serviceWorker.getRegistration();
  if (!registration) {
    await subscribeUser();
    await updatePushBtnText();
    return;
  }
  const subscription = await registration.pushManager.getSubscription();
  if (subscription) {
    // 解除
    await unsubscribeUser();
    await updatePushBtnText();
  } else {
    await subscribeUser();
    await updatePushBtnText();
  }
}

async function unsubscribeUser() {
  if ('serviceWorker' in navigator && 'PushManager' in window) {
    const registration = await navigator.serviceWorker.getRegistration();
    if (!registration) return;
    const subscription = await registration.pushManager.getSubscription();
    if (subscription) {
      await subscription.unsubscribe();
      // サーバー側にも解除通知を送る場合はここでfetch等を実装
      // await fetch('./unsubscribe.php', {...});
      alert('プッシュ通知の購読を解除しました');
    }
  }
}