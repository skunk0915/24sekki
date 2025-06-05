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
      const timeInput = document.getElementById('push-time-input');
      const notifyTime = timeInput ? timeInput.value : '';
      
      // 時刻をローカルストレージに保存
      if (notifyTime) {
        localStorage.setItem('notifyTime', notifyTime);
        console.log('通知時刻を保存しました:', notifyTime);
      }
      
      // PHPに購読情報＋時刻とブラウザIDを送信
      const sendData = Object.assign({}, subscription, {
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

      // テスト通知を送信するためのリクエスト
      console.log('テスト通知をリクエストします');
      try {
        // 即時テスト通知を使用する方法に変更
        console.log('即時テスト通知をリクエストします');
        
        // GETリクエストでテスト通知を送信
        const testUrl = PUSH_CONFIG.ENDPOINTS.SEND;
        console.log('テスト通知URL:', testUrl);
        
        const testResponse = await fetch(testUrl);
        console.log('テスト通知のレスポンスステータス:', testResponse.status);
        
        if (!testResponse.ok) {
          throw new Error(`テスト通知リクエストエラー: ${testResponse.status} ${testResponse.statusText}`);
        }
        
        const testResult = await testResponse.text();
        console.log('テスト通知の結果:', testResult);
        alert('テスト通知を送信しました');
      } catch (testError) {
        console.error('テスト通知の送信に失敗しました:', testError);
        console.error('エラーの詳細:', {
          name: testError.name,
          message: testError.message,
          stack: testError.stack
        });
        // エラーが発生しても通知購読自体は成功しているのでユーザーには通知しない
      }
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
    // 時刻入力欄を作成
    timeInput = document.createElement('input');
    timeInput.type = 'time';
    timeInput.id = 'push-time-input';
    timeInput.value = savedTime; // 保存された時刻またはデフォルト値
    timeInput.style.marginRight = '8px';
    const area = document.getElementById('push-btn-area');
    if (area) {
      area.appendChild(timeInput);
      area.appendChild(btn);
    } else {
      document.body.appendChild(timeInput);
      document.body.appendChild(btn);
    }
  } else if (timeInput) {
    timeInput.value = savedTime; // 既存の時刻入力欄にも値を設定
  }
  updatePushBtnText();
  btn.onclick = togglePushSubscription;
});

async function updatePushBtnText() {
  const btn = document.getElementById('push-subscribe-btn');
  const timeInput = document.getElementById('push-time-input');
  if (!btn || !timeInput) return;
  if (!('serviceWorker' in navigator && 'PushManager' in window)) {
    btn.textContent = 'プッシュ非対応';
    btn.disabled = true;
    timeInput.disabled = true;
    return;
  }
  const registration = await navigator.serviceWorker.getRegistration();
  if (!registration) {
    btn.textContent = '通知をONにする';
    btn.disabled = false;
    timeInput.disabled = false;
    return;
  }
  const subscription = await registration.pushManager.getSubscription();
  if (subscription) {
    btn.textContent = '通知をOFFにする';
    btn.disabled = false;
    timeInput.disabled = true; // ON時は時刻変更不可
  } else {
    btn.textContent = '通知をONにする';
    btn.disabled = false;
    timeInput.disabled = false;
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
  const timeInput = document.getElementById('push-time-input');
  if (timeInput) {
    const notifyTime = timeInput.value;
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
        sendData = Object.assign({}, subscription, sendData);
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
