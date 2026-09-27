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

// ดึงข้อมูลยอดขายแยกตามสินค้า (Top 15) เพื่อประหยัด Token
$stmt = $pdo->query("SELECT product_name, SUM(quantity) as qty, SUM(total_price) as r FROM sales_data GROUP BY product_name ORDER BY r DESC LIMIT 15");
$products = $stmt->fetchAll();
$prod_summary = "";
foreach($products as $p) {
    $prod_summary .= $p['product_name'] . " (" . round($p['r'], 2) . " บาท / " . $p['qty'] . " ชิ้น), ";
}

// ดึงข้อมูลยอดขายสินค้า Top 3 แยกตามสาขา
$branch_prod_summary = "";
foreach($branches as $b) {
    $b_name = $b['branch'];
    $stmt_bp = $pdo->prepare("SELECT product_name, SUM(total_price) as r FROM sales_data WHERE branch = ? GROUP BY product_name ORDER BY r DESC LIMIT 3");
    $stmt_bp->execute([$b_name]);
    $bps = $stmt_bp->fetchAll();
    
    $items = [];
    foreach($bps as $bp) {
        $items[] = $bp['product_name'] . " (" . round($bp['r'], 2) . "บ.)";
    }
    $branch_prod_summary .= $b_name . ": " . implode(", ", $items) . " | ";
}

// ดึงข้อมูลยอดขายสินค้า Top 3 แยกตามหมวดหมู่
$cat_prod_summary = "";
$stmt_cats = $pdo->query("SELECT DISTINCT category FROM sales_data");
$categories = $stmt_cats->fetchAll(PDO::FETCH_COLUMN);
foreach($categories as $cat) {
    $stmt_cp = $pdo->prepare("SELECT product_name, SUM(total_price) as r FROM sales_data WHERE category = ? GROUP BY product_name ORDER BY r DESC LIMIT 3");
    $stmt_cp->execute([$cat]);
    $cps = $stmt_cp->fetchAll();
    
    $items = [];
    foreach($cps as $cp) {
        $items[] = $cp['product_name'] . " (" . round($cp['r'], 2) . "บ.)";
    }
    $cat_prod_summary .= $cat . ": " . implode(", ", $items) . " | ";
}

// ดึงข้อมูลยอดขายเดือนนี้
$currentMonthStr = date('Y-m');
$stmt_month = $pdo->prepare("SELECT SUM(total_price) as t FROM sales_data WHERE DATE_FORMAT(sale_date, '%Y-%m') = ?");
$stmt_month->execute([$currentMonthStr]);
$totalThisMonth = $stmt_month->fetch()['t'] ?? 0;

$stmt_month_branches = $pdo->prepare("SELECT branch, SUM(total_price) as r FROM sales_data WHERE DATE_FORMAT(sale_date, '%Y-%m') = ? GROUP BY branch");
$stmt_month_branches->execute([$currentMonthStr]);
$branchesThisMonth = $stmt_month_branches->fetchAll();
$summaryThisMonth = "";
foreach($branchesThisMonth as $b) {
    $summaryThisMonth .= "สาขา " . $b['branch'] . ": " . round($b['r'], 2) . " บาท, ";
}

$context = "ข้อมูลปัจจุบัน(วันที่ " . date('Y-m-d') . "): 
ยอดขายรวมตั้งแต่เริ่มต้นคือ $total บาท
ยอดขายสะสมแต่ละสาขาคือ: $summary
ยอดขายรวมเดือนนี้คือ $totalThisMonth บาท
ยอดขายเดือนนี้แต่ละสาขา: $summaryThisMonth
ยอดขายสินค้ายอดนิยม 15 อันดับแรก(รวมทุกสาขา): $prod_summary
สินค้าขายดีที่สุดในแต่ละสาขา(Top 3): $branch_prod_summary
สินค้าขายดีที่สุดในแต่ละหมวดหมู่(Top 3): $cat_prod_summary";

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
