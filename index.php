<?php
// 共通関数を読み込み
require_once 'functions.php';
// スプレッドシートからデータを取得する関数を読み込み
require_once 'get_spreadsheet_data.php';

$kou_list = load_kou_data('72kou.csv');
$sekki_list = load_sekki_data('24sekki.csv');

// スプレッドシートからコラム解説データを取得
$spreadsheet_url = 'https://docs.google.com/spreadsheets/d/1XwNR5IxzWG3Z2AGUnifdjWZk1LWQWVnpfzNzEoPxSzw/edit?usp=sharing';
$column_data = get_spreadsheet_data($spreadsheet_url);

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

    // 今日の日付を M/D 形式に変換
    $today_md = date('n/j');
    $found_sekki = null;
    foreach ($sekki_list as $i => $sekki) {
        if (isset($sekki['開始年月日']) && $sekki['開始年月日'] === $today_md) {
            $found_sekki = $sekki;
            $found_sekki['候名'] = $found_sekki['節気名'];
            $found_sekki['和名'] = $found_sekki['節気名'];
            if (!isset($found_sekki['読み'])) {
                $found_sekki['読み'] = $found_sekki['節気名'];
            }
            $idx = $i;
            break;
        }
    }
    if ($found_sekki !== null) {
        // 二十四節気の初日を優先
        $display_kou = $found_sekki;
        // 前後の節気を取得
        $prev_sekki = get_prev_sekki($sekki_list, $idx);
        $next_sekki = get_next_sekki($sekki_list, $idx);
        // ナビゲーション用のリンクを準備
        $prev_link = "index.php?type=sekki&idx=" . array_search($prev_sekki, $sekki_list);
        $next_link = "index.php?type=sekki&idx=" . array_search($next_sekki, $sekki_list);
        $current_type = 'sekki';
    } else {
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
    <link rel="stylesheet" href="css/column-style.css">
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

            <?php
            // スプレッドシートから取得したコラム解説を表示
            if ($current_type === 'kou') {
                // 現在表示している候の名前を取得
                $current_kou_name = $display_kou['和名'];
                
                // この候に一致するコラム解説を取得（スプレッドシートのA列を参照）
                $related_columns = [];
                foreach ($column_data as $column) {
                    if (isset($column['時期']) && $column['時期'] === $current_kou_name) {
                        $related_columns[] = $column;
                    }
                }
                
                // 関連するコラム解説があれば表示
                if (!empty($related_columns)) {
                    echo '<div class="column-section">';
                    echo '<h3 class="column-title">季節のことば</h3>';
                    
                    foreach ($related_columns as $column) {
                        echo '<div class="column-item">';
                        if (isset($column['語句']) && !empty($column['語句'])) {
                            $term = htmlspecialchars($column['語句']);
                            $reading = isset($column['語句（読み）']) && !empty($column['語句（読み）']) ? htmlspecialchars($column['語句（読み）']) : '';
                            
                            // 語句とルビが全く同じ場合はルビを表示しない
                            if ($reading !== '' && $term !== $reading) {
                                echo '<h4 class="column-term"><ruby>' . $term . '<rt class="column-reading">' . $reading . '</rt></ruby></h4>';
                            } else {
                                echo '<h4 class="column-term">' . $term . '</h4>';
                            }
                        }
                        if (isset($column['解説']) && !empty($column['解説'])) {
                            $column_text = htmlspecialchars($column['解説']);
                            // 句点（。）の後に<br>タグを追加
                            $column_text = str_replace('。', '。<br>', $column_text);
                            echo '<p class="column-description">' . $column_text . '</p>';
                        }
                        echo '</div>';
                    }
                    
                    echo '</div>';
                }
            } elseif ($current_type === 'sekki') {
                // 節気の場合は、その節気名に一致するコラム解説のみを表示
                $current_sekki_name = $display_kou['節気名'];
                
                // この節気に一致するコラム解説を取得（スプレッドシートのA列を参照）
                $related_columns = [];
                foreach ($column_data as $column) {
                    if (isset($column['時期']) && $column['時期'] === $current_sekki_name) {
                        $related_columns[] = $column;
                    }
                }
                
                // 関連するコラム解説があれば表示
                if (!empty($related_columns)) {
                    echo '<div class="column-section">';
                    echo '<h3 class="column-title">季節のことば</h3>';
                    
                    foreach ($related_columns as $column) {
                        echo '<div class="column-item">';
                        if (isset($column['語句']) && !empty($column['語句'])) {
                            $term = htmlspecialchars($column['語句']);
                            $reading = isset($column['語句（読み）']) && !empty($column['語句（読み）']) ? htmlspecialchars($column['語句（読み）']) : '';
                            
                            // 語句とルビが全く同じ場合はルビを表示しない
                            if ($reading !== '' && $term !== $reading) {
                                echo '<h4 class="column-term"><ruby>' . $term . '<rt class="column-reading">' . $reading . '</rt></ruby></h4>';
                            } else {
                                echo '<h4 class="column-term">' . $term . '</h4>';
                            }
                        }
                        if (isset($column['解説']) && !empty($column['解説'])) {
                            $column_text = htmlspecialchars($column['解説']);
                            // 句点（。）の後に<br>タグを追加
                            $column_text = str_replace('。', '。<br>', $column_text);
                            echo '<p class="column-description">' . $column_text . '</p>';
                        }
                        echo '</div>';
                    }
                    
                    echo '</div>';
                }
            }
            ?>


            <?php if (isset($prev_link) && isset($next_link)): ?>
                <div class="navigation">
                    <a href="<?php echo $prev_link; ?>" class="nav-button prev-button"><?php echo $current_type === 'kou' ? htmlspecialchars($prev_kou['和名']) : htmlspecialchars($prev_sekki['節気名']); ?></a>
                    <a href="calendar.php?from=<?php echo $current_type; ?>&idx=<?php echo $idx; ?>" class="calendar-button">こよみ一覧</a>
                    <a href="<?php echo $next_link; ?>" class="nav-button next-button"><?php echo $current_type === 'kou' ? htmlspecialchars($next_kou['和名']) : htmlspecialchars($next_sekki['節気名']); ?></a>
                </div>
            <?php else: ?>
                <a href="calendar.php" class="calendar-button">こよみ一覧を見る</a>
            <?php endif; ?>

        </div>

        <script src="js/horizon-scroll.js"></script>
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
