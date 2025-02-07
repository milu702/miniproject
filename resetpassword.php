<?php
session_start();
$error_message = '';

if (!isset($_SESSION['email'])) {
    header('Location: forgot.php');
    exit(); 
}
$conn= new mysqli('localhost','root','','growguide');
if ($conn->connect_error) {
    error_log("Connection failed: " . $conn->connect_error);
    $error_message = "An error occurred during login. Please try again later.";
} else {
    if($_SERVER["REQUEST_METHOD"]=="POST"){
        $password= $_POST['new_password'];
        $confirm_password= $_POST['confirm_password'];
        if($password==$confirm_password){
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $sql = "UPDATE users SET password = '$hashed_password' WHERE email = '" . $_SESSION['email'] . "'";
            
            if ($conn->query($sql) === TRUE) {
                $_SESSION['success_message'] = "Your password has been successfully updated!";
                header('Location: login.php');
                unset($_SESSION['email']);  
                exit();
            } else {
                $error_message = "Error updating password: " . $conn->error;
            }
            
    }
    
} 
}  

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskMate - Reset Password</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f0f7ff 0%, #ffffff 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }

        .login-container {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            animation: slideUp 0.5s ease-out;
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
            font-size: 2rem;
            font-weight: 700;
        }

        .task { color: #2563eb; }
        .mate { color: #3b82f6; }

        h2 {
            text-align: center;
            color: #1e293b;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #64748b;
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.9rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .login-btn {
            width: 100%;
            padding: 1rem;
            background: #2563eb;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .login-btn:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .error-message {
            color: #dc2626;
            font-size: 0.85rem;
            margin-top: 0.5rem;
            font-weight: 400;
        }

        .error-message1 {
            background-color: #fee2e2;
            color: #dc2626;
            padding: 0.75rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
        }
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <span class="task">Grow</span><span class="mate">Guide</span>
        </div>
        <h2>Reset Password</h2>
        <?php if (!empty($error_message)): ?>
            <div class="error-message1">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form id="resetForm" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="form-group">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required minlength="6">
                <div id="password-error" class="error-message"></div> <!-- Error message for password -->
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                <div id="cpassword-error" class="error-message"></div> <!-- Error message for confirm password -->
            </div>
            <button type="submit" class="login-btn">Reset Password</button>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
    const passwordError = document.getElementById('password-error');
    const cpasswordError = document.getElementById('cpassword-error');

    const passwordInput = document.getElementById('new_password');
    const cpasswordInput = document.getElementById('confirm_password');

    function checkPassword() {
        const password = passwordInput.value.trim();
        const passwordPattern = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[A-Za-z\d@$!%?&]{8,}$/;

        if (password === '') {
            passwordError.innerHTML = "Password is required";
            passwordInput.style.border = "2px solid red";
            return false;
        } else if (!passwordPattern.test(password)) {
            passwordError.innerHTML = "Password must be 8+ characters with uppercase, lowercase, and a number.";
            passwordInput.style.border = "2px solid red";
            return false;
        } else {
            passwordError.innerHTML = "";
            passwordInput.style.border = "2px solid green";
            return true;
        }
    }


            function checkConfirmPassword() {
                const password = passwordInput.value.trim();
                const confirmPassword = cpasswordInput.value.trim();
                
                // Confirm password should only be checked if both fields are filled
                if (confirmPassword === '') {
                    cpasswordError.innerHTML = "Please confirm your password";
                    cpasswordInput.style.border = "2px solid red";
                    return false;
                } else if (confirmPassword !== password) {
                    cpasswordError.innerHTML = "Passwords do not match";
                    cpasswordInput.style.border = "2px solid red";
                    return false;
                } else {
                    cpasswordError.innerHTML = "";
                    cpasswordInput.style.border = "2px solid green";
                    return true;
                }
            }

            passwordInput.addEventListener('input', function() {
            checkPassword();
            if (cpasswordInput.value !== '') {
                checkConfirmPassword();
            }
        });
        cpasswordInput.addEventListener('input', checkConfirmPassword);

            document.getElementById('resetForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                let isValid = true;
                if (!checkPassword()) isValid = false;
                if (!checkConfirmPassword()) isValid = false;
                
                if (isValid) {
                    console.log('Form is valid, submitting...');
                    this.submit();  
                } else {
                    console.log('Form has errors, not submitting.');
                }
            });
        });
    </script>
</body>
</html>