<?php
session_start();

// Ensure user is logged in and has the 'farmer' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT 
        u.*,  -- This will select all fields from users table
        COALESCE(f.username, u.username) as farmer_name,
        f.phone,
        f.notification_preferences
        
        
       
    FROM users u
    LEFT JOIN farmers f ON u.id = f.user_id
    WHERE u.id = ? AND u.role = 'farmer'
");

if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$userData = $stmt->get_result()->fetch_assoc();

$farmerName = isset($userData['farmer_name']) ? htmlspecialchars($userData['farmer_name']) : 'Farmer';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Include all the CSS from farmer.php */
        /* Additional styles for settings page */
        .back-button {
            display: inline-flex;
            align-items: center;
            padding: 10px 15px;
            background: var(--primary-color);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            margin-bottom: 20px;
            transition: 0.3s;
        }

        .back-button:hover {
            background: var(--secondary-color);
        }

        .back-button i {
            margin-right: 8px;
        }

        .notification-group {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .notification-option {
            display: flex;
            align-items: center;
            margin: 10px 0;
        }

        .notification-option label {
            margin-left: 10px;
            flex: 1;
        }

        .settings-section {
            margin-bottom: 30px;
        }

        .settings-header {
            border-bottom: 2px solid var(--accent-color);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .farm-info-card {
            background: linear-gradient(145deg, #ffffff, #f5f5f5);
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .farm-info-header {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--accent-color);
        }

        .farm-info-header h2 {
            color: var(--primary-color);
            font-size: 1.5rem;
            margin: 0;
        }

        .farm-info-header i {
            margin-right: 12px;
            color: var(--accent-color);
            font-size: 1.8rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .form-group {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        .form-group h3 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-size: 1.2rem;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        .input-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .input-group input:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(var(--accent-color-rgb), 0.1);
            outline: none;
        }

        .notification-group {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .notification-group h3 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-size: 1.2rem;
        }

        .notification-option {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .notification-option:hover {
            background: #f0f1f2;
            transform: translateX(5px);
        }

        .notification-option input[type="checkbox"] {
            accent-color: var(--accent-color);
            width: 18px;
            height: 18px;
        }

        .submit-btn {
            background: linear-gradient(45deg, #2c5282, #4299e1);
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
            background: linear-gradient(45deg, #4299e1, #2c5282);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(44, 82, 130, 0.2);
        }

        .submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .sidebar {
            background: linear-gradient(180deg, #2c5282, #4299e1);
            width: 80px;
            padding: 20px 0;
            height: 100vh;
            position: fixed;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            overflow-y: auto;
            transition: width 0.3s ease;
        }

        .sidebar:hover {
            width: 200px;
        }

        .sidebar-header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }

        .sidebar-header h2 {
            font-size: 1.2rem;
            margin: 0;
            display: none;
        }

        .sidebar:hover .sidebar-header h2 {
            display: block;
        }

        .nav-menu {
            width: 100%;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 15px;
            color: white;
            text-decoration: none;
            transition: background 0.3s;
            width: 100%;
            box-sizing: border-box;
        }

        .nav-item i {
            font-size: 1.5rem;
            min-width: 40px;
            text-align: center;
        }

        .nav-item span {
            display: none;
            margin-left: 10px;
            white-space: nowrap;
        }

        .sidebar:hover .nav-item span {
            display: inline;
        }

        .nav-item:hover, .nav-item.active {
            background: rgba(255, 255, 255, 0.1);
        }

        .layout-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }

        .input-group input.error {
            border-color: #dc3545;
            background-color: #fff8f8;
        }

        .input-group input.error:focus {
            box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.25);
        }

        .form-requirements {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }

        .validation-message {
            color: #dc3545;
            font-size: 0.8rem;
            margin-top: 5px;
            display: block;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .update-btn {
            background: var(--secondary-color);
            color: black;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .update-btn:hover {
            background: var(--accent-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .input-group small {
            color: #666;
            font-size: 0.8rem;
            margin-top: 5px;
            display: block;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6B7280;
            transition: all 0.3s ease;
        }

        .input-with-icon input {
            padding-right: 40px;
        }

        .input-with-icon i.error-icon {
            color: #dc3545;
        }

        /* Animation classes */
        .animate__animated {
            animation-duration: 0.5s;
        }

        .animate__fadeIn {
            animation-name: fadeIn;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="layout-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-seedling"></i> <span><?php echo $farmerName; ?></span></h2>
            </div>
            <nav class="nav-menu">
                <a href="farmer.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                
              
                <a href="schedule.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Schedule</span>
                </a>
                <a href="weather.php" class="nav-item">
                    <i class="fas fa-cloud-sun"></i>
                    <span>Weather</span>
                </a>
               
                <a href="settings.php" class="nav-item active">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </nav>
        </div>

        <div class="main-content">
            <a href="farmer.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>

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

            <div class="farm-info-card">
                <div class="farm-info-header">
                    <h2><i class="fas fa-user-cog"></i> Account Settings</h2>
                </div>
                <form action="update_settings.php" method="POST" class="data-form" id="settingsForm" onsubmit="return validateForm()">
                    <div class="form-grid">
                        <div class="form-group">
                            <h3>Profile Information</h3>
                            <div class="input-group">
                                <label for="name">Full Name *</label>
                                <input type="text" id="name" name="name" value="<?php echo $farmerName; ?>" required>
                                <span class="validation-message" id="nameError"></span>
                            </div>
                            <div class="input-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
                                <span class="validation-message" id="emailError"></span>
                            </div>
                            <div class="input-group">
                                <label for="phone">Phone</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>" pattern="[0-9]{10}">
                                <span class="validation-message" id="phoneError"></span>
                                <small class="form-requirements">Enter 10-digit phone number</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <h3>Security</h3>
                            <div class="input-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password">
                            </div>
                            <div class="input-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password">
                                <i class="fas fa-lock"></i>
                                <div class="password-strength">
                                    <div class="strength-bar-container">
                                        <div id="passwordStrength" class="strength-bar"></div>
                                    </div>
                                    <span id="strengthText" class="strength-text"></span>
                                </div>
                                <span class="validation-message" id="new_passwordError"></span>
                                <div class="form-requirements">
                                    Password must be at least 8 characters long and contain:
                                    <ul>
                                        <li class="requirement" id="req-length"><i class="fas fa-circle"></i> 8+ characters</li>
                                        <li class="requirement" id="req-uppercase"><i class="fas fa-circle"></i> One uppercase letter</li>
                                        <li class="requirement" id="req-lowercase"><i class="fas fa-circle"></i> One lowercase letter</li>
                                        <li class="requirement" id="req-number"><i class="fas fa-circle"></i> One number</li>
                                        <li class="requirement" id="req-special"><i class="fas fa-circle"></i> One special character (@$!%*?&)</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="input-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password">
                            </div>
                        </div>
                    </div>
                    <div class="button-group">
                        <button type="submit" class="submit-btn" name="action" value="save">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                        <button type="submit" class="submit-btn" name="action" value="submit">
                            <i class="fas fa-check-circle"></i> Submit Changes
                        </button>
                       
                    </div>
                </form>
            </div>

            <div class="farm-info-card">
                <div class="farm-info-header">
                    <h2><i class="fas fa-bell"></i> Notification Preferences</h2>
                </div>
                <form action="update_notifications.php" method="POST" class="data-form">
                    <div class="notification-group">
                        <h3>System Notifications</h3>
                        <div class="notification-option">
                            <input type="checkbox" id="weather_alerts" name="notifications[]" value="weather_alerts">
                            <label for="weather_alerts">Weather Alerts</label>
                        </div>
                        <div class="notification-option">
                            <input type="checkbox" id="harvest_reminders" name="notifications[]" value="harvest_reminders">
                            <label for="harvest_reminders">Harvest Reminders</label>
                        </div>
                        <div class="notification-option">
                            <input type="checkbox" id="market_updates" name="notifications[]" value="market_updates">
                            <label for="market_updates">Market Price Updates</label>
                        </div>
                    </div>

                    <div class="notification-group">
                        <h3>Communication Preferences</h3>
                        <div class="notification-option">
                            <input type="checkbox" id="email_notifications" name="notifications[]" value="email_notifications">
                            <label for="email_notifications">Email Notifications</label>
                        </div>
                        <div class="notification-option">
                            <input type="checkbox" id="sms_notifications" name="notifications[]" value="sms_notifications">
                            <label for="sms_notifications">SMS Notifications</label>
                        </div>
                    </div>
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-bell"></i> Update Preferences
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
    function validateForm() {
        let isValid = true;
        const errorMessages = [];
        clearValidationMessages();

        // Name validation
        const name = document.getElementById('name').value.trim();
        if (name.length < 3 || name.length > 50) {
            isValid = false;
            showError('name', 'Name must be between 3 and 50 characters');
            errorMessages.push("Name must be between 3 and 50 characters");
        } else if (!/^[a-zA-Z\s]+$/.test(name)) {
            isValid = false;
            showError('name', 'Name can only contain letters and spaces');
            errorMessages.push("Name can only contain letters and spaces");
        }

        // Email validation
        const email = document.getElementById('email').value.trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) {
            isValid = false;
            showError('email', 'Please enter a valid email address');
            errorMessages.push("Please enter a valid email address");
        }

        // Phone validation
        const phone = document.getElementById('phone').value.trim();
        if (phone && !/^[0-9]{10}$/.test(phone)) {
            isValid = false;
            showError('phone', 'Please enter a valid 10-digit phone number');
            errorMessages.push("Please enter a valid 10-digit phone number");
        }

        // Password validation
        const currentPassword = document.getElementById('current_password').value;
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;

        if (newPassword) {
            if (currentPassword.length === 0) {
                isValid = false;
                document.getElementById('current_password').classList.add('error');
                errorMessages.push("Current password is required to set a new password");
            }
            
            if (newPassword.length < 8) {
                isValid = false;
                document.getElementById('new_password').classList.add('error');
                errorMessages.push("Password must be at least 8 characters long");
            } else if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/.test(newPassword)) {
                isValid = false;
                document.getElementById('new_password').classList.add('error');
                errorMessages.push("Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character");
            }

            if (newPassword !== confirmPassword) {
                isValid = false;
                document.getElementById('confirm_password').classList.add('error');
                errorMessages.push("Passwords do not match");
            }
        }

        // Display error summary if needed
        if (!isValid) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-error';
            errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${errorMessages.join('<br>')}`;
            
            const existingError = document.querySelector('.alert.alert-error');
            if (existingError) {
                existingError.remove();
            }
            
            const form = document.querySelector('.data-form');
            form.insertBefore(errorDiv, form.firstChild);
        }

        return isValid;
    }

    function showError(fieldId, message) {
        const errorSpan = document.getElementById(fieldId + 'Error');
        errorSpan.textContent = message;
        document.getElementById(fieldId).classList.add('error');
    }

    function clearValidationMessages() {
        document.querySelectorAll('.validation-message').forEach(span => span.textContent = '');
        document.querySelectorAll('.input-group input').forEach(input => input.classList.remove('error'));
    }

    function updateDashboard() {
        if (validateForm()) {
            // Show loading state
            const updateBtn = document.querySelector('.update-btn');
            const originalBtnText = updateBtn.innerHTML;
            updateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            updateBtn.disabled = true;

            // Save form data
            const formData = new FormData(document.getElementById('settingsForm'));
            formData.append('action', 'update_dashboard'); // Add action identifier

            fetch('update_settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    const successDiv = document.createElement('div');
                    successDiv.className = 'alert alert-success';
                    successDiv.innerHTML = '<i class="fas fa-check-circle"></i> Dashboard updated successfully!';
                    
                    // Remove any existing alerts
                    document.querySelectorAll('.alert').forEach(alert => alert.remove());
                    
                    const form = document.querySelector('.data-form');
                    form.insertBefore(successDiv, form.firstChild);

                    // Refresh dashboard data
                    setTimeout(() => {
                        window.location.href = 'farmer.php';
                    }, 1500);
                } else {
                    throw new Error(data.message || 'Update failed');
                }
            })
            .catch(error => {
                // Show error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-error';
                errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${error.message || 'Failed to update dashboard. Please try again.'}`;
                
                // Remove any existing alerts
                document.querySelectorAll('.alert').forEach(alert => alert.remove());
                
                const form = document.querySelector('.data-form');
                form.insertBefore(errorDiv, form.firstChild);
            })
            .finally(() => {
                // Restore button state
                updateBtn.innerHTML = originalBtnText;
                updateBtn.disabled = false;
            });
        }
    }

    // Add real-time validation
    document.querySelectorAll('.input-group input').forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('error');
            document.getElementById(this.id + 'Error').textContent = '';
        });
    });

    // Live validation functions
    function setupLiveValidation() {
        const inputs = {
            name: {
                element: document.getElementById('name'),
                icon: '<i class="fas fa-user"></i>',
                validate: (value) => {
                    if (value.length < 3) return 'Name must be at least 3 characters';
                    if (!/^[a-zA-Z\s]+$/.test(value)) return 'Name can only contain letters and spaces';
                    return '';
                }
            },
            email: {
                element: document.getElementById('email'),
                icon: '<i class="fas fa-envelope"></i>',
                validate: (value) => {
                    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'Please enter a valid email';
                    return '';
                }
            },
            phone: {
                element: document.getElementById('phone'),
                icon: '<i class="fas fa-phone"></i>',
                validate: (value) => {
                    if (value && !/^[0-9]{10}$/.test(value)) return 'Please enter a valid 10-digit number';
                    return '';
                }
            },
            current_password: {
                element: document.getElementById('current_password'),
                icon: '<i class="fas fa-key"></i>',
                validate: (value) => {
                    const newPassword = document.getElementById('new_password').value;
                    if (newPassword && !value) return 'Current password is required when setting new password';
                    return '';
                }
            },
            new_password: {
                element: document.getElementById('new_password'),
                icon: '<i class="fas fa-lock"></i>',
                validate: (value) => {
                    if (!value) return '';
                    if (value.length < 8) return 'Password must be at least 8 characters';
                    if (!/[A-Z]/.test(value)) return 'Password must contain an uppercase letter';
                    if (!/[a-z]/.test(value)) return 'Password must contain a lowercase letter';
                    if (!/[0-9]/.test(value)) return 'Password must contain a number';
                    if (!/[@$!%*?&]/.test(value)) return 'Password must contain a special character (@$!%*?&)';
                    return '';
                }
            },
            confirm_password: {
                element: document.getElementById('confirm_password'),
                icon: '<i class="fas fa-check-circle"></i>',
                validate: (value) => {
                    const newPassword = document.getElementById('new_password').value;
                    if (newPassword && value !== newPassword) return 'Passwords do not match';
                    return '';
                }
            }
        };

        // Add icons and setup validation for each input
        Object.keys(inputs).forEach(key => {
            const input = inputs[key];
            const wrapper = input.element.parentElement;
            
            // Add icon
            wrapper.classList.add('input-with-icon');
            wrapper.insertAdjacentHTML('beforeend', input.icon);

            // Setup live validation
            input.element.addEventListener('input', () => {
                const error = input.validate(input.element.value.trim());
                const errorElement = document.getElementById(`${key}Error`);
                
                if (error) {
                    input.element.classList.add('error');
                    errorElement.textContent = error;
                    wrapper.querySelector('i').classList.add('error-icon');
                } else {
                    input.element.classList.remove('error');
                    errorElement.textContent = '';
                    wrapper.querySelector('i').classList.remove('error-icon');
                }
            });
        });
    }

    // Enhanced update function
    function updateSettings(event) {
        event.preventDefault();
        const form = document.getElementById('settingsForm');
        const submitBtn = form.querySelector('.submit-btn');
        const originalBtnText = submitBtn.innerHTML;

        // Show loading state
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        submitBtn.disabled = true;

        fetch('update_settings.php', {
            method: 'POST',
            body: new FormData(form)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                showAlert('success', 'Settings updated successfully!');
                
                // Store updated name in session storage for dashboard
                sessionStorage.setItem('updatedFarmerName', data.updatedData.name);
                
                // Update sidebar name immediately
                const sidebarName = document.querySelector('.sidebar-header span');
                if (sidebarName) {
                    sidebarName.textContent = data.updatedData.name;
                }
                
                // Redirect to dashboard after delay
                setTimeout(() => {
                    window.location.href = 'farmer.php';
                }, 1500);
            } else {
                throw new Error(data.message || 'Update failed');
            }
        })
        .catch(error => {
            showAlert('error', error.message);
        })
        .finally(() => {
            // Restore button state
            submitBtn.innerHTML = originalBtnText;
            submitBtn.disabled = false;
        });
    }

    // Helper function to show alerts
    function showAlert(type, message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} animate__animated animate__fadeIn`;
        alertDiv.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
            ${message}
        `;
        
        // Remove existing alerts
        document.querySelectorAll('.alert').forEach(alert => alert.remove());
        
        // Add new alert
        const form = document.querySelector('.data-form');
        form.insertBefore(alertDiv, form.firstChild);
    }

    // Initialize live validation
    document.addEventListener('DOMContentLoaded', setupLiveValidation);

    // Setup form submission
    document.getElementById('settingsForm').addEventListener('submit', updateSettings);
    </script>
</body>
</html>