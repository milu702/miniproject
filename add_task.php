<?php
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=localhost;dbname=growguide", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get form data
    $title = $_POST['title'];
    $date = $_POST['date'];
    $description = $_POST['description'];
    $priority = $_POST['priority'];
    $user_id = $_SESSION['user_id'];

    // Insert task into database
    $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, task_date, description, priority) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $title, $date, $description, $priority]);

    // Get the ID of the newly inserted task
    $task_id = $pdo->lastInsertId();

    echo json_encode(['success' => true, 'task_id' => $task_id]);

} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 