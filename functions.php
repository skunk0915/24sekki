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
    // 月と日を別々に取得して数値比較を行う
    $month = (int)date('n');
    $day = (int)date('j');
    
    foreach ($kou_list as $kou) {
        // 開始日と終了日を分解
        list($start_month, $start_day) = explode('/', $kou['開始年月日']);
        list($end_month, $end_day) = explode('/', $kou['終了年月日']);
        
        // 数値として比較
        $start_month = (int)$start_month;
        $start_day = (int)$start_day;
        $end_month = (int)$end_month;
        $end_day = (int)$end_day;
        
        // 月が同じ場合は日だけで比較
        if ($month == $start_month && $month == $end_month) {
            if ($day >= $start_day && $day <= $end_day) {
                return $kou;
            }
        }
        // 開始月と終了月が異なる場合
        elseif ($start_month != $end_month) {
            // 現在の月が開始月で、日が開始日以上
            if ($month == $start_month && $day >= $start_day) {
                return $kou;
            }
            // 現在の月が終了月で、日が終了日以下
            elseif ($month == $end_month && $day <= $end_day) {
                return $kou;
            }
            // 現在の月が開始月と終了月の間
            elseif ($month > $start_month && $month < $end_month) {
                return $kou;
            }
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
    // 月と日を別々に取得して数値比較を行う
    $month = (int)date('n');
    $day = (int)date('j');
    
    foreach ($sekki_list as $sekki) {
        // 開始日と終了日を分解
        list($start_month, $start_day) = explode('/', $sekki['開始年月日']);
        list($end_month, $end_day) = explode('/', $sekki['終了年月日']);
        
        // 数値として比較
        $start_month = (int)$start_month;
        $start_day = (int)$start_day;
        $end_month = (int)$end_month;
        $end_day = (int)$end_day;
        
        // 月が同じ場合は日だけで比較
        if ($month == $start_month && $month == $end_month) {
            if ($day >= $start_day && $day <= $end_day) {
                return $sekki;
            }
        }
        // 開始月と終了月が異なる場合
        elseif ($start_month != $end_month) {
            // 現在の月が開始月で、日が開始日以上
            if ($month == $start_month && $day >= $start_day) {
                return $sekki;
            }
            // 現在の月が終了月で、日が終了日以下
            elseif ($month == $end_month && $day <= $end_day) {
                return $sekki;
            }
            // 現在の月が開始月と終了月の間
            elseif ($month > $start_month && $month < $end_month) {
                return $sekki;
            }
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
