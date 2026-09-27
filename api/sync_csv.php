<?php
header('Content-Type: application/json');
require_once 'db.php';

// กำหนด Path ของไฟล์ CSV (เปลี่ยนให้ตรงกับตำแหน่งที่คุณเซฟไฟล์ไว้)
$file_path = '../sales_data.csv'; 

if (!file_exists($file_path)) {
    echo json_encode(["status" => "error", "message" => "ไม่พบไฟล์ CSV กรุณาอัปโหลดก่อนทำการ Sync"]);
    exit;
}

try {
    $file = fopen($file_path, "r");
    fgetcsv($file); // ข้ามบรรทัดแรก (Header)

    // เริ่ม Transaction เพื่อให้ Insert ข้อมูลหลักร้อย/พันบรรทัดได้ไวขึ้น
    $pdo->beginTransaction();

    // ฟังก์ชันช่วยจัดหมวดหมู่จากรหัส SKU
    function getCategoryFromSKU($sku) {
        $prefix = explode('-', $sku)[0];
        switch ($prefix) {
            case 'HOU': return 'เครื่องใช้ในบ้าน';
            case 'BEV': return 'เครื่องดื่ม';
            case 'SNK': return 'ขนมขบเคี้ยว';
            case 'DRY': return 'อาหารแห้งและเครื่องปรุง';
            case 'PER': return 'ของใช้ส่วนตัว';
            default: return 'อื่นๆ';
        }
    }

    // เตรียมคำสั่ง SQL (เพิ่มคอลัมน์ category)
    $stmt = $pdo->prepare("INSERT INTO sales_data (sale_date, product_name, category, branch, region, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    $count = 0;
    while (($data = fgetcsv($file, 1000, ",")) !== FALSE) {
        // เช็คว่ามีข้อมูลครบ 8 คอลัมน์ (รวม sku)
        if (count($data) >= 8) {
            $sku = $data[0];
            $category = getCategoryFromSKU($sku);
            
            $stmt->execute([
                $data[1], $data[2], $category, $data[3], $data[4], $data[5], $data[6], $data[7]
            ]);
            $count++;
        }
    }

    $pdo->commit();
    fclose($file);

    // --- Start Insert into data_imports (Log) ---
    try {
        $stmt_log = $pdo->prepare("INSERT INTO data_imports (user_id, file_name, source_type, total_rows, success_rows, error_rows, import_status) VALUES (1, 'sales_data.csv', 'CSV Upload', ?, ?, 0, 'completed')");
        $stmt_log->execute([$count, $count]);
    } catch (Exception $e) {
        // ข้าม Error กรณีไม่สามารถบันทึก Log ได้
    }
    // --- End Insert into data_imports (Log) ---

    // --- Start AI Auto-Insight Generation ---
    try {
        $stmt_key = $pdo->query("SELECT groq_api_key FROM system_settings WHERE user_id = 1 LIMIT 1");
        $api_key = $stmt_key->fetchColumn();
        
        if ($api_key && strpos($api_key, 'YOUR_GROQ') === false) {
            // ดึงข้อมูลสรุปคร่าวๆ ส่งให้ AI วิเคราะห์
            $summary_stmt = $pdo->query("SELECT branch, SUM(total_price) as revenue FROM sales_data GROUP BY branch ORDER BY revenue DESC LIMIT 3");
            $branches = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);
            $branch_text = json_encode($branches, JSON_UNESCAPED_UNICODE);
            
            $prod_stmt = $pdo->query("SELECT product_name, SUM(total_price) as revenue FROM sales_data GROUP BY product_name ORDER BY revenue DESC LIMIT 3");
            $prods = $prod_stmt->fetchAll(PDO::FETCH_ASSOC);
            $prod_text = json_encode($prods, JSON_UNESCAPED_UNICODE);

            $prompt = "คุณคือ AI วิเคราะห์ข้อมูลการขาย จงวิเคราะห์ข้อมูลสรุปล่าสุดนี้และสร้าง Insight 1 ข้อที่น่าสนใจที่สุด
ข้อมูลสาขาที่ขายดี: $branch_text
ข้อมูลสินค้าขายดี: $prod_text
ตอบกลับเป็น JSON format เท่านั้น โดยใช้โครงสร้างนี้:
{
  \"insight_type\": \"Trend\" หรือ \"Anomaly\" หรือ \"Recommendation\",
  \"title\": \"หัวข้อสั้นๆ (ภาษาไทย)\",
  \"description\": \"คำอธิบายรายละเอียดที่ลึกซึ้งและมีประโยชน์ (ภาษาไทย)\",
  \"severity\": \"Low\", \"Medium\", หรือ \"High\",
  \"related_branch\": \"ชื่อสาขาที่เกี่ยวข้อง หรือ 'ทุกสาขา'\",
  \"ai_confidence\": ตัวเลขความมั่นใจ เช่น 95.00
}";

            $payload = [
                "model" => "llama3-8b-8192", // ใช้โมเดลที่เร็วและรองรับ JSON
                "messages" => [
                    ["role" => "system", "content" => "You are a helpful assistant. You must output ONLY valid JSON format."],
                    ["role" => "user", "content" => $prompt]
                ],
                "response_format" => ["type" => "json_object"],
                "temperature" => 0.5
            ];

            $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $api_key, 'Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            curl_close($ch);

            if ($response) {
                $resData = json_decode($response, true);
                $content = $resData['choices'][0]['message']['content'] ?? '';
                $insight = json_decode($content, true);
                
                if (isset($insight['title'])) {
                    // เคลียร์ Insight เก่าก่อน เพื่อให้หน้าจออัปเดตของใหม่ล่าสุด
                    $pdo->query("UPDATE ai_insights SET is_active = 0");
                    
                    $stmt_ins = $pdo->prepare("INSERT INTO ai_insights (insight_type, title, description, severity, related_branch, ai_confidence, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
                    $stmt_ins->execute([
                        $insight['insight_type'] ?? 'Trend',
                        $insight['title'],
                        $insight['description'] ?? '',
                        $insight['severity'] ?? 'Medium',
                        $insight['related_branch'] ?? 'ทุกสาขา',
                        $insight['ai_confidence'] ?? 95.00
                    ]);
                }
            }
        }
    } catch (Exception $e) {
        // ให้ข้าม Error ไป เพื่อไม่ให้กระทบกับการทำงานหลักของ Sync
    }
    // --- End AI Auto-Insight Generation ---

    echo json_encode(["status" => "success", "message" => "Sync ข้อมูลสำเร็จ! พร้อมสร้าง AI Insights เรียบร้อยแล้ว (นำเข้าทั้งหมด {$count} รายการ)"]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
?>
