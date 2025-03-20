<?php
session_start();

// Authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// Add database connection
$conn = mysqli_connect("localhost", "root", "", "growguide");

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Fetch admin data
$admin_id = $_SESSION['user_id'];
$admin_query = "SELECT * FROM users WHERE id = ? AND role = 'admin'";
$stmt = mysqli_prepare($conn, $admin_query);
mysqli_stmt_bind_param($stmt, "i", $admin_id);
mysqli_stmt_execute($stmt);
$admin_result = mysqli_stmt_get_result($stmt);
$admin_data = mysqli_fetch_assoc($admin_result);

// Fetch system statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'farmer') as total_farmers,
    (SELECT COUNT(*) FROM users WHERE role = 'employee') as total_employees,
    (SELECT COUNT(*) FROM soil_tests) as total_soil_tests,
    (SELECT COUNT(*) FROM employee_queries WHERE status = 'pending') as pending_queries";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Fetch recent activity logs
$logs_query = "SELECT * FROM activity_logs WHERE user_id = ? ORDER BY timestamp DESC LIMIT 5";
$stmt = mysqli_prepare($conn, $logs_query);
mysqli_stmt_bind_param($stmt, "i", $admin_id);
mysqli_stmt_execute($stmt);
$logs_result = mysqli_stmt_get_result($stmt);

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Settings Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Reuse existing styles from admin.php */
        /* ... existing styles ... */

        /* Additional Settings-specific styles */
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .settings-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }

        .settings-card:hover {
            transform: translateY(-5px);
        }

        .settings-card h3 {
            color: #2e7d32;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .settings-card i {
            font-size: 1.5rem;
        }

        .settings-item {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .settings-item:last-child {
            border-bottom: none;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: #2e7d32;
        }

        input:checked + .slider:before {
            transform: translateX(26px);
        }

        .btn-action {
            background: #2e7d32;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-action:hover {
            background: #43a047;
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

/* Update these CSS styles for the sidebar */
:root {
    --primary-green: #1b5e20;
    --hover-green: #2e7d32;
    --active-green: #43a047;
    --text-color: #ffffff;
}

.sidebar {
    width: 250px;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    background-color: var(--primary-green);
    color: var(--text-color);
    display: flex;
    flex-direction: column;
    z-index: 1000;
}

.logo-section {
    padding: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.logo-section img {
    max-width: 100%;
    height: auto;
}

.menu-section {
    flex: 1;
    padding: 10px 0;
}

.sidebar-btn {
    display: flex;
    align-items: center;
    padding: 12px 20px;
    color: var(--text-color);
    text-decoration: none;
    transition: all 0.3s ease;
    border-left: 4px solid transparent;
}

.sidebar-btn:hover {
    background-color: var(--hover-green);
    border-left-color: var(--text-color);
}

.sidebar-btn.active {
    background-color: var(--active-green);
    border-left-color: var(--text-color);
}

.sidebar-btn i {
    width: 24px;
    margin-right: 10px;
    font-size: 1.2rem;
}

.bottom-section {
    padding: 10px 0;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

/* Update main content margin to match new sidebar */
.main-content {
    margin-left: 250px;
    padding: 20px;
    background-color: #f5f5f5;
    min-height: 100vh;
}

/* Add hover effect for menu items */
.sidebar-btn {
    position: relative;
    overflow: hidden;
}

.sidebar-btn::after {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 0;
    background-color: rgba(255, 255, 255, 0.1);
    transition: width 0.3s ease;
}

.sidebar-btn:hover::after {
    width: 100%;
}

/* Add active indicator animation */
.sidebar-btn.active::before {
    content: '';
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 70%;
    background-color: var(--text-color);
    border-radius: 2px;
}

/* Add subtle shadow to sidebar */
.sidebar {
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
}

/* Update logo section */
.logo-section {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 20px;
}

.logo-section i {
    font-size: 24px;
}

.logo-section h1 {
    font-size: 20px;
    margin: 0;
    font-weight: 600;
}

/* Add smooth transition for sidebar collapse if needed */
.sidebar, .main-content {
    transition: all 0.3s ease;
}

/* Update menu text style */
.menu-text {
    font-size: 14px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.settings-header {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.settings-header h1 {
    color: var(--primary-color);
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 1.8rem;
}

.profile-section {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-top: 20px;
    padding: 20px;
    background: var(--background-color);
    border-radius: 10px;
}

.profile-image {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: var(--primary-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 2rem;
}

.profile-info h2 {
    color: var(--primary-color);
    margin-bottom: 5px;
}

.profile-info p {
    color: var(--text-secondary);
    margin: 2px 0;
}

.activity-log {
    background: white;
    padding: 20px;
    border-radius: 10px;
    margin-top: 20px;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.activity-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--background-color);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
}

.activity-details h4 {
    color: var(--text-primary);
    margin-bottom: 5px;
}

.activity-details p {
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.stats-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.stat-item {
    background: white;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    transition: transform 0.3s ease;
}

.stat-item:hover {
    transform: translateY(-5px);
}

.stat-item i {
    font-size: 2rem;
    color: var(--primary-color);
    margin-bottom: 10px;
}

.stat-item h3 {
    color: var(--text-primary);
    font-size: 1.8rem;
    margin-bottom: 5px;
}

.stat-item p {
    color: var(--text-secondary);
}

/* Add animation classes */
.fade-in {
    animation: fadeIn 0.5s ease-in;
}

.slide-in {
    animation: slideIn 0.5s ease-out;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* Add these styles to your existing CSS */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    padding: 20px;
    border-radius: 10px;
    width: 90%;
    max-width: 500px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.close-btn {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    color: #333;
}

.form-group input {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.toast {
    position: fixed;
    bottom: 20px;
    right: 20px;
    padding: 15px 25px;
    border-radius: 5px;
    color: white;
    z-index: 1000;
    animation: slideIn 0.3s ease-in-out;
}

.toast-success {
    background: #2e7d32;
}

.toast-error {
    background: #c62828;
}

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

    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo-section">
            <i class="fas fa-leaf"></i>
            <h1 class="menu-text">GrowGuide</h1>
        </div>

        <div class="menu-section">
            <a href="admin.php" class="sidebar-btn">
                <i class="fas fa-th-large"></i>
                <span class="menu-text">Dashboard</span>
            </a>
            
            <a href="farmers.php" class="sidebar-btn">
                <i class="fas fa-users"></i>
                <span class="menu-text">Farmers</span>
            </a>
            
            <a href="ad_employee.php" class="sidebar-btn">
                <i class="fas fa-user-tie"></i>
                <span class="menu-text">Employees</span>
            </a>
            
            <a href="varieties.php" class="sidebar-btn">
                <i class="fas fa-seedling"></i>
                <span class="menu-text">Varieties</span>
            </a>
        </div>

        <div class="bottom-section">
            <a href="admin_notifications.php" class="sidebar-btn">
                <i class="fas fa-bell"></i>
                <span class="menu-text">Notifications</span>
            </a>
            
            <a href="admin_setting.php" class="sidebar-btn active">
                <i class="fas fa-cog"></i>
                <span class="menu-text">Settings</span>
            </a>
            
            <a href="logout.php" class="sidebar-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span class="menu-text">Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="settings-header fade-in">
            <h1><i class="fas fa-cog"></i> Admin Settings</h1>
            
            <div class="profile-section">
                <div class="profile-image">
                    <i class="fas fa-user"></i>
                </div>
                <div class="profile-info">
                    <h2><?php echo htmlspecialchars($admin_data['username']); ?></h2>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($admin_data['email']); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($admin_data['phone']); ?></p>
                </div>
            </div>

            <div class="stats-summary">
                <div class="stat-item slide-in">
                    <i class="fas fa-users"></i>
                    <h3><?php echo $stats['total_farmers']; ?></h3>
                    <p>Total Farmers</p>
                </div>
                <div class="stat-item slide-in">
                    <i class="fas fa-user-tie"></i>
                    <h3><?php echo $stats['total_employees']; ?></h3>
                    <p>Total Employees</p>
                </div>
                <div class="stat-item slide-in">
                    <i class="fas fa-flask"></i>
                    <h3><?php echo $stats['total_soil_tests']; ?></h3>
                    <p>Soil Tests</p>
                </div>
                <div class="stat-item slide-in">
                    <i class="fas fa-question-circle"></i>
                    <h3><?php echo $stats['pending_queries']; ?></h3>
                    <p>Pending Queries</p>
                </div>
            </div>
    </div>
    
        <!-- Existing settings grid -->
        <div class="settings-grid">
            <div class="settings-card">
                <h3><i class="fas fa-user-circle"></i> Account Settings</h3>
                <div class="settings-item">
                    <span>Profile Information</span>
                    <button class="btn-action" data-action="edit-profile">Edit</button>
                </div>
                <div class="settings-item">
                    <span>Change Password</span>
                    <button class="btn-action" data-action="change-password">Update</button>
                </div>
                <div class="settings-item" data-setting="two_factor">
                    <span>Two-Factor Authentication</span>
                    <label class="toggle-switch">
                        <input type="checkbox">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div class="settings-card">
                <h3><i class="fas fa-bell"></i> Notification Settings</h3>
                <div class="settings-item" data-setting="email_notifications">
                    <span>Email Notifications</span>
                    <label class="toggle-switch">
                        <input type="checkbox" checked>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="settings-item" data-setting="sms">
                    <span>SMS Alerts</span>
                    <label class="toggle-switch">
                        <input type="checkbox">
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="settings-item" data-setting="system">
                    <span>System Notifications</span>
                    <label class="toggle-switch">
                        <input type="checkbox" checked>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div class="settings-card">
                <h3><i class="fas fa-shield-alt"></i> Privacy & Security</h3>
                <div class="settings-item" data-setting="activity">
                    <span>Activity Log</span>
                    <button class="btn-action">View</button>
                </div>
                <div class="settings-item" data-setting="data">
                    <span>Data Sharing</span>
                    <label class="toggle-switch">
                        <input type="checkbox">
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="settings-item" data-setting="backup">
                    <span>Backup Data</span>
                    <button class="btn-action">Backup</button>
                </div>
            </div>

            <div class="settings-card">
                <h3><i class="fas fa-palette"></i> Appearance</h3>
                <div class="settings-item" data-setting="dark">
                    <span>Dark Mode</span>
                    <label class="toggle-switch">
                        <input type="checkbox">
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="settings-item" data-setting="compact">
                    <span>Compact View</span>
                    <label class="toggle-switch">
                        <input type="checkbox">
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="settings-item">
                    <span>Font Size</span>
                    <select class="btn-action">
                        <option>Small</option>
                        <option selected>Medium</option>
                        <option>Large</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Activity Log Section -->
        <div class="activity-log fade-in">
            <h3><i class="fas fa-history"></i> Recent Activity</h3>
            <?php while ($log = mysqli_fetch_assoc($logs_result)): ?>
                <div class="activity-item slide-in">
                    <div class="activity-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="activity-details">
                        <h4><?php echo htmlspecialchars($log['action']); ?></h4>
                        <p><?php echo date('M d, Y H:i', strtotime($log['timestamp'])); ?></p>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Profile Information Edit
        document.querySelector('[data-action="edit-profile"]').addEventListener('click', function() {
            const profileInfo = {
                username: '<?php echo htmlspecialchars($admin_data['username']); ?>',
                email: '<?php echo htmlspecialchars($admin_data['email']); ?>',
                phone: '<?php echo htmlspecialchars($admin_data['phone']); ?>'
            };
            
            // Create and show modal
            const modal = createModal('Edit Profile', `
                <form id="profile-form">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" value="${profileInfo.username}" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="${profileInfo.email}" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" value="${profileInfo.phone}" required>
                    </div>
                    <button type="submit" class="btn-action">Save Changes</button>
                </form>
            `);
            
            document.getElementById('profile-form').addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                formData.append('action', 'update_profile');
                
                try {
                    const response = await fetch('update_admin_settings.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    
                    if (result.status === 'success') {
                        showToast('Success', result.message);
                        setTimeout(() => location.reload(), 1500);
                    } else {
                        showToast('Error', result.message);
                    }
                } catch (error) {
                    showToast('Error', 'Failed to update profile');
                }
            });
        });
        
        // Change Password
        document.querySelector('[data-action="change-password"]').addEventListener('click', function() {
            const modal = createModal('Change Password', `
                <form id="password-form">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" required>
                    </div>
                    <button type="submit" class="btn-action">Update Password</button>
                </form>
            `);
            
            document.getElementById('password-form').addEventListener('submit', async function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                if (formData.get('new_password') !== formData.get('confirm_password')) {
                    showToast('Error', 'Passwords do not match');
                    return;
                }
                
                formData.append('action', 'change_password');
                
                try {
                    const response = await fetch('update_admin_settings.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    
                    if (result.status === 'success') {
                        showToast('Success', result.message);
                        modal.remove();
                    } else {
                        showToast('Error', result.message);
                    }
                } catch (error) {
                    showToast('Error', 'Failed to change password');
                }
            });
        });
        
        // Toggle Switches
        document.querySelectorAll('.toggle-switch input').forEach(switch => {
            switch.addEventListener('change', async function() {
                const settingType = this.closest('.settings-item').getAttribute('data-setting');
                const formData = new FormData();
                formData.append('action', 'update_settings');
                formData.append('setting_type', settingType);
                formData.append('value', this.checked ? '1' : '0');
                
                try {
                    const response = await fetch('update_admin_settings.php', {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();
                    
                    if (result.status === 'success') {
                        showToast('Success', 'Setting updated successfully');
                    } else {
                        showToast('Error', result.message);
                        this.checked = !this.checked; // Revert the toggle if failed
                    }
                } catch (error) {
                    showToast('Error', 'Failed to update setting');
                    this.checked = !this.checked;
                }
            });
        });
    });

    // Utility functions
    function createModal(title, content) {
        const modal = document.createElement('div');
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2>${title}</h2>
                    <button class="close-btn">&times;</button>
                </div>
                <div class="modal-body">
                    ${content}
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        modal.querySelector('.close-btn').addEventListener('click', () => modal.remove());
        return modal;
    }

    function showToast(type, message) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type.toLowerCase()}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => toast.remove(), 3000);
    }
    </script>
</body>
</html>