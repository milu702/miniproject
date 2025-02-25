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
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .form-group:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.12);
        }

        .form-group h3 {
            color: var(--primary-color);
            margin-bottom: 20px;
            font-size: 1.2rem;
        }

        .input-group {
            position: relative;
            margin-bottom: 25px;
        }

        .input-group i {
            position: absolute;
            left: 12px;
            top: 40px;
            color: #6B7280;
            transition: color 0.3s ease;
        }

        .input-group input {
            padding: 12px 40px;
            background-color: #F3F4F6;
            border: 2px solid transparent;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .input-group input:focus {
            border-color: #4299e1;
            background-color: #fff;
        }

        .input-group input:focus + i {
            color: #4299e1;
        }

        /* Field-specific colors */
        .input-group.name-field i { color: #3B82F6; }
        .input-group.email-field i { color: #10B981; }
        .input-group.phone-field i { color: #F59E0B; }
        .input-group.password-field i { color: #6366F1; }

        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        .input-group input.error {
            border-color: #dc3545;
            background-color: #fff8f8;
        }

        .validation-message {
            color: #dc3545;
            font-size: 0.8rem;
            margin-top: 5px;
            display: block;
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
            background: var(--primary-color);
            color: white;
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

        .submit-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
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

        .form-requirements {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
        }

        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .update-btn {
            background: linear-gradient(135deg, #4299e1, #3B82F6);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .update-btn:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                120deg,
                transparent,
                rgba(255, 255, 255, 0.3),
                transparent
            );
            transition: 0.5s;
        }

        .update-btn:hover:before {
            left: 100%;
        }

        .update-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(59, 130, 246, 0.3);
        }

        .update-btn:active {
            transform: translateY(1px);
        }

        /* Loading animation */
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-spin {
            animation: spin 1s linear infinite;
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
                            <h3><i class="fas fa-user-circle"></i> Profile Information</h3>
                            <div class="input-group name-field">
                                <label for="name">Full Name *</label>
                                <input type="text" id="name" name="name" value="<?php echo $farmerName; ?>" required>
                                <i class="fas fa-user"></i>
                                <span class="validation-message" id="nameError"></span>
                            </div>
                            <div class="input-group email-field">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" required>
                                <i class="fas fa-envelope"></i>
                                <span class="validation-message" id="emailError"></span>
                            </div>
                            <div class="input-group phone-field">
                                <label for="phone">Phone</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>" pattern="[0-9]{10}">
                                <i class="fas fa-phone"></i>
                                <span class="validation-message" id="phoneError"></span>
                                <small class="form-requirements">Enter 10-digit phone number</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <h3><i class="fas fa-shield-alt"></i> Security</h3>
                            <div class="input-group password-field">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div class="input-group password-field">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password">
                                <i class="fas fa-key"></i>
                                <div class="form-requirements">
                                    Password must be at least 8 characters long and contain:
                                    <ul>
                                        <li>One uppercase letter</li>
                                        <li>One lowercase letter</li>
                                        <li>One number</li>
                                        <li>One special character (@$!%*?&)</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="input-group password-field">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password">
                                <i class="fas fa-check-circle"></i>
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
                        <button type="button" class="update-btn" onclick="updateDashboard()">
                            <i class="fas fa-sync"></i> Update Dashboard
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
            const updateBtn = document.querySelector('.update-btn');
            const originalBtnText = updateBtn.innerHTML;
            
            // Enhanced loading state
            updateBtn.innerHTML = `
                <i class="fas fa-spinner loading-spin"></i>
                <span>Updating...</span>
            `;
            updateBtn.style.background = 'linear-gradient(135deg, #3B82F6, #2563EB)';
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
    </script>
</body>
</html>