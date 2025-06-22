<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

require_once '../functions.php';

try {
    $current_id = isset($_GET['current_id']) ? (int)$_GET['current_id'] : null;
    
    if ($current_id === null) {
        throw new Exception('current_idパラメータが必要です');
    }
    
    $sekki_list = load_sekki_data('../24sekki.csv');
    
    if (empty($sekki_list)) {
        throw new Exception('節気データの読み込みに失敗しました');
    }
    
    $next_sekki = get_next_sekki($sekki_list, $current_id);
    
    if (!$next_sekki) {
        throw new Exception('次の節気が見つかりません');
    }
    
    $response = [
        'id' => array_search($next_sekki, $sekki_list),
        'name' => $next_sekki['節気名'],
        'start_date' => $next_sekki['開始年月日'],
        'end_date' => $next_sekki['終了年月日']
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
