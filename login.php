<?php
session_start();
require_once 'config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identifier = trim($_POST['identifier']); // Now just email
    $password = $_POST['password'];
    
    if (empty($identifier) || empty($password)) {
        $error = "Please fill in all fields";
    } else {
        // Check only email
        $sql = "SELECT * FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $identifier);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['username'] = $user['username'];
                
                // Redirect based on role
                switch($user['role']) {
                    case 'admin':
                        header("Location: admin.php");
                        exit();
                    case 'employee':
                        header("Location: employe.php");
                        exit();
                    case 'farmer':
                        header("Location: farmer.php");
                        exit();
                    default:
                        // Fallback for unknown roles
                        header("Location: dashboard.php");
                        exit();
                }
            } else {
                $error = "Invalid credentials";
            }
        } else {
            $error = "Invalid credentials";
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
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5); /* Darker overlay */
            z-index: 1;
        }

        .container {
            position: relative;
            z-index: 2;
            width: 350px;
            background: rgba(255, 255, 255, 0.2);
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

        .error-message {
            background: rgba(255, 0, 0, 0.2);
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: <?php echo !empty($error) ? 'block' : 'none'; ?>;
        }

        .input-group {
            position: relative;
            margin-bottom: 15px;
        }

        .input-group i {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.8);
        }

        .input-group input {
            padding-left: 35px;
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
        }
        
        .home-icon {
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
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="identifier">Email</label>
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="identifier" name="identifier" placeholder="Enter your email address" required>
                </div>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
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