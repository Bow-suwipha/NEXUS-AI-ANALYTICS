<?php
require '../vendor/autoload.php'; require 'db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;


} catch (\PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Fetch Data
$sales = [];
$reportName = "sales_report";

if (isset($_GET['id'])) {
    $reportId = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM reports WHERE id = ?");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();
    
    if ($report && isset($report['period_start']) && isset($report['period_end'])) {
        $reportName = $report['report_name'];
        $stmt = $pdo->prepare("SELECT sale_date, product_name, category, branch, quantity, total_price FROM sales_data WHERE sale_date BETWEEN ? AND ? ORDER BY sale_date ASC");
        $stmt->execute([$report['period_start'], $report['period_end']]);
        $sales = $stmt->fetchAll();
    } else {
        if ($report) $reportName = $report['report_name'];
        $stmt = $pdo->query("SELECT sale_date, product_name, category, branch, quantity, total_price FROM sales_data ORDER BY sale_date DESC LIMIT 100");
        $sales = $stmt->fetchAll();
    }
} else {
    $stmt = $pdo->query("SELECT sale_date, product_name, category, branch, quantity, total_price FROM sales_data ORDER BY sale_date DESC LIMIT 100");
    $sales = $stmt->fetchAll();
}

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Garuda');
$sheet = $spreadsheet->getActiveSheet();

// Set Header
$sheet->setCellValue('A1', 'Sale Date');
$sheet->setCellValue('B1', 'Product Name');
$sheet->setCellValue('C1', 'Category');
$sheet->setCellValue('D1', 'Branch');
$sheet->setCellValue('E1', 'Quantity');
$sheet->setCellValue('F1', 'Total Price');

// Apply Header Style
$headerStyle = [
    'font' => ['bold' => true, 'name' => 'Garuda'],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THICK]],
];
$sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

// Fill Data
$row = 2;
foreach ($sales as $sale) {
    $sheet->setCellValue('A' . $row, $sale['sale_date']);
    $sheet->setCellValue('B' . $row, $sale['product_name']);
    $sheet->setCellValue('C' . $row, $sale['category']);
    $sheet->setCellValue('D' . $row, $sale['branch']);
    $sheet->setCellValue('E' . $row, $sale['quantity']);
    $sheet->setCellValue('F' . $row, number_format((float)$sale['total_price'], 2));
    $row++;
}

// Auto-size columns
foreach (range('A', 'F') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Custom Mpdf class to enable Thai language support automatically
class CustomMpdf extends \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf {
    protected function createExternalWriterInstance(array $config): \Mpdf\Mpdf {
        $mpdf = parent::createExternalWriterInstance($config);
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        return $mpdf;
    }
}

try {
    \PhpOffice\PhpSpreadsheet\IOFactory::registerWriter('CustomPdf', CustomMpdf::class);
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'CustomPdf');
    
    $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower(str_replace(' ', '_', $reportName)));
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $safeName . '.pdf"');
    header('Cache-Control: max-age=0');
    
    $writer->save('php://output');
} catch (Exception $e) {
    echo "Error generating PDF. Please ensure you have installed mpdf via: composer require mpdf/mpdf\n";
    echo $e->getMessage();
}

