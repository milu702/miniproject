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

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $selected_district = isset($_POST['district']) ? $_POST['district'] : '';
    
    // Get weather data if district is selected
    if ($selected_district) {
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
                                    $temp = round($forecast['main']['temp']);
                                    $humidity = $forecast['main']['humidity'];
                                    $weather_icon = $forecast['weather'][0]['icon'];
                                    $weather_desc = ucfirst($forecast['weather'][0]['description']);
                                    ?>
                                    <div class='forecast-card'>
                                        <h4><?php echo $day_name; ?></h4>
                                        <img src='http://openweathermap.org/img/wn/<?php echo $weather_icon; ?>@2x.png' alt='<?php echo $weather_desc; ?>'>
                                        <p><?php echo $temp; ?>°C</p>
                                        <p><?php echo $humidity; ?>% Humidity</p>
                                        <p><?php echo $weather_desc; ?></p>
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
</body>
</html> 