<?php
// デバッグログ用関数
$debugLog = "subscribe_debug.log";
function logDebug($message) {
    global $debugLog;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($debugLog, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

// Web Push購読情報を端末ごとに管理して保存
$rawData = file_get_contents("php://input");
logDebug("受信データ: {$rawData}");

if ($rawData) {
    $data = json_decode($rawData, true);
    if (!$data) {
        logDebug("JSONデコードエラー: " . json_last_error_msg());
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "invalid json"]);
        exit;
    }
    
    logDebug("デコードされたデータ: " . print_r($data, true));
    
    // 購読情報ファイルの読み込み
    $subscriptionsFile = "subscriptions.txt";
    $subscriptions = [];
    
    if (file_exists($subscriptionsFile)) {
        $lines = file($subscriptionsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $subscription = json_decode($line, true);
            if ($subscription) {
                // 通知時刻のみのデータも含めて保持
                if (isset($subscription['endpoint']) || isset($subscription['notifyTime'])) {
                    $subscriptions[] = $subscription;
                }
            }
        }
    }
    
    // エンドポイントがある場合は端末ごとに管理
    if (isset($data['endpoint'])) {
        // 既存の購読情報から同じエンドポイントを持つエントリを探す
        $found = false;
        foreach ($subscriptions as &$subscription) {
            if (isset($subscription['endpoint']) && $subscription['endpoint'] === $data['endpoint']) {
                // 既存の購読情報を更新
                if (isset($data['notifyTime'])) {
                    $subscription['notifyTime'] = $data['notifyTime'];
                    logDebug("既存のエンドポイントの通知時刻を更新: {$data['notifyTime']}");
                }
                $found = true;
                break;
            }
        }
        
        // 新規購読情報の場合は追加
        if (!$found) {
            $subscriptions[] = $data;
            logDebug("新規購読情報を追加: " . json_encode($data));
        }
    } 
    // エンドポイントがない場合は通知時刻のみのデータとして追加
    elseif (isset($data['notifyTime'])) {
        // 通知時刻のみのデータを追加
        $subscriptions[] = ['notifyTime' => $data['notifyTime']];
        logDebug("通知時刻のみのデータを追加: {$data['notifyTime']}");
    } else {
        logDebug("エンドポイントも通知時刻もないデータを受信");
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "no endpoint or notifyTime"]);
        exit;
    }
    
    // 書き込み
    $fp = fopen($subscriptionsFile, 'w');
    if ($fp) {
        foreach ($subscriptions as $subscription) {
            fwrite($fp, json_encode($subscription) . "\n");
        }
        fclose($fp);
        
        logDebug("購読情報を正常に保存しました");
        http_response_code(201);
        echo json_encode(["status" => "ok"]);
    } else {
        logDebug("ファイル書き込みエラー");
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "failed to write file"]);
    }
} else {
    logDebug("受信データが空");
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "no data"]);
}
