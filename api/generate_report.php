<?php
require 'db.php';

header('Content-Type: application/json; charset=utf-8');

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

if (!isset($input['date_val']) || empty($input['date_val'])) {
    // Fallback for backwards compatibility
    if (isset($input['month_val']) && !empty($input['month_val'])) {
        $input['date_val'] = $input['month_val'];
        $input['type'] = 'Monthly';
    } else {
        echo json_encode(["status" => "error", "message" => "Missing date_val"]);
        exit;
    }
}

$dateVal = $input['date_val'];
$type = $input['type'] ?? 'Monthly';
$reportName = "";
$periodStart = "";
$periodEnd = "";

try {
    if ($type === 'Daily') {
        // date_val format: YYYY-MM-DD
        $periodStart = $dateVal;
        $periodEnd = $dateVal;
        $dateObj = new DateTime($dateVal);
        $reportName = "Daily_" . $dateObj->format('d_m_Y');
        
    } else if ($type === 'Weekly') {
        // date_val format: YYYY-W## (e.g. 2026-W41)
        if (preg_match('/^(\d{4})-W(\d{2})$/', $dateVal, $matches)) {
            $year = $matches[1];
            $week = $matches[2];
            $dto = new DateTime();
            $dto->setISODate($year, $week, 1); // Monday
            $periodStart = $dto->format('Y-m-d');
            $dto->setISODate($year, $week, 7); // Sunday
            $periodEnd = $dto->format('Y-m-d');
            $reportName = "Weekly_" . $week . "_" . $year;
        } else {
            throw new Exception("Invalid week format");
        }
    } else {
        // Monthly
        // date_val format: YYYY-MM
        $parts = explode('-', $dateVal);
        if (count($parts) !== 2) throw new Exception("Invalid month format");
        
        $year = $parts[0];
        $monthNum = $parts[1];
        
        $monthNames = [
            "01" => "January", "02" => "February", "03" => "March",
            "04" => "April", "05" => "May", "06" => "June",
            "07" => "July", "08" => "August", "09" => "September",
            "10" => "October", "11" => "November", "12" => "December"
        ];
        
        $monthName = $monthNames[$monthNum] ?? "Month";
        $reportName = $monthName . "_" . $year; 
        
        $periodStart = $dateVal . "-01";
        $periodEnd = date("Y-m-t", strtotime($periodStart));
    }

    $stmt = $pdo->prepare("INSERT INTO reports (report_name, report_type, period_start, period_end, file_path, file_type, report_status, created_at) 
                           VALUES (?, ?, ?, ?, ?, 'PDF', 'ready', NOW())");

    $dummyPath = "/exports/" . strtolower($reportName) . ".pdf";

    $stmt->execute([$reportName, $type, $periodStart, $periodEnd, $dummyPath]);
    echo json_encode(["status" => "success", "message" => "Report generated"]);

} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => "Error: " . $e->getMessage()]);
}
?>
