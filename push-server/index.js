const express = require('express');
const fetch = require('node-fetch');
const webpush = require('web-push');
require('dotenv').config();

const app = express();
const PORT = process.env.PORT || 3000;

// VAPID設定
webpush.setVapidDetails(
  'mailto:you@example.com',
  process.env.VAPID_PUBLIC_KEY,
  process.env.VAPID_PRIVATE_KEY
);

// さくらにある購読情報を取得して通知送信
app.get('/send', async (req, res) => {
  try {
    const txt = await (await fetch(process.env.SUBSCRIPTIONS_URL)).text();
    const lines = txt.trim().split('\n');

    const payload = JSON.stringify({
      title: 'お知らせ',
      body: 'これはRenderからのプッシュ通知です',
    });

    for (const line of lines) {
      try {
        const subscription = JSON.parse(line);
        await webpush.sendNotification(subscription, payload);
      } catch (err) {
        console.error('通知失敗:', err.message);
      }
    }

    res.send('通知送信完了');
  } catch (err) {
    console.error(err);
    res.status(500).send('通知送信失敗');
  }
});

app.listen(PORT, () => {
  console.log(`Push server is running on port ${PORT}`);
});
