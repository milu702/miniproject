<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$role = $_GET['role'] ?? '';
$users = [];

if ($role === 'farmer') {
    $query = "
        SELECT u.*, f.farm_location, f.farm_size 
        FROM users u 
        LEFT JOIN farmers f ON u.user_id = f.user_id 
        WHERE u.role = 'farmer'
    ";
} elseif ($role === 'employee') {
    $query = "
        SELECT u.*, e.position 
        FROM users u 
        LEFT JOIN employees e ON u.user_id = e.user_id 
        WHERE u.role = 'employee'
    ";
} else {
    http_response_code(400);
    exit('Invalid role specified');
}

$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Remove sensitive information
        unset($row['password']);
        $users[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($users); 