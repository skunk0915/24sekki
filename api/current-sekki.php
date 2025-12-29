<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

require_once '../functions.php';

try {
    $sekki_list = load_sekki_data('../24sekki.csv');
    
    if (empty($sekki_list)) {
        throw new Exception('節気データの読み込みに失敗しました');
    }
    
    $today_sekki = get_today_sekki($sekki_list);
    
    if (!$today_sekki) {
        throw new Exception('現在の節気が見つかりません');
    }
    
    $kou_list = load_kou_data('../72kou.csv');
    $today_kou = get_today_kou($kou_list);

    $response = [
        'id' => array_search($today_sekki, $sekki_list),
        'name' => $today_sekki['節気名'],
        'kou_name' => $today_kou['和名'] ?? '',
        'start_date' => $today_sekki['開始年月日'],
        'end_date' => $today_sekki['終了年月日']
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}
?>
