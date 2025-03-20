<?php
session_start();

// Add these PHPMailer requirements at the top
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Add these requires before your config.php
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

require_once 'config.php';

// Initialize database connection
$dbConfig = new DatabaseConfig();
$conn = $dbConfig->getConnection();

// Get employees data from database
$employees = [];
$query = "SELECT id, username, email, status, role 
          FROM users 
          WHERE role = 'employee'
          ORDER BY username";

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $employees[] = $row;
    }
    mysqli_free_result($result);
}

// Form processing
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_employee'])) {
        $username = mysqli_real_escape_string($conn, $_POST['username']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $password = $_POST['password'];
        $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        // Check if username or email already exists
        $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        
        if ($check_stmt === false) {
            $message = 'Error preparing statement: ' . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($check_stmt, "ss", $username, $email);
            
            if (!mysqli_stmt_execute($check_stmt)) {
                $message = 'Error executing check query: ' . mysqli_error($conn);
            } else {
                $check_result = mysqli_stmt_get_result($check_stmt);
                
                if (mysqli_num_rows($check_result) > 0) {
                    $message = 'Username or email already exists!';
                } else {
                    // Insert new employee
                    $insert_query = "INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'employee', 1)";
                    $insert_stmt = mysqli_prepare($conn, $insert_query);
                    
                    if ($insert_stmt === false) {
                        $message = 'Error preparing insert statement: ' . mysqli_error($conn);
                    } else {
                        mysqli_stmt_bind_param($insert_stmt, "sss", $username, $email, $hashed_password);
                        
                        if (mysqli_stmt_execute($insert_stmt)) {
                            // Send welcome email to new employee
                            try {
                                $mail = new PHPMailer(true);
                                
                                // Server settings
                                $mail->isSMTP();
                                $mail->Host       = 'smtp.gmail.com';
                                $mail->SMTPAuth   = true;
                                $mail->Username   = 'milujiji702@gmail.com';
                                $mail->Password   = 'dglt rbly eujw zstx';
                                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                                $mail->Port       = 587;

                                // Recipients
                                $mail->setFrom('milujiji702@gmail.com', 'GrowGuide Admin');
                                $mail->addAddress($email, $username);
                                
                                // Generate password reset token
                                $reset_token = bin2hex(random_bytes(32));
                                $token_expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
                                
                                // Store reset token in database
                                $token_query = "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?";
                                $token_stmt = mysqli_prepare($conn, $token_query);
                                mysqli_stmt_bind_param($token_stmt, "sss", $reset_token, $token_expiry, $email);
                                mysqli_stmt_execute($token_stmt);

                                // Content
                                $mail->isHTML(true);
                                $mail->Subject = 'Welcome to GrowGuide - Your Account Details';
                                
                                $reset_link = "http://yourdomain.com/reset-password.php?token=" . $reset_token;
                                
                                $mail->Body = "
                                <html>
                                <head>
                                    <style>
                                        body { font-family: Arial, sans-serif; line-height: 1.6; }
                                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                                        .header { background: #2D5A27; color: white; padding: 20px; text-align: center; }
                                        .content { padding: 20px; background: #f9f9f9; }
                                        .button { 
                                            display: inline-block;
                                            padding: 10px 20px;
                                            background: #2D5A27;
                                            color: white;
                                            text-decoration: none;
                                            border-radius: 5px;
                                            margin: 20px 0;
                                        }
                                        .footer { text-align: center; margin-top: 20px; color: #666; }
                                        .credentials { 
                                            background: #f0f0f0; 
                                            padding: 15px; 
                                            border-radius: 5px; 
                                            margin: 15px 0;
                                        }
                                    </style>
                                </head>
                                <body>
                                    <div class='container'>
                                        <div class='header'>
                                            <h1>Welcome to GrowGuide!</h1>
                                        </div>
                                        
                                        <div class='content'>
                                            <h2>Hello $username,</h2>
                                            
                                            <p>Welcome to the GrowGuide team! Your employee account has been created successfully.</p>
                                            
                                            <div class='credentials'>
                                                <p><strong>Your login credentials:</strong></p>
                                                <ul>
                                                    <li>Username: $username</li>
                                                    <li>Email: $email</li>
                                                    <li>Temporary Password: $password</li>
                                                </ul>
                                            </div>
                                            
                                            <p><strong>Important Security Notice:</strong></p>
                                            <ul>
                                                <li>Please login using this link: <a href='http://localhost/mini/login.php'>GrowGuide Login</a></li>
                                                <li>For security reasons, please change your password after your first login</li>
                                                <li>Keep your credentials confidential</li>
                                                <li>Never share your password with anyone</li>
                                            </ul>
                                            
                                            <p>If you have any questions or need assistance, please don't hesitate to contact the admin team.</p>
                                        </div>
                                        
                                        <div class='footer'>
                                            <p>This is an automated message, please do not reply.</p>
                                            <p>© " . date('Y') . " GrowGuide. All rights reserved.</p>
                                        </div>
                                    </div>
                                </body>
                                </html>";

                                if($mail->send()) {
                                    $message = 'Employee added successfully and welcome email sent!';
                                } else {
                                    $message = 'Employee added but failed to send welcome email.';
                                }
                                
                            } catch (Exception $e) {
                                $message = "Employee added but email could not be sent. Error: {$mail->ErrorInfo}";
                                error_log("Email sending failed: " . $mail->ErrorInfo);
                            }
                            
                            header("Location: " . $_SERVER['PHP_SELF']);
                            exit();
                        } else {
                            $message = 'Error adding employee: ' . mysqli_error($conn);
                        }
                        mysqli_stmt_close($insert_stmt);
                    }
                }
                mysqli_free_result($check_result);
            }
            mysqli_stmt_close($check_stmt);
        }
    }

    if (isset($_POST['update_status'])) {
        $employee_id = $_POST['employee_id'];
        $new_status = $_POST['status'];
        
        $update_query = "UPDATE users SET status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "ii", $new_status, $employee_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = 'Employee status updated successfully!';
        } else {
            $message = 'Error updating employee status!';
        }
        mysqli_stmt_close($stmt);
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    if (isset($_POST['delete_employee'])) {
        $employee_id = mysqli_real_escape_string($conn, $_POST['employee_id']);
        
        $delete_query = "DELETE FROM users WHERE id = ? AND role = 'employee'";
        $stmt = mysqli_prepare($conn, $delete_query);
        mysqli_stmt_bind_param($stmt, "i", $employee_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = 'Employee deleted successfully!';
        } else {
            $message = 'Error deleting employee!';
        }
        mysqli_stmt_close($stmt);
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    if (isset($_POST['edit_employee'])) {
        $employee_id = mysqli_real_escape_string($conn, $_POST['employee_id']);
        $username = mysqli_real_escape_string($conn, $_POST['edit_username']);
        $email = mysqli_real_escape_string($conn, $_POST['edit_email']);
        
        $update_query = "UPDATE users SET username = ?, email = ? WHERE id = ? AND role = 'employee'";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "ssi", $username, $email, $employee_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = 'Employee updated successfully!';
        } else {
            $message = 'Error updating employee!';
        }
        mysqli_stmt_close($stmt);
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Employee Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary-color: #2e7d32;
            --primary-dark: #1b5e20;
            --error-color: #dc3545;
            --success-color: #28a745;
            --transition-speed: 0.3s;
            --hover-scale: 1.02;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            background: #f5f5f5;
        }

        .sidebar {
            width: 250px;
            background: var(--primary-color);
            color: white;
            height: 100vh;
            padding: 10px;
            position: fixed;
            transition: all var(--transition-speed);
        }

        .sidebar-header {
            font-size: 20px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header i {
            font-size: 22px;
        }

        .menu {
            list-style: none;
            padding: 0;
            margin: 10px 0;
        }

        .menu li {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: all var(--transition-speed);
            margin: 5px 0;
            position: relative;
        }

        .menu li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: white;
            transform: scaleY(0);
            transition: transform var(--transition-speed);
        }

        .menu li:hover::before,
        .menu li.active::before {
            transform: scaleY(1);
        }

        .menu li a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 15px;
            width: 100%;
        }

        .collapsed {
            width: 70px;
        }

        .collapsed .menu li {
            justify-content: center;
        }

        .collapsed .menu li span {
            display: none;
        }

        .content {
            margin-left: 260px;
            padding: 20px;
            transition: margin-left 0.3s;
            width: calc(100% - 260px);
        }

        .collapsed + .content {
            margin-left: 90px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            animation: fadeIn 0.5s ease-out;
            transition: transform var(--transition-speed);
        }

        .form-grid:hover {
            transform: scale(var(--hover-scale));
        }

        .form-group {
            position: relative;
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px 40px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: all var(--transition-speed);
            box-sizing: border-box;
        }

        .form-group i {
            position: absolute;
            left: 12px;
            top: 42px;
            color: #666;
            transition: all var(--transition-speed);
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(46, 125, 50, 0.15);
        }

        .error-message {
            color: var(--error-color);
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .form-group.error input {
            border-color: var(--error-color);
        }

        .form-group.error .error-message {
            display: block;
        }

        button[type="submit"] {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all var(--transition-speed);
            margin-top: 20px;
            position: relative;
            overflow: hidden;
        }

        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(46, 125, 50, 0.2);
        }

        button[type="submit"]::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }

        button[type="submit"]:hover::after {
            animation: ripple 1s ease-out;
        }

        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            100% {
                transform: scale(100, 100);
                opacity: 0;
            }
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            animation: fadeIn 0.7s ease-out;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        td button {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
            transition: background 0.3s;
        }

        td button:first-child {
            background: #4caf50;
            color: white;
        }

        td button:last-child {
            background: #f44336;
            color: white;
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .toggle-btn {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 10px;
            margin-top: 20px;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            transition: all var(--transition-speed);
        }
        
        .status-badge.active {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);
        }
        
        .status-badge.inactive {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.2);
        }
        .admin-dashboard-link {
    position: fixed;
    top: 20px;
    right: 20px;
    background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
    color: white;
    padding: 12px 24px;
    border-radius: 50px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
    box-shadow: 0 4px 15px rgba(46, 125, 50, 0.2);
    transition: all 0.3s ease;
    z-index: 1000;
    border: 2px solid rgba(255, 255, 255, 0.1);
}

.admin-dashboard-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(46, 125, 50, 0.3);
    background: linear-gradient(135deg, #33873b 0%, #1e6823 100%);
}

.admin-dashboard-link i {
    font-size: 20px;
}

.admin-dashboard-link .icon-container {
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    width: 32px;
    height: 32px;
    transition: all 0.3s ease;
}

.admin-dashboard-link:hover .icon-container {
    transform: rotate(360deg);
    background: rgba(255, 255, 255, 0.2);
}

.admin-dashboard-link .text {
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

@media (max-width: 768px) {
    .admin-dashboard-link {
        padding: 10px 16px;
    }
    
    .admin-dashboard-link .text {
        display: none;
    }
    
    .admin-dashboard-link .icon-container {
        width: 28px;
        height: 28px;
    }
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes slideIn {
    from { transform: translateX(-20px); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.form-grid {
    animation: fadeIn 0.5s ease-out;
    transition: transform var(--transition-speed);
}

.form-grid:hover {
    transform: scale(var(--hover-scale));
}

.form-group input {
    transition: all var(--transition-speed);
}

.form-group input:focus {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(46, 125, 50, 0.15);
}

button[type="submit"] {
    transition: all var(--transition-speed);
}

button[type="submit"]:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(46, 125, 50, 0.2);
}

button[type="submit"]::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 5px;
    height: 5px;
    background: rgba(255, 255, 255, 0.5);
    opacity: 0;
    border-radius: 100%;
    transform: scale(1, 1) translate(-50%);
    transform-origin: 50% 50%;
}

button[type="submit"]:hover::after {
    animation: ripple 1s ease-out;
}

@keyframes ripple {
    0% {
        transform: scale(0, 0);
        opacity: 0.5;
    }
    100% {
        transform: scale(100, 100);
        opacity: 0;
    }
}

table {
    animation: fadeIn 0.7s ease-out;
}

tr {
    transition: all var(--transition-speed);
}

tr:hover {
    background-color: rgba(46, 125, 50, 0.05);
    transform: scale(var(--hover-scale));
}

.status-badge {
    transition: all var(--transition-speed);
}

.status-badge.active {
    background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);
}

.status-badge.inactive {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.2);
}

.sidebar {
    transition: all var(--transition-speed);
}

.menu li {
    transition: all var(--transition-speed);
    position: relative;
}

.menu li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 3px;
    background: white;
    transform: scaleY(0);
    transition: transform var(--transition-speed);
}

.menu li:hover::before,
.menu li.active::before {
    transform: scaleY(1);
}

.menu li i {
    transition: all var(--transition-speed);
}

.menu li:hover i {
    transform: translateX(5px) rotate(5deg);
}

.form-group i {
    transition: all var(--transition-speed);
}

.form-group input:focus + i {
    color: var(--primary-color);
    transform: scale(1.1);
}
    </style>
</head>
<body>
<a href="admin.php" class="admin-dashboard-link">
    <div class="icon-container">
        <i class="fas fa-user-shield"></i>
    </div>
    <span class="text">Admin Dashboard</span>
</a>
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <i class="fas fa-seedling"></i>
        <span>GrowGuide</span>
    </div>
    <ul class="menu">
        <li>
            <a href="admin.php">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <li>
            <a href="farmers.php">
                <i class="fas fa-users"></i>
                <span>Farmers</span>
            </a>
        </li>
        <li class="active">
            <a href="ad_employees.php">
                <i class="fas fa-user-tie"></i>
                <span>Employees</span>
            </a>
        </li>
    </ul>
    <button class="toggle-btn" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
</div>

<div class="content">
    <h2>Employee Management</h2>
    <?php if ($message): ?>
    <div class="alert alert-success">
        <?php echo $message; ?>
    </div>
    <?php endif; ?>
    
    <form method="POST" onsubmit="return validateForm()">
        <div class="form-grid">
            <div class="form-group">
                <label><i class="fas fa-user-circle"></i> Username</label>
                <input type="text" name="username" required pattern="[a-zA-Z0-9_]{3,20}">
                <i class="fas fa-user-circle"></i>
                <div class="error-message"></div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email</label>
                <input type="email" name="email" required>
                <i class="fas fa-envelope"></i>
                <div class="error-message"></div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <input type="password" name="password" required minlength="8">
                <i class="fas fa-lock"></i>
                <div class="error-message"></div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Confirm Password</label>
                <input type="password" name="confirm_password" required minlength="8">
                <i class="fas fa-lock"></i>
                <div class="error-message"></div>
            </div>
        </div>
        
        <button type="submit" name="add_employee">Add Employee</button>
    </form>
    
    <table>
        <thead>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($employees)): ?>
                <tr>
                    <td colspan="4" style="text-align: center;">No employees registered yet</td>
                </tr>
            <?php else: ?>
                <?php foreach ($employees as $employee): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($employee['username']); ?></td>
                        <td><?php echo htmlspecialchars($employee['email']); ?></td>
                        <td>
                            <span class="status-badge <?php echo $employee['status'] ? 'active' : 'inactive'; ?>">
                                <?php echo $employee['status'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <button onclick="editEmployee(<?php echo $employee['id']; ?>)">Edit</button>
                            <button onclick="deleteEmployee(<?php echo $employee['id']; ?>)">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function validateForm() {
    let isValid = true;
    const username = document.querySelector('input[name="username"]');
    const email = document.querySelector('input[name="email"]');
    const password = document.querySelector('input[name="password"]');
    const confirmPassword = document.querySelector('input[name="confirm_password"]');
    
    // Reset error states
    document.querySelectorAll('.form-group').forEach(group => {
        group.classList.remove('error');
        group.querySelector('.error-message').textContent = '';
    });
    
    // Username validation
    if (!username.value.match(/^[a-zA-Z0-9_]{3,20}$/)) {
        showError(username, 'Username must be 3-20 characters and contain only letters, numbers, and underscores');
        isValid = false;
    }
    
    // Email validation
    if (!email.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
        showError(email, 'Please enter a valid email address');
        isValid = false;
    }
    
    // Password validation
    if (password.value.length < 8) {
        showError(password, 'Password must be at least 8 characters long');
        isValid = false;
    }
    
    // Password confirmation
    if (password.value !== confirmPassword.value) {
        showError(confirmPassword, 'Passwords do not match');
        isValid = false;
    }
    
    return isValid;
}

function showError(input, message) {
    const formGroup = input.closest('.form-group');
    formGroup.classList.add('error');
    formGroup.querySelector('.error-message').textContent = message;
}

function setupFieldValidation() {
    const fields = {
        username: {
            element: document.querySelector('input[name="username"]'),
            validate: (value) => {
                if (!value) return 'Username is required';
                if (!value.match(/^[a-zA-Z0-9_]{3,20}$/)) {
                    return 'Username must be 3-20 characters and contain only letters, numbers, and underscores';
                }
                return '';
            }
        },
        email: {
            element: document.querySelector('input[name="email"]'),
            validate: (value) => {
                if (!value) return 'Email is required';
                if (!value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                    return 'Please enter a valid email address';
                }
                return '';
            }
        },
        password: {
            element: document.querySelector('input[name="password"]'),
            validate: (value) => {
                if (!value) return 'Password is required';
                if (value.length < 8) return 'Password must be at least 8 characters long';
                return '';
            }
        },
        confirm_password: {
            element: document.querySelector('input[name="confirm_password"]'),
            validate: (value) => {
                if (!value) return 'Please confirm your password';
                const password = document.querySelector('input[name="password"]').value;
                if (value !== password) return 'Passwords do not match';
                return '';
            }
        }
    };

    // Add blur event listeners to all fields
    Object.entries(fields).forEach(([fieldName, field]) => {
        field.element.addEventListener('blur', () => {
            const error = field.validate(field.element.value);
            const formGroup = field.element.closest('.form-group');
            
            if (error) {
                formGroup.classList.add('error');
                formGroup.querySelector('.error-message').textContent = error;
            } else {
                formGroup.classList.remove('error');
                formGroup.querySelector('.error-message').textContent = '';
            }
        });

        // Clear error on focus
        field.element.addEventListener('focus', () => {
            const formGroup = field.element.closest('.form-group');
            formGroup.classList.remove('error');
            formGroup.querySelector('.error-message').textContent = '';
        });
    });
}

// Call setupFieldValidation when the document loads
document.addEventListener('DOMContentLoaded', setupFieldValidation);

function editEmployee(employeeId) {
    const row = event.target.closest('tr');
    const username = row.cells[0].textContent;
    const email = row.cells[1].textContent;
    
    // Create modal for editing
    const modal = document.createElement('div');
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    `;
    
    modal.innerHTML = `
        <div style="background: white; padding: 20px; border-radius: 8px; width: 400px;">
            <h3 style="margin-bottom: 20px;">Edit Employee</h3>
            <form method="POST" id="editForm">
                <input type="hidden" name="employee_id" value="${employeeId}">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="edit_username" value="${username}" required>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="edit_email" value="${email}" required>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" name="edit_employee">Save Changes</button>
                    <button type="button" onclick="this.closest('div').parentElement.remove()">Cancel</button>
                </div>
            </form>
        </div>
    `;
    
    modal.style.opacity = '0';
    document.body.appendChild(modal);
    
    requestAnimationFrame(() => {
        modal.style.opacity = '1';
        modal.style.transition = 'opacity 0.3s ease';
    });
}

function deleteEmployee(employeeId) {
    if (confirm('Are you sure you want to delete this employee?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="employee_id" value="${employeeId}">
            <input type="hidden" name="delete_employee" value="1">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

// Existing toggleSidebar function remains unchanged
function toggleSidebar() {
    // ... existing code ...
}

// Add smooth scrolling animation
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelector(this.getAttribute('href')).scrollIntoView({
            behavior: 'smooth'
        });
    });
});

// Add loading animation
window.addEventListener('load', () => {
    document.body.style.opacity = '0';
    document.body.style.transition = 'opacity 0.5s ease';
    requestAnimationFrame(() => {
        document.body.style.opacity = '1';
    });
});
</script>
</body>
</html>