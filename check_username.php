<?php
require_once 'config.php';

$dbConfig = new DatabaseConfig();
$conn = $dbConfig->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    
    $result = $stmt->get_result();
    
    header('Content-Type: application/json');
    echo json_encode(['exists' => $result->num_rows > 0]);
    exit;
}

header('HTTP/1.1 400 Bad Request');
echo json_encode(['error' => 'Invalid request']); 