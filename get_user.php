<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$user_id = $_GET['id'] ?? 0;

$query = "SELECT u.*, f.farm_location, f.farm_size, e.position 
          FROM users u 
          LEFT JOIN farmers f ON u.user_id = f.user_id 
          LEFT JOIN employees e ON u.user_id = e.user_id 
          WHERE u.user_id = $user_id";

$result = $conn->query($query);

if ($result && $row = $result->fetch_assoc()) {
    echo json_encode($row);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
} 