<?php
session_start();
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match("/@gmail\.com$/", $email)) {
        $error = "Invalid email. Only Gmail accounts (@gmail.com) are allowed.";
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        
        // Add error checking for prepare()
        if ($stmt === false) {
            $error = "Database preparation error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // Generate reset token
                $reset_token = bin2hex(random_bytes(32));
                $reset_token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Store reset token in database
                $stmt_update = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
                
                // Add error checking for prepare()
                if ($stmt_update === false) {
                    $error = "Database preparation error: " . $conn->error;
                } else {
                    $stmt_update->bind_param("sss", $reset_token, $reset_token_expiry, $email);
                    
                    if ($stmt_update->execute()) {
                        $success = "Password reset link has been sent to your email.";
                    } else {
                        $error = "Failed to generate reset token: " . $stmt_update->error;
                    }
                    $stmt_update->close();
                }
            } else {
                $error = "Email not found in our system.";
            }
            $stmt->close();
        }
    }
}
?>

<!-- Rest of the HTML remains the same as in the previous artifact -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Reset Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body {
            background: url('log.jpeg') no-repeat center center/cover;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            width: 350px;
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
            text-align: center;
            color: white;
        }
        .container h2 { font-size: 24px; margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; text-align: left; }
        label { font-weight: 600; }
        input {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: none;
            border-radius: 5px;
            outline: none;
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }
        input::placeholder { color: rgba(255, 255, 255, 0.8); }
        .btn-primary {
            width: 100%;
            padding: 10px;
            border: none;
            background: #ff9800;
            color: white;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-primary:hover { background: #e68900; }
        .text-center { margin-top: 15px; }
        .text-center a { color: #ff9800; text-decoration: none; }
        .text-center a:hover { text-decoration: underline; }
        .home-icon { position: fixed; top: 20px; left: 20px; color: white; text-decoration: none; }
        .error-message {
            background: rgba(255, 0, 0, 0.2);
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .success-message {
            background: rgba(0, 255, 0, 0.2);
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <a href="index.html" class="home-icon"><i class="fas fa-home"></i> Home</a>
    <div class="container">
        <h2>Reset Password</h2>
        <?php if (!empty($error)) echo "<div class='error-message'>$error</div>"; ?>
        <?php if (!empty($success)) echo "<div class='success-message'>$success</div>"; ?>
        
        <form method="POST">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token ?? ''); ?>">
            
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" placeholder="Enter new password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
            </div>
            <button type="submit" class="btn-primary">Reset Password</button>
        </form>
        <p class="text-center">Remember your password? <a href="login.php">Login</a></p>
    </div>
</body>
</html>