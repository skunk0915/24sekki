# 二十四節気・七十二候 Webアプリケーション

日本の伝統的な暦である「二十四節気」と「七十二候」を表示し、季節の変わり目にWeb Pushプッシュ通知を配信するWebアプリケーションです。

## システム構成

- **インフラ / サーバー**: カゴヤVPS（Ubuntu 24.04 LTS）
- **公開URL**: `https://72kou.133.18.144.38.sslip.io/`
- **Webサーバー**: Nginx 1.24.0（SSL/TLS対応、Let's Encrypt証明書）
- **フロントエンド / 購読管理**: PHP 8.3-FPM, HTML/CSS(SCSS), JavaScript (PWA対応 / Service Worker)
- **プッシュ通知サーバー**: Node.js (Express, `web-push`), PM2 (`72kou-push`, Port 3007)
- **定期実行タスク**: crontab（5分おきに `check_notifications.php` を実行し、通知時刻・節気変更を監視）

## ディレクトリ構成

- `index.php`: メイン画面（現在の節気・候の表示）
- `calendar.php`: こよみ一覧・カレンダー画面（通知設定UI含む）
- `subscribe.php`: ブラウザからのWeb Push購読登録エンドポイント
- `check_notifications.php`: cron定期実行スクリプト（通知判定および配信トリガー）
- `config.php`: サーバー全体設定
- `push-server/`: Node.js プッシュ配信サーバー（ポート 3007で常駐稼働）
- `24sekki.csv`, `72kou.csv`: 二十四節気・七十二候のデータストア

