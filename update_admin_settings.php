<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized access']));
}

$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_id = $_SESSION['user_id'];
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_profile':
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone = $_POST['phone'] ?? '';
            
            $stmt = $conn->prepare("UPDATE users SET username=?, email=?, phone=? WHERE id=?");
            $stmt->bind_param("sssi", $username, $email, $phone, $admin_id);
            
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'Profile updated successfully'];
            } else {
                $response = ['status' => 'error', 'message' => 'Failed to update profile'];
            }
            break;
            
        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
            $stmt->bind_param("i", $admin_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if (password_verify($current_password, $user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
                $stmt->bind_param("si", $hashed_password, $admin_id);
                
                if ($stmt->execute()) {
                    $response = ['status' => 'success', 'message' => 'Password changed successfully'];
                } else {
                    $response = ['status' => 'error', 'message' => 'Failed to change password'];
                }
            } else {
                $response = ['status' => 'error', 'message' => 'Current password is incorrect'];
            }
            break;
            
        case 'update_settings':
            $setting_type = $_POST['setting_type'] ?? '';
            $setting_value = $_POST['value'] ?? '';
            
            $stmt = $conn->prepare("INSERT INTO user_settings (user_id, setting_type, setting_value) 
                                  VALUES (?, ?, ?) 
                                  ON DUPLICATE KEY UPDATE setting_value=?");
            $stmt->bind_param("isss", $admin_id, $setting_type, $setting_value, $setting_value);
            
            if ($stmt->execute()) {
                $response = ['status' => 'success', 'message' => 'Setting updated successfully'];
            } else {
                $response = ['status' => 'error', 'message' => 'Failed to update setting'];
            }
            break;
    }
}

echo json_encode($response);
?> 