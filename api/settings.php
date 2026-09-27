<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
require 'db.php';
session_start();

$userId = null;
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($authHeader) && function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? '';
}

if (!empty($authHeader)) {
    $tokenPart = str_replace('Bearer ', '', $authHeader);
    $payload = json_decode(base64_decode($tokenPart), true);
    if ($payload && isset($payload['id'])) {
        $userId = $payload['id'];
        $_SESSION['user_id'] = $userId;
    }
}

if (!$userId && isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
}

if (!$userId) {
    // Default to admin user for local dashboard
    $userId = 1;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmtUser = $pdo->prepare("SELECT fullname, email, role FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $user = $stmtUser->fetch();

    $stmtSettings = $pdo->prepare("SELECT * FROM system_settings WHERE user_id = ? LIMIT 1");
    $stmtSettings->execute([$userId]);
    $setting = $stmtSettings->fetch();
    
    if ($user) {
        echo json_encode([
            "groq_api_key" => $setting['groq_api_key'] ?? '',
            "model_name" => $setting['model_name'] ?? 'llama3-8b-8192',
            "refresh_interval" => (int)($setting['refresh_interval'] ?? 60),
            "fullname" => $user['fullname'] ?? '',
            "email" => $user['email'] ?? '',
            "role" => $user['role'] ?? 'Admin',
            "alert_anomaly" => (bool)($setting['alert_anomaly'] ?? true),
            "alert_weekly_summary" => (bool)($setting['alert_weekly_summary'] ?? false)
        ]);
    } else {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "User not found"]);
    }
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(["status" => "error", "message" => "Invalid payload"]);
        exit;
    }
    
    $apiKey = $input['groq_api_key'] ?? '';
    $modelName = $input['model_name'] ?? 'llama3-8b-8192';
    $interval = $input['refresh_interval'] ?? 60;
    
    $fullname = $input['fullname'] ?? '';
    $email = $input['email'] ?? '';
    $role = $input['role'] ?? 'Admin';
    $alertAnomaly = isset($input['alert_anomaly']) ? (int)$input['alert_anomaly'] : 1;
    $alertWeekly = isset($input['alert_weekly_summary']) ? (int)$input['alert_weekly_summary'] : 0;
    
    // Update user profile for the logged in user
    $stmtUser = $pdo->prepare("UPDATE users SET fullname = ?, email = ?, role = ? WHERE id = ?");
    $stmtUser->execute([$fullname, $email, $role, $userId]);
    
    // Update system settings (User Specific)
    $stmt = $pdo->prepare("SELECT id, groq_api_key FROM system_settings WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // If API Key changed, reset tokens_used to 0 to simulate a fresh API key quota
        if ($apiKey !== $existing['groq_api_key'] && !empty($apiKey)) {
            $pdo->query("UPDATE server_metrics SET tokens_used = 0");
        }
        $stmtSettings = $pdo->prepare("UPDATE system_settings SET groq_api_key = ?, alert_anomaly = ?, alert_weekly_summary = ? WHERE id = ?");
        $stmtSettings->execute([$apiKey, $alertAnomaly, $alertWeekly, $existing['id']]);
    } else {
        if (!empty($apiKey)) {
            $pdo->query("UPDATE server_metrics SET tokens_used = 0");
        }
        $stmtSettings = $pdo->prepare("INSERT INTO system_settings (user_id, groq_api_key, alert_anomaly, alert_weekly_summary) VALUES (?, ?, ?, ?)");
        $stmtSettings->execute([$userId, $apiKey, $alertAnomaly, $alertWeekly]);
    }
    
    echo json_encode(["status" => "success", "message" => "Settings updated successfully"]);
}
?>
