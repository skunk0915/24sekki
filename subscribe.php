<?php
$sftp_host = 'mizy.sakura.ne.jp';
$sftp_username = 'mizy';
$sftp_password = 'CQgg_WkPXus2';
$remote_path = '/home/mizy/www/72kou/subscriptions.txt';

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
    // エンドポイントがない場合は通知時刻のみのデータとして処理
    elseif (isset($data['notifyTime'])) {
        // ブラウザIDがあれば、それを使用して同じブラウザの購読情報を探す
        $browserId = $data['browserId'] ?? null;
        $updated = false;
        
        if ($browserId) {
            foreach ($subscriptions as &$subscription) {
                if (isset($subscription['browserId']) && $subscription['browserId'] === $browserId) {
                    // 同じブラウザIDの購読情報を更新
                    $subscription['notifyTime'] = $data['notifyTime'];
                    logDebug("ブラウザID {$browserId} の通知時刻を更新: {$data['notifyTime']}");
                    $updated = true;
                    break;
                }
            }
        }
        
        // 最新のエンドポイント情報を探して通知時刻を更新
        if (!$updated) {
            $latestEndpoint = null;
            $latestIndex = -1;
            
            // 最新のエンドポイント情報を持つ購読情報を探す
            for ($i = count($subscriptions) - 1; $i >= 0; $i--) {
                if (isset($subscriptions[$i]['endpoint'])) {
                    $latestEndpoint = $subscriptions[$i];
                    $latestIndex = $i;
                    break;
                }
            }
            
            if ($latestEndpoint) {
                // 最新のエンドポイント情報に通知時刻を追加
                $subscriptions[$latestIndex]['notifyTime'] = $data['notifyTime'];
                if (isset($data['browserId'])) {
                    $subscriptions[$latestIndex]['browserId'] = $data['browserId'];
                }
                logDebug("最新のエンドポイント情報に通知時刻を追加: {$data['notifyTime']}");
                $updated = true;
            }
        }
        
        // どのエンドポイントとも紐づけられなかった場合は、ダミーのエンドポイントを作成
        if (!$updated) {
            // ダミーのエンドポイント情報を作成して通知時刻を紐づける
            $dummySubscription = [
                'endpoint' => 'dummy_endpoint_' . time(),
                'notifyTime' => $data['notifyTime'],
                'isDummy' => true
            ];
            
            if (isset($data['browserId'])) {
                $dummySubscription['browserId'] = $data['browserId'];
            }
            
            $subscriptions[] = $dummySubscription;
            logDebug("ダミーのエンドポイントを作成して通知時刻を紐づけ: {$data['notifyTime']}");
        }
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
        
        $remoteUploadUrl = 'https://mizy.sakura.ne.jp/72kou/upload_subscriptions.php';
        $uploadData = json_encode($subscriptions);
        
        $ch = curl_init($remoteUploadUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $uploadData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $uploadResult = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            logDebug("リモートサーバーへのHTTPアップロード成功");
        } else {
            logDebug("リモートサーバーへのHTTPアップロード失敗: HTTP $httpCode");
        }
        
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
