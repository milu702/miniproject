<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get selected notifications
        $notifications = isset($_POST['notifications']) ? $_POST['notifications'] : [];
        
        // Convert array to JSON for storage
        $notification_preferences = json_encode($notifications);
        
        // Update the farmers table
        $stmt = $conn->prepare("
            UPDATE farmers 
            SET notification_preferences = ?
            WHERE user_id = ?
        ");
        
        $stmt->bind_param("si", $notification_preferences, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['notification_preferences'] = $notifications;
            $_SESSION['success'] = "Notification preferences updated successfully!";
            
            // Send response for AJAX requests
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                $response['success'] = true;
                $response['message'] = "Notification preferences updated successfully!";
                echo json_encode($response);
                exit;
            }
            
            header("Location: settings.php");
            exit;
        } else {
            throw new Exception("Error updating notification preferences");
        }
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error: " . $e->getMessage();
    
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        $response['message'] = $e->getMessage();
        echo json_encode($response);
        exit;
    }
    
    header("Location: settings.php");
    exit;
}
?> 