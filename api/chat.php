<?php
require 'db.php';

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(["reply" => "Invalid JSON payload"]);
    exit;
}

$stmt = $pdo->query("SELECT groq_api_key FROM system_settings LIMIT 1");
$setting = $stmt->fetch();

if (!$setting || empty($setting['groq_api_key'])) {
    echo json_encode(["reply" => "กรุณาตั้งค่า Groq API Key ในหน้า Settings ก่อนใช้งาน AI Chatbot ครับ"]);
    exit;
}
$apiKey = $setting['groq_api_key'];

// ดึงข้อมูลบริบท
$stmt = $pdo->query("SELECT SUM(total_price) as t FROM sales_data");
$total = $stmt->fetch()['t'] ?? 0;

$stmt = $pdo->query("SELECT branch, SUM(total_price) as r FROM sales_data GROUP BY branch");
$branches = $stmt->fetchAll();
$summary = "";
foreach($branches as $b) {
    $summary .= "สาขา " . $b['branch'] . ": " . round($b['r'], 2) . " บาท, ";
}

$context = "ข้อมูลปัจจุบัน: ยอดขายรวมของบริษัทคือ $total บาท\nยอดขายแต่ละสาขาคือ: $summary\nสินค้าหลักคือ น้ำดื่มบริสุทธิ์ 600ml, ชาเขียว รสต้นตำรับ 500ml";

$messages = [
    [
        "role" => "system",
        "content" => "คุณคือ NEXUS AI ผู้เชี่ยวชาญด้านยอดขาย ตอบสั้นๆ กระชับ เข้าใจง่าย นี่คือข้อมูลบริษัท: " . $context
    ]
];

if (isset($input['history']) && is_array($input['history'])) {
    foreach ($input['history'] as $h) {
        $messages[] = ["role" => $h['role'], "content" => $h['content']];
    }
}

$messages[] = ["role" => "user", "content" => $input['message']];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.groq.com/openai/v1/chat/completions');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $apiKey,
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$payload = [
    "model" => "qwen/qwen3.8-27b",
    "messages" => $messages,
    "temperature" => 0.5
];
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
if (curl_errno($ch)) {
    echo json_encode(["reply" => "เกิดข้อผิดพลาด: " . curl_error($ch)]);
} else {
    $resObj = json_decode($response, true);
    if (isset($resObj['error'])) {
        echo json_encode(["reply" => "Error จาก Groq API: " . $resObj['error']['message']]);
    } else {
        $reply = $resObj['choices'][0]['message']['content'] ?? "ไม่มีการตอบกลับ";
        echo json_encode(["reply" => $reply]);
    }
}
curl_close($ch);
?>
