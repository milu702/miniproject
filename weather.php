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

// Add API key configuration - add this to your config.php
$weather_api_key = "cc02c9dee7518466102e748f211bca05"; // Store just the API key

// Define Kerala districts array with sub-places
$kerala_districts = [
    'Alappuzha' => [],
    'Ernakulam' => [],
    'Idukki' => [
        'Thodupuzha',
        'Munnar',
        'Adimali',
        'Devikulam',
        'Kattappana',
        'Nedumkandam',
        'Peermade',
        'Vagamon'
    ],
    'Kannur' => [],
    'Kasaragod' => [],
    'Kollam' => [],
    'Kottayam' => [],
    'Kozhikode' => [],
    'Malappuram' => [],
    'Palakkad' => [],
    'Pathanamthitta' => [],
    'Thiruvananthapuram' => [],
    'Thrissur' => [],
    'Wayanad' => [
        'Kalpetta',
        'Sulthan Bathery',
        'Mananthavady',
        'Meenangadi',
        'Vythiri',
        'Pulpally',
        'Panamaram'
    ]
];

// Handle location form submission
$weather_data = [];
$forecast_data = [];
$soil_moisture = 0;
$solar_radiation = 0;
$selected_district = '';
$message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $selected_district = isset($_POST['district']) ? $_POST['district'] : '';
    
    // Check if the selected district is suitable for cardamom plantation
    if ($selected_district === 'Wayanad' || $selected_district === 'Idukki') {
        $location = $selected_district . ', Kerala, India';
        getWeatherData($location, $weather_data, $forecast_data, $soil_moisture, $solar_radiation, $weather_api_key);
    } else {
        // Set a message indicating that the weather is not suitable for cardamom plantation
        $weather_data = null; // Clear weather data
        $message = "The weather is not suitable for cardamom plantation in " . htmlspecialchars($selected_district) . ".";
        
        // Get weather data and forecast even if not suitable
        $location = $selected_district . ', Kerala, India';
        getWeatherData($location, $weather_data, $forecast_data, $soil_moisture, $solar_radiation, $weather_api_key);
    }
}

// Function to get weather data
function getWeatherData($location, &$weather_data, &$forecast_data, &$soil_moisture, &$solar_radiation, $api_key) {
    // Function to make API calls using cURL
    function makeApiCall($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            return json_decode($response, true);
        }
        return null;
    }

    $geocode_url = "http://api.openweathermap.org/geo/1.0/direct?q=" . urlencode($location) . "&limit=1&appid=" . $api_key;
    $geocode_data = makeApiCall($geocode_url);
    
    if (!empty($geocode_data)) {
        $lat = $geocode_data[0]['lat'];
        $lon = $geocode_data[0]['lon'];
        
        // Get weather data
        $api_url = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lon}&units=metric&appid={$api_key}";
        $weather_data = makeApiCall($api_url);

        // Get forecast data
        $forecast_url = "https://api.openweathermap.org/data/2.5/forecast?lat={$lat}&lon={$lon}&units=metric&appid={$api_key}";
        $forecast_data = makeApiCall($forecast_url);

        // Simulate soil moisture and solar radiation data (since OpenWeatherMap doesn't provide these)
        // In a real application, you would get this from soil sensors or specialized APIs
        $soil_moisture = rand(30, 80); // Random value between 30-80%
        $solar_radiation = rand(100, 1000); // Random value between 100-1000 W/m²
    }
}

// Function to get weather analysis based on conditions
function getWeatherAnalysis($weather_data, $soil_moisture) {
    $analysis = [];
    
    // Temperature analysis
    $temp = $weather_data['main']['temp'];
    if ($temp < 15 || $temp > 25) {
        $analysis['temperature'] = ['status' => 'Poor', 'message' => 'Temperature out of ideal range (15°C - 25°C). Consider using shade nets or irrigation to regulate temperature.'];
    } else {
        $analysis['temperature'] = ['status' => 'Good', 'message' => 'Optimal temperature for cardamom growth.'];
    }

    // Humidity analysis
    $humidity = $weather_data['main']['humidity'];
    if ($humidity < 70) {
        $analysis['humidity'] = ['status' => 'Poor', 'message' => 'Low humidity (<70%). Consider using misting or increasing irrigation frequency.'];
    } elseif ($humidity > 90) {
        $analysis['humidity'] = ['status' => 'Warning', 'message' => 'High humidity (>90%). Monitor for fungal diseases and ensure proper air circulation.'];
    } else {
        $analysis['humidity'] = ['status' => 'Good', 'message' => 'Optimal humidity (70-90%) for cardamom growth.'];
    }

    // Wind speed analysis
    $wind_speed = $weather_data['wind']['speed'];
    if ($wind_speed > 2.8) { // 10 km/h ≈ 2.8 m/s
        $analysis['wind'] = ['status' => 'Warning', 'message' => 'High wind speeds may damage plants. Consider installing windbreaks or protective barriers.'];
    } else {
        $analysis['wind'] = ['status' => 'Good', 'message' => 'Wind speed is within acceptable range for cardamom cultivation.'];
    }

    // Pressure analysis
    $pressure = $weather_data['main']['pressure'];
    if ($pressure < 900) {
        $analysis['pressure'] = ['status' => 'Warning', 'message' => 'Low pressure may indicate incoming storms. Take precautionary measures.'];
    } elseif ($pressure > 1015) {
        $analysis['pressure'] = ['status' => 'Warning', 'message' => 'High pressure may lead to dry conditions. Monitor irrigation needs.'];
    } else {
        $analysis['pressure'] = ['status' => 'Good', 'message' => 'Atmospheric pressure is within normal range.'];
    }

    // Soil moisture analysis
    if ($soil_moisture < 30) {
        $analysis['soil'] = ['status' => 'Poor', 'message' => 'Soil too dry (<30%). Immediate irrigation needed.'];
    } elseif ($soil_moisture > 50) {
        $analysis['soil'] = ['status' => 'Warning', 'message' => 'Soil too wet (>50%). Reduce irrigation and ensure proper drainage.'];
    } else {
        $analysis['soil'] = ['status' => 'Good', 'message' => 'Optimal soil moisture (30-50%) for cardamom roots.'];
    }

    // Solar radiation analysis (using the global variable)
    global $solar_radiation;
    if ($solar_radiation < 200) {
        $analysis['solar'] = ['status' => 'Poor', 'message' => 'Insufficient light (<200 W/m²). Consider reducing shade coverage.'];
    } elseif ($solar_radiation > 500) {
        $analysis['solar'] = ['status' => 'Warning', 'message' => 'Excessive light (>500 W/m²). Increase shade coverage to protect plants.'];
    } else {
        $analysis['solar'] = ['status' => 'Good', 'message' => 'Optimal light levels (200-500 W/m²) for cardamom growth.'];
    }

    return $analysis;
}
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
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
        }

        .forecast-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            align-items: center;
            gap: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }

        .forecast-card:hover {
            transform: translateX(10px);
        }

        .forecast-date {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .forecast-date h4 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .forecast-date .date {
            color: #666;
            font-size: 0.9rem;
        }

        .forecast-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .forecast-temp {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .forecast-details {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .forecast-detail-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .forecast-detail-item i {
            color: var(--primary-color);
        }

        .forecast-desc {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .forecast-desc i {
            color: var(--primary-color);
        }

        .location-form {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .location-form select {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            background-color: white;
        }

        .location-form select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .location-form button {
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .location-form button:hover {
            background: var(--secondary-color);
        }

        /* Weather Overview Styles */
        .main-weather {
            grid-column: 1 / -1;
        }

        .weather-overview {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .weather-main {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .temperature {
            display: flex;
            flex-direction: column;
        }

        .temp-value {
            font-size: 3rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .weather-desc {
            font-size: 1.2rem;
            color: #666;
        }

        .weather-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 10px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
        }

        .detail-item i {
            font-size: 1.5rem;
            color: var(--primary-color);
            width: 30px;
            text-align: center;
        }

        .detail-info {
            display: flex;
            flex-direction: column;
        }

        .detail-info .label {
            font-size: 0.9rem;
            color: #666;
        }

        .detail-info .value {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
        }

        /* Add to your existing style section */
        .welcome-message {
            margin: 15px 0;
            color: #4a5568;
            font-size: 1.1rem;
        }

        .weather-analysis-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
            padding: 25px;
            margin: 30px 0;
        }

        .analysis-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        .analysis-table th,
        .analysis-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .analysis-table th {
            background-color: #f7fafc;
            font-weight: 600;
            color: #2d3748;
        }

        .status-good {
            color: #2f855a;
            font-weight: 600;
        }

        .status-warning {
            color: #c05621;
            font-weight: 600;
        }

        .status-poor {
            color: #c53030;
            font-weight: 600;
        }

        .running-bar {
            background-color: #2c5282;
            color: white;
            padding: 10px;
            overflow: hidden;
            white-space: nowrap;
            position: relative;
        }

        .running-content {
            display: inline-block;
            animation: move 30s linear infinite;
        }

        @keyframes move {
            0% {
                transform: translateX(100%);
            }
            100% {
                transform: translateX(-100%);
            }
        }

        /* New Running Message Box */
        .running-message-box {
            background: #f7fafc;
            border-radius: 10px;
            padding: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            overflow: hidden;
            position: relative;
        }

        .running-message {
            white-space: nowrap;
            animation: move 10s linear infinite;
            margin-left: 10px;
        }

        @keyframes move {
            0% {
                transform: translateX(100%);
            }
            100% {
                transform: translateX(-100%);
            }
        }

        /* Replace the running-bar and running-message-box styles with these new styles */
        .alert-box {
            background: linear-gradient(135deg, #f6f9fc, #ffffff);
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid #e2e8f0;
        }

        .alert-icon {
            background: var(--primary-color);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .alert-icon i {
            color: white;
            font-size: 1.5rem;
            animation: shake 2s infinite;
        }

        .alert-content {
            flex: 1;
            overflow: hidden;
        }

        .alert-messages {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .alert-message {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            border-radius: 8px;
            background: rgba(44, 82, 130, 0.05);
            transition: transform 0.3s ease;
        }

        .alert-message:hover {
            transform: translateX(10px);
            background: rgba(44, 82, 130, 0.1);
        }

        .alert-message i {
            color: var(--primary-color);
            width: 20px;
            text-align: center;
        }

        .alert-message span {
            color: #4a5568;
            font-size: 0.95rem;
        }

        @keyframes shake {
            0%, 100% { transform: rotate(0); }
            2%, 6% { transform: rotate(-5deg); }
            4%, 8% { transform: rotate(5deg); }
            10% { transform: rotate(0); }
        }

        .fa-shake {
            animation: shake 2s infinite;
        }

        /* Add these new styles to your existing style section */
        .warning-banner {
            background: #fff5f5;
            border: 1px solid #fc8181;
            border-radius: 8px;
            padding: 12px;
            margin: 15px 0;
            overflow: hidden;
            position: relative;
        }

        .warning-content {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #e53e3e;
            font-weight: 600;
            white-space: nowrap;
            animation: slowScroll 20s linear infinite;
        }

        .warning-content i {
            font-size: 1.2rem;
        }

        @keyframes slowScroll {
            0% {
                transform: translateX(100%);
            }
            100% {
                transform: translateX(-100%);
            }
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
                    <?php if (isset($message) && !empty($message)): ?>
                        <div class="warning-banner">
                            <div class="warning-content">
                                <i class="fas fa-exclamation-triangle"></i>
                                <?php echo $message; ?>
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="welcome-message">
                        <p style="font-size: 1.5rem; font-weight: bold; color: #2c5282;">Welcome back, <span style="color: #4299e1;"><?php echo $username; ?></span>! 🌱 Here's your personalized weather report for today.</p>
                    </div>
                </div>
                
                <!-- Replace the existing location form with this new form -->
                <form method="POST" class="location-form">
                    <select name="district" id="district" required onchange="this.form.submit()">
                        <option value="">Select District</option>
                        <?php foreach ($kerala_districts as $district => $places): ?>
                            <option value="<?php echo $district; ?>" <?php echo ($selected_district === $district) ? 'selected' : ''; ?>>
                                <?php echo $district; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit"><i class="fas fa-search"></i> Get Weather</button>
                </form>

                <div class="weather-grid">
                    <?php if (!empty($weather_data)): ?>
                        <div class="weather-card main-weather">
                            <h3><?php echo $selected_district; ?></h3>
                            <div class="weather-overview">
                                <div class="weather-main">
                                    <div class="weather-icon">
                                        <img src="http://openweathermap.org/img/wn/<?php echo $weather_data['weather'][0]['icon']; ?>@2x.png" 
                                             alt="<?php echo $weather_data['weather'][0]['description']; ?>">
                                    </div>
                                    <div class="temperature">
                                        <span class="temp-value"><?php echo round($weather_data['main']['temp']); ?>°C</span>
                                        <span class="weather-desc"><?php echo ucfirst($weather_data['weather'][0]['description']); ?></span>
                                    </div>
                                </div>
                                <div class="weather-details">
                                    <div class="detail-item">
                                        <i class="fas fa-temperature-high"></i>
                                        <div class="detail-info">
                                            <span class="label">Feels Like</span>
                                            <span class="value"><?php echo round($weather_data['main']['feels_like']); ?>°C</span>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-tint"></i>
                                        <div class="detail-info">
                                            <span class="label">Humidity</span>
                                            <span class="value"><?php echo $weather_data['main']['humidity']; ?>%</span>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-wind"></i>
                                        <div class="detail-info">
                                            <span class="label">Wind Speed</span>
                                            <span class="value"><?php echo $weather_data['wind']['speed']; ?> m/s</span>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <i class="fas fa-compress-arrows-alt"></i>
                                        <div class="detail-info">
                                            <span class="label">Pressure</span>
                                            <span class="value"><?php echo $weather_data['main']['pressure']; ?> hPa</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="weather-card">
                            <div class="detail-item">
                                <i class="fas fa-water"></i>
                                <div class="detail-info">
                                    <span class="label">Soil Moisture</span>
                                    <span class="value"><?php echo $soil_moisture; ?>%</span>
                                    <span class="description">Optimal range: 50-75%</span>
                                </div>
                            </div>
                        </div>

                        <div class="weather-card">
                            <div class="detail-item">
                                <i class="fas fa-sun"></i>
                                <div class="detail-info">
                                    <span class="label">Solar Radiation</span>
                                    <span class="value"><?php echo $solar_radiation; ?> W/m²</span>
                                    <span class="description">Optimal range: 200-1000 W/m²</span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
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

                        <div class="weather-card">
                            <div class="weather-icon">
                                <i class="fas fa-water"></i>
                            </div>
                            <h3>Soil Moisture</h3>
                            <div class="stat-value">60%</div>
                            <p>Optimal range: 50-75%</p>
                        </div>

                        <div class="weather-card">
                            <div class="weather-icon">
                                <i class="fas fa-sun"></i>
                            </div>
                            <h3>Solar Radiation</h3>
                            <div class="stat-value">500 W/m²</div>
                            <p>Optimal range: 200-1000 W/m²</p>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($weather_data)): ?>
                    <div class="weather-analysis-card">
                        <h3>Weather Analysis</h3>
                        
                        <!-- Updated Alert Box -->
                        <div class="alert-box">
                            <div class="alert-icon">
                                <i class="fas fa-bell fa-shake"></i>
                            </div>
                            <div class="alert-content">
                                <div class="alert-messages">
                                    <div class="alert-message">
                                        <i class="fas fa-temperature-high"></i>
                                        <span>Temperature: Highs of 31°C to 33°C are slightly above the ideal range (15°C – 25°C). 
                                            <strong>Recommendation:</strong> Implement shade nets or increase canopy cover to reduce direct sunlight and lower ambient temperature around the plants.
                                        </span>
                                    </div>
                                    <div class="alert-message">
                                        <i class="fas fa-tint"></i>
                                        <span>Humidity: Assumed to be moderate; however, if it falls below the ideal 70% – 90% range. 
                                            <strong>Recommendation:</strong> Use misting systems or increase irrigation frequency to boost humidity levels.
                                        </span>
                                    </div>
                                    <div class="alert-message">
                                        <i class="fas fa-wind"></i>
                                        <span>Wind Speed: Assumed to be low, which is suitable for cardamom cultivation. 
                                            <strong>Recommendation:</strong> Maintain existing windbreaks (e.g., planting trees or installing barriers) to protect plants from potential future wind exposure.
                                        </span>
                                    </div>
                                    <div class="alert-message">
                                        <i class="fas fa-compress-arrows-alt"></i>
                                        <span>Pressure: Assumed to be within the normal range (900 - 1015 hPa). 
                                            <strong>Recommendation:</strong> No specific action required. Continue to monitor for any significant changes.
                                        </span>
                                    </div>
                                    <div class="alert-message">
                                        <i class="fas fa-water"></i>
                                        <span>Soil Moisture: Assumed to be adequate; however, with high temperatures. 
                                            <strong>Recommendation:</strong> Ensure consistent irrigation to maintain soil moisture within the 30% – 50% range. Utilize mulching to retain soil moisture and reduce evaporation.
                                        </span>
                                    </div>
                                    <div class="alert-message">
                                        <i class="fas fa-sun"></i>
                                        <span>Solar Radiation: High solar radiation is expected due to sunny conditions. 
                                            <strong>Recommendation:</strong> Increase shade coverage to achieve 50% – 60% shade, protecting plants from excessive sunlight and preventing leaf scorching.
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Rest of the analysis table -->
                        <table class="analysis-table">
                            <thead>
                                <tr>
                                    <th>Parameter</th>
                                    <th>Status</th>
                                    <th>Recommendation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $analysis = getWeatherAnalysis($weather_data, $soil_moisture);
                                foreach ($analysis as $parameter => $data):
                                    $statusClass = strtolower($data['status']);
                                ?>
                                <tr>
                                    <td><?php echo ucfirst($parameter); ?></td>
                                    <td class="status-<?php echo $statusClass; ?>"><?php echo $data['status']; ?></td>
                                    <td><?php echo $data['message']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div class="farm-info-card">
                    <h3>5-Day Forecast</h3>
                    <div class="forecast-grid">
                        <?php if (!empty($forecast_data)): ?>
                            <?php
                            $processed_days = [];
                            $day_count = 0;
                            
                            foreach ($forecast_data['list'] as $forecast) {
                                $date = date('Y-m-d', $forecast['dt']);
                                
                                if (!in_array($date, $processed_days) && $date != date('Y-m-d')) {
                                    $processed_days[] = $date;
                                    $day_count++;
                                    
                                    if ($day_count > 5) break;
                                    
                                    $day_name = date('l', $forecast['dt']);
                                    $formatted_date = date('M d', $forecast['dt']);
                                    $temp = round($forecast['main']['temp']);
                                    $humidity = $forecast['main']['humidity'];
                                    $weather_icon = $forecast['weather'][0]['icon'];
                                    $weather_desc = ucfirst($forecast['weather'][0]['description']);
                                    $wind_speed = round($forecast['wind']['speed']);
                                    ?>
                                    <div class='forecast-card'>
                                        <div class="forecast-date">
                                            <h4><?php echo $day_name; ?></h4>
                                            <span class="date"><?php echo $formatted_date; ?></span>
                                        </div>
                                        <div class="forecast-info">
                                            <img src='http://openweathermap.org/img/wn/<?php echo $weather_icon; ?>@2x.png' 
                                                 alt='<?php echo $weather_desc; ?>' 
                                                 style="width: 50px; height: 50px;">
                                            <span class="forecast-temp"><?php echo $temp; ?>°C</span>
                                            <div class="forecast-details">
                                                <div class="forecast-detail-item">
                                                    <i class="fas fa-tint"></i>
                                                    <span><?php echo $humidity; ?>%</span>
                                                </div>
                                                <div class="forecast-detail-item">
                                                    <i class="fas fa-wind"></i>
                                                    <span><?php echo $wind_speed; ?> m/s</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="forecast-desc">
                                            <i class="fas fa-cloud"></i>
                                            <span><?php echo $weather_desc; ?></span>
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                            ?>
                        <?php else: ?>
                            <p>Please select a district to see the forecast.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Display the message if it exists -->
    <?php if (isset($message)): ?>
        <div class="alert alert-warning">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <script>
        // Update current time
        function updateTime() {
            const timeElement = document.getElementById('current-time');
            const now = new Date();
            timeElement.textContent = now.toLocaleTimeString();
        }

        // Update time every second
        setInterval(updateTime, 1000);
        updateTime(); // Initial call
    </script>
</body>
</html> 