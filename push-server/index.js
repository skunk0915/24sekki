const express = require('express');
const fetch = require('node-fetch');
const webpush = require('web-push');
const cors = require('cors');
const cron = require('node-cron');
require('dotenv').config();
const fs = require('fs');
const config = require('./config.json');

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

  // keys はブラウザによって含まれない場合がある（iOS Safari など）。
  // endpoint が有効なら keys の有無は問わない。
  return true;
}

// 七十二候データをCSVから読み込む関数
function loadKouData() {
  try {
    const fs = require('fs');
    const path = require('path');
    const results = [];

    // プロジェクトルートの72kou.csvを探索して読み込む
    const candidatePaths = [
      path.join(__dirname, '../72kou.csv'),      // 通常: push-server 上位1階層
      path.join(__dirname, '../../72kou.csv'),   // 旧パス互換
      path.join(process.cwd(), '72kou.csv')      // カレントディレクトリ
    ];

    // 最初に存在するパスを採用
    const csvPath = candidatePaths.find(p => fs.existsSync(p));
    console.log(`[scheduler] 七十二候CSV検索パス: ${candidatePaths.join(' | ')}`);

    if (!csvPath) {
      console.error('[scheduler] 七十二候CSVファイルが見つかりません');
      return null;
    }

    // ファイルを一度に読み込む
    const fileContent = fs.readFileSync(csvPath, 'utf8');

    // 改行コードを統一してから行に分割
    const lines = fileContent.replace(/\r\n?/g, '\n').split('\n');
    if (lines.length === 0) {
      console.error('[scheduler] CSVファイルが空です');
      return null;
    }

    // ヘッダー行を取得（BOMを除去）
    const headers = lines[0].split(',').map(h => h.trim().replace(/^\ufeff/, ''));

    // データ行を処理（複数行にまたがる値を考慮）
    let currentRow = [];
    let inQuotes = false;
    let currentValue = '';

    // ヘッダー行を除くすべての行を処理
    for (let i = 1; i < lines.length; i++) {
      const line = lines[i];
      if (!line && i === lines.length - 1) continue;

      for (let j = 0; j < line.length; j++) {
        const char = line[j];

        // ダブルクォーテーションの処理
        if (char === '"') {
          if (inQuotes && line[j + 1] === '"') {
            // エスケープされたダブルクォーテーション
            currentValue += '"';
            j++; // 次の文字をスキップ
          } else {
            inQuotes = !inQuotes;
          }
        } else if (char === ',' && !inQuotes) {
          // カンマで区切る（ダブルクォーテーション内でない場合）
          currentRow.push(currentValue);
          currentValue = '';
        } else {
          currentValue += char;
        }
      }

      // 行末の処理
      if (!inQuotes) {
        // 現在の値を追加
        currentRow.push(currentValue);
        currentValue = '';

        // 行が完了したらエントリを追加
        if (currentRow.length >= headers.length) {
          const entry = {};
          headers.forEach((header, index) => {
            entry[header] = (currentRow[index] || '').trim();
          });
          results.push(entry);
          currentRow = [];
        }
      } else {
        // 改行が含まれる値の続き
        currentValue += '\n';
      }
    }

    console.log(`[scheduler] 七十二候データを読み込みました: ${results.length}件`);
    return results;
  } catch (error) {
    console.error('[scheduler] 七十二候CSVの読み込み中にエラーが発生しました:', error);
    return null;
  }
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

    const payload = JSON.stringify({ title, body });
    console.log('送信するペイロード:', payload);

    let successCount = 0;
    let failedCount = 0;
    let validCount = 0;
    let errors = [];

    for (let i = 0; i < lines.length; i++) {
      try {
        const line = lines[i];
        const subscription = JSON.parse(line);

        if (!isValidSubscription(subscription)) {
          console.log(`購読者 ${i + 1}/${lines.length} をスキップ: 無効な購読情報`);
          continue;
        }
        validCount++;

        console.log(`購読者 ${i + 1}/${lines.length} に通知を送信中...`);
        await webpush.sendNotification(subscription, payload);
        console.log(`購読者 ${i + 1} への通知送信成功`);
        successCount++;
      } catch (err) {
        console.error(`購読者 ${i + 1} への通知失敗:`, err);
        failedCount++;
        errors.push({
          index: i,
          error: err.message,
          statusCode: err.statusCode,
          headers: err.headers
        });
      }
    }

    console.log(`通知送信結果: 成功=${successCount}, 失敗=${failedCount}, 有効購読=${validCount}`);
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

    const title = currentSekki.name;
    const body = `${currentSekki.name}：${currentSekki.kou_name || ''}`;

    const result = await sendNotificationToAll(title, body);
    res.send(`通知送信完了: 成功=${result.success}, 失敗=${result.failed}`);
  } catch (err) {
    console.error(err);
    res.status(500).send('通知送信失敗: ' + err.message);
  }
});

// 個別の購読情報を登録（POST）
app.post('/send', async (req, res) => {
  console.log('POSTリクエストを受信しました:', req.body);
  try {
    const { subscription } = req.body;

    if (!subscription) {
      return res.status(400).json({ error: '購読情報が必要です' });
    }

    // 購読情報の検証のみを行い、テスト通知は送信しない
    if (!isValidSubscription(subscription)) {
      return res.status(400).json({ error: '無効な購読情報です' });
    }

    console.log('有効な購読情報を受信しました');

    return res.status(200).json({ success: true, message: '購読情報を受け付けました' });
  } catch (err) {
    console.error('購読処理エラー:', err);
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
      const title = currentSekki.name;
      const body = `${currentSekki.name}：${currentSekki.kou_name || ''}`;

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

        const title = nextSekki.name;
        const body = `${nextSekki.name}：${nextSekki.kou_name || ''}`;

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

// 時刻指定通知を送信する関数
async function sendScheduledNotifications() {
  try {
    const jstNow = new Date(Date.now() + 9 * 60 * 60 * 1000); // JST
    const hh = String(jstNow.getHours()).padStart(2, '0');
    const mm = String(jstNow.getMinutes()).padStart(2, '0');
    const currentTime = `${hh}:${mm}`;

    const todayMD = `${String(jstNow.getMonth() + 1).padStart(2, '0')}-${String(jstNow.getDate()).padStart(2, '0')}`; // MM-DD形式
    let sekkiTitle = null;
    let sekkiKouBody = null; // 本文用
    let isSekkiDay = false;
    let isKouDay = false;
    let currentKou = null; // 現在の七十二候を保存

    // 現在の暦をAPIから取得
    const currentSekki = await getCurrentSekki();
    if (!currentSekki) {
      console.error('[scheduler] 現在の節気取得失敗');
      return;
    }

    const sekkiMD = currentSekki.start_date; // M/D形式
    const [sekkiMonth, sekkiDay] = sekkiMD.split('/').map(num => num.padStart(2, '0'));
    const formattedSekkiDate = `${sekkiMonth}-${sekkiDay}`;

    if (formattedSekkiDate === todayMD) {
      sekkiTitle = currentSekki.name;
      sekkiKouBody = `${currentSekki.name}：${currentSekki.kou_name || ''}`;
      isSekkiDay = true;
      console.log(`[scheduler] 二十四節気の開始日です: ${formattedSekkiDate} ${sekkiTitle}`);
    } else {
      console.log(`[scheduler] 二十四節気の開始日ではありません (${currentSekki.name} 開始日: ${formattedSekkiDate})`);
    }

    // 2. 七十二候をチェック（常に実行して現在の七十二候を特定）
    const kouList = loadKouData();
    if (kouList && kouList.length > 0) {
      // 今日の七十二候の開始日を探す
      const todayKou = kouList.find(kou => {
        if (!kou['開始年月日']) return false;
        // 日付をMM-DD形式に正規化
        const dateParts = kou['開始年月日'].trim().split(/[\/\-]/);
        if (dateParts.length !== 2) return false;
        const kouDate = `${dateParts[0].padStart(2, '0')}-${dateParts[1].padStart(2, '0')}`;
        return kouDate === todayMD;
      });

      if (todayKou && !isSekkiDay) {
        sekkiTitle = currentSekki.name; // タイトルは常に二十四節気
        sekkiKouBody = `${currentSekki.name}：${todayKou['和名'] || todayKou['候名'] || ''}`;
        isKouDay = true;
        currentKou = todayKou;
        console.log(`[scheduler] 七十二候の開始日です: ${todayMD} ${todayKou['和名']}`);
      } else if (!isSekkiDay) {
        // 開始日でない場合、現在進行中の七十二候を探す
        const today = new Date(jstNow.getFullYear(), jstNow.getMonth(), jstNow.getDate());
        for (const kou of kouList) {
          if (!kou['開始年月日'] || !kou['終了年月日']) continue;

          // 開始日と終了日をパース
          const startParts = kou['開始年月日'].trim().split(/[\/\-]/);
          const endParts = kou['終了年月日'].trim().split(/[\/\-]/);
          if (startParts.length !== 2 || endParts.length !== 2) continue;

          const startMonth = parseInt(startParts[0]) - 1;
          const startDay = parseInt(startParts[1]);
          const endMonth = parseInt(endParts[0]) - 1;
          const endDay = parseInt(endParts[1]);

          let startDate = new Date(jstNow.getFullYear(), startMonth, startDay);
          let endDate = new Date(jstNow.getFullYear(), endMonth, endDay);

          if (endDate < startDate) {
            if (today.getMonth() >= startMonth) {
              endDate = new Date(jstNow.getFullYear() + 1, endMonth, endDay);
            } else {
              startDate = new Date(jstNow.getFullYear() - 1, startMonth, startDay);
            }
          }

          if (today >= startDate && today <= endDate) {
            currentKou = kou;
            break;
          }
        }
      }
    }

    // 定期処理ログ（毎分）
    console.log(`[scheduler] 現在時刻(JST): ${currentTime}`);

    const response = await fetch(process.env.SUBSCRIPTIONS_URL);
    if (!response.ok) {
      console.error('[scheduler] 購読情報取得エラー:', response.statusText);
      return;
    }
    const txt = await response.text();
    if (!txt || txt.trim() === '') {
      console.log('[scheduler] 購読者なし');
      return;
    }

    const lines = txt.trim().split('\n');
    let success = 0;
    let failed = 0;
    let targets = 0;

    for (let i = 0; i < lines.length; i++) {
      let sub;
      try {
        sub = JSON.parse(lines[i]);
      } catch (e) {
        continue; // JSON解析エラーはスキップ
      }

      if (!sub.notifyTime || sub.notifyTime !== currentTime) continue; // 時刻が一致しない
      if (!isValidSubscription(sub)) continue; // 無効な購読情報

      const notifyFrequency = sub.notifyFrequency || 'sekki-only';

      // 節気・候変更日のみの設定の場合、開始日でない場合はスキップ
      if (notifyFrequency === 'sekki-only' && !isSekkiDay && !isKouDay) {
        continue;
      }

      targets++;

      // 通知内容を決定
      let notifyTitle = currentSekki.name;
      let notifyBody = `${currentSekki.name}：${(currentKou ? (currentKou['和名'] || currentKou['候名']) : currentSekki.kou_name) || ''}`;

      const payload = JSON.stringify({
        title: notifyTitle,
        body: notifyBody,
      });

      try {
        await webpush.sendNotification(sub, payload);
        success++;
      } catch (err) {
        failed++;
        console.error('[scheduler] 通知送信失敗:', err.message);
      }
    }

    if (targets) {
      console.log(`[scheduler] 通知送信結果: 成功=${success}, 失敗=${failed}, 対象=${targets}`);
    }
  } catch (err) {
    console.error('[scheduler] エラー:', err);
  }
}

// 毎分スケジュール
cron.schedule('* * * * *', sendScheduledNotifications);

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

      const title = currentSekki.name;
      const body = `${currentSekki.name}：${currentSekki.kou_name || ''}`;

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
  // 起動直後にスケジュールチェックを即実行
  sendScheduledNotifications();
  res.json({
    status: 'ok',
    message: 'サーバーが起動しました',
    timestamp: new Date().toISOString()
  });
});

// 指定された購読者の情報を検証するエンドポイント
app.post('/notify', async (req, res) => {
  try {
    console.log('購読情報検証リクエストを受信しました:', req.body);
    const { subscription } = req.body;

    if (!subscription) {
      return res.status(400).json({ error: '購読情報が必要です' });
    }

    // 購読情報の検証のみを行い、テスト通知は送信しない
    if (!isValidSubscription(subscription)) {
      return res.status(400).json({ error: '無効な購読情報です' });
    }

    console.log('購読情報の検証に成功しました');
    return res.status(200).json({ success: true, message: '有効な購読情報です' });
  } catch (err) {
    console.error('購読情報検証エラー:', err);
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
