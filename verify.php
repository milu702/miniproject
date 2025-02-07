<?php
session_start();
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $verification_code = trim($_POST['verification_code']);
    $stored_code = $_SESSION['verification_code'];  // Get the code from session

    if ($verification_code == $stored_code) {
        // Update user to mark them as verified
        $contact = $_SESSION['contact']; // Store contact information in session after sign-up
        $stmt = $conn->prepare("UPDATE users SET verified = 1 WHERE (email = ? OR phone = ?) AND verification_code = ?");
        $stmt->bind_param("sss", $contact, $contact, $stored_code);
        if ($stmt->execute()) {
            $success = "Verification successful! Redirecting to login...";
            header("refresh:3;url=login.php");
        } else {
            $error = "Verification failed. Please try again.";
        }
    } else {
        $error = "Invalid verification code. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code - GrowGuide</title>
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
            box-shadow: 0 0 15px rgba(85, 82, 82, 0.2);
            color: white;
            position: relative;
            text-align: left;
        }
        label { font-weight: 600; display: block; margin-top: 10px; }
        input {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: none;
            border-radius: 5px;
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }
        input::placeholder { color: black; }
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
            margin-top: 15px;
        }
        .btn-primary:hover { background: #e68900; }
        .home-icon {
            position: absolute;
            top: 10px;
            left: 10px;
            color: white;
            font-size: 20px;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="home-icon"><i class="fas fa-home"></i></a>
        <h2 style="text-align: center;">Verify Your Code</h2>
        <form method="POST">
            <label>Verification Code <span style="color: red;">*</span></label>
            <input type="text" name="verification_code" placeholder="Enter verification code" required>
            
            <button type="submit" class="btn-primary">Verify</button>

            <?php if ($error): ?>
                <div style="color: red; text-align: center; margin-top: 10px;"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div style="color: green; text-align: center; margin-top: 10px;"><?php echo $success; ?></div>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>
<h3>are you an employee?...signup here!!!</h3>