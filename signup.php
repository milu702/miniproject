<?php
session_start();
require_once 'config.php';

$dbConfig = new DatabaseConfig();
$conn = $dbConfig->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirmPassword'];
    $role = $_POST['role'];
    
    $errors = [];
    
    // Validation
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $errors[] = "Username must be 3-20 characters and contain only letters, numbers, and underscores";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Valid email is required";
    }
    
    if (empty($phone) || !preg_match('/^[6-9]\d{9}$/', $phone)) {
        $errors[] = "Valid phone number is required and must be 10 digits long starting with 6-9";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Check existing email only
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $errors[] = "Email already exists";
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Check if the issue is a database constraint by attempting the insert directly
        try {
            $stmt = $conn->prepare("INSERT INTO users (username, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $email, $phone, $hashed_password, $role);
            
            if ($stmt->execute()) {
                if ($role === 'employee') {
                    $_SESSION['temp_email'] = $email;
                    $_SESSION['temp_password'] = $password;
                    header("Location: employee_signup.php");
                    exit();
                } else {
                    $success = "Registration successful! Please login.";
                    header("refresh:3;url=login.php");
                }
            } else {
                // If there's a database constraint error, try a different approach
                if ($conn->errno == 1062) { // MySQL duplicate entry error code
                    // This is likely a duplicate username constraint at the database level
                    // We need to add a unique identifier to make it different
                    $timestamp = time();
                    $modified_username = $username . '_' . $timestamp;
                    
                    $stmt = $conn->prepare("INSERT INTO users (username, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $modified_username, $email, $phone, $hashed_password, $role);
                    
                    if ($stmt->execute()) {
                        if ($role === 'employee') {
                            $_SESSION['temp_email'] = $email;
                            $_SESSION['temp_password'] = $password;
                            header("Location: employee_signup.php");
                            exit();
                        } else {
                            $success = "Registration successful! Please login.";
                            header("refresh:3;url=login.php");
                        }
                    } else {
                        $error = "Registration failed. Please try again.";
                    }
                } else {
                    $error = "Registration failed. Please try again. Error: " . $conn->error;
                }
            }
        } catch (Exception $e) {
            $error = "Registration failed. Error: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Sign Up</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            animation: slideIn 0.5s ease-in-out;
        }

        .container h2 {
            font-size: 24px;
            margin-bottom: 20px;
        }

        .form-group {
            position: relative;
            margin-bottom: 25px;
        }

        .form-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.8);
            font-size: 18px;
            z-index: 1;
        }

        input {
            width: 100%;
            padding: 12px 12px 12px 45px;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid transparent;
            border-radius: 8px;
            color: white;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .validation-message {
            position: absolute;
            bottom: -20px;
            left: 0;
            font-size: 12px;
            color: #ff4444;
            background: rgba(0, 0, 0, 0.6);
            padding: 2px 8px;
            border-radius: 4px;
            display: none;
        }

        input.valid {
            border-color: #00c851;
        }

        input.invalid {
            border-color: #ff4444;
        }

        .validation-message.show {
            display: block;
        }

        /* Success icon styles */
        .form-group .success-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #00c851;
            display: none;
        }

        .form-group.valid .success-icon {
            display: block;
        }

        .role-group {
            position: relative;
            margin-bottom: 25px;
        }

        .role-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.8);
            font-size: 18px;
            z-index: 1;
        }

        select {
            width: 100%;
            padding: 12px 12px 12px 45px;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid transparent;
            border-radius: 8px;
            color: rgba(255, 255, 255, 0.9);
            font-size: 16px;
            transition: all 0.3s ease;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        select:focus {
            background: rgba(255, 255, 255, 0.3);
            box-shadow: 0 0 15px rgba(255, 152, 0, 0.3);
            outline: none;
        }

        select option {
            background: #2C3639;
            color: white;
        }

        .role-group:after {
            content: '\f107';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.8);
            pointer-events: none;
        }

        /* Style for the placeholder option */
        select option[value=""][disabled] {
            color: rgba(255, 255, 255, 0.9);
        }

        /* Hover effect for select */
        select:hover {
            background: rgba(255, 255, 255, 0.25);
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

        .text-center {
            margin-top: 15px;
        }

        .text-center a {
            color: #ff9800;
            text-decoration: none;
        }

        .text-center a:hover {
            text-decoration: underline;
        }

        .home-icon {
            position: fixed;
            top: 20px;
            left: 20px;
            color: white;
            text-decoration: none;
        }

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

        /* Add these new animation styles */
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Animation for form submission */
        @keyframes formSuccess {
            0% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0); }
        }

        .form-success {
            animation: formSuccess 0.5s ease;
        }

        /* Enhanced container animation */
        .container {
            animation: fadeInUp 0.8s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        input::placeholder {
            color: rgba(255, 255, 255, 0.9);
            opacity: 1; /* For Firefox */
        }

        /* For Edge */
        input::-ms-input-placeholder {
            color: rgba(255, 255, 255, 0.9);
        }

        /* For IE */
        input:-ms-input-placeholder {
            color: rgba(255, 255, 255, 0.9);
        }
    </style>
</head>
<body>
    <a href="index.html" class="home-icon">
        <i class="fas fa-home"></i> Home
    </a>
    <div class="container">
        <h2>GrowGuide Sign Up</h2>
        <?php if(!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if(!empty($success)): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" id="signupForm">
            <div class="form-group">
                <i class="fas fa-user"></i>
                <input type="text" id="username" name="username" placeholder="Username" required>
                <i class="fas fa-check-circle success-icon"></i>
                <div class="validation-message"></div>
            </div>
            
            <div class="form-group">
                <i class="fas fa-envelope"></i>
                <input type="email" id="email" name="email" placeholder="Email address" required>
                <i class="fas fa-check-circle success-icon"></i>
                <div class="validation-message"></div>
            </div>
            
            <div class="form-group">
                <i class="fas fa-phone"></i>
                <input type="tel" id="phone" name="phone" placeholder="Phone number" required>
                <i class="fas fa-check-circle success-icon"></i>
                <div class="validation-message"></div>
            </div>
            
            <div class="form-group">
                <i class="fas fa-lock"></i>
                <input type="password" id="password" name="password" placeholder="Password" required>
                <i class="fas fa-check-circle success-icon"></i>
                <div class="validation-message"></div>
            </div>
            
            <div class="form-group">
                <i class="fas fa-shield-alt"></i>
                <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm Password" required>
                <i class="fas fa-check-circle success-icon"></i>
                <div class="validation-message"></div>
            </div>
            
            <div class="role-group">
                <i class="fas fa-users-gear"></i>
                <select name="role" id="role" required>
                    <option value="" disabled selected>Select your role</option>
                    <option value="farmer">Farmer</option>
                </select>
            </div>
            
            <button type="submit" class="btn-primary">Sign Up</button>
        </form>
        
        <p class="text-center">
            Already have an account? <a href="login.php">Login</a>
        </p>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('signupForm');
        
        // Validation patterns
        const patterns = {
            username: /^[a-zA-Z0-9_]{3,20}$/,
            email: /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/,
            phone: /^[6-9]\d{9}$/,
            password: /^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$/
        };

        // Validation messages
        const messages = {
            username: 'Username must be 3-20 characters long and can contain letters, numbers, and underscore',
            email: 'Please enter a valid email address',
            phone: 'Phone number must start with 6-9 and be 10 digits long',
            password: 'Password must be at least 8 characters long and contain letters and numbers',
            confirmPassword: 'Passwords do not match'
        };

        // Live validation for all inputs
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', function() {
                if(this.id === 'confirmPassword') {
                    validateConfirmPassword();
                } else {
                    validateField(this);
                }
                
                // If password changes, revalidate confirm password
                if(this.id === 'password') {
                    const confirmPassword = document.getElementById('confirmPassword');
                    if(confirmPassword.value) {
                        validateConfirmPassword();
                    }
                }
            });
        });

        function validateField(field) {
            const pattern = patterns[field.id];
            const validationMessage = field.parentElement.querySelector('.validation-message');
            
            if (pattern) {
                if (pattern.test(field.value)) {
                    field.classList.remove('invalid');
                    field.classList.add('valid');
                    field.parentElement.classList.add('valid');
                    validationMessage.classList.remove('show');
                } else {
                    field.classList.remove('valid');
                    field.classList.add('invalid');
                    field.parentElement.classList.remove('valid');
                    validationMessage.textContent = messages[field.id];
                    validationMessage.classList.add('show');
                }
            }
        }

        function validateConfirmPassword() {
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirmPassword');
            const validationMessage = confirmPassword.parentElement.querySelector('.validation-message');

            if(confirmPassword.value === password.value && password.value !== '') {
                confirmPassword.classList.remove('invalid');
                confirmPassword.classList.add('valid');
                confirmPassword.parentElement.classList.add('valid');
                validationMessage.classList.remove('show');
                return true;
            } else {
                confirmPassword.classList.remove('valid');
                confirmPassword.classList.add('invalid');
                confirmPassword.parentElement.classList.remove('valid');
                validationMessage.textContent = messages.confirmPassword;
                validationMessage.classList.add('show');
                return false;
            }
        }

        // Form submission
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            document.querySelectorAll('input').forEach(input => {
                if (input.id === 'confirmPassword') {
                    if (!validateConfirmPassword()) {
                        isValid = false;
                    }
                } else if (patterns[input.id] && !patterns[input.id].test(input.value)) {
                    isValid = false;
                    validateField(input);
                }
            });

            if (!isValid) {
                e.preventDefault();
            }
        });
    });
    </script>
</body>
</html>