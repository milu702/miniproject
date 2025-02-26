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
            --primary-color: #2c5282;
            --secondary-color: #4299e1;
            --accent-color: #90cdf4;
            --success-color: #48bb78;
            --error-color: #f56565;
        }

        /* Copy all the styles from settings.php and modify the color scheme */
        /* ... (include all the styles from settings.php) ... */

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
                <h2><i class="fas fa-user-tie"></i> <span><?php echo $employeeName; ?></span></h2>
            </div>
            <nav class="nav-menu">
                <a href="employe.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="farmers_list.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>Farmers</span>
                </a>
                <a href="soil_tests.php" class="nav-item">
                    <i class="fas fa-flask"></i>
                    <span>Soil Tests</span>
                </a>
                <a href="recommendations.php" class="nav-item">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Recommendations</span>
                </a>
                <a href="employee_settings.php" class="nav-item active">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
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
