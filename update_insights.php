<?php
require 'api/db.php';

// Fix Product A
$pdo->query("UPDATE ai_insights 
             SET description = REPLACE(description, 'Product A', 'น้ำดื่มบริสุทธิ์ 600ml') 
             WHERE description LIKE '%Product A%'");

// Fix สาขา B
$pdo->query("UPDATE ai_insights 
             SET description = REPLACE(description, 'สาขา B', 'สาขาเชียงใหม่') 
             WHERE description LIKE '%สาขา B%'");

echo "Insights updated successfully.";
?>
