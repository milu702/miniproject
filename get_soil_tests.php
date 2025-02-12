<?php
require_once 'config.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$query = "
    SELECT st.*, u.username 
    FROM soil_tests st
    JOIN farmers f ON st.farmer_id = f.farmer_id
    JOIN users u ON f.user_id = u.user_id
    ORDER BY st.test_date DESC
";

$result = $conn->query($query);
$tests = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $tests[] = $row;
    }
}

header('Content-Type: application/json');
echo json_encode($tests); 