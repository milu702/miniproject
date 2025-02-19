<?php
session_start();
require_once 'config.php';

// Add these lines at the very top after session_start()
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'debug.log');

// Initialize error variable
$error = '';

// At the beginning of the file, after session_start()
if (isset($_SESSION['login_success'])) {
    unset($_SESSION['login_success']); // Clear the flag
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identifier = trim($_POST['identifier']);
    $password = $_POST['password'];
    
    try {
        if (!$conn) {
            throw new Exception("Database connection failed");
        }
        
        // Modified query to check both email and status
        $sql = "SELECT * FROM users WHERE email = ? AND status = 1";
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param("s", $identifier);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Add debug logging
            error_log("Login attempt - Email: " . $identifier . ", Role: " . $user['role']);
            
            if (password_verify($password, $user['password'])) {
                // Set all necessary session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['last_activity'] = time();
                
                // Store success message in session if needed
                $_SESSION['login_success'] = true;
                
                // Debug logging for successful login
                error_log("Login successful - User ID: " . $user['id'] . ", Role: " . $user['role']);
                
                // Specific handling for admin role with immediate redirect
                if ($user['role'] === 'admin') {
                    error_log("Redirecting admin to admin.php");
                    header("Location: admin.php", true, 303);
                    exit();
                }
                
                // Handle other roles with 303 See Other status code
                switch ($user['role']) {
                    case 'farmer':
                        header("Location: farmer.php", true, 303);
                        break;
                    case 'expert':
                        header("Location: expert_dashboard.php", true, 303);
                        break;
                    case 'employee':
                        header("Location: employe.php", true, 303);
                        break;
                    default:
                        header("Location: index.html", true, 303);
                        break;
                }
                exit();
            } else {
                error_log("Password verification failed for email: " . $identifier);
                $error = "Invalid password";
            }
        } else {
            error_log("No user found with email: " . $identifier);
            $error = "Email not found or account is inactive";
        }
        
        $stmt->close();
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $error = "An error occurred. Please try again.";
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
            background: rgba(255, 255, 255, 0.2);
            padding: 10px 15px;
            border-radius: 8px;
            backdrop-filter: blur(5px);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 3;
        }

        .home-icon:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .home-icon i {
            font-size: 18px;
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