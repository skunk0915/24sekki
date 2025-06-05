const express = require('express');
const fetch = require('node-fetch');
const webpush = require('web-push');
const cors = require('cors');
const cron = require('node-cron');
require('dotenv').config();

// Expressアプリの初期化
const app = express();
app.use(express.json());
app.use(cors({
  origin: '*',
  methods: ['GET', 'POST'],
  allowedHeaders: ['Content-Type']
}));
const PORT = process.env.PORT || 3000;

// 前日の暦情報を保存する変数
let previousSekki = null;

console.log('プッシュサーバーを起動します...');
console.log('VAPID_PUBLIC_KEY:', process.env.VAPID_PUBLIC_KEY ? '設定済み' : '未設定');
console.log('SUBSCRIPTIONS_URL:', process.env.SUBSCRIPTIONS_URL ? '設定済み' : '未設定');

// VAPID設定
webpush.setVapidDetails(
  'mailto:you@example.com',
  process.env.VAPID_PUBLIC_KEY,
  process.env.VAPID_PRIVATE_KEY
);

function isValidSubscription(subscription) {
  if (!subscription || typeof subscription !== 'object') {
    return false;
  }
  
  if (!subscription.endpoint || 
      typeof subscription.endpoint !== 'string' ||
      subscription.endpoint.startsWith('dummy_endpoint_')) {
    return false;
  }
  
  if (!subscription.keys || 
      !subscription.keys.p256dh || 
      !subscription.keys.auth) {
    return false;
  }
  
  return true;
}

// 暦情報を取得する関数
async function getCurrentSekki() {
  try {
    const response = await fetch('https://mizy.sakura.ne.jp/72kou/api/current-sekki.php');
    if (!response.ok) {
      throw new Error(`API応答エラー: ${response.status}`);
    }
    const data = await response.json();
    return data;
  } catch (error) {
    console.error('暦情報の取得に失敗しました:', error);
    return null;
  }
}

// 購読者全員に通知を送信する関数
async function sendNotificationToAll(title, body) {
  try {
    console.log(`通知を送信します: ${title} - ${body}`);
    console.log('購読情報URL:', process.env.SUBSCRIPTIONS_URL);
    
    const response = await fetch(process.env.SUBSCRIPTIONS_URL);
    console.log('購読情報取得レスポンス:', response.status, response.statusText);
    
    const txt = await response.text();
    console.log('購読情報データ長:', txt.length);
    
    if (!txt || txt.trim() === '') {
      console.log('購読者情報がありません');
      return { success: 0, failed: 0, error: '購読者情報がありません' };
    }
    
    const lines = txt.trim().split('\n');
    console.log(`${lines.length}人の購読者に通知を送信します`);
    
    // 最初の購読情報をログに記録（デバッグ用）
    if (lines.length > 0) {
      try {
        const firstSubscription = JSON.parse(lines[0]);
        const isValid = isValidSubscription(firstSubscription);
        console.log('最初の購読情報サンプル:', {
          endpoint: firstSubscription.endpoint ? (firstSubscription.endpoint.startsWith('dummy_endpoint_') ? 'ダミーエンドポイント' : '有効なエンドポイント') : 'エンドポイントなし',
          keys: firstSubscription.keys ? Object.keys(firstSubscription.keys) : 'keysなし',
          isValid: isValid
        });
      } catch (e) {
        console.error('購読情報の解析エラー:', e);
      }
    }
    
    const payload = JSON.stringify({ title, body });
    console.log('送信するペイロード:', payload);
    
    let successCount = 0;
    let failedCount = 0;
    let errors = [];
    
    for (let i = 0; i < lines.length; i++) {
      try {
        const line = lines[i];
        const subscription = JSON.parse(line);
        
        if (!isValidSubscription(subscription)) {
          console.log(`購読者 ${i+1}/${lines.length} をスキップ: 無効な購読情報`);
          continue;
        }
        
        console.log(`購読者 ${i+1}/${lines.length} に通知を送信中...`);
        await webpush.sendNotification(subscription, payload);
        console.log(`購読者 ${i+1} への通知送信成功`);
        successCount++;
      } catch (err) {
        console.error(`購読者 ${i+1} への通知失敗:`, err);
        failedCount++;
        errors.push({
          index: i,
          error: err.message,
          statusCode: err.statusCode,
          headers: err.headers
        });
      }
    }
    
    console.log(`通知送信結果: 成功=${successCount}, 失敗=${failedCount}`);
    return { success: successCount, failed: failedCount, errors: errors.length > 0 ? errors : undefined };
  } catch (err) {
    console.error('通知送信処理でエラーが発生しました:', err);
    return { success: 0, failed: 0, error: err.message, stack: err.stack };
  }
}

// さくらにある購読情報を取得して通知送信（GET）
app.get('/send', async (req, res) => {
  try {
    const currentSekki = await getCurrentSekki();
    if (!currentSekki) {
      return res.status(500).send('暦情報の取得に失敗しました');
    }
    
    const title = '暦のお知らせ';
    const body = `現在の暦は「${currentSekki.name}」です（${currentSekki.start_date}～${currentSekki.end_date}）`;
    
    const result = await sendNotificationToAll(title, body);
    res.send(`通知送信完了: 成功=${result.success}, 失敗=${result.failed}`);
  } catch (err) {
    console.error(err);
    res.status(500).send('通知送信失敗: ' + err.message);
  }
});

// 個別の購読情報に通知を送信（POST）
app.post('/send', async (req, res) => {
  console.log('POSTリクエストを受信しました:', req.body);
  try {
    const { subscription, title, body } = req.body;
    
    if (!subscription) {
      return res.status(400).json({ error: '購読情報が必要です' });
    }
    
    const payload = JSON.stringify({
      title: title || 'テスト通知',
      body: body || 'これはテスト通知です',
    });
    
    console.log('通知を送信します:', { subscription, payload });
    await webpush.sendNotification(subscription, payload);
    
    return res.status(200).json({ success: true, message: '通知送信完了' });
  } catch (err) {
    console.error('通知送信エラー:', err);
    return res.status(500).json({ error: err.message });
  }
});

// 暦が変わったかチェックするエンドポイント
app.get('/check-sekki-change', async (req, res) => {
  try {
    const currentSekki = await getCurrentSekki();
    if (!currentSekki) {
      return res.status(500).json({ error: '暦情報の取得に失敗しました' });
    }
    
    const isChanged = previousSekki && previousSekki.id !== currentSekki.id;
    previousSekki = currentSekki;
    
    if (isChanged) {
      const title = '暦が変わりました';
      const body = `新しい暦は「${currentSekki.name}」です（${currentSekki.start_date}～${currentSekki.end_date}）`;
      
      const result = await sendNotificationToAll(title, body);
      return res.json({
        changed: true,
        previous: previousSekki,
        current: currentSekki,
        notification: result
      });
    }
    
    return res.json({
      changed: false,
      current: currentSekki
    });
  } catch (err) {
    console.error('暦変更チェックでエラーが発生しました:', err);
    return res.status(500).json({ error: err.message });
  }
});

// テスト用：即時にテスト通知を送信するエンドポイント
app.get('/test-notification-now', async (req, res) => {
  try {
    console.log('即時テスト通知を送信します');
    
    const title = 'テスト通知';
    const body = `これはテスト通知です - ${new Date().toLocaleString()}`;
    
    // 通知を送信
    const result = await sendNotificationToAll(title, body);
    
    return res.json({
      status: 'sent',
      message: 'テスト通知を送信しました',
      result: result,
      timestamp: new Date().toISOString()
    });
  } catch (err) {
    console.error('テスト通知送信中にエラーが発生しました:', err);
    return res.status(500).json({
      error: err.message,
      stack: err.stack,
      timestamp: new Date().toISOString()
    });
  }
});

// テスト用：購読情報を確認するエンドポイント
app.get('/check-subscriptions', async (req, res) => {
  try {
    console.log('購読情報を確認します');
    console.log('購読情報URL:', process.env.SUBSCRIPTIONS_URL);
    
    const response = await fetch(process.env.SUBSCRIPTIONS_URL);
    console.log('購読情報取得レスポンス:', response.status, response.statusText);
    
    const txt = await response.text();
    console.log('購読情報データ長:', txt.length);
    
    if (!txt || txt.trim() === '') {
      return res.json({
        status: 'empty',
        message: '購読者情報がありません',
        timestamp: new Date().toISOString()
      });
    }
    
    const lines = txt.trim().split('\n');
    const subscriptions = [];
    
    for (let i = 0; i < lines.length; i++) {
      try {
        const subscription = JSON.parse(lines[i]);
        const isValid = isValidSubscription(subscription);
        subscriptions.push({
          index: i,
          endpoint: subscription.endpoint,
          keys: subscription.keys ? Object.keys(subscription.keys) : null,
          isValid: isValid,
          isDummy: subscription.endpoint && subscription.endpoint.startsWith('dummy_endpoint_')
        });
      } catch (e) {
        subscriptions.push({
          index: i,
          error: e.message,
          raw: lines[i].substring(0, 100) + '...',
          isValid: false
        });
      }
    }
    
    return res.json({
      status: 'success',
      count: lines.length,
      subscriptions: subscriptions,
      timestamp: new Date().toISOString()
    });
  } catch (err) {
    console.error('購読情報の確認中にエラーが発生しました:', err);
    return res.status(500).json({
      error: err.message,
      stack: err.stack,
      timestamp: new Date().toISOString()
    });
  }
});

// テスト用：時間差を置いて暦が変わったと想定して通知を送信するエンドポイント
app.get('/test-next-sekki', async (req, res) => {
  try {
    // 現在の暦を取得
    const currentSekki = await getCurrentSekki();
    if (!currentSekki) {
      return res.status(500).json({ error: '暦情報の取得に失敗しました' });
    }
    
    // 次の暦のデータを取得するためのAPIを呼び出す
    const nextSekkiResponse = await fetch(`https://mizy.sakura.ne.jp/72kou/api/next-sekki.php?current_id=${currentSekki.id}`);
    if (!nextSekkiResponse.ok) {
      return res.status(500).json({ error: `次の暦情報の取得に失敗しました: ${nextSekkiResponse.status}` });
    }
    
    const nextSekki = await nextSekkiResponse.json();
    
    // 2分後に通知を送信するタイマーを設定
    const waitMinutes = 2;
    const waitMs = waitMinutes * 60 * 1000;
    
    res.json({
      status: 'scheduled',
      message: `${waitMinutes}分後に「${nextSekki.name}」に変わったとして通知を送信します`,
      currentSekki: currentSekki,
      nextSekki: nextSekki,
      scheduledTime: new Date(Date.now() + waitMs).toLocaleString()
    });
    
    // 指定時間後に通知を送信
    setTimeout(async () => {
      try {
        console.log(`テスト: 暦が変わりました: ${currentSekki.name} → ${nextSekki.name}`);
        
        const title = '暦が変わりました';
        const body = `新しい暦は「${nextSekki.name}」です（${nextSekki.start_date}～${nextSekki.end_date}）`;
        
        const result = await sendNotificationToAll(title, body);
        console.log(`テスト通知結果: 成功=${result.success}, 失敗=${result.failed}`);
        
        // テスト後は元の暦に戻す
        previousSekki = currentSekki;
      } catch (error) {
        console.error('テスト通知送信中にエラーが発生しました:', error);
      }
    }, waitMs);
    
  } catch (err) {
    console.error('テスト通知の設定中にエラーが発生しました:', err);
    return res.status(500).json({ error: err.message });
  }
});

// 毎日午前0時に暦の変更をチェック
cron.schedule('0 0 * * *', async () => {
  console.log('暦の変更をチェックします...');
  try {
    const currentSekki = await getCurrentSekki();
    if (!currentSekki) {
      console.error('暦情報の取得に失敗しました');
      return;
    }
    
    // 初回実行時はpreviousSekkiがnullなので通知しない
    if (previousSekki && previousSekki.id !== currentSekki.id) {
      console.log(`暦が変わりました: ${previousSekki.name} → ${currentSekki.name}`);
      
      const title = '暦が変わりました';
      const body = `新しい暦は「${currentSekki.name}」です（${currentSekki.start_date}～${currentSekki.end_date}）`;
      
      const result = await sendNotificationToAll(title, body);
      console.log(`通知結果: 成功=${result.success}, 失敗=${result.failed}`);
    } else {
      console.log(`暦は変わっていません: ${currentSekki.name}`);
    }
    
    // 現在の暦を保存
    previousSekki = currentSekki;
  } catch (error) {  
    console.error('暦変更チェック中にエラーが発生しました:', error);
  }
});

// サーバー起動時に現在の暦情報を取得
(async () => {
  try {
    previousSekki = await getCurrentSekki();
    console.log('初期暦情報を取得しました:', previousSekki ? previousSekki.name : 'なし');
  } catch (error) {
    console.error('初期暦情報の取得に失敗しました:', error);
  }
})();

// シンプルなテストエンドポイント
app.get('/test', (req, res) => {
  res.json({
    status: 'ok',
    message: 'サーバーが正常に動作しています',
    timestamp: new Date().toISOString(),
    env: {
      VAPID_PUBLIC_KEY: process.env.VAPID_PUBLIC_KEY ? '設定済み' : '未設定',
      SUBSCRIPTIONS_URL: process.env.SUBSCRIPTIONS_URL ? '設定済み' : '未設定'
    }
  });
});

// サーバー起動用エンドポイント（通知の5分前に呼び出される）
app.get('/wake', (req, res) => {
  console.log('サーバー起動リクエストを受信しました:', new Date().toLocaleString());
  res.json({
    status: 'ok',
    message: 'サーバーが起動しました',
    timestamp: new Date().toISOString()
  });
});

// 指定された購読者に通知を送信するエンドポイント
app.post('/notify', async (req, res) => {
  try {
    console.log('個別通知リクエストを受信しました:', req.body);
    const { title, body, subscription } = req.body;
    
    if (!subscription) {
      return res.status(400).json({ error: '購読情報が必要です' });
    }
    
    const payload = JSON.stringify({
      title: title || 'プッシュ通知',
      body: body || '通知内容'
    });
    
    console.log('通知を送信します:', { title, body });
    await webpush.sendNotification(subscription, payload);
    
    return res.status(200).json({ success: true, message: '通知送信完了' });
  } catch (err) {
    console.error('通知送信エラー:', err);
    return res.status(500).json({ error: err.message });
  }
});

// ルートエンドポイント
app.get('/', (req, res) => {
  res.json({
    status: 'ok',
    message: 'プッシュ通知サーバーが動作しています',
    endpoints: [
      '/test',
      '/send',
      '/wake',
      '/notify',
      '/check-subscriptions',
      '/test-notification-now',
      '/test-next-sekki',
      '/check-sekki-change'
    ],
    timestamp: new Date().toISOString()
  });
});

app.listen(PORT, () => {
  console.log(`Push server is running on port ${PORT}`);
});
