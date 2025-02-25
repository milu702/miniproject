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

// Get farmer's location from users table instead of farmers table
$location_stmt = $conn->prepare("
    SELECT farm_location 
    FROM users 
    WHERE id = ?
");
$location_stmt->bind_param("i", $_SESSION['user_id']);
$location_stmt->execute();
$location_result = $location_stmt->get_result()->fetch_assoc();

// Function to get coordinates from location name using OpenWeatherMap Geocoding API
function getCoordinates($location, $api_key) {
    $url = "http://api.openweathermap.org/geo/1.0/direct?q=" . urlencode($location) . "&limit=1&appid=" . $api_key;
    $response = @file_get_contents($url);
    if ($response) {
        $data = json_decode($response, true);
        if (!empty($data)) {
            return [
                'lat' => $data[0]['lat'],
                'lon' => $data[0]['lon']
            ];
        }
    }
    return null;
}

// After database connection checks, add this array of Kerala districts
$kerala_districts = [
    'Alappuzha',
    'Ernakulam',
    'Idukki',
    'Kannur',
    'Kasaragod',
    'Kollam',
    'Kottayam',
    'Kozhikode',
    'Malappuram',
    'Palakkad',
    'Pathanamthitta',
    'Thiruvananthapuram',
    'Thrissur',
    'Wayanad'
];

// Handle location form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_location'])) {
    $new_location = $_POST['farm_location'];
    
    // Get coordinates for the new location
    $coordinates = getCoordinates($new_location, $weather_api_key);
    
    if ($coordinates) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Update farm_location in users table only
            $update_users = $conn->prepare("
                UPDATE users 
                SET farm_location = ? 
                WHERE id = ?
            ");
            $update_users->bind_param("si", 
                $new_location, 
                $_SESSION['user_id']
            );
            $update_users->execute();

            // Commit transaction
            $conn->commit();
            
            $_SESSION['success'] = "Location updated successfully!";
            
            // Update location_result for immediate display
            $location_result = [
                'farm_location' => $new_location
            ];
            
            // Get weather data for new location
            $weather_url = "https://api.openweathermap.org/data/2.5/weather?lat={$coordinates['lat']}&lon={$coordinates['lon']}&units=metric&appid={$weather_api_key}";
            $weather_response = file_get_contents($weather_url);
            if ($weather_response) {
                $weather_data = json_decode($weather_response, true);
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $_SESSION['error'] = "Error updating location: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Invalid location. Please enter a valid city name.";
    }
    
    // Redirect to refresh the page
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get weather data if location exists
$weather_data = null;
if ($location_result && isset($location_result['farm_location'])) {
    $weather_url = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($location_result['farm_location']) . "&units=metric&appid=" . $weather_api_key;
    
    $weather_response = @file_get_contents($weather_url);
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

        .farmer-location {
            margin-top: 10px;
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            font-size: 0.9em;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--accent-color);
        }

        .farmer-location i {
            color: #ff6b6b;
            font-size: 1em;
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
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .weather-banner-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .weather-info-header {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .weather-icon {
            font-size: 2.5em;
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: float 3s ease-in-out infinite;
        }

        .weather-details-header {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .weather-location {
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .weather-temp {
            font-size: 2em;
            font-weight: bold;
        }

        .weather-description {
            font-size: 1.1em;
            opacity: 0.9;
        }

        .weather-extra-info {
            display: flex;
            gap: 15px;
            margin-top: 5px;
            font-size: 0.9em;
        }

        .weather-extra-info span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .weather-link {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 25px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .weather-link:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        @keyframes float {
            0% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-5px);
            }
            100% {
                transform: translateY(0px);
            }
        }

        @media (max-width: 768px) {
            .weather-banner-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .weather-info-header {
                flex-direction: column;
            }
            
            .weather-extra-info {
                justify-content: center;
            }
            
            .weather-link {
                margin-top: 15px;
            }
        }

        .info-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 30px;
        }

        @media (max-width: 992px) {
            .info-columns {
                grid-template-columns: 1fr;
            }
        }

        .cardamom-section {
            margin-bottom: 30px;
        }

        .cardamom-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .location-form select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            background-color: white;
            cursor: pointer;
        }

        .location-form select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 5px rgba(45, 106, 79, 0.2);
        }

        .location-form select option {
            padding: 10px;
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
                <?php if (isset($location_result['farm_location']) && !empty($location_result['farm_location'])): ?>
                    <div class="farmer-location">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($location_result['farm_location']); ?>
                    </div>
                <?php endif; ?>
            </div>
            <nav class="nav-menu">
                <div class="nav-menu-items">
                    <a href="farmer.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'farmer.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    
                    <a href="soil_test.php?farmer_id=<?php echo $farmerData['farmer_id']; ?>" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'soil_test.php' ? 'active' : ''; ?>">
                        <i class="fas fa-flask"></i> Soil Test
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
                <?php if ($weather_data): ?>
                    <div class="weather-banner-content">
                        <div class="weather-info-header">
                            <div class="weather-icon">
                                <?php
                                $weather_code = $weather_data['weather'][0]['id'];
                                $icon_class = 'fas ';
                                
                                // Map weather codes to Font Awesome icons
                                if ($weather_code >= 200 && $weather_code < 300) {
                                    $icon_class .= 'fa-bolt'; // Thunderstorm
                                } elseif ($weather_code >= 300 && $weather_code < 400) {
                                    $icon_class .= 'fa-cloud-rain'; // Drizzle
                                } elseif ($weather_code >= 500 && $weather_code < 600) {
                                    $icon_class .= 'fa-cloud-showers-heavy'; // Rain
                                } elseif ($weather_code >= 600 && $weather_code < 700) {
                                    $icon_class .= 'fa-snowflake'; // Snow
                                } elseif ($weather_code >= 700 && $weather_code < 800) {
                                    $icon_class .= 'fa-smog'; // Atmosphere
                                } elseif ($weather_code == 800) {
                                    $icon_class .= 'fa-sun'; // Clear sky
                                } else {
                                    $icon_class .= 'fa-cloud'; // Clouds
                                }
                                ?>
                                <i class="<?php echo $icon_class; ?>"></i>
                            </div>
                            <div class="weather-details-header">
                                <div class="weather-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($location_result['farm_location']); ?>
                                </div>
                                <div class="weather-temp">
                                    <?php echo round($weather_data['main']['temp']); ?>°C
                                </div>
                                <div class="weather-description">
                                    <?php echo ucfirst($weather_data['weather'][0]['description']); ?>
                                </div>
                                <div class="weather-extra-info">
                                    <span><i class="fas fa-tint"></i> <?php echo $weather_data['main']['humidity']; ?>%</span>
                                    <span><i class="fas fa-wind"></i> <?php echo round($weather_data['wind']['speed']); ?> m/s</span>
                                </div>
                            </div>
                        </div>
                        <a href="weather.php" class="weather-link">
                            <i class="fas fa-chart-line"></i>
                            Detailed Forecast
                        </a>
                    </div>
                <?php else: ?>
                    <div class="weather-banner-content">
                        <div class="weather-info-header">
                            <div class="weather-icon">
                                <i class="fas fa-cloud-sun"></i>
                            </div>
                            <div class="weather-details-header">
                                <div class="weather-description">
                                    Weather information unavailable
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
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

            <!-- Modified layout for cardamom types -->
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

            <!-- Two-column layout for cultivation tips and location weather -->
            <div class="info-columns">
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
                                <label for="farm_location">Select District:</label>
                                <select id="farm_location" name="farm_location" required>
                                    <option value="">Select a district</option>
                                    <?php foreach ($kerala_districts as $district): ?>
                                        <option value="<?php echo htmlspecialchars($district); ?>" 
                                                <?php echo (isset($location_result['farm_location']) && 
                                                          $location_result['farm_location'] === $district) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($district); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
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
    </div>
</body>
</html>

