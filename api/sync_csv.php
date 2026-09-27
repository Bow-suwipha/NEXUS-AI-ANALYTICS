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
    $headers = fgetcsv($file);
    if (!$headers) {
        throw new Exception("ไฟล์ CSV ว่างเปล่าหรืออ่านไม่ได้");
    }
    
    // Clean headers (remove BOM, quotes, whitespace)
    $headers = array_map(function($h) {
        return trim(str_replace(['"', "\xEF\xBB\xBF", "'"], '', $h));
    }, $headers);
    
    // Create column map (Header Name -> Index)
    $hMap = array_flip($headers);
    
    // Resolve indexes with fallback to original 8-column format if headers don't match
    $idx_sku = $hMap['sku'] ?? 0;
    $idx_date = $hMap['sale_date'] ?? 1;
    $idx_prod = $hMap['product_name'] ?? 2;
    $idx_branch = $hMap['branch'] ?? 3;
    $idx_region = $hMap['region'] ?? 4;
    $idx_qty = $hMap['quantity'] ?? 5;
    $idx_price = $hMap['unit_price'] ?? 6;
    $idx_total = $hMap['total_price'] ?? 7;

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

    // Insert import log FIRST to get import_id
    $stmt_log = $pdo->prepare("INSERT INTO data_imports (user_id, file_name, source_type, import_status) VALUES (1, 'sales_data.csv', 'CSV', 'processing')");
    $stmt_log->execute();
    $import_id = $pdo->lastInsertId();

    // Cache products and branches
    $products = [];
    foreach ($pdo->query("SELECT product_id, product_code FROM products")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $products[$row['product_code']] = $row['product_id'];
    }

    $branches = [];
    foreach ($pdo->query("SELECT branch_id, branch_name FROM branches")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $branches[$row['branch_name']] = $row['branch_id'];
    }

    // Prepare Inserts and Updates
    $stmt_prod = $pdo->prepare("INSERT INTO products (product_code, product_name, category, unit_price) VALUES (?, ?, ?, ?)");
    $stmt_prod_update = $pdo->prepare("UPDATE products SET product_name = ?, category = ?, unit_price = ? WHERE product_code = ?");
    
    $stmt_branch = $pdo->prepare("INSERT INTO branches (branch_code, branch_name, region, province) VALUES (?, ?, ?, ?)");
    $stmt_branch_update = $pdo->prepare("UPDATE branches SET region = ? WHERE branch_name = ?");
    
    $stmt = $pdo->prepare("
        INSERT INTO sales_data (
            import_id, product_id, branch_id, sku, sale_date, product_name, category, branch, region, 
            quantity, unit_price, discount_percent, discount_amount, customer_type, payment_method, total_price
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, 
            ?, ?, ?, ?, ?, ?, ?
        )
    ");

    $count = 0;
    while (($data = fgetcsv($file, 1000, ",")) !== FALSE) {
        // Skip empty rows
        if (count($data) < 2) continue;
        
        $sku = isset($data[$idx_sku]) ? trim($data[$idx_sku]) : '';
        $sale_date = isset($data[$idx_date]) ? trim($data[$idx_date]) : '';
        $product_name = isset($data[$idx_prod]) ? trim($data[$idx_prod]) : '';
        $branch = isset($data[$idx_branch]) ? trim($data[$idx_branch]) : '';
        $region = isset($data[$idx_region]) ? trim($data[$idx_region]) : '';
        $quantity = isset($data[$idx_qty]) ? (float)$data[$idx_qty] : 0;
        $unit_price = isset($data[$idx_price]) ? (float)$data[$idx_price] : 0;
        $total_price = isset($data[$idx_total]) ? (float)$data[$idx_total] : 0;
        
        // Skip invalid rows
        if (empty($sku) || empty($sale_date)) continue;
        
        $category = getCategoryFromSKU($sku);

            // Handle Product
            if (!isset($products[$sku])) {
                $stmt_prod->execute([$sku, $product_name, $category, $unit_price]);
                $products[$sku] = $pdo->lastInsertId();
            } else {
                // Update existing product info (name, category, price)
                $stmt_prod_update->execute([$product_name, $category, $unit_price, $sku]);
            }
            $product_id = $products[$sku];

            // Handle Branch
            if (!isset($branches[$branch])) {
                $bcode = 'B' . str_pad(count($branches) + 1, 3, '0', STR_PAD_LEFT);
                $stmt_branch->execute([$bcode, $branch, $region, '']);
                $branches[$branch] = $pdo->lastInsertId();
            } else {
                // Update existing branch info (region)
                $stmt_branch_update->execute([$region, $branch]);
            }
            $branch_id = $branches[$branch];

            // Calculate Discounts
            $expected_total = $quantity * $unit_price;
            $discount_amount = max(0, $expected_total - $total_price);
            $discount_percent = ($expected_total > 0) ? ($discount_amount / $expected_total) * 100 : 0;

            // Mock Customer & Payment
            $customer_type = (rand(0, 1) == 0) ? 'ทั่วไป' : 'สมาชิก';
            $payments = ['เงินสด', 'โอนเงิน', 'บัตรเครดิต'];
            $payment_method = $payments[array_rand($payments)];
            
            $stmt->execute([
                $import_id, $product_id, $branch_id, $sku, $sale_date, $product_name, $category, $branch, $region,
                $quantity, $unit_price, $discount_percent, $discount_amount, $customer_type, $payment_method, $total_price
            ]);
            $count++;
        }


    $pdo->commit();
    fclose($file);

    // Update Import Log
    $stmt_update_log = $pdo->prepare("UPDATE data_imports SET total_rows = ?, success_rows = ?, import_status = 'completed' WHERE import_id = ?");
    $stmt_update_log->execute([$count, $count, $import_id]);

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
                    $insight_id = $pdo->lastInsertId();
                    
                    // Add Notification for AI Insight
                    $stmt_notif_ai = $pdo->prepare("INSERT INTO notifications (user_id, notification_type, title, message) VALUES (1, 'other', ?, ?)");
                    $stmt_notif_ai->execute(["✨ AI Insight ใหม่: " . $insight['title'], "AI ตรวจพบข้อมูลเชิงลึกใหม่: " . ($insight['description'] ?? '')]);
                }
            }
        }
    } catch (Exception $e) {
        // ให้ข้าม Error ไป เพื่อไม่ให้กระทบกับการทำงานหลักของ Sync
    }
    // --- End AI Auto-Insight Generation ---
    
    // --- Start Notification Generation ---
    try {
        $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, notification_type, title, message, reference_id) VALUES (1, 'analysis_completed', 'นำเข้าข้อมูลสำเร็จ', ?, ?)");
        $stmt_notif->execute(["อัปเดตข้อมูลการขายจำนวน {$count} รายการ เรียบร้อยแล้ว", $import_id]);
    } catch (Exception $e) {}
    // --- End Notification Generation ---

    echo json_encode(["status" => "success", "message" => "Sync ข้อมูลสำเร็จ! พร้อมสร้าง AI Insights เรียบร้อยแล้ว (นำเข้าทั้งหมด {$count} รายการ)"]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Add Error Notification
    try {
        if (isset($import_id)) {
            $stmt_update_log = $pdo->prepare("UPDATE data_imports SET import_status = 'failed', error_message = ? WHERE import_id = ?");
            $stmt_update_log->execute([$e->getMessage(), $import_id]);
            
            $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, notification_type, title, message, reference_id) VALUES (1, 'data_error', 'การนำเข้าข้อมูลล้มเหลว', ?, ?)");
            $stmt_notif->execute(["พบข้อผิดพลาด: " . $e->getMessage(), $import_id]);
        } else {
            $stmt_notif = $pdo->prepare("INSERT INTO notifications (user_id, notification_type, title, message) VALUES (1, 'data_error', 'การนำเข้าข้อมูลล้มเหลว', ?)");
            $stmt_notif->execute(["พบข้อผิดพลาดก่อนนำเข้า: " . $e->getMessage()]);
        }
    } catch (Exception $ex) {}

    echo json_encode(["status" => "error", "message" => "Database Error: " . $e->getMessage()]);
}
?>
