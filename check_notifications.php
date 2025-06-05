<?php
/**
 * 節気変更日のプッシュ通知を管理するスクリプト
 * cronで定期的に実行する（例：5分ごと）
 */

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
    $sekkiFile = __DIR__ . '/24sekki.csv';
    $kouFile = __DIR__ . '/72kou.csv';
    $sekkiData = [];
    
    // 二十四節気のデータを読み込む
    if (($handle = fopen($sekkiFile, 'r')) !== false) {
        while (($data = fgetcsv($handle)) !== false) {
            if (isset($data[2]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data[2])) {
                $sekkiData[$data[2]] = [
                    'type' => '二十四節気',
                    'name' => $data[0],
                    'reading' => $data[1]
                ];
            }
        }
        fclose($handle);
    }
    
    // 七十二候のデータを読み込む
    if (($handle = fopen($kouFile, 'r')) !== false) {
        while (($data = fgetcsv($handle)) !== false) {
            if (isset($data[3]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data[3])) {
                $sekkiData[$data[3]] = [
                    'type' => '七十二候',
                    'name' => $data[0],
                    'reading' => $data[2]
                ];
            }
        }
        fclose($handle);
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

if (file_exists($subscriptionsFile)) {
    $lines = file($subscriptionsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $subscription = json_decode($line, true);
        // エンドポイントと通知時刻の両方がある購読情報のみ処理
        if ($subscription && isset($subscription['endpoint']) && isset($subscription['notifyTime'])) {
            $subscriptions[] = $subscription;
        }
    }
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
        // 一時的に/testエンドポイントを使用（既に存在するエンドポイント）
        $pushServerUrl = 'https://putsushiyutong-zhi-yong.onrender.com/test';
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
        
        // 通知送信リクエスト
        $notifyUrl = 'https://putsushiyutong-zhi-yong.onrender.com/notify';
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
