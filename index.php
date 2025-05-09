<?php
// 72kou.csvを読み込んで候を表示
function load_kou_data($csv_file) {
    $kou_list = array();
    if (($handle = fopen($csv_file, "r")) !== FALSE) {
        $header = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== FALSE) {
            $kou = array();
            foreach ($header as $i => $col) {
                $kou[$col] = isset($row[$i]) ? $row[$i] : '';
            }
            $kou_list[] = $kou;
        }
        fclose($handle);
    }
    return $kou_list;
}

// 24sekki.csvを読み込む
function load_sekki_data($csv_file) {
    $sekki_list = array();
    if (($handle = fopen($csv_file, "r")) !== FALSE) {
        $header = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== FALSE) {
            $sekki = array();
            foreach ($header as $i => $col) {
                $sekki[$col] = isset($row[$i]) ? $row[$i] : '';
            }
            $sekki_list[] = $sekki;
        }
        fclose($handle);
    }
    return $sekki_list;
}

function get_today_kou($kou_list) {
    // 月と日を別々に取得して数値比較を行う
    $month = (int)date('n');
    $day = (int)date('j');
    
    foreach ($kou_list as $kou) {
        // 開始日と終了日を分解
        list($start_month, $start_day) = explode('/', $kou['開始年月日']);
        list($end_month, $end_day) = explode('/', $kou['終了年月日']);
        
        // 数値として比較
        $start_month = (int)$start_month;
        $start_day = (int)$start_day;
        $end_month = (int)$end_month;
        $end_day = (int)$end_day;
        
        // 月が同じ場合は日だけで比較
        if ($month == $start_month && $month == $end_month) {
            if ($day >= $start_day && $day <= $end_day) {
                return $kou;
            }
        }
        // 開始月と終了月が異なる場合
        elseif ($start_month != $end_month) {
            // 現在の月が開始月で、日が開始日以上
            if ($month == $start_month && $day >= $start_day) {
                return $kou;
            }
            // 現在の月が終了月で、日が終了日以下
            elseif ($month == $end_month && $day <= $end_day) {
                return $kou;
            }
            // 現在の月が開始月と終了月の間
            elseif ($month > $start_month && $month < $end_month) {
                return $kou;
            }
        }
    }
    
    // デバッグ用に現在の日付を表示
    error_log("現在の日付: {$month}/{$day}");
    
    // 範囲外の場合は最初の候を返す
    return $kou_list[0];
}

// 今日の日付に該当する節気を取得
function get_today_sekki($sekki_list) {
    // 月と日を別々に取得して数値比較を行う
    $month = (int)date('n');
    $day = (int)date('j');
    
    foreach ($sekki_list as $sekki) {
        // 開始日と終了日を分解
        list($start_month, $start_day) = explode('/', $sekki['開始年月日']);
        list($end_month, $end_day) = explode('/', $sekki['終了年月日']);
        
        // 数値として比較
        $start_month = (int)$start_month;
        $start_day = (int)$start_day;
        $end_month = (int)$end_month;
        $end_day = (int)$end_day;
        
        // 月が同じ場合は日だけで比較
        if ($month == $start_month && $month == $end_month) {
            if ($day >= $start_day && $day <= $end_day) {
                return $sekki;
            }
        }
        // 開始月と終了月が異なる場合
        elseif ($start_month != $end_month) {
            // 現在の月が開始月で、日が開始日以上
            if ($month == $start_month && $day >= $start_day) {
                return $sekki;
            }
            // 現在の月が終了月で、日が終了日以下
            elseif ($month == $end_month && $day <= $end_day) {
                return $sekki;
            }
            // 現在の月が開始月と終了月の間
            elseif ($month > $start_month && $month < $end_month) {
                return $sekki;
            }
        }
    }
    
    // 範囲外の場合は最初の節気を返す
    return $sekki_list[0];
}

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
            </div>
            <p class="description"><?php echo nl2br(htmlspecialchars($display_kou['本文'])); ?></p>
            <div class="date">
                <p><?php echo htmlspecialchars($display_kou['開始年月日']); ?>～<?php echo htmlspecialchars($display_kou['終了年月日']); ?></p>
            </div>
            <a href="calendar.php" class="calendar-button">暦カレンダーを見る</a>
        </div>
    </div>

    <script src="js/scripts.js"></script>
</body>
</html>
