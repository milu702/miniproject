<?php
session_start();
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
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        // Check if username or email already exists
        $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
        $check_stmt = mysqli_prepare($conn, $check_query);
        
        if ($check_stmt === false) {
            $message = 'Error preparing statement: ' . mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($check_stmt, "ss", $username, $email);
            mysqli_stmt_execute($check_stmt);
            mysqli_stmt_store_result($check_stmt);
            
            if (mysqli_stmt_num_rows($check_stmt) > 0) {
                $message = 'Username or email already exists!';
            } else {
                // Insert new employee
                $insert_query = "INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'employee', 1)";
                $insert_stmt = mysqli_prepare($conn, $insert_query);
                
                if ($insert_stmt === false) {
                    $message = 'Error preparing insert statement: ' . mysqli_error($conn);
                } else {
                    mysqli_stmt_bind_param($insert_stmt, "sss", $username, $email, $password);
                    
                    if (mysqli_stmt_execute($insert_stmt)) {
                        $message = 'Employee added successfully!';
                        // Clear any POST data to prevent duplicate submissions
                        header("Location: " . $_SERVER['PHP_SELF']);
                        exit();
                    } else {
                        $message = 'Error adding employee: ' . mysqli_error($conn);
                    }
                    mysqli_stmt_close($insert_stmt);
                }
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
            transition: width 0.3s;
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
            transition: background 0.3s;
            margin: 5px 0;
        }

        .menu li:hover, .menu .active {
            background: var(--primary-dark);
            border-radius: 4px;
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
            transition: all 0.3s;
            box-sizing: border-box;
        }

        .form-group i {
            position: absolute;
            left: 12px;
            top: 42px;
            color: #666;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
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
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: background 0.3s;
            margin-top: 20px;
        }

        button[type="submit"]:hover {
            background: var(--primary-dark);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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
        }
        
        .status-badge.active {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-badge.inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
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
    
    document.body.appendChild(modal);
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
</script>
</body>
</html>