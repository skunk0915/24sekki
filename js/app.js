// PWAのPush通知購読登録用スクリプト
const publicVapidKey = PUSH_CONFIG.VAPID_PUBLIC_KEY;

async function subscribeUser() {
  console.log('subscribeUser関数が呼び出されました');
  if ('serviceWorker' in navigator && 'PushManager' in window) {
    try {
      console.log('ServiceWorkerの登録を開始します');
      const registration = await navigator.serviceWorker.register('./service-worker.js');
      console.log('ServiceWorkerの登録が完了しました', registration);

      console.log('通知の許可を要求します');
      const permission = await Notification.requestPermission();
      console.log('通知の許可状態:', permission);
      if (permission !== 'granted') {
        alert('通知を許可してください');
        return;
      }

      // ブラウザIDを生成または取得
      let browserId = localStorage.getItem('browserId');
      if (!browserId) {
        browserId = 'browser_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        localStorage.setItem('browserId', browserId);
        console.log('新しいブラウザIDを生成しました:', browserId);
      } else {
        console.log('既存のブラウザIDを使用します:', browserId);
      }

      console.log('プッシュマネージャーの購読を開始します');
      const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(publicVapidKey)
      });
      console.log('購読情報:', subscription);

      // 時刻入力値も一緒に送信
      const hourSelect = document.getElementById('push-hour-input');
      const minuteSelect = document.getElementById('push-minute-input');
      const notifyTime = (hourSelect && minuteSelect) ? `${hourSelect.value}:${minuteSelect.value}` : '';
      
      // 時刻をローカルストレージに保存
      if (notifyTime) {
        localStorage.setItem('notifyTime', notifyTime);
        console.log('通知時刻を保存しました:', notifyTime);
      }
      
      // PHPに購読情報＋時刻とブラウザIDを送信
      const subscriptionData = subscription && typeof subscription.toJSON === 'function' ? subscription.toJSON() : subscription;
      const sendData = Object.assign({}, subscriptionData, {
        notifyTime,
        browserId
      });
      console.log('購読情報をサーバーに送信します', sendData);
      const response = await fetch('./subscribe.php', {
        method: 'POST',
        body: JSON.stringify(sendData),
        headers: { 'Content-Type': 'application/json' },
      });
      const responseData = await response.json();
      console.log('サーバーからの応答:', responseData);
      alert('プッシュ通知の購読が完了しました');

      // サーバー起動予約
      scheduleWakeCall(notifyTime);
    } catch (error) {
      console.error('購読処理中にエラーが発生しました:', error);
      alert('購読処理中にエラーが発生しました: ' + error.message);
    }
  } else {
    console.error('このブラウザはプッシュ通知に対応していません');
  }
}

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
  const rawData = atob(base64);
  return Uint8Array.from([...rawData].map(char => char.charCodeAt(0)));
}

// ページロード時に購読登録ボタンを自動設置＆状態に応じて文言切替
document.addEventListener('DOMContentLoaded', async () => {
  if (!('serviceWorker' in navigator && 'PushManager' in window)) return;
  let btn = document.getElementById('push-subscribe-btn');
  let timeInput = document.getElementById('push-time-input');
  
  // ブラウザIDを生成または取得
  let browserId = localStorage.getItem('browserId');
  if (!browserId) {
    browserId = 'browser_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    localStorage.setItem('browserId', browserId);
    console.log('新しいブラウザIDを生成しました:', browserId);
  } else {
    console.log('既存のブラウザIDを使用します:', browserId);
  }
  
  // 以前設定した通知時刻を取得
  let savedTime = '08:00'; // デフォルト値
  
  // 現在の購読情報から時刻を取得する
  try {
    const registration = await navigator.serviceWorker.getRegistration();
    if (registration) {
      const subscription = await registration.pushManager.getSubscription();
      if (subscription) {
        // ローカルストレージから時刻を取得
        const storedTime = localStorage.getItem('notifyTime');
        if (storedTime) {
          savedTime = storedTime;
        }
      }
    }
  } catch (error) {
    console.error('時刻取得エラー:', error);
  }
  
  if (!btn) {
    btn = document.createElement('button');
    btn.id = 'push-subscribe-btn';

    // 保存された時刻を分解
    const [savedHour, savedMinute] = savedTime.split(':');

    // 時間選択欄を作成
    const hourSelect = document.createElement('select');
    hourSelect.id = 'push-hour-input';
    hourSelect.style.marginRight = '4px';
    for (let h = 0; h < 24; h++) {
      const option = document.createElement('option');
      option.value = String(h).padStart(2, '0');
      option.textContent = String(h).padStart(2, '0');
      if (String(h).padStart(2, '0') === savedHour) {
        option.selected = true;
      }
      hourSelect.appendChild(option);
    }

    // 区切り文字
    const separator = document.createElement('span');
    separator.textContent = ':';
    separator.style.marginRight = '4px';

    // 分選択欄を作成（15分刻み）
    const minuteSelect = document.createElement('select');
    minuteSelect.id = 'push-minute-input';
    minuteSelect.style.marginRight = '8px';
    ['00', '15', '30', '45'].forEach(m => {
      const option = document.createElement('option');
      option.value = m;
      option.textContent = m;
      if (m === savedMinute) {
        option.selected = true;
      }
      minuteSelect.appendChild(option);
    });

    const area = document.getElementById('push-btn-area');
    if (area) {
      area.appendChild(hourSelect);
      area.appendChild(separator);
      area.appendChild(minuteSelect);
      area.appendChild(btn);
    } else {
      document.body.appendChild(hourSelect);
      document.body.appendChild(separator);
      document.body.appendChild(minuteSelect);
      document.body.appendChild(btn);
    }
  } else {
    // 既存の要素に値を設定
    const hourSelect = document.getElementById('push-hour-input');
    const minuteSelect = document.getElementById('push-minute-input');
    if (hourSelect && minuteSelect && savedTime) {
      const [savedHour, savedMinute] = savedTime.split(':');
      hourSelect.value = savedHour;
      minuteSelect.value = savedMinute;
    }
  }
  updatePushBtnText();
  btn.onclick = togglePushSubscription;
});

async function updatePushBtnText() {
  const btn = document.getElementById('push-subscribe-btn');
  const hourSelect = document.getElementById('push-hour-input');
  const minuteSelect = document.getElementById('push-minute-input');
  if (!btn || !hourSelect || !minuteSelect) return;
  if (!('serviceWorker' in navigator && 'PushManager' in window)) {
    btn.textContent = 'プッシュ非対応';
    btn.disabled = true;
    hourSelect.disabled = true;
    minuteSelect.disabled = true;
    return;
  }
  const registration = await navigator.serviceWorker.getRegistration();
  if (!registration) {
    btn.textContent = '通知をONにする';
    btn.disabled = false;
    hourSelect.disabled = false;
    minuteSelect.disabled = false;
    return;
  }
  const subscription = await registration.pushManager.getSubscription();
  if (subscription) {
    btn.textContent = '通知をOFFにする';
    btn.disabled = false;
    hourSelect.disabled = true; // ON時は時刻変更不可
    minuteSelect.disabled = true;
  } else {
    btn.textContent = '通知をONにする';
    btn.disabled = false;
    hourSelect.disabled = false;
    minuteSelect.disabled = false;
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
  
  // 通知時刻のみの変更の場合もサーバーに送信する
  const hourSelect = document.getElementById('push-hour-input');
  const minuteSelect = document.getElementById('push-minute-input');
  if (hourSelect && minuteSelect) {
    const notifyTime = `${hourSelect.value}:${minuteSelect.value}`;
    const savedTime = localStorage.getItem('notifyTime');

    // 時刻が変更されている場合はサーバーに送信
    if (notifyTime !== savedTime) {
      localStorage.setItem('notifyTime', notifyTime);
      console.log('通知時刻を更新しました:', notifyTime);

      // ブラウザIDを取得
      let browserId = localStorage.getItem('browserId');
      if (!browserId) {
        browserId = 'browser_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        localStorage.setItem('browserId', browserId);
      }

      // 最新のエンドポイント情報を取得して送信
      let sendData = { notifyTime, browserId };

      if (subscription) {
        const subData = typeof subscription.toJSON === 'function' ? subscription.toJSON() : subscription;
        sendData = Object.assign({}, subData, sendData);
      }

      console.log('通知時刻のみをサーバーに送信します', sendData);
      fetch('./subscribe.php', {
        method: 'POST',
        body: JSON.stringify(sendData),
        headers: { 'Content-Type': 'application/json' },
      })
      .then(response => response.json())
      .then(data => console.log('サーバーからの応答:', data))
      .catch(error => console.error('時刻送信エラー:', error));
    }
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

// --- サーバー起動予約関連 ---
let wakeTimerId = null;

function scheduleWakeCall(notifyTime) {
  if (!notifyTime) return;
  // 既存タイマーをクリア
  if (wakeTimerId) clearTimeout(wakeTimerId);

  const [hh, mm] = notifyTime.split(':').map(Number);
  const now = new Date();
  const target = new Date(now.getFullYear(), now.getMonth(), now.getDate(), hh, mm);
  // 当日で既に過ぎている場合は翌日扱い
  if (target.getTime() <= now.getTime()) {
    target.setDate(target.getDate() + 1);
  }
  // 5分前
  target.setMinutes(target.getMinutes() - 5);

  const diffMs = target.getTime() - now.getTime();
  if (diffMs <= 0) {
    // すでに5分前を過ぎている場合は即起動
    callWakeEndpoint();
    return;
  }
  console.log('サーバー起動を予約:', new Date(target.getTime()).toLocaleString(), `まで ${Math.round(diffMs/60000)} 分後`);
  wakeTimerId = setTimeout(() => {
    callWakeEndpoint();
    // 予約後に再度翌日分を設定
    scheduleWakeCall(notifyTime);
  }, diffMs);
}

function callWakeEndpoint() {
  console.log('wake エンドポイントを呼び出します');
  fetch(PUSH_CONFIG.ENDPOINTS.WAKE)
    .then(res => res.json())
    .then(data => console.log('wake 応答:', data))
    .catch(err => console.error('wake 呼び出し失敗:', err));
}

// ページ読み込み時に既存の通知時刻があれば予約
document.addEventListener('DOMContentLoaded', () => {
  const storedTime = localStorage.getItem('notifyTime');
  if (storedTime) {
    scheduleWakeCall(storedTime);
  }
});
