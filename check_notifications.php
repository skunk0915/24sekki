<?php
/**
 * 節気変更日のプッシュ通知を管理するスクリプト
 * cronで定期的に実行する（例：5分ごと）
 */

require_once 'functions.php';
require_once 'config.php';

// HTTPリクエストからの実行時のセキュリティチェック
if (php_sapi_name() !== 'cli') {
    // コマンドラインからの実行ではない場合
    $token = $_GET['token'] ?? '';
    $validToken = '24sekki_notification_secure_token'; // 安全なトークンに変更してください
    
    if ($token !== $validToken) {
        header('HTTP/1.1 403 Forbidden');
        echo 'アクセスが拒否されました';
        exit;
    }
}

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// ログ出力関数
function logMessage($message) {
    $logFile = __DIR__ . '/notification_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

logMessage('通知チェックを開始します');

// 現在の日時を取得
$now = new DateTime();
$today = $now->format('m-d'); // 年を無視して月日だけで判定
$currentTime = $now->format('H:i');

// テスト用：2025/06/05を節気変更日として扱う
$testDate = '2025-06-05';
$isTestMode = ($today === $testDate);

// 節気データを読み込む
function loadSekkiData() {
    $sekkiData = [];
    $currentYear = date('Y');

    // m/d または yyyy-mm-dd を yyyy-mm-dd へ正規化するクロージャ
    // m/d または yyyy-mm-dd を mm-dd へ正規化するクロージャ
    $normalizeDate = function($date) {
        // 例: "6/16" → "06-16"、"2025-06-16" → "06-16"
        if (preg_match('/^(\d{1,2})\/(\d{1,2})$/', trim($date), $m)) {
            return sprintf('%02d-%02d', $m[1], $m[2]);
        }
        if (preg_match('/^\d{4}-(\d{2})-(\d{2})$/', trim($date), $m)) {
            return sprintf('%02d-%02d', $m[1], $m[2]);
        }
        return trim($date); // 既に mm-dd 形式
    };

    // 二十四節気のデータを読み込む
    $sekki_list = load_sekki_data(__DIR__ . '/24sekki.csv');
    logMessage('24sekki.csv load result: count=' . count($sekki_list) . ' sample=' . (isset($sekki_list[0]) ? json_encode($sekki_list[0], JSON_UNESCAPED_UNICODE) : 'none'));

    foreach ($sekki_list as $sekki) {
        if (!isset($sekki['開始年月日'])) {
            logMessage('開始年月日が存在しません: ' . json_encode($sekki, JSON_UNESCAPED_UNICODE));
            continue;
        }
        $dateKey = $normalizeDate($sekki['開始年月日']);
        logMessage("normalizeDate: raw={$sekki['開始年月日']} -> key={$dateKey}");
        if (preg_match('/^\d{2}-\d{2}$/', $dateKey)) {
            $sekkiData[$dateKey] = [
                'type' => '二十四節気',
                'name' => $sekki['和名'] ?? '',
                'reading' => $sekki['読み'] ?? ''
            ];
            logMessage("24節気データ追加: {$dateKey} {$sekkiData[$dateKey]['name']}");
        } else {
            logMessage("dateKey形式不正: {$dateKey}");
        }
    }
    
    // 七十二候のデータを読み込む
    $kou_list = load_kou_data(__DIR__ . '/72kou.csv');
    logMessage('72kou.csv load result: count=' . count($kou_list) . ' sample=' . (isset($kou_list[0]) ? json_encode($kou_list[0], JSON_UNESCAPED_UNICODE) : 'none'));
    foreach ($kou_list as $idx => $kou) {
        logMessage('72kouデータループ: idx=' . $idx . ' data=' . json_encode($kou, JSON_UNESCAPED_UNICODE));
        if (!isset($kou['開始年月日'])) {
            logMessage('72kouデータ: 開始年月日が存在しません: ' . json_encode($kou, JSON_UNESCAPED_UNICODE));
            continue;
        }
        // 日付をDateTimeとして扱う
        $rawDate = trim($kou['開始年月日']);
        $dateObj = false;
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $rawDate, $m)) {
            $dateObj = DateTime::createFromFormat('Y-m-d', $rawDate);
            logMessage('72kou日付パース: Y-m-d形式 raw=' . $rawDate . ' => ' . ($dateObj ? $dateObj->format('Y-m-d') : '失敗'));
        } elseif (preg_match('/^(\d{1,2})\/(\d{1,2})$/', $rawDate, $m)) {
            $year = $currentYear; // 今年の値を使う
            $dateObj = DateTime::createFromFormat('Y-m-d', $year . '-' . sprintf('%02d', $m[1]) . '-' . sprintf('%02d', $m[2]));
            logMessage('72kou日付パース: m/d形式 raw=' . $rawDate . ' => ' . ($dateObj ? $dateObj->format('Y-m-d') : '失敗'));
        } elseif (preg_match('/^(\d{2})-(\d{2})$/', $rawDate, $m)) {
            $year = $currentYear;
            $dateObj = DateTime::createFromFormat('Y-m-d', $year . '-' . $m[1] . '-' . $m[2]);
            logMessage('72kou日付パース: mm-dd形式 raw=' . $rawDate . ' => ' . ($dateObj ? $dateObj->format('Y-m-d') : '失敗'));
        }
        if (!$dateObj) {
            logMessage('72kou日付パース失敗: raw=' . $rawDate);
            continue;
        }
        // mm-dd形式のキーを生成
        $mmddKey = $dateObj->format('m-d');
        // 既に何らかのデータ（二十四節気または他の七十二候）が登録されている場合は、日付が空くまで1日ずつ進める
        $tryCount = 0;
        while (isset($sekkiData[$mmddKey]) && $tryCount < 10) {
            $dateObj->modify('+1 day');
            $mmddKey = $dateObj->format('m-d');
            $tryCount++;
        }
        // 七十二候データを追加
        $sekkiData[$mmddKey] = [
            'type' => '七十二候',
            'name' => $kou['和名'] ?? '',
            'reading' => $kou['読み'] ?? ''
        ];
        logMessage("72候データ追加: {$mmddKey} {$sekkiData[$mmddKey]['name']}（元日付:{$rawDate}）");
    }
    
    // デバッグ: キー一覧をログ出力
    logMessage('sekkiData keys: ' . implode(',', array_keys($sekkiData)));
    return $sekkiData;
}

// 今日が節気変更日かどうか確認
$sekkiData = loadSekkiData();
logMessage('判定用today=' . $today);
logMessage('sekkiDataにtodayキーが存在するか: ' . (isset($sekkiData[$today]) ? 'YES' : 'NO'));
$isSekkiChangeDay = isset($sekkiData[$today]) || $isTestMode; // $todayはmm-dd形式

if (!$isSekkiChangeDay) {
    logMessage('今日は節気変更日ではありません');
    exit;
}
logMessage('今日は節気変更日です: ' . $today . ' ' . ($sekkiData[$today]['name'] ?? ''));
// テストモードの場合、節気情報をセット
if ($isTestMode && !isset($sekkiData[$today])) {
    $sekkiData[$today] = [
        'type' => 'テスト',
        'name' => 'テスト節気',
        'reading' => 'てすとせっき'
    ];
    logMessage('テストモード: 今日を節気変更日として処理します');
}

// 購読情報を読み込む
$subscriptionsFile = __DIR__ . '/subscriptions.txt';
$subscriptions = [];
$notifyTimes = [];

if (file_exists($subscriptionsFile)) {
    $lines = file($subscriptionsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $data = json_decode($line, true);
        if (!$data) {
            logMessage("JSON解析エラー: {$line}");
            continue;
        }
        
        // エンドポイントと通知時刻の両方がある購読情報
        if (isset($data['endpoint']) && isset($data['notifyTime'])) {
            // ダミーのエンドポイントかどうかチェック
            if (isset($data['isDummy']) && $data['isDummy'] === true) {
                // ダミーエンドポイントの場合は操作用の撮伺購読情報として扱う
                $data['pseudo_subscription'] = true;
                $subscriptions[] = $data;
                $notifyTimes[] = $data['notifyTime']; // 通知時刻も記録
            } else {
                // 通常の購読情報
                $subscriptions[] = $data;
            }
        }
        // 通知時刻のみのエントリ
        elseif (isset($data['notifyTime']) && !isset($data['endpoint'])) {
            $notifyTimes[] = $data['notifyTime'];
        }
    }
}

// 通知時刻のみのエントリを処理するために擬似的な購読情報を作成
foreach ($notifyTimes as $time) {
    $subscriptions[] = [
        'notifyTime' => $time,
        'pseudo_subscription' => true // 擬似的な購読情報であることを示すフラグ
    ];
}

logMessage('購読者数: ' . count($subscriptions));

// 各購読者の通知時刻をチェック
foreach ($subscriptions as $subscription) {
    $notifyTime = $subscription['notifyTime'] ?? '08:00'; // デフォルト時刻は8:00
    
    // 通知時刻の5分前の時刻を計算
    $notifyTimeParts = explode(':', $notifyTime);
    $notifyDateTime = new DateTime();
    $notifyDateTime->setTime((int)$notifyTimeParts[0], (int)$notifyTimeParts[1], 0);
    
    $wakeupTime = clone $notifyDateTime;
    $wakeupTime->modify('-5 minutes');
    $wakeupTimeStr = $wakeupTime->format('H:i');
    
    // 現在時刻が起動時刻（通知の5分前）かどうかチェック
    if ($currentTime === $wakeupTimeStr) {
        logMessage("通知時刻 {$notifyTime} の5分前です。プッシュサーバーを起動します。");
        
        // render.comのプッシュサーバーを起動するリクエストを送信
        $pushServerUrl = PUSH_SERVER_WAKE_ENDPOINT;
        $ch = curl_init($pushServerUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        logMessage("プッシュサーバー起動リクエスト結果: HTTP {$httpCode}, レスポンス: {$response}");
    }
    
    // 現在時刻が通知時刻かどうかチェック
    if ($currentTime === $notifyTime) {
        logMessage("通知時刻 {$notifyTime} になりました。通知を送信します。");
        
        // 節気情報を取得
        $sekkiInfo = $sekkiData[$today];
        $title = "{$sekkiInfo['type']}「{$sekkiInfo['name']}」";
        $body = "本日から{$sekkiInfo['name']}（{$sekkiInfo['reading']}）です。";
        
        // 擬似的な購読情報の場合は通知を送信しない
        if (isset($subscription['pseudo_subscription']) && $subscription['pseudo_subscription']) {
            logMessage("通知時刻 {$notifyTime} の擬似購読情報です。実際の通知は送信しません。");
            continue;
        }
        
        // エンドポイントが存在しない購読情報は通知を送信しない
        if (!isset($subscription['endpoint']) || empty($subscription['endpoint'])) {
            logMessage("通知時刻 {$notifyTime} の購読情報にエンドポイントがありません。通知をスキップします。");
            logMessage("購読データの詳細: " . json_encode($subscription));
            continue;
        }
        
        // 通知送信リクエスト
        $notifyUrl = PUSH_SERVER_NOTIFY_ENDPOINT;
        $postData = json_encode([
            'title' => $title,
            'body' => $body,
            'subscription' => $subscription
        ]);
        
        $ch = curl_init($notifyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        logMessage("通知送信リクエスト結果: HTTP {$httpCode}, レスポンス: {$response}");
    }
}

logMessage('通知チェックを完了しました');
