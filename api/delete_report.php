<?php
require 'db.php';

$input = json_decode(file_get_contents('php://input'), true);
if (isset($input['id'])) {
    $id = (int)$input['id'];
    try {
        $pdo->beginTransaction();
        
        // Delete dependent reviews first to avoid foreign key constraint errors
        $stmt = $pdo->prepare("DELETE FROM report_reviews WHERE report_id = ?");
        $stmt->execute([$id]);
        
        // Then delete the main report
        $stmt = $pdo->prepare("DELETE FROM reports WHERE id = ?");
        $stmt->execute([$id]);
        
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Missing ID']);
}
?>
