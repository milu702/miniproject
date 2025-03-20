<?php
session_start();

// Ensure user is logged in and has the 'farmer' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Initialize response array for AJAX requests
$response = array('success' => false, 'message' => '');

// Clear any output buffers
ob_clean();

// Set JSON header
header('Content-Type: application/json');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

// Get current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();

try {
    // Start transaction
    $conn->begin_transaction();

    // Validate and sanitize input data
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    
    // Basic validation
    if (empty($name) || empty($email)) {
        throw new Exception("Name and email are required fields.");
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format.");
    }

    if (!empty($phone) && !preg_match("/^[0-9]{10}$/", $phone)) {
        throw new Exception("Invalid phone number format.");
    }

    // Update users table
    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
    $stmt->bind_param("ssi", $name, $email, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Error updating user information.");
    }

    // Update farmers table
    $stmt = $conn->prepare("UPDATE farmers SET phone = ?, username = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $phone, $name, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Error updating farmer information.");
    }

    // Handle password update if provided
    if (!empty($_POST['current_password']) && !empty($_POST['new_password'])) {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (!password_verify($_POST['current_password'], $user['password'])) {
            throw new Exception("Current password is incorrect.");
        }

        // Update password
        $new_password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $new_password_hash, $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating password.");
        }
    }

    // Commit transaction
    $conn->commit();

    // Update session variables
    $_SESSION['username'] = $name;

    // Track changes for email notification
    $changes = [];
    if ($name !== $userData['username']) {
        $changes['name'] = $name;
    }
    if ($email !== $userData['email']) {
        $changes['email'] = $email;
    }
    if (isset($phone) && $phone !== $userData['phone']) {
        $changes['phone'] = $phone;
    }
    if (!empty($_POST['new_password'])) {
        $changes['password'] = 'updated';
    }

    // Send email notification if there are changes
    if (!empty($changes)) {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'milujiji702@gmail.com';
            $mail->Password = 'dglt rbly eujw zstx';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Recipients
            $mail->setFrom('milujiji702@gmail.com', 'GrowGuide');
            $mail->addAddress($email, $name);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Your GrowGuide Account Settings Have Been Updated';

            // Email body
            $emailBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <div style='background: #2D5A27; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0;'>
                    <h1>🌱 GrowGuide Account Update</h1>
                </div>
                
                <div style='background: #f9f9f9; padding: 20px; border-radius: 0 0 10px 10px;'>
                    <h2>Hello {$name},</h2>
                    <p>Your GrowGuide account settings have been successfully updated.</p>
                    
                    <div style='background: white; padding: 15px; border-radius: 8px; margin: 15px 0;'>
                        <h3>Changes Made:</h3>";

            foreach ($changes as $field => $value) {
                $field = ucfirst(str_replace('_', ' ', $field));
                $emailBody .= "<p>✅ <strong>{$field}</strong> has been updated</p>";
            }

            $emailBody .= "
                    </div>
                    
                    <div style='background: #FFF3CD; border: 1px solid #FFE69C; color: #856404; padding: 10px; border-radius: 5px; margin: 15px 0;'>
                        <p>🔔 If you didn't make these changes, please contact us immediately.</p>
                    </div>

                    <p style='text-align: center;'>
                        <a href='http://localhost/mini/login.php' 
                           style='display: inline-block; padding: 10px 20px; background: #2D5A27; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0;'>
                            Login to Your Account
                        </a>
                    </p>
                </div>
                
                <div style='text-align: center; margin-top: 20px; color: #666; font-size: 12px;'>
                    <p>This is an automated message from GrowGuide. Please do not reply.</p>
                    <p>© " . date('Y') . " GrowGuide. All rights reserved.</p>
                </div>
            </div>";

            $mail->Body = $emailBody;
            $mail->send();
        } catch (Exception $e) {
            error_log("Failed to send settings update email: " . $mail->ErrorInfo);
        }
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Settings updated successfully',
        'updatedData' => [
            'name' => $name,
            'email' => $email,
            'phone' => $phone
        ]
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

exit; 