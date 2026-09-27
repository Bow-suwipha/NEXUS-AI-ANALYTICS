<?php
header('Content-Type: application/json');

// Ensure error reporting doesn't break JSON output
error_reporting(E_ERROR | E_PARSE);

require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['detail' => 'No file uploaded or upload error.']);
    http_response_code(400);
    exit;
}

$file = $_FILES['file']['tmp_name'];
$fileName = $_FILES['file']['name'];
$destination = '../sales_data.csv';

if (!move_uploaded_file($file, $destination)) {
    echo json_encode(['detail' => 'Failed to save uploaded file on server.']);
    http_response_code(500);
    exit;
}

try {
    $spreadsheet = IOFactory::load($destination);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();
    
    if (count($rows) <= 1) {
        echo json_encode(['detail' => 'File is empty or contains only headers.']);
        http_response_code(400);
        exit;
    }
    
    // Simple Data Quality Check
    $headers = array_shift($rows);
    $totalCells = 0;
    $missingValues = 0;
    
    foreach ($rows as $row) {
        // Skip completely empty rows
        $isEmptyRow = true;
        foreach ($row as $cellValue) {
            if ($cellValue !== null && trim((string)$cellValue) !== '') {
                $isEmptyRow = false;
                break;
            }
        }
        if ($isEmptyRow) continue;

        foreach ($row as $index => $cellValue) {
            // Only evaluate columns that have a defined header
            if (isset($headers[$index]) && trim((string)$headers[$index]) !== '') {
                $totalCells++;
                if ($cellValue === null || trim((string)$cellValue) === '') {
                    $missingValues++;
                }
            }
        }
    }
    
    $score = 100;
    if ($totalCells > 0) {
        $score = round((($totalCells - $missingValues) / $totalCells) * 100);
    }
    
    echo json_encode([
        'data_quality_score' => $score,
        'missing_values' => $missingValues,
        'total_rows' => count($rows),
        'detail' => 'Success'
    ]);
    
} catch (\Exception $e) {
    echo json_encode(['detail' => 'Failed to parse file: ' . $e->getMessage()]);
    http_response_code(500);
}
?>
