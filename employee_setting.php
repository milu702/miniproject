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
        COALESCE(e.username, u.username) as employee_name,
        e.phone,
        e.notification_preferences,
        e.department,
        e.designation
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
    <title>GrowGuide - Employee Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #1a4a1d;
            --primary-color: #2B7A30;
            --hover-color: #3c8c40;
            --text-light: #ffffff;
            --sidebar-width: 250px;
        }

        /* Updated Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: var(--text-light);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-header i {
            font-size: 24px;
        }

        .sidebar-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 500;
        }

        .nav-menu {
            display: flex;
            flex-direction: column;
            padding: 20px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 24px;
            color: var(--text-light);
            text-decoration: none;
            transition: all 0.3s ease;
            gap: 12px;
        }

        .nav-item.active {
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            padding-left: 28px;
        }

        .nav-item i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        .nav-item span {
            font-size: 16px;
        }

        /* Logout button specific styles */
        .logout-btn {
            margin-top: auto;
            background: rgba(220, 53, 69, 0.1);
            color: #ff6b6b;
            border: none;
            cursor: pointer;
            margin: 20px;
            border-radius: 8px;
        }

        .logout-btn:hover {
            background: #ff6b6b;
            color: white;
        }

        /* Update main content to accommodate sidebar */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            min-height: 100vh;
            background: #f5f7fa;
        }

        /* Remove the back button since we have sidebar navigation */
        .back-button {
            display: none;
        }

        /* Add these employee-specific styles */
        .employee-info-card {
            background: linear-gradient(145deg, #ffffff, #f0f7ff);
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(44, 82, 130, 0.1);
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid rgba(66, 153, 225, 0.1);
        }

        .department-field i { color: #805ad5; }
        .designation-field i { color: #d53f8c; }

        .employee-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-item {
            background: rgba(66, 153, 225, 0.1);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-item i {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        /* Add animation for the sidebar */
        .sidebar {
            background: linear-gradient(180deg, var(--primary-color), var(--secondary-color));
        }

        .nav-item:hover {
            background: linear-gradient(90deg, rgba(255,255,255,0.1), transparent);
            transform: translateX(5px);
        }

        /* Add hover effects for buttons */
        .submit-btn, .update-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }

        .submit-btn:hover, .update-btn:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
        }
    </style>
</head>
<body>
    <div class="layout-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-seedling"></i>
                <h2>GrowGuide</h2>
            </div>
            
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="varieties.php" class="nav-item">
                    <i class="fas fa-seedling"></i>
                    <span>Varieties</span>
                </a>
                <a href="notifications.php" class="nav-item">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </a>
                <a href="settings.php" class="nav-item active">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
               
                <a href="logout.php" class="nav-item logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </div>

        <div class="main-content">
            <a href="employe.php" class="back-button">
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

            <div class="employee-info-card">
                <div class="farm-info-header">
                    <h2><i class="fas fa-user-cog"></i> Employee Settings</h2>
                </div>
                <form action="update_employee_settings.php" method="POST" class="data-form" id="settingsForm" onsubmit="return validateForm()">
                    <div class="form-grid">
                        <div class="form-group">
                            <h3><i class="fas fa-user-circle"></i> Profile Information</h3>
                            <div class="input-group name-field">
                                <label for="name">Full Name *</label>
                                <input type="text" id="name" name="name" value="<?php echo $employeeName; ?>" required>
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
                            </div>
                            <div class="input-group department-field">
                                <label for="department">Department</label>
                                <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($userData['department'] ?? ''); ?>">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="input-group designation-field">
                                <label for="designation">Designation</label>
                                <input type="text" id="designation" name="designation" value="<?php echo htmlspecialchars($userData['designation'] ?? ''); ?>">
                                <i class="fas fa-id-badge"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <h3><i class="fas fa-shield-alt"></i> Security</h3>
                            <!-- Password fields (same as settings.php) -->
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
                        <button type="button" class="update-btn" onclick="updateDashboard()">
                            <i class="fas fa-sync"></i> Update Dashboard
                        </button>
                    </div>
                </form>
            </div>

            <div class="employee-info-card">
                <div class="farm-info-header">
                    <h2><i class="fas fa-bell"></i> Notification Preferences</h2>
                </div>
                <form action="update_employee_notifications.php" method="POST" class="data-form">
                    <div class="notification-group">
                        <h3>System Notifications</h3>
                        <div class="notification-option">
                            <input type="checkbox" id="farmer_updates" name="notifications[]" value="farmer_updates">
                            <label for="farmer_updates">Farmer Updates</label>
                        </div>
                        <div class="notification-option">
                            <input type="checkbox" id="soil_test_alerts" name="notifications[]" value="soil_test_alerts">
                            <label for="soil_test_alerts">Soil Test Alerts</label>
                        </div>
                        <div class="notification-option">
                            <input type="checkbox" id="recommendation_reminders" name="notifications[]" value="recommendation_reminders">
                            <label for="recommendation_reminders">Recommendation Reminders</label>
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

    <!-- Include the same JavaScript from settings.php -->
    <script>
        // ... (include all the JavaScript from settings.php) ...
    </script>
</body>
</html>
