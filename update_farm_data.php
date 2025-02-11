<?php
session_start();

// Ensure user is logged in and has the 'farmer' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    
    // Get farmer_id from the farmers table
    $stmt = $conn->prepare("SELECT farmer_id FROM farmers WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $farmer = $result->fetch_assoc();
    
    if (!$farmer) {
        $_SESSION['error'] = "Farmer not found!";
        header("Location: farmer.php");
        exit();
    }
    
    $farmer_id = $farmer['farmer_id'];
    
    // Sanitize and validate inputs
    $soil_type = filter_input(INPUT_POST, 'soil_type', FILTER_SANITIZE_STRING);
    $soil_ph = filter_input(INPUT_POST, 'soil_ph', FILTER_VALIDATE_FLOAT);
    $soil_moisture = filter_input(INPUT_POST, 'soil_moisture', FILTER_VALIDATE_FLOAT);
    $temperature = filter_input(INPUT_POST, 'temperature', FILTER_VALIDATE_FLOAT);
    $humidity = filter_input(INPUT_POST, 'humidity', FILTER_VALIDATE_FLOAT);
    $rainfall = filter_input(INPUT_POST, 'rainfall', FILTER_VALIDATE_FLOAT);
    
    // Check if farmer profile exists
    $stmt = $conn->prepare("SELECT farmer_id FROM farmer_profiles WHERE farmer_id = ?");
    $stmt->bind_param("i", $farmer_id);
    $stmt->execute();
    $profile_exists = $stmt->get_result()->num_rows > 0;
    
    if ($profile_exists) {
        // Update existing profile
        $stmt = $conn->prepare("
            UPDATE farmer_profiles 
            SET soil_type = ?, soil_ph = ?, soil_moisture = ?, 
                temperature = ?, humidity = ?, rainfall = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE farmer_id = ?
        ");
        $stmt->bind_param("sdddddi", 
            $soil_type, $soil_ph, $soil_moisture, 
            $temperature, $humidity, $rainfall, 
            $farmer_id
        );
    } else {
        // Insert new profile
        $stmt = $conn->prepare("
            INSERT INTO farmer_profiles 
            (farmer_id, soil_type, soil_ph, soil_moisture, temperature, humidity, rainfall)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("isddddd", 
            $farmer_id, $soil_type, $soil_ph, $soil_moisture, 
            $temperature, $humidity, $rainfall
        );
    }
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Farm data updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating farm data: " . $conn->error;
    }
    
    // Redirect back to farmer dashboard
    header("Location: farmer.php");
    exit();
}
?> 