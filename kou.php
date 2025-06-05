<?php
require_once 'functions.php';

$kou_list = load_kou_data('72kou.csv');
$idx = isset($_GET['idx']) ? intval($_GET['idx']) : 0;
if ($idx < 0 || $idx >= count($kou_list)) $idx = 0;
$kou = $kou_list[$idx];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($kou['和名']); ?> | 七十二候</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Serif+JP:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- 背景画像 -->
    <div class="background">
        <img src="<?php echo htmlspecialchars($kou['画像URL']); ?>" alt="<?php echo htmlspecialchars($kou['和名']); ?>">
    </div>
    <div class="overlay"></div>
    <!-- コンテンツ -->
    <div class="content">
        <div class="vertical-text">
            <a href="index.php" class="back">← トップへ戻る</a>
            <div class="title-container">
                <h1 class="main-title"><?php echo htmlspecialchars($kou['候名']); ?></h1>
                <h2 class="sub-title"><?php echo htmlspecialchars($kou['和名']); ?>（<?php echo htmlspecialchars($kou['読み']); ?>）</h2>
            </div>
            <p class="description"><?php echo nl2br(htmlspecialchars($kou['本文'])); ?></p>
            <div class="date">
                <p><?php echo htmlspecialchars($kou['開始年月日']); ?>～<?php echo htmlspecialchars($kou['終了年月日']); ?></p>
            </div>
            <button id="menuBtn">七十二候一覧</button>
        </div>
    </div>
    <div id="kouList" class="kou-list hidden">
        <ul>
        <?php foreach ($kou_list as $i => $kou_item): ?>
            <li><a href="kou.php?idx=<?php echo $i; ?>"><?php echo htmlspecialchars($kou_item['候名']); ?>（<?php echo htmlspecialchars($kou_item['和名']); ?>）</a></li>
        <?php endforeach; ?>
        </ul>
        <button id="closeMenu">閉じる</button>
    </div>

</body>
</html>
