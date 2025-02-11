<?php
session_start();
require_once 'config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Handle delete action
if (isset($_POST['action']) && $_POST['action'] === 'delete') {
    $user_id = $_POST['user_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete from farmers or employees table first (if exists)
        $conn->query("DELETE FROM farmers WHERE user_id = $user_id");
        $conn->query("DELETE FROM employees WHERE user_id = $user_id");
        
        // Delete from users table
        $result = $conn->query("DELETE FROM users WHERE user_id = $user_id");
        
        if ($result) {
            $conn->commit();
            echo json_encode(['success' => true]);
        } else {
            throw new Exception("Error deleting user");
        }
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// Handle create/update
$user_id = $_POST['user_id'] ?? '';
$username = $conn->real_escape_string($_POST['username']);
$full_name = $conn->real_escape_string($_POST['full_name']);
$email = $conn->real_escape_string($_POST['email']);
$role = $conn->real_escape_string($_POST['assigned_role']);
$password = $_POST['password'] ?? '';

// Start transaction
$conn->begin_transaction();

try {
    if ($user_id) {
        // Update existing user
        $password_update = $password ? ", password = '" . password_hash($password, PASSWORD_DEFAULT) . "'" : "";
        $query = "UPDATE users SET 
                    username = '$username',
                    full_name = '$full_name',
                    email = '$email',
                    role = '$role'
                    $password_update
                 WHERE user_id = $user_id";
    } else {
        // Create new user
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO users (username, full_name, email, password, role) 
                 VALUES ('$username', '$full_name', '$email', '$hashed_password', '$role')";
    }
    
    $result = $conn->query($query);
    
    if ($result) {
        $user_id = $user_id ?: $conn->insert_id;
        
        // Handle role-specific data
        if ($role === 'farmer') {
            $farm_location = $conn->real_escape_string($_POST['farm_location']);
            $farm_size = floatval($_POST['farm_size']);
            
            $farmer_query = $user_id ? 
                "REPLACE INTO farmers (user_id, farm_location, farm_size) VALUES ($user_id, '$farm_location', $farm_size)" :
                "INSERT INTO farmers (user_id, farm_location, farm_size) VALUES ($user_id, '$farm_location', $farm_size)";
            
            $conn->query($farmer_query);
        } elseif ($role === 'employee') {
            $position = $conn->real_escape_string($_POST['position']);
            
            $employee_query = $user_id ?
                "REPLACE INTO employees (user_id, position) VALUES ($user_id, '$position')" :
                "INSERT INTO employees (user_id, position) VALUES ($user_id, '$position')";
            
            $conn->query($employee_query);
        }
        
        $conn->commit();
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Error saving user");
    }
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 