<?php
header('Content-Type: application/json');
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method Not Allowed"]);
    exit;
}

$username = trim($_POST['username'] ?? ''); // Frontend passes email as 'username'
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    http_response_code(400);
    echo json_encode(["error" => "Username/Email and password are required."]);
    exit;
}

// Support login by either email or username
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1");
$stmt->execute([$username, $username]);
$user = $stmt->fetch();

// Checking password (supports plain text as seen in the DB screenshot, or password_verify if hashed)
if ($user && ($user['password'] === $password || password_verify($password, $user['password']))) {
    
    session_start();
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['fullname'] = $user['fullname'];
    
    // Create a simple base64 token for frontend localStorage
    $token = base64_encode(json_encode([
        'id' => $user['id'], 
        'role' => $user['role'],
        'time' => time()
    ]));
    
    echo json_encode([
        "status" => "success", 
        "message" => "Login successful", 
        "access_token" => $token,
        "user" => [
            "id" => $user['id'],
            "fullname" => $user['fullname'],
            "role" => $user['role']
        ]
    ]);
} else {
    http_response_code(401);
    echo json_encode(["error" => "Invalid credentials"]);
}
?>
