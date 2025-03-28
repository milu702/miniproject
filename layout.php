<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Cardamom Plantation Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            display: flex;
            background-color: #f5f5f5;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            height: 100vh;
            background-color: #2e7d32;
            color: white;
            position: fixed;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .logo-section {
            padding: 20px;
            border-bottom: 1px solid #43a047;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-section h1 {
            font-size: 1.5rem;
            white-space: nowrap;
        }

        .toggle-btn {
            position: absolute;
            right: -12px;
            top: 30px;
            background: #43a047;
            border: none;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1001;
        }

        .menu-section {
            padding: 20px 0;
        }

        .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            color: white;
        }

        .menu-item:hover {
            background-color: #43a047;
        }

        .menu-item.active {
            background-color: #43a047;
        }

        .menu-item i {
            width: 20px;
            text-align: center;
        }

        .menu-text {
            white-space: nowrap;
            opacity: 1;
            transition: opacity 0.3s;
        }

        .collapsed .menu-text {
            opacity: 0;
            width: 0;
        }

        .bottom-section {
            position: absolute;
            bottom: 0;
            width: 100%;
            border-top: 1px solid #43a047;
            padding: 20px 0;
        }

        /* Main Content Styles */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            width: calc(100% - 250px);
            transition: all 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 70px;
            width: calc(100% - 70px);
        }

        .welcome-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .user-welcome {
            font-size: 1.2rem;
            color: #333;
        }

        .icon-container {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2e7d32;
        }

        .icon-container i {
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-chevron-left" id="toggle-icon"></i>
        </button>

        <div class="logo-section">
            <i class="fas fa-leaf"></i>
            <h1 class="menu-text">GrowGuide</h1>
        </div>

        <div class="menu-section">
            <a href="dashboard.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i>
                <span class="menu-text">Dashboard</span>
            </a>
            <a href="farmers.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'farmers.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span class="menu-text">Farmers</span>
            </a>
            <a href="employees.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'employees.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-tie"></i>
                <span class="menu-text">Employees</span>
            </a>
            <a href="soil-tests.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'soil-tests.php' ? 'active' : ''; ?>">
                <i class="fas fa-flask"></i>
                <span class="menu-text">Soil Tests</span>
            </a>
            <a href="varieties.php" class="menu-item <?php echo basename($_SERVER['PHP_SELF']) == 'varieties.php' ? 'active' : ''; ?>">
                <i class="fas fa-seedling"></i>
                <span class="menu-text">Varieties</span>
            </a>
        </div>

        <div class="bottom-section">
            <a href="notifications.php" class="menu-item">
                <i class="fas fa-bell"></i>
                <span class="menu-text">Notifications</span>
            </a>
            <a href="settings.php" class="menu-item">
                <i class="fas fa-cog"></i>
                <span class="menu-text">Settings</span>
            </a>
            <a href="logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span class="menu-text">Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content Container -->
    <div class="main-content" id="main-content">
        <!-- Welcome Header -->
        <div class="welcome-header">
            <div class="user-welcome">Welcome, milu jiji</div>
            <div class="icon-container">
                <i class="fas fa-user-circle"></i>
            </div>
        </div>

        <!-- Page Content Will Go Here -->
        <?php echo $content ?? ''; ?>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const toggleIcon = document.getElementById('toggle-icon');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            if (sidebar.classList.contains('collapsed')) {
                toggleIcon.classList.remove('fa-chevron-left');
                toggleIcon.classList.add('fa-chevron-right');
            } else {
                toggleIcon.classList.remove('fa-chevron-right');
                toggleIcon.classList.add('fa-chevron-left');
            }
        }
    </script>
</body>
</html>