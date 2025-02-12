<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, task_date, description, priority) VALUES (?, ?, ?, ?, ?)");
    $result = $stmt->execute([
        $_SESSION['user_id'],
        $_POST['title'],
        $_POST['date'],
        $_POST['description'],
        $_POST['priority']
    ]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'task_id' => $pdo->lastInsertId()
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to add task'
        ]);
    }
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 