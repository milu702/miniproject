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
$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Farmer';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Weather</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base styles */
        :root {
            --primary-color: #2c5282;
            --secondary-color: #4299e1;
            --accent-color: #90cdf4;
        }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background: #f7fafc;
        }

        /* Sidebar styles */
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

        /* Main content */
        .main-content {
            flex: 1;
            margin-left: 80px;
            padding: 20px;
        }

        .layout-container {
            display: flex;
            min-height: 100vh;
        }

        /* Weather specific styles */
        .weather-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .weather-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .farm-info-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin-bottom: 30px;
        }

        .farm-info-header {
            margin-bottom: 20px;
        }

        .farm-info-header h2 {
            color: var(--primary-color);
            margin: 0;
        }

        /* Additional weather-specific styles */
        .forecast-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .forecast-card {
            background: var(--bg-color);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="layout-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-seedling"></i> <span>GrowGuide</span></h2>
            </div>
            <nav class="nav-menu">
                <a href="farmer.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="farm.php" class="nav-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>Farm Details</span>
                </a>
                <a href="analytics.php" class="nav-item">
                    <i class="fas fa-chart-line"></i>
                    <span>Analytics</span>
                </a>
                <a href="schedule.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Schedule</span>
                </a>
                <a href="weather.php" class="nav-item active">
                    <i class="fas fa-cloud-sun"></i>
                    <span>Weather</span>
                </a>
                <a href="inventory.php" class="nav-item">
                    <i class="fas fa-warehouse"></i>
                    <span>Inventory</span>
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="farm-info-card">
                <div class="farm-info-header">
                    <h2><i class="fas fa-cloud-sun"></i> Weather Dashboard</h2>
                </div>
                
                <div class="weather-grid">
                    <div class="weather-card">
                        <div class="weather-icon">
                            <i class="fas fa-temperature-high"></i>
                        </div>
                        <h3>Temperature</h3>
                        <div class="stat-value">25°C</div>
                        <p>Optimal range: 10-35°C</p>
                    </div>

                    <div class="weather-card">
                        <div class="weather-icon">
                            <i class="fas fa-tint"></i>
                        </div>
                        <h3>Humidity</h3>
                        <div class="stat-value">75%</div>
                        <p>Optimal range: 70-80%</p>
                    </div>

                    <div class="weather-card">
                        <div class="weather-icon">
                            <i class="fas fa-cloud-rain"></i>
                        </div>
                        <h3>Rainfall</h3>
                        <div class="stat-value">1500mm</div>
                        <p>Annual requirement: 1500-4000mm</p>
                    </div>
                </div>

                <div class="farm-info-card">
                    <h3>5-Day Forecast</h3>
                    <div class="forecast-grid">
                        <?php
                        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
                        foreach ($days as $day) {
                            echo "<div class='forecast-card'>
                                    <h4>$day</h4>
                                    <i class='fas fa-cloud'></i>
                                    <p>24°C</p>
                                    <p>70% Humidity</p>
                                </div>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 