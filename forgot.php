<?php
session_start();
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
   
    $conn = new mysqli('localhost', 'root', '', 'growguide');
        
    if ($conn->connect_error) {
        error_log("Connection failed: " . $conn->connect_error);
        $error_message = "An error occurred during login. Please try again later.";
    } else {
        
        $email = $conn->real_escape_string(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL)); 
        $sql = "SELECT * FROM users WHERE email='$email'";
        
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            echo'<form id="form" method="POST" action="send_otp.php">';
            echo '<input type="hidden" name="email" value="' . $email . '">';
            echo'</form>';
            echo'<script>document.getElementById("form").submit();</script>';

        } else {
            $error_message = "Invalid email";
        }
        
               $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Forgot Password</title>
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
        <h2>Forgot Password</h2>
        <?php if (!empty($error)) echo "<div class='error-message'>$error</div>"; ?>
        <?php if (!empty($success)) echo "<div class='success-message'>$success</div>"; ?>
        
        <form method="POST" action="#">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>
            <button type="submit" class="btn-primary">Reset Password</button>
        </form>
        <p class="text-center">Remember your password? <a href="login.php">Login</a></p>
    </div>
</body>
</html>