<?php
/**
 * 共通関数ファイル
 * 七十二候・二十四節気アプリケーションで使用する共通関数を定義
 */

/**
 * 72kou.csvを読み込んで候のリストを取得
 * 
 * @param string $csv_file CSVファイルのパス
 * @return array 候のリスト
 */
function load_kou_data($csv_file) {
    $kou_list = array();
    if (($handle = fopen($csv_file, "r")) !== FALSE) {
        $header = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== FALSE) {
            $kou = array();
            foreach ($header as $i => $col) {
                $kou[$col] = isset($row[$i]) ? $row[$i] : '';
            }
            $kou_list[] = $kou;
        }
        fclose($handle);
    }
    return $kou_list;
}

/**
 * 24sekki.csvを読み込んで節気のリストを取得
 * 
 * @param string $csv_file CSVファイルのパス
 * @return array 節気のリスト
 */
function load_sekki_data($csv_file) {
    $sekki_list = array();
    if (($handle = fopen($csv_file, "r")) !== FALSE) {
        $header = fgetcsv($handle);
        while (($row = fgetcsv($handle)) !== FALSE) {
            $sekki = array();
            foreach ($header as $i => $col) {
                $sekki[$col] = isset($row[$i]) ? $row[$i] : '';
            }
            $sekki_list[] = $sekki;
        }
        fclose($handle);
    }
    return $sekki_list;
}

/**
 * 今日の日付に該当する候を取得
 * 
 * @param array $kou_list 候のリスト
 * @return array 今日の候
 */
function get_today_kou($kou_list) {
    $month = (int)date('n');
    $day = (int)date('j');
    
    foreach ($kou_list as $kou) {
        if (check_date_in_range($month, $day, $kou['開始年月日'], $kou['終了年月日'])) {
            return $kou;
        }
    }
    
    // デバッグ用に現在の日付を表示
    error_log("現在の日付: {$month}/{$day}");
    
    // 範囲外の場合は最初の候を返す
    return $kou_list[0];
}

/**
 * 今日の日付に該当する節気を取得
 * 
 * @param array $sekki_list 節気のリスト
 * @return array 今日の節気
 */
function get_today_sekki($sekki_list) {
    $month = (int)date('n');
    $day = (int)date('j');
    
    foreach ($sekki_list as $sekki) {
        if (check_date_in_range($month, $day, $sekki['開始年月日'], $sekki['終了年月日'])) {
            return $sekki;
        }
    }
    
    // 範囲外の場合は最初の節気を返す
    return $sekki_list[0];
}

/**
 * 日付が範囲内かどうかを判定する関数
 * 
 * @param int $month 現在の月
 * @param int $day 現在の日
 * @param string $start_date 開始日（'月/日'形式）
 * @param string $end_date 終了日（'月/日'形式）
 * @return bool 範囲内ならtrue
 */
function check_date_in_range($month, $day, $start_date, $end_date) {
    // 開始日と終了日を分解
    list($start_month, $start_day) = explode('/', $start_date);
    list($end_month, $end_day) = explode('/', $end_date);
    
    // 数値として比較
    $start_month = (int)$start_month;
    $start_day = (int)$start_day;
    $end_month = (int)$end_month;
    $end_day = (int)$end_day;
    
    // 月が同じ場合は日だけで比較
    if ($month == $start_month && $month == $end_month) {
        return ($day >= $start_day && $day <= $end_day);
    }
    // 開始月と終了月が異なる場合
    elseif ($start_month != $end_month) {
        // 現在の月が開始月で、日が開始日以上
        if ($month == $start_month && $day >= $start_day) {
            return true;
        }
        // 現在の月が終了月で、日が終了日以下
        elseif ($month == $end_month && $day <= $end_day) {
            return true;
        }
        // 現在の月が開始月と終了月の間
        elseif ($month > $start_month && $month < $end_month) {
            return true;
        }
    }
    
    return false;
}

/**
 * 指定された候の前の候を取得
 * 
 * @param array $kou_list 候のリスト
 * @param int $current_idx 現在の候のインデックス
 * @return array|null 前の候、なければnull
 */
function get_prev_kou($kou_list, $current_idx) {
    if ($current_idx > 0) {
        return $kou_list[$current_idx - 1];
    } elseif (count($kou_list) > 0) {
        // 最初の候の場合は最後の候を返す（循環）
        return $kou_list[count($kou_list) - 1];
    }
    return null;
}

/**
 * 指定された候の次の候を取得
 * 
 * @param array $kou_list 候のリスト
 * @param int $current_idx 現在の候のインデックス
 * @return array|null 次の候、なければnull
 */
function get_next_kou($kou_list, $current_idx) {
    if ($current_idx < count($kou_list) - 1) {
        return $kou_list[$current_idx + 1];
    } elseif (count($kou_list) > 0) {
        // 最後の候の場合は最初の候を返す（循環）
        return $kou_list[0];
    }
    return null;
}

/**
 * 指定された節気の前の節気を取得
 * 
 * @param array $sekki_list 節気のリスト
 * @param int $current_idx 現在の節気のインデックス
 * @return array|null 前の節気、なければnull
 */
function get_prev_sekki($sekki_list, $current_idx) {
    if ($current_idx > 0) {
        return $sekki_list[$current_idx - 1];
    } elseif (count($sekki_list) > 0) {
        // 最初の節気の場合は最後の節気を返す（循環）
        return $sekki_list[count($sekki_list) - 1];
    }
    return null;
}

/**
 * 指定された節気の次の節気を取得
 * 
 * @param array $sekki_list 節気のリスト
 * @param int $current_idx 現在の節気のインデックス
 * @return array|null 次の節気、なければnull
 */
function get_next_sekki($sekki_list, $current_idx) {
    if ($current_idx < count($sekki_list) - 1) {
        return $sekki_list[$current_idx + 1];
    } elseif (count($sekki_list) > 0) {
        // 最後の節気の場合は最初の節気を返す（循環）
        return $sekki_list[0];
    }
    return null;
}

/**
 * 七十二候と二十四節気の対応関係を定義
 * 各七十二候がどの二十四節気に属するかのマッピング
 * 
 * @param int $kou_idx 候のインデックス
 * @param array $kou_list 候のリスト
 * @param array $sekki_list 節気のリスト
 * @return array 対応する節気
 */
function get_sekki_for_kou($kou_idx, $kou_list, $sekki_list) {
    // 七十二候は72個、二十四節気は24個なので、3つの候が1つの節気に対応
    $sekki_idx = floor($kou_idx / 3);
    // 循環するように調整（72候の場合は24節気の範囲内に収める）
    if ($sekki_idx >= count($sekki_list)) {
        $sekki_idx = $sekki_idx % count($sekki_list);
    }
    return $sekki_list[$sekki_idx];
}

/**
 * 二十四節気に含まれる七十二候を取得する関数
 * 
 * @param int $sekki_idx 節気のインデックス
 * @param array $kou_list 候のリスト
 * @param array $sekki_list 節気のリスト
 * @return array 関連する候の配列
 */
function get_kou_for_sekki($sekki_idx, $kou_list, $sekki_list) {
    $related_kou = [];
    
    // 一つの節気には3つの候が含まれる
    $start_idx = $sekki_idx * 3;
    
    // 候の数が72より少ない場合は循環するように調整
    $total_kou = count($kou_list);
    
    // 3つの候を取得
    for ($i = 0; $i < 3; $i++) {
        $kou_idx = $start_idx + $i;
        
        // 候のインデックスが範囲内か確認、範囲外なら循環させる
        if ($kou_idx >= $total_kou) {
            $kou_idx = $kou_idx % $total_kou;
        }
        
        // 候が存在する場合のみ追加
        if (isset($kou_list[$kou_idx])) {
            $related_kou[] = [
                'idx' => $kou_idx,
                'data' => $kou_list[$kou_idx]
            ];
        }
    }
    
    return $related_kou;
}
