<?php
session_start();

// Add database connection
$conn = mysqli_connect("localhost", "root", "", "growguide");

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

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



    </style>
</head>
<body>
    <!-- Reuse sidebar from admin.php -->
    <!-- ... existing sidebar code ... -->

    <!-- Main Content -->
    <a href="admin.php" class="admin-dashboard-link">
    <div class="icon-container">
        <i class="fas fa-user-shield"></i>
    </div>
    <span class="text">Admin Dashboard</span>
</a>
    
        <div class="settings-grid">
            <div class="settings-card">
                <h3><i class="fas fa-user-circle"></i> Account Settings</h3>
                <div class="settings-item">
                    <span>Profile Information</span>
                    <button class="btn-action">Edit</button>
                </div>
                <div class="settings-item">
                    <span>Change Password</span>
                    <button class="btn-action">Update</button>
                </div>
                <div class="settings-item">
                    <span>Two-Factor Authentication</span>
                    <label class="toggle-switch">
                        <input type="checkbox">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div class="settings-card">
                <h3><i class="fas fa-bell"></i> Notification Settings</h3>
                <div class="settings-item">
                    <span>Email Notifications</span>
                    <label class="toggle-switch">
                        <input type="checkbox" checked>
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="settings-item">
                    <span>SMS Alerts</span>
                    <label class="toggle-switch">
                        <input type="checkbox">
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="settings-item">
                    <span>System Notifications</span>
                    <label class="toggle-switch">
                        <input type="checkbox" checked>
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div class="settings-card">
                <h3><i class="fas fa-shield-alt"></i> Privacy & Security</h3>
                <div class="settings-item">
                    <span>Activity Log</span>
                    <button class="btn-action">View</button>
                </div>
                <div class="settings-item">
                    <span>Data Sharing</span>
                    <label class="toggle-switch">
                        <input type="checkbox">
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="settings-item">
                    <span>Backup Data</span>
                    <button class="btn-action">Backup</button>
                </div>
            </div>

            <div class="settings-card">
                <h3><i class="fas fa-palette"></i> Appearance</h3>
                <div class="settings-item">
                    <span>Dark Mode</span>
                    <label class="toggle-switch">
                        <input type="checkbox">
                        <span class="slider"></span>
                    </label>
                </div>
                <div class="settings-item">
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
    </div>

    <!-- Reuse toggle sidebar script from admin.php -->
    <script>
        // ... existing toggleSidebar function ...
    </script>
</body>
</html>