<?php
session_start();
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT id, title, description, task_date, priority FROM tasks WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $events = array_map(function($task) {
        return [
            'id' => $task['id'],
            'title' => $task['title'],
            'start' => $task['task_date'],
            'description' => $task['description'],
            'backgroundColor' => getPriorityColor($task['priority'])
        ];
    }, $tasks);

    echo json_encode($events);
} catch(PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

function getPriorityColor($priority) {
    switch($priority) {
        case 'high':
            return '#ef4444';
        case 'medium':
            return '#f59e0b';
        case 'low':
            return '#3b82f6';
        default:
            return '#6b7280';
    }
} 