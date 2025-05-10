<?php
// 共通関数を読み込み
require_once 'functions.php';

// データ読み込み
$kou_list = load_kou_data('72kou.csv');
$sekki_list = load_sekki_data('24sekki.csv');

// 今日の候と節気を取得
$today_kou = get_today_kou($kou_list);
$today_sekki = get_today_sekki($sekki_list);

// 七十二候を優先する
$today_calendar = $today_kou;
$is_kou_today = true;



// 表示用の季節データを準備
$seasons = [
    '春' => [2, 3, 4],
    '夏' => [5, 6, 7],
    '秋' => [8, 9, 10],
    '冬' => [11, 12, 1]
];

// 二十四節気を月ごとにグループ化
$sekki_by_month = [];
foreach ($sekki_list as $sekki) {
    $month = (int)explode('/', $sekki['開始年月日'])[0];
    if (!isset($sekki_by_month[$month])) {
        $sekki_by_month[$month] = [];
    }
    $sekki_by_month[$month][] = $sekki;
}

// 七十二候を月ごとにグループ化
$kou_by_month = [];
foreach ($kou_list as $kou) {
    $month = (int)explode('/', $kou['開始年月日'])[0];
    if (!isset($kou_by_month[$month])) {
        $kou_by_month[$month] = [];
    }
    $kou_by_month[$month][] = $kou;
}

// 現在の月と日を取得
$current_month = (int)date('n');
$current_day = (int)date('j');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>二十四節気と七十二候 - 日本の季節</title>
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
    <style>
        body {
            overflow: auto;
            background-color: #f5f5f5;
            color: #333;
            font-family: 'Noto Serif JP', serif;
        }
        
        .calendar-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        h1 {
            text-align: center;
            margin-bottom: 30px;
            font-size: 2rem;
        }
        
        .season-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
        }
        
        .season-header {
            background-color: #f8f8f8;
            text-align: center;
            padding: 10px;
            font-size: 1.5rem;
            border: 1px solid #ddd;
        }
        
        .spring {
            background-color: #ffeef1;
        }
        
        .summer {
            background-color: #e6f9ff;
        }
        
        .autumn {
            background-color: #fff9e6;
        }
        
        .winter {
            background-color: #f0f8ff;
        }
        
        .month-row th {
            background-color: #f0f0f0;
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
            width: 33.33%;
        }
        
        .sekki-row th {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
            background-color: #e8e8e8;
        }
        
        .kou-row td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
            vertical-align: top;
            height: 100px;
        }
        
        .kou-item {
            margin-bottom: 5px;
            padding: 3px;
            border-radius: 3px;
            cursor: pointer;
        }
        
        .kou-item:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
        
        .current {
            background-color: #ffeb3b;
            font-weight: bold;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            margin-bottom: 20px;
            text-decoration: none;
            color: #333;
            font-weight: bold;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
        
        .today-info {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
            text-align: center;
        }
        
        .today-info h2 {
            margin-bottom: 10px;
            font-size: 1.8rem;
        }
        
        .today-info p {
            margin-bottom: 5px;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <div class="calendar-container">
        <h1>二十四節気と七十二候</h1>
        
        <div class="today-info">
            <h2>今日の暦</h2>
            <p><?php echo date('Y年n月j日'); ?></p>
            <p>
                <?php if ($is_kou_today): ?>
                    七十二候: <strong><?php echo htmlspecialchars($today_kou['和名']); ?></strong>（<?php echo htmlspecialchars($today_kou['読み']); ?>）
                <?php else: ?>
                    二十四節気: <strong><?php echo htmlspecialchars($today_sekki['節気名']); ?></strong>（<?php echo htmlspecialchars($today_sekki['読み']); ?>）
                <?php endif; ?>
            </p>
            <a href="index.php" class="back-link">詳細を見る</a>
        </div>
        
        <a href="index.php" class="back-link">トップページに戻る</a>
        
        <?php foreach ($seasons as $season_name => $months): ?>
        <table class="season-table">
            <thead>
                <tr>
                    <th colspan="3" class="season-header <?php echo strtolower($season_name); ?>"><?php echo $season_name; ?></th>
                </tr>
                <tr class="month-row">
                    <?php foreach ($months as $month): ?>
                    <th><?php echo $month; ?>月</th>
                    <?php endforeach; ?>
                </tr>
                <tr class="sekki-row">
                    <?php foreach ($months as $month): ?>
                    <th>
                        <?php if (isset($sekki_by_month[$month])): ?>
                            <?php foreach ($sekki_by_month[$month] as $sekki): ?>
                                <div class="kou-item <?php echo ($current_month == $month && check_date_in_range($current_month, $current_day, $sekki['開始年月日'], $sekki['終了年月日'])) ? 'current' : ''; ?>">
                                    <a href="index.php?type=sekki&idx=<?php echo array_search($sekki, $sekki_list); ?>"><?php echo htmlspecialchars($sekki['節気名']); ?></a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr class="kou-row">
                    <?php foreach ($months as $month): ?>
                    <td>
                        <?php if (isset($kou_by_month[$month])): ?>
                            <?php foreach ($kou_by_month[$month] as $kou): ?>
                                <div class="kou-item <?php echo ($current_month == $month && check_date_in_range($current_month, $current_day, $kou['開始年月日'], $kou['終了年月日'])) ? 'current' : ''; ?>">
                                    <a href="index.php?type=kou&idx=<?php echo array_search($kou, $kou_list); ?>"><?php echo htmlspecialchars($kou['和名']); ?></a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
            </tbody>
        </table>
        <?php endforeach; ?>
        
        <a href="index.php" class="back-link">トップページに戻る</a>
    </div>
    
    <script>
    // JavaScriptでの処理が必要な場合はここに記述
    document.addEventListener('DOMContentLoaded', function() {
        // 今日の暦をハイライト表示する要素にスクロール
        const currentElements = document.querySelectorAll('.current');
        if (currentElements.length > 0) {
            currentElements[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
    
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