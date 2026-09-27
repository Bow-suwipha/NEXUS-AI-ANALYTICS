<?php
require 'db.php';

header('Content-Type: application/json; charset=utf-8');

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

if (!isset($input['month_val']) || empty($input['month_val'])) {
    echo json_encode(["status" => "error", "message" => "Missing month_val"]);
    exit;
}

$monthVal = $input['month_val']; // Format: YYYY-MM
$parts = explode('-', $monthVal);
if (count($parts) !== 2) {
    echo json_encode(["status" => "error", "message" => "Invalid month format"]);
    exit;
}

$year = $parts[0];
$monthNum = $parts[1];

// Month names array for naming
$monthNames = [
    "01" => "January", "02" => "February", "03" => "March",
    "04" => "April", "05" => "May", "06" => "June",
    "07" => "July", "08" => "August", "09" => "September",
    "10" => "October", "11" => "November", "12" => "December"
];

$monthName = $monthNames[$monthNum] ?? "Month";
$reportName = $monthName . "_" . $year; // requested format

$periodStart = $monthVal . "-01";
$periodEnd = date("Y-m-t", strtotime($periodStart)); // Last day of month

// Insert into reports table
$stmt = $pdo->prepare("INSERT INTO reports (report_name, report_type, period_start, period_end, file_path, file_type, report_status, created_at) 
                       VALUES (?, 'Monthly', ?, ?, ?, 'PDF', 'ready', NOW())");

// dummy path for now, the actual file is generated on-the-fly by export_pdf.php?id=...
$dummyPath = "/exports/" . strtolower($reportName) . ".pdf";

try {
    $stmt->execute([$reportName, $periodStart, $periodEnd, $dummyPath]);
    echo json_encode(["status" => "success", "message" => "Report generated"]);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "DB Error: " . $e->getMessage()]);
}
?>
