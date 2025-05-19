<?php
/**
 * Google Spreadsheetからデータを取得する関数
 * 
 * @param string $spreadsheet_url スプレッドシートのURL
 * @return array 取得したデータの配列
 */
function get_spreadsheet_data($spreadsheet_url) {
    // スプレッドシートのIDを抽出
    $pattern = '/spreadsheets\/d\/([a-zA-Z0-9-_]+)/';
    preg_match($pattern, $spreadsheet_url, $matches);
    
    if (empty($matches[1])) {
        return array('error' => 'スプレッドシートのIDが見つかりません');
    }
    
    $spreadsheet_id = $matches[1];
    
    // CSVエクスポートURLを作成
    $csv_export_url = "https://docs.google.com/spreadsheets/d/{$spreadsheet_id}/export?format=csv";
    
    // CSVデータを取得
    $csv_data = file_get_contents($csv_export_url);
    
    if ($csv_data === false) {
        return array('error' => 'スプレッドシートからデータを取得できませんでした');
    }
    
    // CSVデータを配列に変換
    $lines = explode("\n", $csv_data);
    $data = array();
    
    // ヘッダー行を取得
    $header = str_getcsv(array_shift($lines));
    
    // 各行のデータを連想配列に変換
    foreach ($lines as $line) {
        if (empty($line)) continue;
        
        $row_data = str_getcsv($line);
        $row = array();
        
        foreach ($header as $i => $column_name) {
            $row[$column_name] = isset($row_data[$i]) ? $row_data[$i] : '';
        }
        
        $data[] = $row;
    }
    
    return $data;
}

/**
 * 候名からその候に関連するコラム解説を取得する関数
 * 
 * @param array $column_data コラムデータの配列
 * @param string $kou_name 候名
 * @return array 関連するコラム解説の配列
 */
function get_column_data_for_kou($column_data, $kou_name) {
    $related_columns = array();
    
    foreach ($column_data as $column) {
        if (strpos($column['時期'], $kou_name) !== false) {
            $related_columns[] = $column;
        }
    }
    
    return $related_columns;
}
?>
