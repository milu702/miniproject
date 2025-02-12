<?php
session_start();
require_once 'config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

$query = "SELECT * FROM cardamom_variety ORDER BY variety_name";
$result = $conn->query($query);

$varieties = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $varieties[] = $row;
    }
}

echo json_encode($varieties); 