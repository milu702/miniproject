<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        // Handle deletion
        $variety_id = $_POST['variety_id'];
        $stmt = $conn->prepare("DELETE FROM cardamom_variety WHERE variety_id = ?");
        $stmt->bind_param("i", $variety_id);
        
        $response = ['success' => $stmt->execute()];
        if (!$response['success']) {
            $response['message'] = $conn->error;
        }
        
        echo json_encode($response);
        exit();
    }

    // Handle insert/update
    $variety_id = $_POST['variety_id'] ?? null;
    $variety_name = $_POST['variety_name'];
    $scientific_name = $_POST['scientific_name'];
    $description = $_POST['description'];
    $growing_period = $_POST['growing_period'];
    $yield_rate = $_POST['yield_rate'];
    $optimal_ph_min = $_POST['optimal_ph_min'];
    $optimal_ph_max = $_POST['optimal_ph_max'];
    $planting_distance = $_POST['planting_distance'];
    $disease_resistance = $_POST['disease_resistance'];
    $characteristics = $_POST['characteristics'];
    $maintenance_requirements = $_POST['maintenance_requirements'];

    if ($variety_id) {
        // Update existing variety
        $stmt = $conn->prepare("UPDATE cardamom_variety SET 
            variety_name = ?, scientific_name = ?, description = ?,
            growing_period = ?, yield_rate = ?, optimal_ph_min = ?,
            optimal_ph_max = ?, planting_distance = ?, disease_resistance = ?,
            characteristics = ?, maintenance_requirements = ?
            WHERE variety_id = ?");
        
        $stmt->bind_param("sssiiddsssssi", 
            $variety_name, $scientific_name, $description,
            $growing_period, $yield_rate, $optimal_ph_min,
            $optimal_ph_max, $planting_distance, $disease_resistance,
            $characteristics, $maintenance_requirements, $variety_id
        );
    } else {
        // Insert new variety
        $stmt = $conn->prepare("INSERT INTO cardamom_variety (
            variety_name, scientific_name, description,
            growing_period, yield_rate, optimal_ph_min,
            optimal_ph_max, planting_distance, disease_resistance,
            characteristics, maintenance_requirements
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("sssiiddssss", 
            $variety_name, $scientific_name, $description,
            $growing_period, $yield_rate, $optimal_ph_min,
            $optimal_ph_max, $planting_distance, $disease_resistance,
            $characteristics, $maintenance_requirements
        );
    }

    $response = ['success' => $stmt->execute()];
    if (!$response['success']) {
        $response['message'] = $conn->error;
    }

    echo json_encode($response);
} 