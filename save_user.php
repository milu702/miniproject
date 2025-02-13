<?php
header('Content-Type: application/json');
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate CSRF token if implemented
// if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
//     header('HTTP/1.1 403 Forbidden');
//     echo json_encode(['success' => false, 'message' => 'Invalid token']);
//     exit();
// }

// Function to validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to validate phone number
function isValidPhone($phone) {
    return preg_match('/^\+?[\d\s-]{10,}$/', $phone);
}

// Function to sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

try {
    // Check if it's a delete request
    if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['user_id'])) {
        $userId = intval($_POST['user_id']);
        
        // Begin transaction
        $conn->begin_transaction();
        
        // First delete related records in farmers table if exists
        $conn->query("DELETE FROM farmers WHERE user_id = $userId");
        
        // Then delete the user
        $deleteResult = $conn->query("DELETE FROM users WHERE user_id = $userId");
        
        if ($deleteResult) {
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        } else {
            throw new Exception('Failed to delete user');
        }
        exit();
    }

    // Validate required fields
    $requiredFields = ['username', 'email', 'phone', 'role'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missingFields));
    }

    // Sanitize and validate input
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $role = sanitizeInput($_POST['role']);
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;

    // Validate email
    if (!isValidEmail($email)) {
        throw new Exception('Invalid email address');
    }

    // Validate phone
    if (!isValidPhone($phone)) {
        throw new Exception('Invalid phone number format');
    }

    // Validate role
    if (!in_array($role, ['farmer', 'employee'])) {
        throw new Exception('Invalid role specified');
    }

    // Check if username or email already exists (excluding current user if editing)
    $existingUserQuery = "SELECT user_id FROM users WHERE (username = ? OR email = ?)";
    $params = [$username, $email];
    $types = "ss";
    
    if ($userId) {
        $existingUserQuery .= " AND user_id != ?";
        $params[] = $userId;
        $types .= "i";
    }
    
    $stmt = $conn->prepare($existingUserQuery);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        throw new Exception('Username or email already exists');
    }

    // Begin transaction
    $conn->begin_transaction();

    if ($userId) {
        // Update existing user
        $updateQuery = "UPDATE users SET username = ?, email = ?, phone = ?, role = ?";
        $params = [$username, $email, $phone, $role];
        $types = "ssss";
        
        // Only update password if provided
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $updateQuery .= ", password = ?";
            $params[] = $password;
            $types .= "s";
        }
        
        $updateQuery .= " WHERE user_id = ?";
        $params[] = $userId;
        $types .= "i";
        
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param($types, ...$params);
        $success = $stmt->execute();
    } else {
        // Create new user
        if (empty($_POST['password'])) {
            throw new Exception('Password is required for new users');
        }
        
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, phone, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $username, $email, $password, $phone, $role);
        $success = $stmt->execute();
        $userId = $stmt->insert_id;
    }

    if (!$success) {
        throw new Exception('Failed to save user data');
    }

    // If user is a farmer, handle farmer-specific data
    if ($role === 'farmer') {
        // Add or update farmer details
        if (isset($_POST['farm_size']) && isset($_POST['farm_location'])) {
            $farmSize = floatval($_POST['farm_size']);
            $farmLocation = sanitizeInput($_POST['farm_location']);
            
            $farmerStmt = $conn->prepare("INSERT INTO farmers (user_id, farm_size, farm_location) 
                                        VALUES (?, ?, ?) 
                                        ON DUPLICATE KEY UPDATE 
                                        farm_size = VALUES(farm_size), 
                                        farm_location = VALUES(farm_location)");
            $farmerStmt->bind_param("ids", $userId, $farmSize, $farmLocation);
            $farmerSuccess = $farmerStmt->execute();
            
            if (!$farmerSuccess) {
                throw new Exception('Failed to save farmer details');
            }
        }
    }

    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $userId ? 'User updated successfully' : 'User created successfully',
        'user_id' => $userId
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($conn->connect_errno === 0) {
        $conn->rollback();
    }
    
    header('HTTP/1.1 400 Bad Request');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Close connection
$conn->close();
?> 