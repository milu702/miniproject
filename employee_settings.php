<?php
session_start();

// Ensure user is logged in and has the 'employee' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT 
        u.*,
        COALESCE( u.username) as employee_name,
        u.email,u.phone
       
    FROM users u
    LEFT JOIN employees e ON u.id = e.user_id
    WHERE u.id = ? AND u.role = 'employee'
");

if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();

$employeeName = isset($userData['employee_name']) ? htmlspecialchars($userData['employee_name']) : 'Employee';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Settings - GrowGuide</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2d6a4f;  /* Dark green */
            --secondary-color: #40916c; /* Medium green */
            --accent-color: #95d5b2;   /* Light green */
            --light-color: #ecf0f1;
            --dark-color: #1B4D1E;     /* Very dark green */
        }

        /* Copy all the base styles from employe.php */
        /* ... (include all the base styles from employe.php) ... */

        /* Add these employee-specific styles */
        .employee-info-card {
            background: linear-gradient(145deg, #ffffff, #f0f7ff);
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(43, 122, 48, 0.1);
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid rgba(43, 122, 48, 0.1);
            animation: fadeIn 0.5s ease-out;
        }

        .form-group {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(45, 106, 79, 0.08);  /* Green tinted shadow */
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .form-group:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(45, 106, 79, 0.12);  /* Darker green shadow on hover */
        }

        .input-group {
            position: relative;
            margin-bottom: 25px;
        }

        .input-group input {
            width: 100%;
            padding: 12px 40px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .input-group input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 10px rgba(45, 106, 79, 0.1);
        }

        .input-group i {
            position: absolute;
            left: 12px;
            top: 40px;
            color: var(--primary-color);
        }

        .submit-btn {
            background: linear-gradient(135deg, #2d6a4f, #40916c);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(45, 106, 79, 0.2);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
        }

        .alert-success {
            background-color: #d4edda;
            color: #2d6a4f;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .validation-message {
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .input-group input:focus {
            outline: none;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .welcome-header {
            color: var(--primary-color);
        }

        .running-message {
            background: linear-gradient(135deg, #2d6a4f, #40916c);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .validation-message i.fa-check-circle {
            color: #2d6a4f;
        }

        /* Add these new styles for better visual hierarchy */
        .form-group h3 {
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 25px;
            font-size: 1.2em;
        }

        .form-group h3 i {
            color: var(--primary-color);
            font-size: 1.2em;
        }

        .input-group label {
            color: #2d6a4f;
            font-weight: 500;
            margin-bottom: 8px;
            display: block;
        }

        /* Password requirements styling */
        .password-requirements {
            background: #f8f9fa;
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            margin: 15px 0;
            border-radius: 0 8px 8px 0;
        }

        .password-requirements ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .password-requirements li {
            color: #2d6a4f;
            margin: 5px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .password-requirements li i {
            color: var(--primary-color);
        }

        .back-to-dashboard {
            margin-bottom: 20px;
        }

        .dashboard-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            border: 2px solid var(--primary-color);
            border-radius: 8px;
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .dashboard-link:hover {
            background: var(--primary-color);
            color: white;
            transform: translateX(-5px);
            box-shadow: 0 4px 12px rgba(45, 106, 79, 0.2);
        }

        .dashboard-link i {
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <!-- Sidebar (copy from employe.php) -->
    <div class="sidebar">
        <div class="sidebar-logo">
            <i class="fas fa-leaf"></i>
            <h1>GrowGuide</h1>
        </div>
        
        

    <div class="content">
        <div class="back-to-dashboard">
            <a href="employe.php" class="dashboard-link">
                <i class="fas fa-arrow-left"></i>
                <span>Back to Dashboard</span>
            </a>
        </div>

        <div class="running-message">
            <div class="message-content">
                <div class="welcome-header">
                    <i class="fas fa-user-cog"></i>
                    <h2>Account Settings</h2>
                </div>
                <div class="message-body">
                    <p>Manage your profile and preferences</p>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php 
                echo $_SESSION['success']; 
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php 
                echo $_SESSION['error']; 
                unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <div class="employee-info-card">
            <form action="update_employee_settings.php" method="POST" class="data-form" id="settingsForm" onsubmit="return validateForm()">
                <div class="form-grid">
                    <div class="form-group">
                        <h3><i class="fas fa-user-circle"></i> Profile Information</h3>
                        <div class="input-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" value="<?php echo $employeeName; ?>" required>
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="input-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="input-group">
                            <label for="phone">Phone</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>" pattern="[0-9]{10}">
                            <i class="fas fa-phone"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <h3><i class="fas fa-shield-alt"></i> Security</h3>
                        <div class="input-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="input-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password">
                            <i class="fas fa-key"></i>
                        </div>
                        <div class="input-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>

                <div class="button-group">
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('settingsForm');
            const currentPassword = document.getElementById('current_password');
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            
            // Add validation message elements
            function addValidationMessage(inputElement) {
                const messageDiv = document.createElement('div');
                messageDiv.className = 'validation-message';
                messageDiv.style.color = '#dc3545';
                messageDiv.style.fontSize = '0.8rem';
                messageDiv.style.marginTop = '5px';
                inputElement.parentNode.appendChild(messageDiv);
                return messageDiv;
            }

            const newPasswordMessage = addValidationMessage(newPassword);
            const confirmPasswordMessage = addValidationMessage(confirmPassword);

            // Password requirements
            const requirements = {
                length: 8,
                hasUpper: /[A-Z]/,
                hasLower: /[a-z]/,
                hasNumber: /[0-9]/,
                hasSpecial: /[!@#$%^&*]/
            };

            // Live validation for new password
            newPassword.addEventListener('input', function() {
                const value = this.value;
                let errors = [];

                if (value.length < requirements.length) {
                    errors.push(`At least ${requirements.length} characters`);
                }
                if (!requirements.hasUpper.test(value)) {
                    errors.push('One uppercase letter');
                }
                if (!requirements.hasLower.test(value)) {
                    errors.push('One lowercase letter');
                }
                if (!requirements.hasNumber.test(value)) {
                    errors.push('One number');
                }
                if (!requirements.hasSpecial.test(value)) {
                    errors.push('One special character (!@#$%^&*)');
                }

                if (errors.length > 0) {
                    newPasswordMessage.innerHTML = `
                        <i class="fas fa-exclamation-circle"></i>
                        Password needs: ${errors.join(', ')}
                    `;
                    newPassword.style.borderColor = '#dc3545';
                } else {
                    newPasswordMessage.innerHTML = `
                        <i class="fas fa-check-circle" style="color: #28a745;"></i>
                        Password meets all requirements
                    `;
                    newPasswordMessage.style.color = '#28a745';
                    newPassword.style.borderColor = '#28a745';
                }
            });

            // Live validation for confirm password
            confirmPassword.addEventListener('input', function() {
                if (this.value !== newPassword.value) {
                    confirmPasswordMessage.innerHTML = `
                        <i class="fas fa-exclamation-circle"></i>
                        Passwords do not match
                    `;
                    confirmPassword.style.borderColor = '#dc3545';
                } else {
                    confirmPasswordMessage.innerHTML = `
                        <i class="fas fa-check-circle" style="color: #28a745;"></i>
                        Passwords match
                    `;
                    confirmPasswordMessage.style.color = '#28a745';
                    confirmPassword.style.borderColor = '#28a745';
                }
            });

            // Form submission handling
            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                // Reset previous error messages
                const existingAlert = document.querySelector('.alert');
                if (existingAlert) {
                    existingAlert.remove();
                }

                // Create alert function
                function showAlert(message, type) {
                    const alert = document.createElement('div');
                    alert.className = `alert alert-${type}`;
                    alert.innerHTML = `<i class="fas fa-${type === 'success' ? 'check' : 'exclamation'}-circle"></i> ${message}`;
                    form.insertBefore(alert, form.firstChild);
                }

                // Validate current password if trying to change password
                if (newPassword.value && !currentPassword.value) {
                    showAlert('Current password is required to set a new password', 'error');
                    return;
                }

                // If new password is provided, validate it
                if (newPassword.value) {
                    let isValid = true;
                    const value = newPassword.value;

                    if (value.length < requirements.length ||
                        !requirements.hasUpper.test(value) ||
                        !requirements.hasLower.test(value) ||
                        !requirements.hasNumber.test(value) ||
                        !requirements.hasSpecial.test(value)) {
                        isValid = false;
                    }

                    if (!isValid) {
                        showAlert('Please ensure the new password meets all requirements', 'error');
                        return;
                    }

                    if (newPassword.value !== confirmPassword.value) {
                        showAlert('New passwords do not match', 'error');
                        return;
                    }
                }

                // Submit form
                try {
                    const formData = new FormData(form);
                    const response = await fetch('update_employee_settings.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        showAlert(result.message || 'Settings updated successfully!', 'success');
                        // Reset password fields
                        currentPassword.value = '';
                        newPassword.value = '';
                        confirmPassword.value = '';
                        // Reset validation messages
                        newPasswordMessage.innerHTML = '';
                        confirmPasswordMessage.innerHTML = '';
                        // Reset border colors
                        newPassword.style.borderColor = '';
                        confirmPassword.style.borderColor = '';
                    } else {
                        showAlert(result.message || 'Failed to update settings', 'error');
                        if (result.error === 'incorrect_password') {
                            currentPassword.style.borderColor = '#dc3545';
                            const currentPasswordMessage = addValidationMessage(currentPassword);
                            currentPasswordMessage.innerHTML = `
                                <i class="fas fa-exclamation-circle"></i>
                                Current password is incorrect
                            `;
                        }
                    }
                } catch (error) {
                    showAlert('An error occurred while updating settings', 'error');
                }
            });
        });
    </script>
</body>
</html>