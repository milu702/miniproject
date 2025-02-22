<?php
session_start();

// Ensure user is logged in and has the 'farmer' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: login.php");
    exit();
}

// Include database configuration
require_once 'config.php';

// Check if database connection exists
if (!$conn) {
    die("Database connection failed");
}
if (isset($_SESSION['redirect_url'])) {
    $redirect_url = $_SESSION['redirect_url'];
    unset($_SESSION['redirect_url']); // Clear the stored URL
    header("Location: " . $redirect_url);
    exit();
}

// After database connection checks, add this code
$weather_api_key = "cc02c9dee7518466102e748f211bca05";

// Get cardamom specific data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT 
        f.farmer_id,
        u.username,
        COALESCE(f.farm_size, 0) as farm_size,
        COALESCE(f.farm_location, 'Not Set') as farm_location,
        COALESCE(COUNT(c.crop_id), 0) as total_cardamom_plots,
        COALESCE(SUM(c.area_planted), 0) as total_cardamom_area,
        COALESCE(SUM(CASE 
            WHEN c.status = 'harvested' THEN c.harvest_yield 
            ELSE 0 
        END), 0) as total_revenue,
        COALESCE(p.soil_type, '') as soil_type,
        COALESCE(p.soil_ph, 0) as soil_ph,
        COALESCE(p.soil_moisture, 0) as soil_moisture,
        0 as temperature,
        0 as humidity,
        0 as rainfall
    FROM farmers f
    LEFT JOIN crops c ON f.farmer_id = c.farmer_id AND c.crop_name LIKE '%cardamom%'
    LEFT JOIN farmer_profiles p ON f.farmer_id = p.farmer_id
    LEFT JOIN users u ON f.user_id = u.id
    WHERE f.user_id = ?
    GROUP BY f.farmer_id, u.username, f.farm_size, f.farm_location, p.soil_type, p.soil_ph, p.soil_moisture"
);

if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$farmerData = $stmt->get_result()->fetch_assoc() ?: [
    'farm_size' => 0,
    'total_cardamom_plots' => 0,
    'total_cardamom_area' => 0,
    'total_revenue' => 0
];

// Get recent crops
$stmt = $conn->prepare("
    SELECT crop_name, planted_date, status, area_planted, expected_harvest_date
    FROM crops 
    WHERE farmer_id = ?
    ORDER BY planted_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $farmerData['farmer_id']);
$stmt->execute();
$recentCrops = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$username = isset($farmerData['username']) && !empty($farmerData['username']) 
    ? htmlspecialchars($farmerData['username']) 
    : (isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Farmer');

// Get farmer's location from database
$location_stmt = $conn->prepare("
    SELECT latitude, longitude, farm_location 
    FROM farmers 
    WHERE user_id = ?
");
$location_stmt->bind_param("i", $user_id);
$location_stmt->execute();
$location_result = $location_stmt->get_result()->fetch_assoc();

$weather_data = null;
if ($location_result && $location_result['latitude'] && $location_result['longitude']) {
    $lat = $location_result['latitude'];
    $lon = $location_result['longitude'];
    $weather_url = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lon}&units=metric&appid={$weather_api_key}";
    
    $weather_response = file_get_contents($weather_url);
    if ($weather_response) {
        $weather_data = json_decode($weather_response, true);
    }
}

// Add this after the database connection checks
function getWeatherData($location) {
    $api_key = "cc02c9dee7518466102e748f211bca05";
    $url = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($location) . "&units=metric&appid=" . $api_key;
    
    $response = @file_get_contents($url);
    if ($response) {
        return json_decode($response, true);
    }
    return null;
}

// Handle location form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_location'])) {
    $new_location = $_POST['farm_location'];
    
    // Verify the location exists by checking weather data
    $weather_check = getWeatherData($new_location);
    
    if ($weather_check) {
        // Update location in database
        $update_stmt = $conn->prepare("UPDATE farmers SET farm_location = ? WHERE user_id = ?");
        $update_stmt->bind_param("si", $new_location, $_SESSION['user_id']);
        
        if ($update_stmt->execute()) {
            $_SESSION['success'] = "Location updated successfully!";
        } else {
            $_SESSION['error'] = "Error updating location.";
        }
    } else {
        $_SESSION['error'] = "Invalid location. Please enter a valid city name.";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Farmer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2d6a4f;
            --secondary-color: #40916c;
            --accent-color: #95d5b2;
            --bg-color: #f0f7f4;
            --text-color: #1b4332;
            --sidebar-width: 250px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 20px;
            color: var(--text-color);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .cardamom-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .cardamom-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .cardamom-card h3 {
            color: var(--primary-color);
            margin-top: 0;
        }

        .cardamom-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: var(--primary-color);
        }

        .cultivation-tips {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-top: 20px;
        }

        .tip-item {
            margin-bottom: 15px;
            padding-left: 20px;
            border-left: 3px solid var(--accent-color);
        }

        .layout-container {
            display: flex;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            position: relative;
        }

        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .farmer-profile {
            text-align: center;
            padding: 20px 0;
        }

        .farmer-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--accent-color);
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
        }

        .nav-menu {
            margin-top: 30px;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 250px);
            justify-content: space-between;
        }

        .nav-menu-items {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .nav-item {
            padding: 15px;
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            transition: 0.3s;
            border-radius: 8px;
            margin-bottom: 5px;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.1);
        }

        .nav-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .nav-item.active {
            background: rgba(255,255,255,0.2);
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            width: calc(100% - var(--sidebar-width));
            box-sizing: border-box;
        }

        .farm-info-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .farm-info-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .location-badge {
            background: var(--accent-color);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
        }

        .data-form {
            padding: 20px 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .form-group {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
        }

        .input-group {
            margin-bottom: 15px;
        }

        .input-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-color);
            font-weight: 500;
        }

        .input-group input,
        .input-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .submit-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }

        .submit-btn:hover {
            background: var(--secondary-color);
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .welcome-section {
            background: linear-gradient(135deg, #2d6a4f, #40916c);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .welcome-section h1 {
            font-size: 2.5em;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .welcome-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .welcome-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
            backdrop-filter: blur(5px);
            text-align: center;
        }

        .welcome-card i {
            font-size: 2.5em;
            margin-bottom: 15px;
            color: var(--accent-color);
        }

        .welcome-card h3 {
            font-size: 1.3em;
            margin-bottom: 10px;
            color: white;
        }

        .welcome-card p {
            font-size: 0.95em;
            line-height: 1.5;
            color: rgba(255, 255, 255, 0.9);
        }

        .quick-guide {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .guide-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .guide-item {
            text-align: center;
            padding: 20px;
            background: var(--bg-color);
            border-radius: 10px;
            transition: transform 0.3s ease;
        }

        .guide-item:hover {
            transform: translateY(-5px);
        }

        .guide-item i {
            font-size: 2em;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .guide-item h4 {
            color: var(--text-color);
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .guide-item p {
            color: #666;
            font-size: 0.9em;
        }

        .fas.fa-hand-wave {
            animation: wave 1s infinite;
        }

        @keyframes wave {
            0% { transform: rotate(0deg); }
            25% { transform: rotate(20deg); }
            50% { transform: rotate(0deg); }
            75% { transform: rotate(-20deg); }
            100% { transform: rotate(0deg); }
        }

        .horizontal-layout {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
        }

        .cardamom-section {
            flex: 2;
        }

        .tasks-section {
            flex: 1;
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .cardamom-types {
            display: flex;
            gap: 20px;
            overflow-x: auto;
            padding: 10px 0;
        }

        .cardamom-card {
            min-width: 300px;
            flex: 1;
        }

        .task-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .task-item {
            padding: 15px;
            background: var(--bg-color);
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .task-title {
            font-weight: bold;
            color: var(--primary-color);
        }

        .task-date {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }

        .location-weather-section {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .location-card {
            padding: 20px;
        }

        .weather-info {
            margin-top: 20px;
            padding: 15px;
            background: var(--bg-color);
            border-radius: 10px;
        }

        .weather-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .weather-details p {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1em;
        }

        .weather-details i {
            color: var(--primary-color);
        }

        .location-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 1em;
        }

        .location-btn:hover {
            background: var(--secondary-color);
        }

        .location-form {
            margin: 20px 0;
            padding: 20px;
            background: var(--bg-color);
            border-radius: 10px;
        }

        .location-form .input-group {
            margin-bottom: 15px;
        }

        .location-form input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .current-location {
            margin: 15px 0;
            padding: 10px;
            background: #e8f5e9;
            border-radius: 5px;
        }

        .weather-info {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .alert-soil-test {
            background: linear-gradient(135deg, #2d6a4f, #40916c);
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 20px;
            animation: pulse 2s infinite;
        }

        .soil-test-icon {
            background: rgba(255, 255, 255, 0.2);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .soil-test-icon i {
            font-size: 24px;
            color: white;
        }

        .soil-test-message {
            flex: 1;
        }

        .soil-test-message strong {
            font-size: 1.2em;
            display: block;
            margin-bottom: 5px;
        }

        .soil-test-message p {
            margin: 0;
        }

        .soil-test-message a {
            color: white;
            text-decoration: none;
            font-weight: bold;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            margin-left: 10px;
            transition: background 0.3s ease;
        }

        .soil-test-message a:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }

        .nav-menu-bottom {
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: auto;
        }

        .logout-btn {
            color: #ff6b6b !important;
            transition: background-color 0.3s ease, color 0.3s ease;
            width: 100%;
            display: flex;
            align-items: center;
        }

        .logout-btn:hover {
            background-color: #ff6b6b !important;
            color: white !important;
        }

        .weather-banner {
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
            position: relative;
        }

        .weather-banner-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideText 20s linear infinite;
        }

        .weather-text {
            white-space: nowrap;
            margin-right: 20px;
        }

        .weather-link {
            color: white;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 20px;
            transition: background 0.3s ease;
            white-space: nowrap;
        }

        .weather-link:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        @keyframes slideText {
            0% {
                transform: translateX(100%);
            }
            100% {
                transform: translateX(-100%);
            }
        }

        .weather-banner:hover .weather-banner-content {
            animation-play-state: paused;
        }
    </style>
</head>
<body>
    <div class="layout-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-seedling"></i> GrowGuide</h2>
            </div>
            <div class="farmer-profile">
                <div class="farmer-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h3><?php echo $username; ?></h3>
                <p>Cardamom Farmer</p>
            </div>
            <nav class="nav-menu">
                <div class="nav-menu-items">
                    <a href="farmer.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'farmer.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    
                    <a href="soil_test.php?farmer_id=<?php echo $farmerData['farmer_id']; ?>" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'soil_test.php' ? 'active' : ''; ?>">
                        <i class="fas fa-flask"></i> Soil Test
                    </a>
                    
                    <a href="analytics.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'analytics.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-line"></i> Analytics
                    </a>
                    <a href="schedule.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'schedule.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar"></i> Schedule
                    </a>
                    <a href="weather.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'weather.php' ? 'active' : ''; ?>">
                        <i class="fas fa-cloud-sun"></i> Weather
                    </a>
                    <a href="settings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </div>
                <div class="nav-menu-bottom">
                    <a href="logout.php" class="nav-item logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </nav>
            
        </div>

        <div class="main-content">
            <div class="weather-banner">
                <div class="weather-banner-content">
                    <span class="weather-text">
                        <?php 
                        if ($weather_data) {
                            echo "Current weather in " . htmlspecialchars($location_result['farm_location']) . ": " . 
                                 round($weather_data['main']['temp']) . "°C, " . 
                                 ucfirst($weather_data['weather'][0]['description']);
                        }
                        ?>
                    </span>
                    <a href="weather.php" class="weather-link">Check detailed weather forecast →</a>
                </div>
            </div>

            <div class="alert alert-soil-test">
                <div class="soil-test-icon">
                    <i class="fas fa-flask"></i>
                </div>
                <div class="soil-test-message">
                    <strong>Time to Test Your Soil!</strong>
                    <p>Ensure optimal cardamom growth with regular soil testing. <a href="soil_test.php">Test Now <i class="fas fa-arrow-right"></i></a></p>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Add Welcome Section -->
            <div class="welcome-section">
                <h1><i class="fas fa-hand-wave"></i> Welcome, <?php echo $username; ?>!</h1>
                <div class="welcome-cards">
                    <div class="welcome-card">
                        <i class="fas fa-leaf"></i>
                        <h3>About GrowGuide</h3>
                        <p>Your intelligent companion for cardamom farming. We provide personalized recommendations and insights to help you maximize your yield.</p>
                    </div>
                    <div class="welcome-card">
                        <i class="fas fa-lightbulb"></i>
                        <h3>Getting Started</h3>
                        <p>Update your farm data regularly and check the dashboard for real-time insights. Use the navigation menu to access different features.</p>
                    </div>
                    <div class="welcome-card">
                        <i class="fas fa-chart-line"></i>
                        <h3>Track Progress</h3>
                        <p>Monitor your farm's performance, soil conditions, and weather data. Set tasks and get timely reminders for important activities.</p>
                    </div>
                </div>
            </div>

            <!-- Add Quick Guide Section -->
            <div class="quick-guide">
                <h2><i class="fas fa-book-reader"></i> Quick Guide</h2>
                <div class="guide-grid">
                    <div class="guide-item">
                        <i class="fas fa-tachometer-alt"></i>
                        <h4>Dashboard</h4>
                        <p>Overview of your farm metrics and current status</p>
                    </div>
                    <div class="guide-item">
                        <i class="fas fa-flask"></i>
                        <h4>Soil Analysis</h4>
                        <p>Track and update soil conditions</p>
                    </div>
                    <div class="guide-item">
                        <i class="fas fa-calendar-check"></i>
                        <h4>Schedule</h4>
                        <p>Plan and manage farming activities</p>
                    </div>
                    <div class="guide-item">
                        <i class="fas fa-cloud-sun"></i>
                        <h4>Weather</h4>
                        <p>Real-time weather updates and forecasts</p>
                    </div>
                </div>
            </div>

            <!-- Modified layout for cardamom types and tasks -->
            <div class="horizontal-layout">
                <div class="cardamom-section">
                    <h2><i class="fas fa-leaf"></i> Cardamom Varieties</h2>
                    <div class="cardamom-types">
                        <div class="cardamom-card">
                            <h3>Malabar Cardamom</h3>
                            <img src="img/harvast.jpeg" alt="Malabar Cardamom">
                            <p><strong>Characteristics:</strong></p>
                            <ul>
                                <li>Large, dark green pods</li>
                                <li>Strong aromatic flavor</li>
                                <li>Best for culinary use</li>
                                <li>Harvest period: 120-150 days</li>
                            </ul>
                        </div>

                        <div class="cardamom-card">
                            <h3>Mysore Cardamom</h3>
                            <img src="img/pla.jpg" alt="Mysore Cardamom">
                            <p><strong>Characteristics:</strong></p>
                            <ul>
                                <li>Medium-sized, light green pods</li>
                                <li>Mild, sweet flavor</li>
                                <li>High oil content</li>
                                <li>Harvest period: 100-120 days</li>
                            </ul>
                        </div>

                        <div class="cardamom-card">
                            <h3>Vazhukka Cardamom</h3>
                            <img src="img/card45.jpg" alt="Vazhukka Cardamom">
                            <p><strong>Characteristics:</strong></p>
                            <ul>
                                <li>Small, light green pods</li>
                                <li>Intense aroma</li>
                                <li>Disease resistant</li>
                                <li>Harvest period: 90-110 days</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="tasks-section">
                    <h2><i class="fas fa-tasks"></i> Upcoming Tasks</h2>
                    <div class="task-list">
                        <?php
                        try {
                            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE user_id = ? AND task_date >= CURRENT_DATE ORDER BY task_date ASC LIMIT 5");
                            $stmt->execute([$user_id]);
                            $tasks = $stmt->fetchAll();

                            if (count($tasks) > 0) {
                                foreach ($tasks as $task) {
                                    echo '<div class="task-item">
                                        <div class="task-content">
                                            <div class="task-title">' . htmlspecialchars($task['title']) . '</div>
                                            <div class="task-date">' . date('M d, Y H:i', strtotime($task['task_date'])) . '</div>
                                        </div>
                                    </div>';
                                }
                            } else {
                                echo '<p>No upcoming tasks</p>';
                            }
                        } catch (PDOException $e) {
                            echo '<p>Error loading tasks</p>';
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div class="cultivation-tips">
                <h2><i class="fas fa-book"></i> Cardamom Cultivation Guide</h2>
                <div class="tip-item">
                    <h3>Ideal Growing Conditions</h3>
                    <p>Temperature: 10-35°C<br>
                       Rainfall: 1500-4000mm/year<br>
                       Altitude: 600-1500m above sea level<br>
                       Soil pH: 6.0-6.5</p>
                </div>
                <div class="tip-item">
                    <h3>Planting Season</h3>
                    <p>Best planted during the pre-monsoon period (May-June)</p>
                </div>
                <div class="tip-item">
                    <h3>Spacing</h3>
                    <p>2m x 2m for optimal growth</p>
                </div>
                <div class="tip-item">
                    <h3>Irrigation</h3>
                    <p>Regular irrigation needed during dry spells</p>
                </div>
            </div>

            <div class="location-weather-section">
                <div class="location-card">
                    <h2><i class="fas fa-map-marker-alt"></i> Farm Location</h2>
                    
                    <form method="POST" class="location-form">
                        <div class="input-group">
                            <label for="farm_location">Farm Location:</label>
                            <input type="text" 
                                   id="farm_location" 
                                   name="farm_location" 
                                   value="<?php echo isset($location_result['farm_location']) ? htmlspecialchars($location_result['farm_location']) : ''; ?>" 
                                   placeholder="Enter city name"
                                   required>
                        </div>
                        <button type="submit" name="update_location" class="location-btn">
                            <i class="fas fa-save"></i> Update Location
                        </button>
                    </form>

                    <?php if ($location_result && $location_result['farm_location']): ?>
                        <div class="current-location">
                            <p><strong>Current Location:</strong> <?php echo htmlspecialchars($location_result['farm_location']); ?></p>
                        </div>
                        
                        <?php if ($weather_data): ?>
                            <div class="weather-info">
                                <h3>Current Weather</h3>
                                <div class="weather-details">
                                    <p><i class="fas fa-temperature-high"></i> Temperature: <?php echo round($weather_data['main']['temp']); ?>°C</p>
                                    <p><i class="fas fa-tint"></i> Humidity: <?php echo $weather_data['main']['humidity']; ?>%</p>
                                    <p><i class="fas fa-wind"></i> Wind: <?php echo $weather_data['wind']['speed']; ?> m/s</p>
                                    <p><i class="fas fa-cloud"></i> Weather: <?php echo ucfirst($weather_data['weather'][0]['description']); ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

