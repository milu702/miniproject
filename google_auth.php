<?php
session_start();
require_once 'config.php';
require_once 'vendor/autoload.php'; // You'll need to install google/apiclient using Composer

$dbConfig = new DatabaseConfig();
$conn = $dbConfig->getConnection();

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$credential = $data['credential'] ?? null;

if (!$credential) {
    echo json_encode(['success' => false, 'error' => 'No credential provided']);
    exit;
}

try {
    // Create Google Client
    $client = new Google_Client(['client_id' => 'YOUR_GOOGLE_CLIENT_ID']);
    
    // Verify the token
    $payload = $client->verifyIdToken($credential);
    
    if ($payload) {
        $email = $payload['email'];
        $name = $payload['name'];
        $google_id = $payload['sub'];
        
        // Check if user already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR google_id = ?");
        $stmt->bind_param("ss", $email, $google_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // User exists, update google_id if necessary
            $user = $result->fetch_assoc();
            $stmt = $conn->prepare("UPDATE users SET google_id = ? WHERE id = ?");
            $stmt->bind_param("si", $google_id, $user['id']);
            $stmt->execute();
        } else {
            // Create new user
            $stmt = $conn->prepare("INSERT INTO users (username, email, google_id, role) VALUES (?, ?, ?, 'farmer')");
            $stmt->bind_param("sss", $name, $email, $google_id);
            $stmt->execute();
        }
        
        // Set session variables
        $_SESSION['user_id'] = $stmt->insert_id ?? $user['id'];
        $_SESSION['email'] = $email;
        $_SESSION['role'] = 'farmer';
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 