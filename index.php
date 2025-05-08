<?php
// 72kou.csvを読み込んで現在の候を表示
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

function get_today_kou($kou_list) {
    $today = date('n/j');
    foreach ($kou_list as $kou) {
        if ($kou['開始年月日'] <= $today && $today <= $kou['終了年月日']) {
            return $kou;
        }
    }
    // 範囲外の場合は最初の候を返す
    return $kou_list[0];
}

$kou_list = load_kou_data('72kou.csv');
$today_kou = get_today_kou($kou_list);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>七十二候カレンダー</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body style="background-image: url('<?php echo $today_kou['画像URL']; ?>');">
    <div class="overlay">
        <h1><?php echo htmlspecialchars($today_kou['和名']); ?></h1>
        <h2><?php echo htmlspecialchars($today_kou['候名']); ?>（<?php echo htmlspecialchars($today_kou['読み']); ?>）</h2>
        <p><?php echo nl2br(htmlspecialchars($today_kou['本文'])); ?></p>
        <button id="menuBtn">七十二候一覧</button>
    </div>
    <div id="kouList" class="kou-list hidden">
        <ul>
        <?php foreach ($kou_list as $i => $kou): ?>
            <li><a href="kou.php?idx=<?php echo $i; ?>"><?php echo htmlspecialchars($kou['候名']); ?>（<?php echo htmlspecialchars($kou['和名']); ?>）</a></li>
        <?php endforeach; ?>
        </ul>
        <button id="closeMenu">閉じる</button>
    </div>
    <script src="js/scripts.js"></script>
</body>
</html>
