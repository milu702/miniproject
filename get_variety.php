<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Variety ID not provided']);
    exit;
}

$id = (int)$_GET['id'];
$query = "SELECT * FROM cardamom_variety WHERE variety_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($variety = $result->fetch_assoc()) {
    echo json_encode($variety);
} else {
    echo json_encode(['success' => false, 'message' => 'Variety not found']);
} 