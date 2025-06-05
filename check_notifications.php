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
$today = $now->format('Y-m-d');
$currentTime = $now->format('H:i');

// テスト用：2025/06/05を節気変更日として扱う
$testDate = '2025-06-05';
$isTestMode = ($today === $testDate);

// 節気データを読み込む
function loadSekkiData() {
    $sekkiData = [];
    
    // 二十四節気のデータを読み込む
    $sekki_list = load_sekki_data('24sekki.csv');
    foreach ($sekki_list as $sekki) {
        if (isset($sekki['開始年月日']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $sekki['開始年月日'])) {
            $sekkiData[$sekki['開始年月日']] = [
                'type' => '二十四節気',
                'name' => $sekki['節気名'],
                'reading' => $sekki['読み']
            ];
        }
    }
    
    // 七十二候のデータを読み込む
    $kou_list = load_kou_data('72kou.csv');
    foreach ($kou_list as $kou) {
        if (isset($kou['開始年月日']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $kou['開始年月日'])) {
            $sekkiData[$kou['開始年月日']] = [
                'type' => '七十二候',
                'name' => $kou['和名'],
                'reading' => $kou['読み']
            ];
        }
    }
    
    return $sekkiData;
}

// 今日が節気変更日かどうか確認
$sekkiData = loadSekkiData();
$isSekkiChangeDay = isset($sekkiData[$today]) || $isTestMode;

if (!$isSekkiChangeDay) {
    logMessage('今日は節気変更日ではありません');
    exit;
}

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
