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
        if (!isset($display_kou['読み'])) {
            $display_kou['読み'] = $display_kou['節気名'];
        }

        // 前後の節気を取得
        $prev_sekki = get_prev_sekki($sekki_list, $idx);
        $next_sekki = get_next_sekki($sekki_list, $idx);

        // ナビゲーション用のリンクを準備
        $prev_link = "index.php?type=sekki&idx=" . array_search($prev_sekki, $sekki_list);
        $next_link = "index.php?type=sekki&idx=" . array_search($next_sekki, $sekki_list);
        $current_type = 'sekki';
    } else {
        if ($idx < 0 || $idx >= count($kou_list)) $idx = 0;
        $display_kou = $kou_list[$idx];

        // 前後の候を取得
        $prev_kou = get_prev_kou($kou_list, $idx);
        $next_kou = get_next_kou($kou_list, $idx);

        // ナビゲーション用のリンクを準備
        $prev_link = "index.php?type=kou&idx=" . array_search($prev_kou, $kou_list);
        $next_link = "index.php?type=kou&idx=" . array_search($next_kou, $kou_list);
        $current_type = 'kou';
    }
} else if (isset($_GET['idx'])) {
    $idx = intval($_GET['idx']);
    if ($idx < 0 || $idx >= count($kou_list)) $idx = 0;
    $display_kou = $kou_list[$idx];

    // 前後の候を取得
    $prev_kou = get_prev_kou($kou_list, $idx);
    $next_kou = get_next_kou($kou_list, $idx);

    // ナビゲーション用のリンクを準備
    $prev_link = "index.php?idx=" . array_search($prev_kou, $kou_list);
    $next_link = "index.php?idx=" . array_search($next_kou, $kou_list);
    $current_type = 'kou';
} else {
    // 今日の候と節気を取得
    $today_kou = get_today_kou($kou_list);
    $today_sekki = get_today_sekki($sekki_list);

    // 七十二候を優先する
    $display_kou = $today_kou;

    // 今日の候のインデックスを取得
    $idx = array_search($today_kou, $kou_list);

    // 前後の候を取得
    $prev_kou = get_prev_kou($kou_list, $idx);
    $next_kou = get_next_kou($kou_list, $idx);

    // ナビゲーション用のリンクを準備
    $prev_link = "index.php?type=kou&idx=" . array_search($prev_kou, $kou_list);
    $next_link = "index.php?type=kou&idx=" . array_search($next_kou, $kou_list);
    $current_type = 'kou';
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
    <meta name="theme-color" content="transparent">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="二十四節気・七十二候">
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
                <?php
                // 七十二候の場合のみ、対応する二十四節気を表示
                if ($current_type === 'kou') {
                    $kou_idx = array_search($display_kou, $kou_list);
                    if ($kou_idx !== false) {
                        $related_sekki = get_sekki_for_kou($kou_idx, $kou_list, $sekki_list);
                        $sekki_idx = array_search($related_sekki, $sekki_list);
                        if ($sekki_idx !== false) {
                            echo '<div class="sekki-link"><a href="index.php?type=sekki&idx=' . $sekki_idx . '">' . htmlspecialchars($related_sekki['節気名']) . '</a></div>';
                        }
                    }
                }
                ?>
                <div class="title_wrapper">
                    <h2 class="sub-title"><?php echo htmlspecialchars($display_kou['読み']); ?></h2>
                    <h1 class="main-title"><?php echo htmlspecialchars($display_kou['和名']); ?></h1>
                </div>
                <div class="date">
                    <p><?php
                        // 開始年月日を処理
                        $start_date = htmlspecialchars($display_kou['開始年月日']);
                        $start_date = preg_replace('/([0-9]+)/', '<span class="num">$1</span>', $start_date);

                        // 終了年月日を処理
                        $end_date = htmlspecialchars($display_kou['終了年月日']);
                        $end_date = preg_replace('/([0-9]+)/', '<span class="num">$1</span>', $end_date);

                        echo $start_date . '～' . $end_date;
                        ?></p>
                </div>


                <?php
                // 二十四節気表示時に、その節気に含まれる七十二候をリスト表示
                if ($current_type === 'sekki') {
                    // 節気のインデックスを取得
                    // ここでは直接$idxを使用することで、array_searchの問題を回避
                    $sekki_idx = $idx;

                    // この節気に関連する七十二候を取得
                    $related_kou = get_kou_for_sekki($sekki_idx, $kou_list, $sekki_list);

                    echo '<div class="includes">';
                    if (!empty($related_kou)) {
                        foreach ($related_kou as $kou) {
                            echo '<a href="index.php?type=kou&idx=' . $kou['idx'] . '">' . htmlspecialchars($kou['data']['和名']) . '</a>';
                        }
                    }
                    echo '</div>';
                }
                ?>
            </div>
            <p class="description">
                <?php
                $text = htmlspecialchars($display_kou['本文']);
                // 句点（。）の後に<br>タグを追加
                $text = str_replace('。', '。<br>', $text);
                echo $text;
                ?>
            </p>



            <?php if (isset($prev_link) && isset($next_link)): ?>
                <div class="navigation">
                    <a href="<?php echo $prev_link; ?>" class="nav-button prev-button"><?php echo $current_type === 'kou' ? htmlspecialchars($prev_kou['和名']) : htmlspecialchars($prev_sekki['節気名']); ?></a>
                    <a href="calendar.php?from=<?php echo $current_type; ?>&idx=<?php echo $idx; ?>" class="calendar-button">暦一覧</a>
                    <a href="<?php echo $next_link; ?>" class="nav-button next-button"><?php echo $current_type === 'kou' ? htmlspecialchars($next_kou['和名']) : htmlspecialchars($next_sekki['節気名']); ?></a>
                </div>
            <?php else: ?>
                <a href="calendar.php" class="calendar-button">暦カレンダーを見る</a>
            <?php endif; ?>

        </div>

        <script src="js/scripts.js"></script>
        <script>
            // サービスワーカーの登録
            if ('serviceWorker' in navigator) {
                window.addEventListener('load', function() {
                    navigator.serviceWorker.register('./service-worker.js', {
                            scope: './'
                        }) // スコープを明示的に指定
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