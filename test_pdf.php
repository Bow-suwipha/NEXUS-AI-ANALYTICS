<?php
$out = shell_exec('C:\laragon\bin\php\php-8.1.10\php.exe c:\laragon\www\NEXUS-AI-ANALYTICS\export_pdf.php 2>&1');
file_put_contents('test_pdf_out.txt', $out);
echo "Done";
