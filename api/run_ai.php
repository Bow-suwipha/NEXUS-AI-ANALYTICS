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
    
    $result = [];

    $hasProduct = !empty($product) && $product !== 'สินค้าทั้งหมด' && $product !== '';
    $hasBranch = !empty($branch) && $branch !== 'ทุกสาขา' && $branch !== 'all';
    $hasRegion = !empty($region) && $region !== 'ทุกพื้นที่' && $region !== 'all';

    // 1. Case Product (ค้นหาสินค้า)
    if ($hasProduct) {
        $stmt = $pdo->prepare("SELECT branch, SUM(quantity) as qty, SUM(total_price) as total FROM sales_data WHERE product_name LIKE ? GROUP BY branch ORDER BY total DESC");
        $stmt->execute(["%$product%"]);
        $result['product_sales_by_branch'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt2 = $pdo->prepare("SELECT SUM(quantity) as total_qty, SUM(total_price) as grand_total FROM sales_data WHERE product_name LIKE ?");
        $stmt2->execute(["%$product%"]);
        $result['product_grand_total'] = $stmt2->fetch(PDO::FETCH_ASSOC);
    }
    // 2. Case Branch (เลือกสาขา)
    else if ($hasBranch) {
        $stmt = $pdo->prepare("SELECT SUM(total_price) as branch_total FROM sales_data WHERE branch = ?");
        $stmt->execute([$branch]);
        $result['branch_total'] = $stmt->fetch(PDO::FETCH_ASSOC)['branch_total'] ?? 0;

        $stmt2 = $pdo->prepare("SELECT product_name, SUM(quantity) as qty, SUM(total_price) as total FROM sales_data WHERE branch = ? GROUP BY product_name ORDER BY total DESC LIMIT 3");
        $stmt2->execute([$branch]);
        $result['branch_top_products'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt3 = $pdo->prepare("SELECT product_name, SUM(quantity) as qty, SUM(total_price) as total FROM sales_data WHERE branch = ? GROUP BY product_name ORDER BY total ASC LIMIT 3");
        $stmt3->execute([$branch]);
        $result['branch_worst_products'] = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    }
    // 3. Case Region (เลือกพื้นที่)
    else if ($hasRegion) {
        $stmt = $pdo->prepare("SELECT branch, SUM(total_price) as branch_total FROM sales_data WHERE region = ? GROUP BY branch ORDER BY branch_total DESC");
        $stmt->execute([$region]);
        $result['region_branches_sales'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt2 = $pdo->prepare("SELECT SUM(total_price) as region_total FROM sales_data WHERE region = ?");
        $stmt2->execute([$region]);
        $result['region_total'] = $stmt2->fetch(PDO::FETCH_ASSOC)['region_total'] ?? 0;

        $stmt3 = $pdo->prepare("SELECT product_name, SUM(quantity) as qty, SUM(total_price) as total FROM sales_data WHERE region = ? GROUP BY product_name ORDER BY total DESC LIMIT 3");
        $stmt3->execute([$region]);
        $result['region_top_products'] = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    }
    // Default: Overall
    else {
        $stmt1 = $pdo->query("SELECT product_name, SUM(quantity) as total_qty, SUM(total_price) as total_rev FROM sales_data GROUP BY product_name ORDER BY total_rev DESC LIMIT 5");
        $result['top_5_products'] = $stmt1->fetchAll(PDO::FETCH_ASSOC);

        $stmt2 = $pdo->query("SELECT branch, SUM(total_price) as total_rev FROM sales_data GROUP BY branch ORDER BY total_rev DESC");
        $result['sales_by_branch'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $stmt3 = $pdo->query("SELECT SUM(total_price) as grand_total FROM sales_data");
        $result['grand_total_thb'] = $stmt3->fetch(PDO::FETCH_ASSOC)['grand_total'] ?? 0;
    }

    return $result;
}

try {
    streamData('log', "<span class='text-emerald-400'>[System]</span> Initiating Autonomous AI Agent Workflow...");
    
    // 1. รับค่าเงื่อนไขที่ส่งมาจากหน้าเว็บ
    $input = json_decode(file_get_contents('php://input'), true);
    $date = !empty($input['date']) ? $input['date'] : 'เดือนปัจจุบัน';
    $product = !empty($input['product']) ? $input['product'] : '';
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

    // 4. สร้าง Message เริ่มต้น และจัด Prompt แบบไดนามิกตามเงื่อนไขที่ค้นหา
    $hasProduct = !empty($product) && $product !== 'สินค้าทั้งหมด' && $product !== '';
    $hasBranch = !empty($branch) && $branch !== 'ทุกสาขา' && $branch !== 'all';
    $hasRegion = !empty($region) && $region !== 'ทุกพื้นที่' && $region !== 'all';

    $system_prompt = "คุณคือ NEXUS AI หน้าที่ของคุณคือวิเคราะห์ยอดขาย คุณต้องเรียกใช้เครื่องมือ 'get_sales_data' เสมอเพื่อเอาข้อมูลจริงมาตอบ\n\nเมื่อได้ข้อมูลแล้ว ให้สรุปผลเป็นภาษาไทย โดยจัดรูปแบบหัวข้อให้ตรงกับที่ระบุด้านล่างอย่างเคร่งครัด (แสดงตัวเลขจำนวนเงิน บาท ให้มีคอมม่า และไม่ต้องมีทศนิยม):\n\n";

    if ($hasProduct) {
        $system_prompt .= "**ผลการค้นหาสินค้า: {$product}**\n";
        $system_prompt .= "- ยอดขายรวมของสินค้านี้: (ยอดเงิน) บาท ((จำนวน) ชิ้น)\n";
        $system_prompt .= "\n**ยอดขายแต่ละสาขา**\n";
        $system_prompt .= "- (สาขา): (ยอดเงิน) บาท ((จำนวน) ชิ้น)\n";
        $system_prompt .= "(ไล่ให้ครบทุกสาขาตามข้อมูลที่มี เรียงจากมากไปน้อย)";
    } elseif ($hasBranch) {
        $system_prompt .= "**ผลการวิเคราะห์สาขา: {$branch}**\n";
        $system_prompt .= "- ยอดขายรวมของสาขานี้: (ยอดเงิน) บาท\n";
        $system_prompt .= "\n**สินค้าที่ขายดีที่สุด (Top 3)**\n";
        $system_prompt .= "1. (ชื่อสินค้า) - (ยอดเงิน) บาท ((จำนวน) ชิ้น)\n";
        $system_prompt .= "2. (ชื่อสินค้า) - (ยอดเงิน) บาท ((จำนวน) ชิ้น)\n";
        $system_prompt .= "3. (ชื่อสินค้า) - (ยอดเงิน) บาท ((จำนวน) ชิ้น)\n";
        $system_prompt .= "\n**สินค้าที่ขายได้น้อยที่สุด (Bottom 3)**\n";
        $system_prompt .= "1. (ชื่อสินค้า) - (ยอดเงิน) บาท ((จำนวน) ชิ้น)\n";
        $system_prompt .= "2. (ชื่อสินค้า) - (ยอดเงิน) บาท ((จำนวน) ชิ้น)\n";
        $system_prompt .= "3. (ชื่อสินค้า) - (ยอดเงิน) บาท ((จำนวน) ชิ้น)\n";
    } elseif ($hasRegion) {
        $system_prompt .= "**ผลการวิเคราะห์พื้นที่: {$region}**\n";
        $system_prompt .= "- ยอดขายรวมของพื้นที่นี้: (ยอดเงิน) บาท\n";
        $system_prompt .= "\n**สาขาในพื้นที่และยอดขาย**\n";
        $system_prompt .= "- (สาขาขายดีสุด): (ยอดเงิน) บาท\n";
        $system_prompt .= "- (สาขาอื่นๆ...): (ยอดเงิน) บาท\n";
        $system_prompt .= "- (สาขาขายได้น้อยสุด): (ยอดเงิน) บาท\n";
        $system_prompt .= "\n**สินค้าขายดีประจำพื้นที่ (Top 3)**\n";
        $system_prompt .= "1. (ชื่อสินค้า) - (ยอดเงิน) บาท ((จำนวน) ชิ้น)\n";
        $system_prompt .= "2. (ชื่อสินค้า) - (ยอดเงิน) บาท ((จำนวน) ชิ้น)\n";
        $system_prompt .= "3. (ชื่อสินค้า) - (ยอดเงิน) บาท ((จำนวน) ชิ้น)\n";
    } else {
        $system_prompt .= "**ภาพรวมยอดขาย**\n- รวมยอดขายทั้งหมด: (ยอดเงิน) บาท\n\n**สินค้าขายดี 5 อันดับแรก**\n1. (ชื่อสินค้า) - ขายได้ (จำนวน) ชิ้น ((ยอดเงิน) บาท)\n\n**ยอดขายรายสาขา**\n- (ชื่อสาขา): (ยอดเงิน) บาท\n";
    }
    
    $messages = [
        ["role" => "system", "content" => $system_prompt],
        ["role" => "user", "content" => "ช่วยวิเคราะห์ยอดขายของ สินค้า: {$product}, สาขา: {$branch}, พื้นที่: {$region} ในช่วงเวลา: {$date} ให้หน่อย"]
    ];

    $payload = [
        "model" => "llama3-8b-8192", // Default to groq model
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
        
        streamData('log', "<span class='text-purple-400'>[Data Agent]</span> Executing Database Query...");
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
            "model" => "llama3-8b-8192", // match payload model
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
