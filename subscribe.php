<?php
// Web Push購読情報を1行ずつ保存
$rawData = file_get_contents("php://input");
if ($rawData) {
    file_put_contents("subscriptions.txt", $rawData . "\n", FILE_APPEND | LOCK_EX);
    http_response_code(201);
    echo json_encode(["status" => "ok"]);
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "no data"]);
}
