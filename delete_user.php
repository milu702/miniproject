<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_POST['user_id'] ?? null;
$role = $_POST['role'] ?? null;

if (!$user_id || !$role) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

try {
    $conn->begin_transaction();

    // Delete role-specific data first
    if ($role === 'farmer') {
        $conn->query("DELETE FROM farmers WHERE user_id = $user_id");
    } elseif ($role === 'employee') {
        $conn->query("DELETE FROM employees WHERE user_id = $user_id");
    }

    // Delete user
    $conn->query("DELETE FROM users WHERE user_id = $user_id");
    
    $conn->commit();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 