<?php
// 72kou.csvから指定インデックスの候を表示
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

$kou_list = load_kou_data('72kou.csv');
$idx = isset($_GET['idx']) ? intval($_GET['idx']) : 0;
if ($idx < 0 || $idx >= count($kou_list)) $idx = 0;
$kou = $kou_list[$idx];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($kou['和名']); ?> | 七十二候</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body style="background-image: url('<?php echo $kou['画像URL']; ?>');">
    <div class="overlay">
        <a href="index.php" class="back">← トップへ戻る</a>
        <h1><?php echo htmlspecialchars($kou['和名']); ?></h1>
        <h2><?php echo htmlspecialchars($kou['候名']); ?>（<?php echo htmlspecialchars($kou['読み']); ?>）</h2>
        <p><?php echo nl2br(htmlspecialchars($kou['本文'])); ?></p>
        <p class="kou-range">期間: <?php echo htmlspecialchars($kou['開始年月日']); ?> ～ <?php echo htmlspecialchars($kou['終了年月日']); ?></p>
    </div>
</body>
</html>
