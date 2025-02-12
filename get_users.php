<?php
header('Content-Type: application/json');
session_start();
require_once 'config.php';

// Verify admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

try {
    $role = isset($_GET['role']) ? $_GET['role'] : null;
    
    if ($role) {
        $stmt = $conn->prepare("SELECT user_id, username, email, phone, role FROM users WHERE role = ?");
        $stmt->bind_param("s", $role);
    } else {
        $stmt = $conn->prepare("SELECT user_id, username, email, phone, role FROM users");
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $users = [];
    
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    
    echo json_encode($users);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?> 