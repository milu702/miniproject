<?php
session_start();

// Ensure user is logged in and has the 'farmer' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Initialize response array for AJAX requests
$response = array('success' => false, 'message' => '');

try {
    // Start transaction
    $conn->begin_transaction();

    // Validate and sanitize input data
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    
    // Basic validation
    if (empty($name) || empty($email)) {
        throw new Exception("Name and email are required fields.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format.");
    }

    if (!empty($phone) && !preg_match("/^[0-9]{10}$/", $phone)) {
        throw new Exception("Invalid phone number format.");
    }

    // Update users table
    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $email, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Error updating user information.");
    }

    // Update farmers table
    $stmt = $conn->prepare("
        INSERT INTO farmers (user_id, phone) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE phone = ?
    ");
    $stmt->bind_param("iss", $user_id, $phone, $phone);
    
    if (!$stmt->execute()) {
        throw new Exception("Error updating farmer information.");
    }

    // Handle password update if provided
    if (!empty($_POST['current_password']) && !empty($_POST['new_password'])) {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (!password_verify($_POST['current_password'], $user['password'])) {
            throw new Exception("Current password is incorrect.");
        }

        // Validate new password
        if (strlen($_POST['new_password']) < 8) {
            throw new Exception("New password must be at least 8 characters long.");
        }

        if (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/", $_POST['new_password'])) {
            throw new Exception("Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.");
        }

        // Update password
        $new_password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $new_password_hash, $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating password.");
        }
    }

    // Commit transaction
    $conn->commit();

    // Set success message
    $_SESSION['success'] = "Settings updated successfully!";
    $response['success'] = true;
    $response['message'] = "Settings updated successfully!";

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Set error message
    $_SESSION['error'] = $e->getMessage();
    $response['message'] = $e->getMessage();
}

// Check if it's an AJAX request
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Redirect back to settings page for normal form submission
header("Location: settings.php");
exit(); 