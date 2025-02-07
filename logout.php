<?php
session_start();
require_once 'config.php';

$error = '';
$logout_message = '';

// Display a message if redirected from the logout page
if (isset($_GET['logout']) && $_GET['logout'] == 'success') {
    $logout_message = "You have been logged out successfully.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['username'] = $user['username'];
                
                // Redirect based on role
                switch($user['role']) {
                    case 'admin':
                        header("Location: admin/dashboard.php");
                        break;
                    case 'employee':
                        header("Location: employee/dashboard.php");
                        break;
                    case 'farmer':
                        header("Location: farmer/dashboard.php");
                        break;
                }
                exit();
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

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

        .container h2 {
            font-size: 24px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
            text-align: left;
        }

        label {
            font-weight: 600;
        }

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

        input::placeholder {
            color: rgba(255, 255, 255, 0.8);
        }

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

        .btn-primary:hover {
            background: #e68900;
        }

        .forgot-password {
            text-align: right;
            margin-top: 10px;
        }

        .forgot-password a, .signup a {
            color: #ff9800;
            text-decoration: none;
        }

        .forgot-password a:hover, .signup a:hover {
            text-decoration: underline;
        }

        .signup {
            margin-top: 15px;
        } .home-icon {
            position: fixed;
            top: 20px;
            left: 20px;
            color: white;
            text-decoration: none;
        }

        
    </style>
</head>
<body>
<a href="index.html" class="home-icon">
        <i class="fas fa-home"></i> Home
    </a>
    <div class="container">
        <h2>GrowGuide Login</h2>
        <form method="POST" >
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            <div class="forgot-password">
                <a href="forgot.php">Forgot Password?</a>
            </div>
            <button type="submit" class="btn-primary">Login</button>
        </form>
        <p class="signup">Don't have an account? <a href="signup.php">Sign Up</a></p>
    </div>
</body>
</html>
