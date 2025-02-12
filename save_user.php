<?php
header('Content-Type: application/json');
session_start();
require_once 'config.php';

// Verify admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $userId = $_POST['user_id'];
        
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting user']);
        }
        
    } else {
        // Handle user creation/update
        $userId = isset($_POST['user_id']) ? $_POST['user_id'] : null;
        $username = $_POST['username'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $role = $_POST['role'];
        $password = isset($_POST['password']) && !empty($_POST['password']) ? 
                   password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

        if ($userId) {
            // Update existing user
            if ($password) {
                $stmt = $conn->prepare("UPDATE users SET username=?, email=?, phone=?, role=?, password=? WHERE user_id=?");
                $stmt->bind_param("sssssi", $username, $email, $phone, $role, $password, $userId);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username=?, email=?, phone=?, role=? WHERE user_id=?");
                $stmt->bind_param("ssssi", $username, $email, $phone, $role, $userId);
            }
        } else {
            // Create new user
            $stmt = $conn->prepare("INSERT INTO users (username, email, phone, role, password) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $email, $phone, $role, $password);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error saving user']);
        }
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?> 