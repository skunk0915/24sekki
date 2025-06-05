<?php
// Web Push購読情報を端末ごとに管理して保存
$rawData = file_get_contents("php://input");

if ($rawData) {
    $data = json_decode($rawData, true);
    
    // エンドポイントが存在するか確認
    if (!isset($data['endpoint'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "endpoint not found"]);
        exit;
    }
    
    // 購読情報ファイルの読み込み
    $subscriptionsFile = "subscriptions.txt";
    $subscriptions = [];
    
    if (file_exists($subscriptionsFile)) {
        $lines = file($subscriptionsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $subscription = json_decode($line, true);
            if ($subscription && isset($subscription['endpoint'])) {
                $subscriptions[] = $subscription;
            }
        }
    }
    
    // 既存の購読情報から同じエンドポイントを持つエントリを探す
    $found = false;
    foreach ($subscriptions as &$subscription) {
        if ($subscription['endpoint'] === $data['endpoint']) {
            // 既存の購読情報を更新
            if (isset($data['notifyTime'])) {
                $subscription['notifyTime'] = $data['notifyTime'];
            }
            $found = true;
            break;
        }
    }
    
    // 新規購読情報の場合は追加
    if (!$found) {
        $subscriptions[] = $data;
    }
    
    // 書き込み
    $fp = fopen($subscriptionsFile, 'w');
    if ($fp) {
        foreach ($subscriptions as $subscription) {
            fwrite($fp, json_encode($subscription) . "\n");
        }
        fclose($fp);
        
        http_response_code(201);
        echo json_encode(["status" => "ok"]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "failed to write file"]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "no data"]);
}
