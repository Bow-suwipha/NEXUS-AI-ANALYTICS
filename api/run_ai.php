<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
if (ob_get_level() > 0) ob_end_flush();

require_once 'db.php';

// ฟังก์ชันสำหรับส่งข้อมูลสตรีมมิ่ง
function streamData($type, $message) {
    echo "data: " . json_encode(['type' => $type, 'message' => $message]) . "\n\n";
    flush();
}

// -------------------------------------------------------------------
// 🛠️ [PHP Data Agent] ฟังก์ชันเครื่องมือสำหรับให้ AI เรียกใช้งาน
// -------------------------------------------------------------------
function tool_get_sales_data($product, $branch, $region) {
    global $pdo;
    
    // ดึงข้อมูล 5 อันดับสินค้าขายดี (เรียงตามยอดขาย)
    $stmt1 = $pdo->query("SELECT product_name, SUM(quantity) as total_qty, SUM(total_price) as total_rev FROM sales_data GROUP BY product_name ORDER BY total_rev DESC LIMIT 5");
    $top_products = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    // ดึงข้อมูลยอดขายแบ่งตามสาขา
    $stmt2 = $pdo->query("SELECT branch, SUM(total_price) as total_rev FROM sales_data GROUP BY branch ORDER BY total_rev DESC");
    $branch_sales = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // ดึงข้อมูลยอดขายรวมทั้งหมด
    $stmt3 = $pdo->query("SELECT SUM(total_price) as grand_total FROM sales_data");
    $grand_total = $stmt3->fetch(PDO::FETCH_ASSOC)['grand_total'];

    return [
        "top_5_products" => $top_products,
        "sales_by_branch" => $branch_sales,
        "grand_total_thb" => $grand_total
    ];
}

try {
    streamData('log', "<span class='text-emerald-400'>[System]</span> Initiating Autonomous AI Agent Workflow...");
    
    // 1. รับค่าเงื่อนไขที่ส่งมาจากหน้าเว็บ
    $input = json_decode(file_get_contents('php://input'), true);
    $date = !empty($input['date']) ? $input['date'] : 'เดือนปัจจุบัน';
    $product = !empty($input['product']) ? $input['product'] : 'สินค้าทั้งหมด';
    $branch = !empty($input['branch']) ? $input['branch'] : 'ทุกสาขา';
    $region = !empty($input['region']) ? $input['region'] : 'ทุกพื้นที่';

    // 2. ดึง API Key
    $stmt = $pdo->query("SELECT groq_api_key FROM system_settings WHERE user_id = 1 LIMIT 1");
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    $api_key = $setting ? $setting['groq_api_key'] : '';

    if (empty($api_key) || strpos($api_key, 'YOUR_GROQ') !== false) {
        streamData('log', "<span class='text-error'>[Error] Invalid or missing Groq API Key.</span>");
        exit;
    }

    sleep(1);
    streamData('log', "<span class='text-purple-400'>[Orchestrator]</span> Preparing tools and sending task to LLM...");

    // 3. กำหนด "เครื่องมือ" (Tools) ให้ Groq AI รู้จัก
    $tools = [
        [
            "type" => "function",
            "function" => [
                "name" => "get_sales_data",
                "description" => "ดึงข้อมูลยอดขาย สถิติ และปัญหาของสินค้าในสาขาหรือพื้นที่ที่ระบุ จากฐานข้อมูลของบริษัท",
                "parameters" => [
                    "type" => "object",
                    "properties" => [
                        "product" => ["type" => "string", "description" => "ชื่อสินค้าที่ต้องการค้นหา"],
                        "branch" => ["type" => "string", "description" => "สาขาเป้าหมาย"],
                        "region" => ["type" => "string", "description" => "ภูมิภาคเป้าหมาย"]
                    ],
                    "required" => ["product", "branch", "region"]
                ]
            ]
        ]
    ];

    // 4. สร้าง Message เริ่มต้น (ใส่ Prompt สั่งงาน)
    $system_prompt = "คุณคือ NEXUS AI หน้าที่ของคุณคือสรุปข้อมูลยอดขาย คุณต้องเรียกใช้เครื่องมือ 'get_sales_data' เสมอก่อนตอบเพื่อเอาข้อมูลจริง\n\nเมื่อได้ข้อมูลแล้ว ให้สรุปผลเป็นภาษาไทย โดยบังคับให้ตอบตามรูปแบบ (Format) นี้เป๊ะๆ เท่านั้น:\n\n**ภาพรวมยอดขาย**\n- รวมยอดขายทั้งหมด: (ใส่ตัวเลข) บาท\n\n**สินค้าขายดี 5 อันดับแรก**\n1. (ชื่อสินค้า) - ขายได้ (จำนวน) ชิ้น (ยอดขาย ... บาท)\n2. (ชื่อสินค้า) - ขายได้ (จำนวน) ชิ้น (ยอดขาย ... บาท)\n3. (ชื่อสินค้า) - ขายได้ (จำนวน) ชิ้น (ยอดขาย ... บาท)\n4. (ชื่อสินค้า) - ขายได้ (จำนวน) ชิ้น (ยอดขาย ... บาท)\n5. (ชื่อสินค้า) - ขายได้ (จำนวน) ชิ้น (ยอดขาย ... บาท)\n\n**ยอดขายรายสาขา**\n- (ชื่อสาขา): (ยอดขาย) บาท\n- (ชื่อสาขา): (ยอดขาย) บาท\n(ไล่ให้ครบทุกสาขา)";
    
    $messages = [
        ["role" => "system", "content" => $system_prompt],
        ["role" => "user", "content" => "ช่วยวิเคราะห์ยอดขายของ สินค้า: {$product}, สาขา: {$branch}, พื้นที่: {$region} ในช่วงเวลา: {$date} ให้หน่อย"]
    ];

    $payload = [
        "model" => "qwen/qwen3.8-27b", // โมเดลล่าสุดของ Groq ที่รองรับ Tool Calling ได้ดีเยี่ยม
        "messages" => $messages,
        "tools" => $tools,
        "tool_choice" => "auto",
        "temperature" => 0.5
    ];

    // --- [STEP 1: ส่งงานให้ Groq AI ตัดสินใจ] ---
    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $api_key, 'Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode != 200) {
        $errorData = json_decode($response, true);
        $errMsg = isset($errorData['error']['message']) ? $errorData['error']['message'] : "HTTP {$httpcode}";
        streamData('log', "<span class='text-error'>[API Error] {$errMsg}</span>");
        exit;
    }

    $responseData = json_decode($response, true);
    $responseMessage = $responseData['choices'][0]['message'];

    // --- [STEP 2: ตรวจสอบว่า AI ต้องการใช้ Tool หรือไม่] ---
    if (isset($responseMessage['tool_calls'])) {
        $toolCall = $responseMessage['tool_calls'][0];
        $funcName = $toolCall['function']['name'];
        $funcArgs = json_decode($toolCall['function']['arguments'], true);

        streamData('log', "<span class='text-blue-400'>[Analyst Agent]</span> Decided to use tool: <b>{$funcName}()</b>");
        sleep(1);
        
        // ให้ PHP (Data Agent) ดึงข้อมูลตามที่ AI สั่ง
        streamData('log', "<span class='text-purple-400'>[Data Agent]</span> Executing Database Query for <b>{$funcArgs['product']}</b> at {$funcArgs['branch']}...");
        $toolResult = tool_get_sales_data($funcArgs['product'], $funcArgs['branch'], $funcArgs['region']);
        
        sleep(1);
        streamData('log', "<span class='text-purple-400'>[Data Agent]</span> Data retrieved successfully. Returning to Analyst Agent...");

        // เพิ่มข้อความเดิมและผลลัพธ์จาก Tool กลับเข้าไปใน History
        $messages[] = $responseMessage; // บันทึกคำสั่งเรียก Tool
        $messages[] = [
            "tool_call_id" => $toolCall['id'],
            "role" => "tool",
            "name" => $funcName,
            "content" => json_encode($toolResult, JSON_UNESCAPED_UNICODE) // ส่งผลลัพธ์ DB กลับไปให้ AI
        ];

        // --- [STEP 3: ส่งข้อมูลกลับไปให้ Groq AI สรุปผลสุดท้าย] ---
        streamData('log', "<span class='text-blue-400'>[Analyst Agent]</span> Analyzing data and formulating Executive Summary...");
        
        $payload2 = [
            "model" => "qwen/qwen3.8-27b",
            "messages" => $messages,
            "temperature" => 0.5
        ];

        $ch2 = curl_init('https://api.groq.com/openai/v1/chat/completions');
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($payload2));
        curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $api_key, 'Content-Type: application/json']);
        curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
        
        $response2 = curl_exec($ch2);
        curl_close($ch2);

        $responseData2 = json_decode($response2, true);
        $finalReply = $responseData2['choices'][0]['message']['content'];
        
        streamData('log', "<span class='text-emerald-500 font-bold'>>> Autonomous Workflow Completed.</span>");
        streamData('result', $finalReply);

    } else {
        // กรณีที่ AI คิดว่าไม่ต้องใช้ Tool (ตอบได้เลย)
        streamData('log', "<span class='text-emerald-500 font-bold'>>> Task completed without tools.</span>");
        streamData('result', $responseMessage['content']);
    }

} catch (Exception $e) {
    streamData('log', "<span class='text-error'>[System Error] " . $e->getMessage() . "</span>");
}
?>
