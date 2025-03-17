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

// Update the district_coordinates array to include sub-places
$district_coordinates = [
    'Wayanad' => [
        'lat' => 11.6854,
        'lon' => 76.1320,
        'Kalpetta' => ['lat' => 11.6087, 'lon' => 76.0832],
        'Sulthan Bathery' => ['lat' => 11.6633, 'lon' => 76.2593],
        'Mananthavady' => ['lat' => 11.8002, 'lon' => 76.0027],
        'Meenangadi' => ['lat' => 11.5062, 'lon' => 76.2362],
        'Vythiri' => ['lat' => 11.5645, 'lon' => 76.0410],
        'Pulpally' => ['lat' => 11.8031, 'lon' => 76.1410],
        'Panamaram' => ['lat' => 11.7403, 'lon' => 76.0735]
    ],
    'Idukki' => [
        'lat' => 9.9189,
        'lon' => 77.1025,
        'Thodupuzha' => ['lat' => 9.8959, 'lon' => 76.7184],
        'Munnar' => ['lat' => 10.0889, 'lon' => 77.0595],
        'Adimali' => ['lat' => 10.0050, 'lon' => 76.9634],
        'Devikulam' => ['lat' => 10.0518, 'lon' => 77.1027],
        'Kattappana' => ['lat' => 9.7503, 'lon' => 77.1152],
        'Nedumkandam' => ['lat' => 9.8957, 'lon' => 77.1609],
        'Peermade' => ['lat' => 9.5768, 'lon' => 77.0273],
        'Vagamon' => ['lat' => 9.6857, 'lon' => 76.9026]
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
    $selected_subplace = isset($_POST['subplace']) ? $_POST['subplace'] : '';
    
    if (!empty($selected_district)) {
        $location = '';
        if (!empty($selected_subplace)) {
            $location = $selected_subplace . ', ' . $selected_district . ', Kerala, India';
        } else {
            $location = $selected_district . ', Kerala, India';
        }
        
        // Initialize arrays before passing them
        $weather_data = [];
        $forecast_data = [];
        $soil_moisture = 0;
        $solar_radiation = 0;
        
        // Get weather data
        getWeatherData($location, $weather_data, $forecast_data, $soil_moisture, $solar_radiation, $weather_api_key);
        
        // Add debug logging
        error_log('Selected District: ' . $selected_district);
        error_log('Selected Subplace: ' . $selected_subplace);
        error_log('Weather Data: ' . print_r($weather_data, true));
        
        // Set message for non-cardamom districts
        if ($selected_district && $selected_district !== 'Wayanad' && $selected_district !== 'Idukki') {
            $message = "The selected district " . htmlspecialchars($selected_district) . " is not optimal for cardamom cultivation.";
        }
    }
}

// Function to get weather data
function getWeatherData($location, &$weather_data, &$forecast_data, &$soil_moisture, &$solar_radiation, $api_key) {
    global $district_coordinates;
    
    // Initialize weather_data with default values
    $weather_data = [
        'main' => [
            'temp' => null,
            'feels_like' => null,
            'humidity' => null,
            'pressure' => null
        ],
        'wind' => [
            'speed' => null
        ],
        'weather' => [
            [
                'description' => 'No data available',
                'icon' => '01d'
            ]
        ]
    ];
    
    // Function to make API calls using cURL
    function makeApiCall($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            error_log('Curl error: ' . curl_error($ch));
            return null;
        }
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            return json_decode($response, true);
        }
        error_log('API returned status code: ' . $http_code . ' for URL: ' . $url);
        return null;
    }

    // Extract district and subplace from location
    $location_parts = array_map('trim', explode(',', $location));
    $subplace = $location_parts[0];
    $district = $location_parts[1] ?? '';
    
    // Get coordinates
    $lat = null;
    $lon = null;
    
    if (isset($district_coordinates[$district])) {
        if (!empty($subplace) && isset($district_coordinates[$district][$subplace])) {
            // Use subplace coordinates if available
            $lat = $district_coordinates[$district][$subplace]['lat'];
            $lon = $district_coordinates[$district][$subplace]['lon'];
        } else {
            // Use district coordinates as fallback
            $lat = $district_coordinates[$district]['lat'];
            $lon = $district_coordinates[$district]['lon'];
        }
    } else {
        // Use geocoding API for other locations
        $geocode_url = "http://api.openweathermap.org/geo/1.0/direct?q=" . urlencode($location) . "&limit=1&appid=" . $api_key;
        $geocode_data = makeApiCall($geocode_url);
        
        if (!empty($geocode_data)) {
            $lat = $geocode_data[0]['lat'];
            $lon = $geocode_data[0]['lon'];
        } else {
            error_log('Failed to get geocode data for location: ' . $location);
            return;
        }
    }
    
    // Get weather data
    $api_url = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lon}&units=metric&appid={$api_key}";
    $response_data = makeApiCall($api_url);

    if ($response_data !== null) {
        $weather_data = $response_data;
    }

    // Get forecast data
    $forecast_url = "https://api.openweathermap.org/data/2.5/forecast?lat={$lat}&lon={$lon}&units=metric&appid={$api_key}";
    $forecast_response = makeApiCall($forecast_url);

    if ($forecast_response !== null) {
        $forecast_data = $forecast_response;
    } else {
        $forecast_data = ['list' => []];
    }

    // Generate soil moisture and solar radiation data for Wayanad and Idukki
    if ($district === 'Wayanad' || $district === 'Idukki') {
        $soil_moisture = rand(30, 80);
        $solar_radiation = rand(100, 1000);
    }
    
    // Add debug logging
    error_log("API URL: " . $api_url);
    error_log("Weather Data Response: " . print_r($weather_data, true));
    error_log("Forecast Data Response: " . print_r($forecast_data, true));
}

// Function to get weather analysis based on conditions
function getWeatherAnalysis($weather_data, $soil_moisture) {
    $analysis = [];
    
    // Check if weather data is valid
    if (empty($weather_data) || !isset($weather_data['main']) || !isset($weather_data['wind'])) {
        return [
            'temperature' => ['status' => 'Error', 'message' => 'Weather data unavailable'],
            'humidity' => ['status' => 'Error', 'message' => 'Weather data unavailable'],
            'wind' => ['status' => 'Error', 'message' => 'Weather data unavailable'],
            'pressure' => ['status' => 'Error', 'message' => 'Weather data unavailable'],
            'soil' => ['status' => 'Error', 'message' => 'Weather data unavailable'],
            'solar' => ['status' => 'Error', 'message' => 'Weather data unavailable']
        ];
    }
    
    // Temperature analysis
    $temp = isset($weather_data['main']['temp']) ? $weather_data['main']['temp'] : null;
    if ($temp !== null) {
        if ($temp < 15 || $temp > 25) {
            $analysis['temperature'] = ['status' => 'Poor', 'message' => 'Temperature out of ideal range (15°C - 25°C). Consider using shade nets or irrigation to regulate temperature.'];
        } else {
            $analysis['temperature'] = ['status' => 'Good', 'message' => 'Optimal temperature for cardamom growth.'];
        }
    } else {
        $analysis['temperature'] = ['status' => 'Error', 'message' => 'Temperature data unavailable'];
    }

    // Humidity analysis
    $humidity = isset($weather_data['main']['humidity']) ? $weather_data['main']['humidity'] : null;
    if ($humidity !== null) {
        if ($humidity < 70) {
            $analysis['humidity'] = ['status' => 'Poor', 'message' => 'Low humidity (<70%). Consider using misting or increasing irrigation frequency.'];
        } elseif ($humidity > 90) {
            $analysis['humidity'] = ['status' => 'Warning', 'message' => 'High humidity (>90%). Monitor for fungal diseases and ensure proper air circulation.'];
        } else {
            $analysis['humidity'] = ['status' => 'Good', 'message' => 'Optimal humidity (70-90%) for cardamom growth.'];
        }
    } else {
        $analysis['humidity'] = ['status' => 'Error', 'message' => 'Humidity data unavailable'];
    }

    // Wind speed analysis
    $wind_speed = isset($weather_data['wind']['speed']) ? $weather_data['wind']['speed'] : null;
    if ($wind_speed !== null) {
        if ($wind_speed > 2.8) {
            $analysis['wind'] = ['status' => 'Warning', 'message' => 'High wind speeds may damage plants. Consider installing windbreaks or protective barriers.'];
        } else {
            $analysis['wind'] = ['status' => 'Good', 'message' => 'Wind speed is within acceptable range for cardamom cultivation.'];
        }
    } else {
        $analysis['wind'] = ['status' => 'Error', 'message' => 'Wind data unavailable'];
    }

    // Pressure analysis
    $pressure = isset($weather_data['main']['pressure']) ? $weather_data['main']['pressure'] : null;
    if ($pressure !== null) {
        if ($pressure < 900) {
            $analysis['pressure'] = ['status' => 'Warning', 'message' => 'Low pressure may indicate incoming storms. Take precautionary measures.'];
        } elseif ($pressure > 1015) {
            $analysis['pressure'] = ['status' => 'Warning', 'message' => 'High pressure may lead to dry conditions. Monitor irrigation needs.'];
        } else {
            $analysis['pressure'] = ['status' => 'Good', 'message' => 'Atmospheric pressure is within normal range.'];
        }
    } else {
        $analysis['pressure'] = ['status' => 'Error', 'message' => 'Pressure data unavailable'];
    }

    // Soil moisture analysis
    if ($soil_moisture !== null) {
        if ($soil_moisture < 30) {
            $analysis['soil'] = ['status' => 'Poor', 'message' => 'Soil too dry (<30%). Immediate irrigation needed.'];
        } elseif ($soil_moisture > 50) {
            $analysis['soil'] = ['status' => 'Warning', 'message' => 'Soil too wet (>50%). Reduce irrigation and ensure proper drainage.'];
        } else {
            $analysis['soil'] = ['status' => 'Good', 'message' => 'Optimal soil moisture (30-50%) for cardamom roots.'];
        }
    } else {
        $analysis['soil'] = ['status' => 'Error', 'message' => 'Soil moisture data unavailable'];
    }

    // Solar radiation analysis
    global $solar_radiation;
    if ($solar_radiation !== null) {
        if ($solar_radiation < 200) {
            $analysis['solar'] = ['status' => 'Poor', 'message' => 'Insufficient light (<200 W/m²). Consider reducing shade coverage.'];
        } elseif ($solar_radiation > 500) {
            $analysis['solar'] = ['status' => 'Warning', 'message' => 'Excessive light (>500 W/m²). Increase shade coverage to protect plants.'];
        } else {
            $analysis['solar'] = ['status' => 'Good', 'message' => 'Optimal light levels (200-500 W/m²) for cardamom growth.'];
        }
    } else {
        $analysis['solar'] = ['status' => 'Error', 'message' => 'Solar radiation data unavailable'];
    }

    return $analysis;
}

// Modify the getCardamomGrowthIndex function
function getCardamomGrowthIndex($weather_data, $soil_moisture) {
    $conditions = [
        'optimal' => 0,
        'moderate' => 0,
        'poor' => 0
    ];
    
    $analysis = [];
    
    // Weather Parameters Analysis with null checks
    $temp = isset($weather_data['main']['temp']) ? $weather_data['main']['temp'] : null;
    $humidity = isset($weather_data['main']['humidity']) ? $weather_data['main']['humidity'] : null;
    $wind_speed = isset($weather_data['wind']['speed']) ? $weather_data['wind']['speed'] : null;
    
    // Create simplified soil data array with available values
    $soil_data = [
        'soil_moisture' => $soil_moisture,
        // Set default values for other parameters
        'ph_level' => 6.0,  // Default optimal value
        'nitrogen_content' => 2.0,  // Default optimal value
        'phosphorus_content' => 1.5,  // Default optimal value
        'potassium_content' => 1.5   // Default optimal value
    ];
    
    // Temperature Analysis (15-25°C optimal)
    if ($temp >= 15 && $temp <= 25) {
        $conditions['optimal']++;
        $analysis['temperature'] = ['status' => 'Optimal', 'message' => 'Ideal temperature for cardamom growth'];
    } elseif ($temp > 25 && $temp <= 30) {
        $conditions['moderate']++;
        $analysis['temperature'] = ['status' => 'Moderate', 'message' => 'Consider shade management'];
    } else {
        $conditions['poor']++;
        $analysis['temperature'] = ['status' => 'Poor', 'message' => 'Temperature needs attention'];
    }
    
    // Humidity Analysis (70-90% optimal)
    if ($humidity >= 70 && $humidity <= 90) {
        $conditions['optimal']++;
        $analysis['humidity'] = ['status' => 'Optimal', 'message' => 'Perfect humidity range'];
    } elseif ($humidity > 90) {
        $conditions['moderate']++;
        $analysis['humidity'] = ['status' => 'Moderate', 'message' => 'Monitor for fungal diseases'];
    } else {
        $conditions['poor']++;
        $analysis['humidity'] = ['status' => 'Poor', 'message' => 'Increase humidity'];
    }
    
    // Soil moisture Analysis (30-50% optimal)
    if ($soil_data['soil_moisture'] >= 30 && $soil_data['soil_moisture'] <= 50) {
        $conditions['optimal']++;
        $analysis['soil_moisture'] = ['status' => 'Optimal', 'message' => 'Ideal soil moisture for cardamom growth'];
    } elseif ($soil_data['soil_moisture'] > 50) {
        $conditions['moderate']++;
        $analysis['soil_moisture'] = ['status' => 'Moderate', 'message' => 'Monitor drainage'];
    } else {
        $conditions['poor']++;
        $analysis['soil_moisture'] = ['status' => 'Poor', 'message' => 'Increase irrigation'];
    }
    
    // Calculate Growth Index
    $total_conditions = array_sum($conditions);
    $growth_index = ($conditions['optimal'] * 100 + $conditions['moderate'] * 50) / ($total_conditions * 100);
    
    // Determine Overall Growth Condition
    $growth_condition = '';
    if ($growth_index >= 0.8) {
        $growth_condition = 'Excellent';
    } elseif ($growth_index >= 0.6) {
        $growth_condition = 'Good';
    } elseif ($growth_index >= 0.4) {
        $growth_condition = 'Fair';
    } else {
        $growth_condition = 'Poor';
    }
    
    return [
        'condition_name' => "Cardamom Growth Index: $growth_condition",
        'index_value' => round($growth_index * 100),
        'analysis' => $analysis,
        'recommendations' => getGrowthRecommendations($analysis)
    ];
}

function getGrowthRecommendations($analysis) {
    $recommendations = [];
    
    foreach ($analysis as $parameter => $data) {
        if ($data['status'] !== 'Optimal') {
            switch ($parameter) {
                case 'temperature':
                    $recommendations[] = "Install shade nets and maintain proper irrigation to regulate temperature";
                    break;
                case 'humidity':
                    $recommendations[] = "Use misting systems during dry periods and ensure proper spacing for air circulation";
                    break;
                case 'soil_moisture':
                    $recommendations[] = "Ensure consistent irrigation to maintain soil moisture within the 30% – 50% range. Utilize mulching to retain soil moisture and reduce evaporation.";
                    break;
                // Add more cases for other parameters
            }
        }
    }
    
    return $recommendations;
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
        /* Update the root variables to match the green theme */
        :root {
            --primary-color: #2D5A27;  /* Cardamom green */
            --primary-dark: #1A3A19;   /* Darker cardamom */
            --accent-color: #8B9D83;   /* Muted cardamom */
            --error-color: #dc3545;
            --success-color: #28a745;
            --button-color: #4A7A43;   /* Cardamom button */
            --button-hover: #3D6337;   /* Darker cardamom button */
            --sidebar-width: 250px;
        }

        /* Update sidebar styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color), var(--primary-dark));
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
        }

        .nav-item.active {
            background: rgba(255,255,255,0.2);
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

        /* Update the sidebar header styles */
        .sidebar-header h2 {
            color: white;
            text-align: center;
            margin-bottom: 0;
            font-size: 1.8em;
            text-shadow: none;
        }

        .sidebar-header i {
            color: white;
            margin-right: 8px;
        }

        /* Update main content margin */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            width: calc(100% - var(--sidebar-width));
            box-sizing: border-box;
        }

        /* Base styles */
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background: #f7fafc;
        }

        /* Weather specific styles */
        .weather-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            width: 100%;
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

        /* Add these new styles for the recommendations box */
        .recommendations-box {
            background: linear-gradient(135deg, #ffffff, #f8fafc);
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            overflow-x: auto;
        }

        .recommendations-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .recommendations-header h3 {
            color: var(--primary-color);
            margin: 0;
            font-size: 1.5rem;
        }

        .recommendations-header i {
            color: var(--primary-color);
            font-size: 1.8rem;
        }

        .recommendations-grid {
            display: flex;
            flex-wrap: nowrap;
            gap: 20px;
            padding: 10px 5px;
            min-width: min-content;
        }

        .recommendation-card {
            flex: 0 0 300px;
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .recommendation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .recommendation-icon {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
        }

        .recommendation-icon i {
            font-size: 1.5rem;
            color: var(--primary-color);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(44, 82, 130, 0.1);
            border-radius: 10px;
        }

        .recommendation-icon h4 {
            margin: 0;
            color: #2d3748;
            font-size: 1.1rem;
        }

        .recommendation-content {
            color: #4a5568;
            line-height: 1.6;
            font-size: 0.95rem;
        }

        .recommendation-footer {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--primary-color);
            font-size: 0.9rem;
        }

        .recommendation-footer i {
            font-size: 0.8rem;
        }

        /* Add smooth scrolling for the recommendations container */
        .recommendations-box {
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
        }

        /* Add custom scrollbar styling */
        .recommendations-box::-webkit-scrollbar {
            height: 8px;
        }

        .recommendations-box::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .recommendations-box::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        .recommendations-box::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }

        /* Add these styles to your existing CSS */
        .growth-analysis-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin: 30px 0;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        .growth-analysis-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .growth-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .growth-title i {
            font-size: 2rem;
            color: var(--primary-color);
        }

        .growth-title h3 {
            margin: 0;
            color: #2d3748;
            font-size: 1.8rem;
        }

        .growth-score {
            position: relative;
            width: 150px;
            height: 150px;
        }

        .score-circle {
            position: relative;
            width: 100%;
            height: 100%;
        }

        .circular-chart {
            width: 100%;
            height: 100%;
            transform: rotate(-90deg);
        }

        .progress {
            transition: stroke-dasharray 1s ease-in-out;
        }

        .score-value {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }

        .score-value .value {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            display: block;
        }

        .score-value .label {
            font-size: 0.9rem;
            color: #718096;
        }

        .analysis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .analysis-card {
            background: #f8fafc;
            border-radius: 15px;
            padding: 20px;
            display: flex;
            gap: 20px;
            transition: transform 0.3s ease;
        }

        .analysis-card:hover {
            transform: translateY(-5px);
        }

        .analysis-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-color);
        }

        .analysis-icon i {
            color: white;
            font-size: 1.5rem;
        }

        .analysis-content {
            flex: 1;
        }

        .analysis-content h4 {
            margin: 0 0 10px 0;
            color: #2d3748;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .status-badge.good {
            background: #C6F6D5;
            color: #2F855A;
        }

        .status-badge.warning {
            background: #FEEBC8;
            color: #C05621;
        }

        .status-badge.poor {
            background: #FED7D7;
            color: #C53030;
        }

        .analysis-content p {
            margin: 0;
            color: #4A5568;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .recommendations-section {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 2px solid #E2E8F0;
        }

        .recommendations-section h4 {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2D3748;
            margin: 0 0 20px 0;
        }

        .recommendations-section h4 i {
            color: var(--primary-color);
        }

        .recommendations-list {
            display: grid;
            gap: 15px;
        }

        .recommendation-item {
            display: flex;
            gap: 15px;
            align-items: flex-start;
            padding: 15px;
            background: #F7FAFC;
            border-radius: 12px;
            transition: transform 0.3s ease;
        }

        .recommendation-item:hover {
            transform: translateX(10px);
        }

        .recommendation-number {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .recommendation-item p {
            margin: 0;
            color: #4A5568;
            font-size: 0.95rem;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="layout-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-seedling"></i> GrowGuide</h2>
            </div>
            <nav class="nav-menu">
                <div class="nav-menu-items">
                    <a href="farmer.php" class="nav-item">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    <a href="soil_test.php" class="nav-item">
                        <i class="fas fa-flask"></i> Soil Test
                    </a>
                    <a href="fertilizerrrr.php" class="nav-item">
                        <i class="fas fa-leaf"></i> Fertilizer Guide
                    </a>
                    <a href="farm_analysis.php" class="nav-item">
                        <i class="fas fa-chart-bar"></i> Farm Analysis
                    </a>
                    <a href="schedule.php" class="nav-item">
                        <i class="fas fa-calendar"></i> Schedule
                    </a>
                    <a href="weather.php" class="nav-item active">
                        <i class="fas fa-cloud-sun"></i> Weather
                    </a>
                    <a href="settings.php" class="nav-item">
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

        <!-- Main Content -->
        <div class="main-content">
            <div class="farm-info-card">
                <div class="farm-info-header">
                    <div class="header-controls">
                        <h2><i class="fas fa-cloud-sun"></i> Weather Dashboard</h2>
                    </div>
                    
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
                        <p style="font-size: 1.5rem; font-weight: bold; color: #2c5282;">
                            Welcome back, 
                            <span style="color: #4299e1;"><?php echo $username; ?></span>! 🌱
                        </p>
                    </div>
                </div>
                
                <!-- Replace the existing location form with this updated form -->
                <form method="POST" class="location-form">
                    <select name="district" id="district" required onchange="updateSubplaces(this.value)">
                        <option value="">Select District</option>
                        <?php foreach ($kerala_districts as $district => $places): ?>
                            <option value="<?php echo $district; ?>" <?php echo ($selected_district === $district) ? 'selected' : ''; ?>>
                                <?php echo $district; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="subplace" id="subplace" style="display: none;">
                        <option value="">Select Location</option>
                    </select>
                    
                    <button type="submit"><i class="fas fa-search"></i> Get Weather</button>
                </form>

                <?php if (!empty($selected_district) && empty($weather_data)): ?>
                    <div class="alert alert-danger">
                        Unable to fetch weather data for <?php echo htmlspecialchars($selected_district); ?>. Please try again later.
                    </div>
                <?php endif; ?>

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

                        <?php if ($selected_district === 'Wayanad' || $selected_district === 'Idukki'): ?>
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
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <?php if ($selected_district === 'Wayanad' || $selected_district === 'Idukki'): ?>
                    <div class="recommendations-box">
                        <div class="recommendations-header">
                            <i class="fas fa-lightbulb"></i>
                            <h3>Weather Recommendations</h3>
                        </div>
                        <div class="recommendations-grid">
                            <div class="recommendation-card">
                                <div class="recommendation-icon">
                                    <i class="fas fa-temperature-high"></i>
                                    <h4>Temperature Management</h4>
                                </div>
                                <div class="recommendation-content">
                                    Current temperature is slightly above the ideal range (15°C – 25°C).
                                    Implement shade nets or increase canopy cover to reduce direct sunlight
                                    and lower ambient temperature around the plants.
                                </div>
                                <div class="recommendation-footer">
                                    <i class="fas fa-clock"></i>
                                    <span>Priority: High</span>
                                </div>
                            </div>

                            <div class="recommendation-card">
                                <div class="recommendation-icon">
                                    <i class="fas fa-tint"></i>
                                    <h4>Humidity Control</h4>
                                </div>
                                <div class="recommendation-content">
                                    Maintain humidity within 70% – 90% range. Use misting systems during dry
                                    periods and ensure proper spacing between plants for adequate air circulation.
                                </div>
                                <div class="recommendation-footer">
                                    <i class="fas fa-clock"></i>
                                    <span>Priority: Medium</span>
                                </div>
                            </div>

                            <div class="recommendation-card">
                                <div class="recommendation-icon">
                                    <i class="fas fa-wind"></i>
                                    <h4>Wind Protection</h4>
                                </div>
                                <div class="recommendation-content">
                                    Current wind conditions are suitable. Maintain existing windbreaks and
                                    monitor for any changes in wind patterns that might affect the plants.
                                </div>
                                <div class="recommendation-footer">
                                    <i class="fas fa-clock"></i>
                                    <span>Priority: Low</span>
                                </div>
                            </div>

                            <div class="recommendation-card">
                                <div class="recommendation-icon">
                                    <i class="fas fa-water"></i>
                                    <h4>Soil Moisture</h4>
                                </div>
                                <div class="recommendation-content">
                                    Maintain soil moisture between 30% – 50%. Use mulching to retain moisture
                                    and implement proper irrigation scheduling based on weather conditions.
                                </div>
                                <div class="recommendation-footer">
                                    <i class="fas fa-clock"></i>
                                    <span>Priority: High</span>
                                </div>
                            </div>

                            <div class="recommendation-card">
                                <div class="recommendation-icon">
                                    <i class="fas fa-sun"></i>
                                    <h4>Solar Radiation</h4>
                                </div>
                                <div class="recommendation-content">
                                    Adjust shade coverage to achieve 50% – 60% shade during peak sunlight hours.
                                    Monitor leaf condition for signs of sun damage or insufficient light.
                                </div>
                                <div class="recommendation-footer">
                                    <i class="fas fa-clock"></i>
                                    <span>Priority: Medium</span>
                                </div>
                            </div>

                            <div class="recommendation-card">
                                <div class="recommendation-icon">
                                    <i class="fas fa-seedling"></i>
                                    <h4>Overall Plant Care</h4>
                                </div>
                                <div class="recommendation-content">
                                    Regular monitoring of plant health and weather conditions is essential.
                                    Adjust care practices based on daily weather forecasts and plant response.
                                </div>
                                <div class="recommendation-footer">
                                    <i class="fas fa-clock"></i>
                                    <span>Priority: Ongoing</span>
                                </div>
                            </div>
                        </div>
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

                    <div class="growth-analysis-card">
                        <?php
                        $growth_analysis = getCardamomGrowthIndex($weather_data, $soil_moisture);
                        ?>
                        <div class="growth-analysis-header">
                            <div class="growth-title">
                                <i class="fas fa-chart-line"></i>
                                <h3>Cardamom Growth Analysis</h3>
                            </div>
                            <div class="growth-score">
                                <div class="score-circle" data-value="<?php echo $growth_analysis['index_value']; ?>">
                                    <svg viewBox="0 0 36 36" class="circular-chart">
                                        <path d="M18 2.0845
                                            a 15.9155 15.9155 0 0 1 0 31.831
                                            a 15.9155 15.9155 0 0 1 0 -31.831"
                                            fill="none"
                                            stroke="#eee"
                                            stroke-width="2.5"
                                        />
                                        <path d="M18 2.0845
                                            a 15.9155 15.9155 0 0 1 0 31.831
                                            a 15.9155 15.9155 0 0 1 0 -31.831"
                                            fill="none"
                                            stroke="url(#gradient)"
                                            stroke-width="2.5"
                                            stroke-dasharray="<?php echo $growth_analysis['index_value']; ?>, 100"
                                            class="progress"
                                        />
                                        <defs>
                                            <linearGradient id="gradient">
                                                <stop offset="0%" stop-color="#2c5282" />
                                                <stop offset="100%" stop-color="#4299e1" />
                                            </linearGradient>
                                        </defs>
                                    </svg>
                                    <div class="score-value">
                                        <span class="value"><?php echo $growth_analysis['index_value']; ?>%</span>
                                        <span class="label"><?php echo $growth_analysis['condition_name']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="analysis-grid">
                            <?php foreach ($growth_analysis['analysis'] as $parameter => $data): ?>
                                <div class="analysis-card <?php echo strtolower($data['status']); ?>">
                                    <div class="analysis-icon">
                                        <?php
                                        $icon = '';
                                        switch ($parameter) {
                                            case 'temperature': $icon = 'fa-temperature-high'; break;
                                            case 'humidity': $icon = 'fa-tint'; break;
                                            case 'soil_moisture': $icon = 'fa-water'; break;
                                            case 'wind': $icon = 'fa-wind'; break;
                                            case 'pressure': $icon = 'fa-compress-arrows-alt'; break;
                                            case 'solar': $icon = 'fa-sun'; break;
                                            default: $icon = 'fa-chart-bar';
                                        }
                                        ?>
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="analysis-content">
                                        <h4><?php echo ucfirst($parameter); ?></h4>
                                        <div class="status-badge <?php echo strtolower($data['status']); ?>">
                                            <?php echo $data['status']; ?>
                                        </div>
                                        <p><?php echo $data['message']; ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="recommendations-section">
                            <h4><i class="fas fa-lightbulb"></i> Recommendations</h4>
                            <div class="recommendations-list">
                                <?php foreach ($growth_analysis['recommendations'] as $index => $recommendation): ?>
                                    <div class="recommendation-item">
                                        <span class="recommendation-number"><?php echo $index + 1; ?></span>
                                        <p><?php echo $recommendation; ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
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

        // Add this JavaScript to animate the progress circle on page load
        document.addEventListener('DOMContentLoaded', function() {
            const circles = document.querySelectorAll('.score-circle');
            circles.forEach(circle => {
                const value = circle.getAttribute('data-value');
                const progress = circle.querySelector('.progress');
                progress.style.strokeDasharray = `${value}, 100`;
            });
        });

        function updateSubplaces(district) {
            const subplaceSelect = document.getElementById('subplace');
            const subplaces = <?php echo json_encode($kerala_districts); ?>;
            
            if (district === 'Idukki' || district === 'Wayanad') {
                subplaceSelect.innerHTML = '<option value="">Select Location</option>';
                subplaces[district].forEach(place => {
                    subplaceSelect.innerHTML += `<option value="${place}">${place}</option>`;
                });
                subplaceSelect.style.display = 'block';
            } else {
                subplaceSelect.style.display = 'none';
            }
        }

        // Initialize subplaces if district is already selected
        document.addEventListener('DOMContentLoaded', function() {
            const district = document.getElementById('district').value;
            if (district) {
                updateSubplaces(district);
            }
        });
    </script>
</body>
</html> 