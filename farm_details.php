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

        /* Main content styles */
        .layout-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 80px;
            padding: 20px;
        }

        /* Weather specific styles */
        .weather-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 30px;
        }

        .weather-header {
            margin-bottom: 20px;
        }

        .weather-header h2 {
            color: var(--primary-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .weather-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .weather-item {
            background: linear-gradient(135deg, #f6f9fc, #fff);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .weather-detail {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .weather-icon {
            font-size: 2rem;
            margin-right: 15px;
            color: var(--primary-color);
        }

        .weather-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        .weather-label {
            color: #666;
            font-size: 0.9rem;
        }

        .forecast-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .forecast-item {
            background: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .forecast-day {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .submit-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 20px;
        }

        .submit-btn:hover {
            background-color: var(--secondary-color);
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
            <div class="weather-card">
                <div class="weather-header">
                    <h2><i class="fas fa-cloud-sun"></i> Current Weather</h2>
                </div>
                <div class="weather-grid">
                    <div class="weather-item">
                        <div class="weather-detail">
                            <i class="fas fa-temperature-high weather-icon"></i>
                            <div>
                                <div class="weather-value">24°C</div>
                                <div class="weather-label">Temperature</div>
                            </div>
                        </div>
                        <div class="weather-detail">
                            <i class="fas fa-tint weather-icon"></i>
                            <div>
                                <div class="weather-value">65%</div>
                                <div class="weather-label">Humidity</div>
                            </div>
                        </div>
                    </div>
                    <div class="weather-item">
                        <div class="weather-detail">
                            <i class="fas fa-wind weather-icon"></i>
                            <div>
                                <div class="weather-value">12 km/h</div>
                                <div class="weather-label">Wind Speed</div>
                            </div>
                        </div>
                        <div class="weather-detail">
                            <i class="fas fa-umbrella weather-icon"></i>
                            <div>
                                <div class="weather-value">20%</div>
                                <div class="weather-label">Chance of Rain</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="weather-card">
                <div class="weather-header">
                    <h2><i class="fas fa-calendar-alt"></i> 5-Day Forecast</h2>
                </div>
                <div class="forecast-container">
                    <div class="forecast-item">
                        <div class="forecast-day">Today</div>
                        <i class="fas fa-sun weather-icon"></i>
                        <div class="weather-value">24°C</div>
                    </div>
                    <div class="forecast-item">
                        <div class="forecast-day">Tomorrow</div>
                        <i class="fas fa-cloud-sun weather-icon"></i>
                        <div class="weather-value">22°C</div>
                    </div>
                    <div class="forecast-item">
                        <div class="forecast-day">Wednesday</div>
                        <i class="fas fa-cloud weather-icon"></i>
                        <div class="weather-value">20°C</div>
                    </div>
                    <div class="forecast-item">
                        <div class="forecast-day">Thursday</div>
                        <i class="fas fa-cloud-showers-heavy weather-icon"></i>
                        <div class="weather-value">19°C</div>
                    </div>
                    <div class="forecast-item">
                        <div class="forecast-day">Friday</div>
                        <i class="fas fa-cloud-sun weather-icon"></i>
                        <div class="weather-value">21°C</div>
                    </div>
                </div>
            </div>

            <div class="weather-card">
                <div class="weather-header">
                    <h2><i class="fas fa-bell"></i> Weather Alerts</h2>
                </div>
                <button class="submit-btn" onclick="setupWeatherAlerts()">
                    <i class="fas fa-plus"></i> Set Up Weather Alerts
                </button>
            </div>
        </div>
    </div>

    <script>
        // Function to handle weather alerts setup
        function setupWeatherAlerts() {
            // Add your weather alerts setup logic here
            alert('Weather alerts setup will be implemented here');
        }

        // You would typically add API calls here to fetch real weather data
        document.addEventListener('DOMContentLoaded', function() {
            // Add your weather API integration here
            // Example: fetchWeatherData();
        });
    </script>
</body>
</html>