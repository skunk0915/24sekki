<?php
// 共通関数を読み込み
require_once 'functions.php';

$kou_list = load_kou_data('72kou.csv');
$sekki_list = load_sekki_data('24sekki.csv');

// GETパラメータでタイプとidxが指定されていれば、その候または節気を表示
// 指定がなければ今日の候を優先表示
if (isset($_GET['type']) && isset($_GET['idx'])) {
    $idx = intval($_GET['idx']);
    if ($_GET['type'] === 'sekki') {
        if ($idx < 0 || $idx >= count($sekki_list)) $idx = 0;
        $display_kou = $sekki_list[$idx];
        // 節気データを候データの形式に合わせる
        $display_kou['候名'] = $display_kou['節気名'];
        $display_kou['和名'] = $display_kou['節気名'];
    } else {
        if ($idx < 0 || $idx >= count($kou_list)) $idx = 0;
        $display_kou = $kou_list[$idx];
    }
} else if (isset($_GET['idx'])) {
    $idx = intval($_GET['idx']);
    if ($idx < 0 || $idx >= count($kou_list)) $idx = 0;
    $display_kou = $kou_list[$idx];
} else {
    // 今日の候と節気を取得
    $today_kou = get_today_kou($kou_list);
    $today_sekki = get_today_sekki($sekki_list);
    
    // 七十二候を優先する
    $display_kou = $today_kou;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($_GET['idx']) || isset($_GET['type']) ? htmlspecialchars($display_kou['和名']) . ' | ' : ''; ?>二十四節気・七十二候 - 日本の季節</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <!-- PWA対応 -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#4285f4">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
    <meta name="apple-mobile-web-app-title" content="暦アプリ">
    <link rel="apple-touch-icon" href="img/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="img/favicon/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="img/favicon/favicon-16.png">
</head>
<body>
    <!-- 背景画像 -->
    <div class="background">
        <img src="<?php echo htmlspecialchars($display_kou['画像URL']); ?>" alt="<?php echo htmlspecialchars($display_kou['和名']); ?>">
    </div>
    <div class="overlay"></div>
    <!-- コンテンツ -->
    <div class="content">
        <div class="vertical-text">
            <div class="title-container">
                <h2 class="sub-title"><?php echo htmlspecialchars($display_kou['読み']); ?></h2>
                <h1 class="main-title"><?php echo htmlspecialchars($display_kou['和名']); ?></h1>
            <p class="description"><?php echo nl2br(htmlspecialchars($display_kou['本文'])); ?></p>
            <div class="date">
                <p><?php echo htmlspecialchars($display_kou['開始年月日']); ?>～<?php echo htmlspecialchars($display_kou['終了年月日']); ?></p>
            </div>
            <a href="calendar.php" class="calendar-button">暦カレンダーを見る</a>
        </div>
    </div>

    <script src="js/scripts.js"></script>
    <script>
        // サービスワーカーの登録
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('./service-worker.js', {scope: './'})  // スコープを明示的に指定
                    .then(function(registration) {
                        console.log('ServiceWorker登録成功: ', registration.scope);
                    })
                    .catch(function(error) {
                        console.log('ServiceWorker登録失敗: ', error);
                    });
            });
        }

        // PWAインストールバナーの表示をデバッグ
        window.addEventListener('beforeinstallprompt', (e) => {
            console.log('beforeinstallpromptイベントが発生しました');
            // イベントを保存しておく
            window.deferredPrompt = e;
        });
    </script>
</body>
</html>
