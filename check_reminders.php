<?php
require_once 'config.php';
require_once 'schedule.php'; // Make sure this path is correct

try {
    // Get all unsent reminders for today
    $stmt = $pdo->prepare("
        SELECT r.*, t.title, t.task_date, t.description, t.priority, u.email 
        FROM task_reminders r
        JOIN tasks t ON r.task_id = t.id
        JOIN users u ON t.user_id = u.id
        WHERE r.reminder_date = CURDATE() 
        AND r.sent = 0
    ");
    $stmt->execute();
    $reminders = $stmt->fetchAll();

    foreach ($reminders as $reminder) {
        // Send reminder notification
        if (sendReminderNotification(
            $reminder['email'],
            $reminder['title'],
            $reminder['task_date'],
            $reminder['description'],
            $reminder['priority']
        )) {
            // Mark reminder as sent
            $updateStmt = $pdo->prepare("UPDATE task_reminders SET sent = 1 WHERE id = ?");
            $updateStmt->execute([$reminder['id']]);
        }
    }
} catch (PDOException $e) {
    error_log("Error processing reminders: " . $e->getMessage());
} 