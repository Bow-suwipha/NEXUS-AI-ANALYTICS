<?php
header('Content-Type: application/json');
// Optional: CORS Headers if needed, but since frontend and backend are both on localhost/spacial, it's not strictly necessary.
// header('Access-Control-Allow-Origin: *');

$host = '127.0.0.1';
$db   = 'nexus_ai_analytics';
$user = 'root';
$pass = ''; // Default laragon password is empty

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}
?>
