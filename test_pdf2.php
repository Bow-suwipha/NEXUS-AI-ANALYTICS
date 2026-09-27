<?php
ob_start();
require 'export_pdf.php';
$content = ob_get_clean();
file_put_contents('test_pdf_out2.txt', $content);
echo "Done";
