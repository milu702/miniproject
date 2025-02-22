<?php
session_start();

// Check if user is logged in and has farmer role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Include database configuration
require_once 'config.php';

// Verify it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Get and validate task_id
$task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;
if ($task_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
    exit();
}

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=localhost;dbname=growguide", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Prepare and execute delete statement (only delete if task belongs to current user)
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
    $result = $stmt->execute([$task_id, $_SESSION['user_id']]);
    
    // Check if a row was actually deleted
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Task deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Task not found or not authorized']);
    }
    
} catch (PDOException $e) {
    // Log the error (in a production environment)
    error_log("Delete task error: " . $e->getMessage());
    
    // Return generic error to client
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?> 