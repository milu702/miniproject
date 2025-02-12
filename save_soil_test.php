<?php
session_start();
require_once 'config.php';

// Ensure user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Set header to return JSON
header('Content-Type: application/json');

try {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        // Handle deletion
        if (!isset($_POST['soil_test_id'])) {
            throw new Exception('Soil test ID is required');
        }
        
        $test_id = intval($_POST['soil_test_id']);
        $stmt = $conn->prepare("DELETE FROM soil_tests WHERE soil_test_id = ?");
        $stmt->bind_param("i", $test_id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Soil test deleted successfully']);
        } else {
            throw new Exception('Failed to delete soil test');
        }
        
    } else {
        // Handle insert/update
        $test_id = isset($_POST['soil_test_id']) ? intval($_POST['soil_test_id']) : null;
        $farmer_id = intval($_POST['farmer_id']);
        $test_date = $_POST['test_date'];
        $ph_level = floatval($_POST['ph_level']);
        $nitrogen = floatval($_POST['nitrogen_level']);
        $phosphorus = floatval($_POST['phosphorus_level']);
        $potassium = floatval($_POST['potassium_level']);
        $organic_matter = !empty($_POST['organic_matter']) ? floatval($_POST['organic_matter']) : null;
        $notes = $_POST['notes'] ?? null;

        if ($test_id) {
            // Update existing record
            $stmt = $conn->prepare("UPDATE soil_tests SET farmer_id = ?, test_date = ?, ph_level = ?, 
                                  nitrogen_level = ?, phosphorus_level = ?, potassium_level = ?, 
                                  organic_matter = ?, notes = ? WHERE soil_test_id = ?");
            $stmt->bind_param("isddddisi", $farmer_id, $test_date, $ph_level, $nitrogen, 
                            $phosphorus, $potassium, $organic_matter, $notes, $test_id);
        } else {
            // Insert new record
            $stmt = $conn->prepare("INSERT INTO soil_tests (farmer_id, test_date, ph_level, 
                                  nitrogen_level, phosphorus_level, potassium_level, organic_matter, notes) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isddddis", $farmer_id, $test_date, $ph_level, $nitrogen, 
                            $phosphorus, $potassium, $organic_matter, $notes);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 
                            'message' => $test_id ? 'Soil test updated successfully' : 'Soil test added successfully']);
        } else {
            throw new Exception('Failed to save soil test');
        }
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
?> 