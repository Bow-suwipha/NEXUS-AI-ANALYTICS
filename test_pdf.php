<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf;

$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Garuda');
$sheet = $spreadsheet->getActiveSheet();
$sheet->setCellValue('A1', 'ทดสอบภาษาไทย');

try {
    $class = \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf::class;
    \PhpOffice\PhpSpreadsheet\IOFactory::registerWriter('Pdf', $class);
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Pdf');
    $writer->save('test_thai.pdf');
    echo "PDF Created successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
