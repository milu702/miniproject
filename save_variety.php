<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            // Handle deletion
            if (!isset($_POST['variety_id'])) {
                throw new Exception('Variety ID is required');
            }
            
            $variety_id = intval($_POST['variety_id']);
            $stmt = $conn->prepare("DELETE FROM cardamom_variety WHERE variety_id = ?");
            $stmt->bind_param("i", $variety_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception('Failed to delete variety');
            }
            
        } else {
            // Handle insert/update
            $variety_id = isset($_POST['variety_id']) ? intval($_POST['variety_id']) : null;
            $variety_name = trim($_POST['variety_name']);
            $scientific_name = trim($_POST['scientific_name']);
            $description = trim($_POST['description']);
            $growing_period = floatval($_POST['growing_period']);
            $yield_rate = floatval($_POST['yield_rate']);
            $optimal_ph_min = !empty($_POST['optimal_ph_min']) ? floatval($_POST['optimal_ph_min']) : null;
            $optimal_ph_max = !empty($_POST['optimal_ph_max']) ? floatval($_POST['optimal_ph_max']) : null;
            $disease_resistance = trim($_POST['disease_resistance']);
            $maintenance_requirements = trim($_POST['maintenance_requirements']);

            // Validate required fields
            if (empty($variety_name) || empty($description) || $growing_period <= 0 || $yield_rate <= 0) {
                throw new Exception('Please fill in all required fields with valid values');
            }

            if ($variety_id) {
                // Update existing variety
                $stmt = $conn->prepare("
                    UPDATE cardamom_variety SET 
                    variety_name = ?, scientific_name = ?, description = ?,
                    growing_period = ?, yield_rate = ?, optimal_ph_min = ?,
                    optimal_ph_max = ?, disease_resistance = ?, maintenance_requirements = ?
                    WHERE variety_id = ?
                ");
                $stmt->bind_param("sssdddsssi", 
                    $variety_name, $scientific_name, $description,
                    $growing_period, $yield_rate, $optimal_ph_min,
                    $optimal_ph_max, $disease_resistance, $maintenance_requirements,
                    $variety_id
                );
            } else {
                // Insert new variety
                $stmt = $conn->prepare("
                    INSERT INTO cardamom_variety (
                        variety_name, scientific_name, description,
                        growing_period, yield_rate, optimal_ph_min,
                        optimal_ph_max, disease_resistance, maintenance_requirements
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("sssdddssss", 
                    $variety_name, $scientific_name, $description,
                    $growing_period, $yield_rate, $optimal_ph_min,
                    $optimal_ph_max, $disease_resistance, $maintenance_requirements
                );
            }

            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception('Failed to save variety');
            }
        }
    } else {
        throw new Exception('Invalid request method');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close(); 