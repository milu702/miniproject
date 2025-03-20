<?php
session_start();
require_once 'config.php';

$dbConfig = new DatabaseConfig();
$conn = $dbConfig->getConnection();

$message = '';
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token)) {
    die('No token provided');
}

// Verify token
$query = "SELECT id, email FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "s", $token);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    die('Invalid or expired token');
}

$user = mysqli_fetch_assoc($result);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($password !== $confirm_password) {
        $message = 'Passwords do not match';
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password and clear token
        $update_query = "UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($update_stmt, "si", $hashed_password, $user['id']);
        
        if (mysqli_stmt_execute($update_stmt)) {
            $message = 'Password updated successfully. You can now login.';
            header("Refresh: 3; URL=login.php");
        } else {
            $message = 'Error updating password';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - GrowGuide</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Add your existing styles here */
    </style>
</head>
<body>
    <div class="container">
        <h2>Set Your Password</h2>
        
        <?php if ($message): ?>
            <div class="alert <?php echo strpos($message, 'successfully') !== false ? 'alert-success' : 'alert-error'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" onsubmit="return validateForm()">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="password" required minlength="8">
            </div>
            
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required minlength="8">
            </div>
            
            <button type="submit">Set Password</button>
        </form>
    </div>

    <script>
        function validateForm() {
            const password = document.querySelector('input[name="password"]');
            const confirmPassword = document.querySelector('input[name="confirm_password"]');
            
            if (password.value !== confirmPassword.value) {
                alert('Passwords do not match');
                return false;
            }
            
            if (password.value.length < 8) {
                alert('Password must be at least 8 characters long');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html> 