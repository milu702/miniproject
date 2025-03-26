<?php
session_start();

// Include the PHPMailer requirements
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

// Function to send email notification (same as in schedule.php)
function sendTaskNotification($userEmail, $taskTitle, $taskDate, $taskDescription, $priority) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'growguide593@gmail.com';
        $mail->Password   = 'dubv llyx bjvf zyyd';  // Your Gmail address
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('growguide593@gmail.com', 'GrowGuide');
        $mail->addAddress($userEmail);
        
        // Format date for better readability
        $formattedDate = date('l, F j, Y \a\t g:i A', strtotime($taskDate));
        
        // Priority label
        $priorityLabel = ucfirst($priority);
        $priorityIcon = ($priority == 'high') ? '🔴' : (($priority == 'medium') ? '🟠' : '🟢');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "GrowGuide: New Task Scheduled - $taskTitle";
        
        // Create an attractive HTML email body
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 5px;'>
            <div style='background-color: #2D5A27; padding: 15px; border-radius: 5px 5px 0 0;'>
                <h2 style='color: white; margin: 0;'>🌱 GrowGuide - Task Scheduled</h2>
            </div>
            
            <div style='padding: 20px; background-color: #f9f9f9;'>
                <p style='font-size: 16px;'>Hello Farmer,</p>
                
                <p style='font-size: 16px;'>A new farming task has been added to your schedule:</p>
                
                <div style='background-color: white; border-left: 4px solid #2D5A27; padding: 15px; margin: 15px 0; border-radius: 4px;'>
                    <h3 style='color: #2D5A27; margin-top: 0;'>$taskTitle</h3>
                    <p><strong>Date and Time:</strong> $formattedDate</p>
                    <p><strong>Priority:</strong> <span style='color: " . ($priority == 'high' ? '#dc3545' : ($priority == 'medium' ? '#28a745' : '#6c757d')) . ";'>$priorityIcon $priorityLabel</span></p>
                    <p><strong>Description:</strong><br>$taskDescription</p>
                </div>
                
                <p>This task has been added to your farming calendar. You can view and manage all your tasks in the GrowGuide Schedule section.</p>
                
                <div style='margin-top: 30px;'>
                    <p style='margin-bottom: 5px;'>Happy Farming!</p>
                    <p style='margin-top: 0;'><em>- The GrowGuide Team</em></p>
                </div>
            </div>
            
            <div style='padding: 15px; text-align: center; font-size: 12px; color: #666; border-top: 1px solid #e0e0e0;'>
                <p>This is an automated message from GrowGuide. Please do not reply to this email.</p>
            </div>
        </div>
        ";
        
        // Plain text alternative
        $mail->AltBody = "Task Scheduled: $taskTitle\nDate: $formattedDate\nPriority: $priorityLabel\nDescription: $taskDescription\n\nView your schedule in GrowGuide for more details.";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Task notification email failed: " . $mail->ErrorInfo);
        return false;
    }
}

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

    // Send notification email if requested
    if (isset($_POST['send_notification'])) {
        // Get user email
        $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if ($user && !empty($user['email'])) {
            sendTaskNotification($user['email'], $title, $date, $description, $priority);
        }
    }

    echo json_encode(['success' => true, 'task_id' => $task_id]);

} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 