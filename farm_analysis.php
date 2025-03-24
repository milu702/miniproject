<?php
session_start();

// Ensure user is logged in and has the 'farmer' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

// Get farmer's data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT 
        f.farmer_id,
        COALESCE(st.ph_level, 0) as avg_ph,
        COALESCE(st.nitrogen_content, 0) as avg_nitrogen,
        COALESCE(st.phosphorus_content, 0) as avg_phosphorus,
        COALESCE(st.potassium_content, 0) as avg_potassium,
        st.test_date,
        COALESCE(u.farm_location, '') as farm_location,
        u.username as farmer_name
    FROM users u
    LEFT JOIN farmers f ON u.id = f.user_id
    LEFT JOIN farmer_profiles p ON f.farmer_id = p.farmer_id
    LEFT JOIN soil_tests st ON u.id = st.farmer_id
    WHERE u.id = ?
    ORDER BY st.test_date DESC LIMIT 1
");

if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$farmerData = $result->fetch_assoc();

// Add error handling if no farmer data is found
if (!$farmerData) {
    // Redirect to an error page or show a message
    $_SESSION['error'] = "No farmer profile found. Please complete your profile first.";
    header("Location: farmer.php");
    exit();
}

// Update the weather data fetching section
$weather_data = null;
if (isset($farmerData['farm_location']) && !empty($farmerData['farm_location'])) {
    $weather_api_key = "cc02c9dee7518466102e748f211bca05";
    $weather_url = "https://api.openweathermap.org/data/2.5/weather?q=" . 
        urlencode($farmerData['farm_location']) . ",IN&units=metric&appid=" . $weather_api_key;
    
    $weather_response = @file_get_contents($weather_url);
    if ($weather_response !== false) {
        $weather_data = json_decode($weather_response, true);
    }
}

// Analysis function
function analyzeConditions($weather_data, $soil_data) {
    $recommendations = [];
    $conditions = [];
    $unsuitable_location = false;
    $current_location = '';

    // Weather-based analysis
    if ($weather_data) {
        // Temperature analysis
        $temp = $weather_data['main']['temp'] ?? null;
        if ($temp !== null) {
            $conditions['temperature'] = [
                'value' => $temp,
                'status' => ($temp >= 10 && $temp <= 35) ? 'optimal' : 'suboptimal'
            ];
            
            if ($temp < 10) {
                $recommendations[] = "Temperature is too low. Consider using frost protection measures and maintain proper shade.";
            } elseif ($temp > 35) {
                $recommendations[] = "Temperature is too high. Increase irrigation frequency and maintain adequate shade cover.";
            }
        }

        // Humidity analysis
        $humidity = $weather_data['main']['humidity'] ?? null;
        if ($humidity !== null) {
            $conditions['humidity'] = [
                'value' => $humidity,
                'status' => ($humidity >= 60 && $humidity <= 90) ? 'optimal' : 'suboptimal'
            ];
            
            if ($humidity < 60) {
                $recommendations[] = "Low humidity detected. Consider using mulching and increasing irrigation frequency.";
            } elseif ($humidity > 90) {
                $recommendations[] = "High humidity detected. Monitor for fungal diseases and ensure proper air circulation.";
            }
        }

        // Weather condition analysis
        $weather_condition = $weather_data['weather'][0]['main'] ?? null;
        if ($weather_condition) {
            switch (strtolower($weather_condition)) {
                case 'rain':
                    $recommendations[] = "Rainy conditions: Ensure proper drainage and monitor for root rot.";
                    break;
                case 'clear':
                    $recommendations[] = "Clear weather: Ideal for pollination. Consider foliar spray applications.";
                    break;
                case 'clouds':
                    $recommendations[] = "Cloudy conditions: Good for plant growth. Monitor soil moisture levels.";
                    break;
                case 'thunderstorm':
                    $recommendations[] = "Thunderstorm warning: Protect plants from strong winds and check drainage systems.";
                    break;
            }
        }

        // Wind speed analysis
        $wind_speed = $weather_data['wind']['speed'] ?? null;
        if ($wind_speed !== null) {
            if ($wind_speed > 10) {
                $recommendations[] = "High wind speeds detected. Check support structures and windbreakers.";
            }
        }

        // Add soil analysis
        if (isset($soil_data['avg_ph'])) {
            $ph = $soil_data['avg_ph'];
            $conditions['soil_ph'] = [
                'value' => $ph,
                'status' => ($ph >= 5.5 && $ph <= 6.5) ? 'optimal' : 'suboptimal'
            ];
            
            if ($ph < 5.5) {
                $recommendations[] = "Soil pH is too acidic. Apply agricultural lime to raise pH. Target pH range is 5.5-6.5.";
            } elseif ($ph > 6.5) {
                $recommendations[] = "Soil pH is too alkaline. Consider adding organic matter or sulfur to lower pH.";
            }
        }

        // Nitrogen content analysis
        if (isset($soil_data['avg_nitrogen'])) {
            $nitrogen = $soil_data['avg_nitrogen'];
            $conditions['nitrogen_content'] = [
                'value' => $nitrogen,
                'status' => ($nitrogen >= 150 && $nitrogen <= 250) ? 'optimal' : 'suboptimal'
            ];
            
            if ($nitrogen < 150) {
                $recommendations[] = "Low nitrogen levels detected. Apply organic nitrogen-rich fertilizers like vermicompost or neem cake.";
            } elseif ($nitrogen > 250) {
                $recommendations[] = "High nitrogen levels. Reduce nitrogen fertilization and monitor leaf growth.";
            }
        }

        // Phosphorus content analysis
        if (isset($soil_data['avg_phosphorus'])) {
            $phosphorus = $soil_data['avg_phosphorus'];
            $conditions['phosphorus_content'] = [
                'value' => $phosphorus,
                'status' => ($phosphorus >= 15 && $phosphorus <= 25) ? 'optimal' : 'suboptimal'
            ];
            
            if ($phosphorus < 15) {
                $recommendations[] = "Low phosphorus levels. Apply rock phosphate or bone meal to improve soil phosphorus content.";
            } elseif ($phosphorus > 25) {
                $recommendations[] = "High phosphorus levels. Avoid phosphorus-rich fertilizers and consider growing phosphorus-hungry cover crops.";
            }
        }

        // Potassium content analysis
        if (isset($soil_data['avg_potassium'])) {
            $potassium = $soil_data['avg_potassium'];
            $conditions['potassium_content'] = [
                'value' => $potassium,
                'status' => ($potassium >= 120 && $potassium <= 200) ? 'optimal' : 'suboptimal'
            ];
            
            if ($potassium < 120) {
                $recommendations[] = "Low potassium levels. Apply potash or wood ash to improve soil potassium content.";
            } elseif ($potassium > 200) {
                $recommendations[] = "High potassium levels. Reduce potassium fertilization and monitor plant growth.";
            }
        }

        // Combined weather and soil recommendations
        $temp = $weather_data['main']['temp'] ?? null;
        $humidity = $weather_data['main']['humidity'] ?? null;
        $soil_moisture = $soil_data['soil_moisture'] ?? null;

        if ($temp > 30 && $humidity < 60 && $soil_moisture < 60) {
            $recommendations[] = "High temperature and low humidity detected. Implement these measures:
                - Increase irrigation frequency
                - Apply mulch to retain soil moisture
                - Consider installing shade nets
                - Use drip irrigation system for water conservation";
        }

        if ($temp < 15 && $humidity > 80) {
            $recommendations[] = "Cold and humid conditions detected. Take these actions:
                - Improve air circulation between plants
                - Monitor for fungal diseases
                - Reduce irrigation frequency
                - Apply copper-based fungicides if necessary";
        }

        // Seasonal recommendations based on weather patterns
        $month = date('n');
        if ($month >= 6 && $month <= 8) { // Monsoon season
            $recommendations[] = "Monsoon season care:
                - Ensure proper drainage
                - Monitor for root rot
                - Apply anti-fungal treatments preventively
                - Maintain proper spacing between plants";
        } elseif ($month >= 12 || $month <= 2) { // Winter season
            $recommendations[] = "Winter season care:
                - Protect plants from frost
                - Reduce watering frequency
                - Apply organic mulch
                - Monitor soil temperature";
        }
    }

    // Location analysis
    if (isset($soil_data['farm_location'])) {
        $suitable_locations = ['idukki', 'wayanad'];
        $current_location = strtolower($soil_data['farm_location']);
        if (!in_array($current_location, $suitable_locations)) {
            $unsuitable_location = true;
            $recommendations[] = "Consider relocating your cardamom plantation to Idukki or Wayanad regions for optimal growth conditions.";
        } else {
            // Location-specific recommendations
            if ($current_location === 'idukki') {
                $recommendations = [
                    "Maintain shade levels at 60-70% using native tree species like Silver Oak or Rosewood",
                    "Plant cardamom at 1000-1500m elevation for best results",
                    "Implement terracing on slopes to prevent soil erosion during monsoon",
                    "Use organic mulching to retain soil moisture during dry spells",
                    "Consider intercropping with pepper or coffee for additional income",
                    "Monitor for capsule rot during heavy rainfall periods (June-August)",
                    "Install proper drainage systems to prevent waterlogging"
                ];
            } elseif ($current_location === 'wayanad') {
                $recommendations = [
                    "Maintain 50-60% shade coverage using mixed shade trees",
                    "Focus on moisture conservation during dry months (December-March)",
                    "Plant cardamom at 800-1200m elevation for optimal growth",
                    "Use bio-fencing with vetiver grass to prevent wild animal entry",
                    "Practice regular pruning of shade trees before monsoon",
                    "Apply organic matter before the onset of southwest monsoon",
                    "Monitor for thrips during dry season (February-May)"
                ];
            }
        }
    }

    return [
        'conditions' => $conditions,
        'recommendations' => $recommendations,
        'unsuitable_location' => $unsuitable_location,
        'current_location' => $current_location
    ];
}

$analysis = analyzeConditions($weather_data, $farmerData);

// Add pesticide and fertilizer recommendation function
function getPesticideAndFertilizerRecommendations($weather_data, $soil_data) {
    $recommendations = [];
    
    // Weather-based recommendations
    if ($weather_data) {
        $temp = $weather_data['main']['temp'] ?? null;
        $humidity = $weather_data['main']['humidity'] ?? null;
        $weather_condition = $weather_data['weather'][0]['main'] ?? null;

        // High humidity conditions
        if ($humidity > 80) {
            $recommendations[] = [
                'type' => 'pesticide',
                'title' => 'Fungicide Application',
                'description' => 'High humidity detected. Apply copper oxychloride (2.5g/L) or bordeaux mixture to prevent fungal diseases.',
                'timing' => 'Apply during early morning or late evening'
            ];
        }

        // Rainy conditions
        if (strtolower($weather_condition) === 'rain') {
            $recommendations[] = [
                'type' => 'pesticide',
                'title' => 'Root Disease Prevention',
                'description' => 'Apply Trichoderma viride (2.5 kg/ha) mixed with organic manure to prevent root rot during wet conditions.',
                'timing' => 'Apply after rain subsides'
            ];
        }
    }

    // Soil-based recommendations
    if (isset($soil_data['avg_ph'])) {
        $ph = $soil_data['avg_ph'];
        if ($ph < 5.5) {
            $recommendations[] = [
                'type' => 'fertilizer',
                'title' => 'pH Correction',
                'description' => 'Apply dolomitic limestone (2-3 tons/ha) to raise soil pH.',
                'timing' => 'Apply before planting or during land preparation'
            ];
        }
    }

    // NPK recommendations based on soil test
    if (isset($soil_data['avg_nitrogen']) && $soil_data['avg_nitrogen'] < 150) {
        $recommendations[] = [
            'type' => 'fertilizer',
            'title' => 'Nitrogen Supplement',
            'description' => 'Apply neem cake (1 kg/plant) and vermicompost (2 kg/plant) to improve nitrogen content.',
            'timing' => 'Apply during pre-monsoon period'
        ];
    }

    if (isset($soil_data['avg_phosphorus']) && $soil_data['avg_phosphorus'] < 15) {
        $recommendations[] = [
            'type' => 'fertilizer',
            'title' => 'Phosphorus Supplement',
            'description' => 'Apply rock phosphate (100g/plant) mixed with organic manure.',
            'timing' => 'Apply during planting or as top dressing'
        ];
    }

    if (isset($soil_data['avg_potassium']) && $soil_data['avg_potassium'] < 120) {
        $recommendations[] = [
            'type' => 'fertilizer',
            'title' => 'Potassium Supplement',
            'description' => 'Apply wood ash (500g/plant) or potassium sulfate (100g/plant).',
            'timing' => 'Apply during flowering stage'
        ];
    }

    return $recommendations;
}

// Get recommendations
$pesticide_fertilizer_recommendations = getPesticideAndFertilizerRecommendations($weather_data, $farmerData);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farm Analysis - GrowGuide</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Update root variables to match the green theme */
        :root {
            --primary-color: #2D5A27;  /* Cardamom green */
            --primary-dark: #1A3A19;   /* Darker cardamom */
            --accent-color: #8B9D83;   /* Muted cardamom */
            --text-color: #333333;     /* Dark gray for text */
            --bg-color: #FFFFFF;       /* White background */
            --error-color: #dc3545;
            --success-color: #28a745;
            --button-color: #4A7A43;
            --button-hover: #3D6337;
            --sidebar-width: 250px;
        }

        /* Update these specific styles */
        .layout-container {
            display: flex;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            background: #f5f7fa;  /* Light gray background for better contrast */
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 30px 40px;  /* Increased padding */
            box-sizing: border-box;
            background: #f5f7fa;
            min-height: 100vh;
            overflow-x: hidden;  /* Prevent horizontal scroll */
        }

        /* Update analysis container layout */
        .analysis-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin: 25px 0;
        }

        /* Update analysis cards */
        .analysis-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .analysis-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        /* Update sidebar styles */
        .sidebar {
            width: var(--sidebar-width);
            background: #2D5A27; /* Solid dark green background */
            color: white;
            padding: 20px;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 1000;
        }

        /* Logo/Header styles */
        .sidebar-header {
            display: flex;
            align-items: center;
            padding: 10px 0 20px;
            margin-bottom: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Farmer profile section */
        .farmer-profile {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .farmer-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .farmer-avatar i {
            font-size: 2rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .farmer-profile h3 {
            margin: 0 0 5px;
            font-size: 1.2rem;
        }

        .farmer-profile p {
            margin: 0;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }

        /* Navigation menu */
        .nav-menu-items {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: background-color 0.2s;
        }

        .nav-item i {
            width: 24px;
            margin-right: 12px;
            font-size: 1.1rem;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-item.active {
            background: rgba(255, 255, 255, 0.15);
            font-weight: 500;
        }

        .nav-item span {
            font-size: 0.95rem;
        }

        /* Bottom navigation items */
        .nav-menu-bottom {
            margin-top: auto;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Update welcome header */
        .welcome-header {
            background: white;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .welcome-header h1 {
            margin: 0;
            font-size: 1.8em;
            color: var(--primary-color);
        }

        /* Update analysis summary */
        .analysis-summary {
            background: white;
            padding: 25px 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin: 25px 0;
        }

        /* Update section title */
        .section-title {
            margin: 35px 0 25px;
        }

        .section-title h2 {
            margin: 0;
            font-size: 1.5em;
            color: var(--primary-color);
        }

        /* Update recommendations card */
        .recommendations-card {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            padding: 30px;
            margin-top: 35px;
        }

        .recommendations-list li {
            margin: 15px 0;
            padding-left: 25px;
            line-height: 1.6;
        }

        /* Update parameter values */
        .parameter-value {
            font-size: 2.2em;
            font-weight: 600;
            color: var(--primary-color);
            margin: 15px 0;
        }

        /* Update status indicators */
        .status-indicator {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .analysis-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .analysis-container {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                padding: 20px;
                margin-left: 0;
            }
        }

        /* District running message styles */
        .district-banner {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 12px 20px;
            margin: 20px 0;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
        }

        .district-text {
            white-space: nowrap;
            animation: scrollText 20s linear infinite;
            display: inline-block;
        }

        @keyframes scrollText {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }

        /* Update logout button */
        .logout-btn {
            background: rgba(220, 53, 69, 0.1);
            color: #ff6b6b !important;
            border-radius: 8px;
            margin-top: 20px;
            font-weight: 600;
        }

        .logout-btn:hover {
            background: #ff6b6b !important;
            color: white !important;
            transform: translateX(5px);
        }

        /* Warning banner styles */
        .warning-banner {
            background: linear-gradient(135deg, #dc3545, #c53030);
            color: white;
            padding: 12px 20px;
            margin: 20px 0;
            border-radius: 8px;
            overflow: hidden;
            position: relative;
        }

        .warning-text {
            white-space: nowrap;
            animation: scrollWarning 25s linear infinite;
            display: inline-block;
            padding-right: 50px; /* Add space between repetitions */
        }

        @keyframes scrollWarning {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
        }

        .warning-icon {
            margin-right: 10px;
            color: #ffd700;
        }

        .setup-notice {
            text-align: center;
            padding: 15px;
            background: rgba(45, 90, 39, 0.1);
            border-radius: 8px;
        }

        .setup-notice p {
            margin: 0 0 10px 0;
            color: var(--primary-dark);
        }

        .setup-link {
            display: inline-block;
            padding: 8px 16px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .setup-link:hover {
            background: var(--primary-dark);
            transform: translateX(5px);
        }

        /* Add to your existing styles */
        .recommendations-section {
            margin-top: 2rem;
        }

        .recommendations-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .recommendation-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            gap: 1rem;
            transition: transform 0.2s ease;
        }

        .recommendation-card:hover {
            transform: translateY(-3px);
        }

        .recommendation-card.pesticide {
            border-left: 4px solid #e53e3e;
        }

        .recommendation-card.fertilizer {
            border-left: 4px solid #38a169;
        }

        .recommendation-icon {
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .recommendation-content h3 {
            margin: 0 0 0.5rem 0;
            color: var(--text-color);
        }

        .recommendation-content p {
            margin: 0 0 1rem 0;
            color: #666;
        }

        .timing {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #666;
            font-size: 0.9rem;
        }

        .no-recommendations {
            grid-column: 1 / -1;
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <!-- Add loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <i class="fas fa-sync loading-spinner"></i>
            <p>Analyzing farm conditions...</p>
        </div>
    </div>

    <div class="layout-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-seedling"></i> GrowGuide</h2>
            </div>
            
            <div class="farmer-profile">
                <div class="farmer-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h3><?php echo htmlspecialchars($farmerData['farmer_name'] ?? 'Farmer'); ?></h3>
                <p>Cardamom Farmer</p>
            </div>

            <nav class="nav-menu">
                <div class="nav-menu-items">
                    <a href="farmer.php" class="nav-item">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="soil_test.php" class="nav-item">
                        <i class="fas fa-flask"></i>
                        <span>Soil Test</span>
                    </a>
                    <a href="fertilizerrrr.php" class="nav-item">
                        <i class="fas fa-leaf"></i>
                        <span>Fertilizer Guide</span>
                    </a>
                    <a href="farm_analysis.php" class="nav-item active">
                        <i class="fas fa-chart-bar"></i>
                        <span>Farm Analysis</span>
                    </a>
                    <a href="schedule.php" class="nav-item">
                        <i class="fas fa-calendar"></i>
                        <span>Schedule</span>
                    </a>
                    <a href="weather.php" class="nav-item">
                        <i class="fas fa-cloud-sun"></i>
                        <span>Weather</span>
                    </a>
                </div>
            </nav>
        </div>

        <div class="main-content">
            <div class="welcome-header fade-in">
                <i class="fas fa-user-farmer"></i>
                <h1>Welcome, <?php echo htmlspecialchars($farmerData['farmer_name'] ?? 'Farmer'); ?></h1>
            </div>

            <!-- Add this district banner after the welcome header in the main content -->
            <div class="district-banner fade-in">
                <div class="district-text">
                    <i class="fas fa-map-marker-alt"></i>
                    <?php if ($farmerData['farm_location'] !== 'Location not set'): ?>
                        Current District: <?php echo htmlspecialchars(ucfirst($farmerData['farm_location'])); ?> | 
                        Elevation: 800-1500m | 
                        Best Suited Crops: Cardamom, Coffee, Pepper | 
                        Annual Rainfall: 2500-3500mm | 
                        Temperature Range: 10-35°C | 
                        Soil Type: Forest Loam
                    <?php else: ?>
                        <div class="setup-notice">
                            <p>Welcome! Please set up your farm location to get weather updates and recommendations.</p>
                            <a href="farmer.php" class="setup-link">Set Location <i class="fas fa-arrow-right"></i></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (isset($analysis['unsuitable_location']) && $analysis['unsuitable_location']): ?>
            <div class="warning-banner fade-in">
                <div class="warning-text">
                    <i class="fas fa-exclamation-triangle warning-icon"></i>
                    ⚠️ Warning: <?php echo ucfirst($analysis['current_location']); ?>'s weather and soil conditions are not ideal for cardamom plantation. For optimal cardamom cultivation, consider locations in Idukki or Wayanad regions which offer the perfect climate and elevation. ⚠️
                    &nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;
                    <i class="fas fa-exclamation-triangle warning-icon"></i>
                    Key Concerns: Unsuitable elevation, inadequate rainfall pattern, and suboptimal soil conditions for cardamom growth. 
                    &nbsp;&nbsp;&nbsp;|&nbsp;&nbsp;&nbsp;
                    <i class="fas fa-exclamation-triangle warning-icon"></i>
                    Recommended Action: Consult with agricultural experts for guidance on relocation or alternative crop selection.
                </div>
            </div>
            <?php endif; ?>

            <!-- Add this new section before the analysis section -->
            <div class="analysis-summary fade-in">
                <div class="summary-message">
                    <i class="fas fa-clipboard-list summary-icon"></i>
                    <div class="summary-text">
                        Hello <span class="highlight"><?php echo htmlspecialchars($farmerData['farmer_name'] ?? 'Farmer'); ?></span>, 
                        based on today's analysis:
                        <?php
                        $optimalCount = 0;
                        $totalConditions = 0;
                        foreach ($analysis['conditions'] as $condition) {
                            if ($condition['status'] === 'optimal') {
                                $optimalCount++;
                            }
                            $totalConditions++;
                        }
                        
                        if ($totalConditions > 0) {
                            $percentage = round(($optimalCount / $totalConditions) * 100);
                            
                            if ($percentage >= 75) {
                                echo "<br><i class='fas fa-circle-check' style='color: #2d6a4f; margin-right: 8px;'></i> 
                                     <strong style='color: #2d6a4f;'>Excellent conditions!</strong> $percentage% of parameters are in optimal range.
                                     <br><span style='font-size: 0.9em; margin-top: 8px; display: block;'>
                                     Your farm is thriving under ideal conditions. Keep up the great work!</span>";
                            } elseif ($percentage >= 50) {
                                echo "<br><i class='fas fa-circle-exclamation' style='color: #b7791f; margin-right: 8px;'></i>
                                     <strong style='color: #b7791f;'>Moderate conditions.</strong> $percentage% of parameters are in optimal range.
                                     <br><span style='font-size: 0.9em; margin-top: 8px; display: block;'>
                                     Some parameters need attention. Check the recommendations below.</span>";
                            } else {
                                echo "<br><i class='fas fa-triangle-exclamation' style='color: #c53030; margin-right: 8px;'></i>
                                     <strong style='color: #c53030;'>Action required!</strong> Only $percentage% of parameters are in optimal range.
                                     <br><span style='font-size: 0.9em; margin-top: 8px; display: block;'>
                                     Several parameters need immediate attention. Please review recommendations carefully.</span>";
                            }
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div class="analysis-section fade-in">
                <div class="section-title">
                    <i class="fas fa-cloud-sun"></i>
                    <h2>Farm Conditions Analysis</h2>
                </div>
                <div class="analysis-container">
                    <!-- Weather Parameters -->
                    <div class="analysis-card">
                        <div class="parameter-icon">
                            <i class="fas fa-temperature-high"></i>
                        </div>
                        <h3>Temperature</h3>
                        <div class="parameter-value">
                            <?php echo isset($weather_data['main']['temp']) ? round($weather_data['main']['temp']) : 'N/A'; ?>°C
                        </div>
                        <?php if (isset($analysis['conditions']['temperature'])): ?>
                            <span class="status-indicator status-<?php echo $analysis['conditions']['temperature']['status']; ?>">
                                <?php echo ucfirst($analysis['conditions']['temperature']['status']); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="analysis-card">
                        <div class="parameter-icon">
                            <i class="fas fa-tint"></i>
                        </div>
                        <h3>Humidity</h3>
                        <div class="parameter-value">
                            <?php echo isset($weather_data['main']['humidity']) ? $weather_data['main']['humidity'] : 'N/A'; ?>%
                        </div>
                        <?php if (isset($analysis['conditions']['humidity'])): ?>
                            <span class="status-indicator status-<?php echo $analysis['conditions']['humidity']['status']; ?>">
                                <?php echo ucfirst($analysis['conditions']['humidity']['status']); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="analysis-card">
                        <div class="parameter-icon">
                            <i class="fas fa-flask"></i>
                        </div>
                        <h3>Soil pH</h3>
                        <div class="parameter-value">
                            <?php echo isset($farmerData['avg_ph']) ? number_format($farmerData['avg_ph'], 1) : 'N/A'; ?>
                        </div>
                        <?php if (isset($analysis['conditions']['soil_ph'])): ?>
                            <span class="status-indicator status-<?php echo $analysis['conditions']['soil_ph']['status']; ?>">
                                <?php echo ucfirst($analysis['conditions']['soil_ph']['status']); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="analysis-card">
                        <div class="parameter-icon">
                            <i class="fas fa-leaf"></i>
                        </div>
                        <h3>Nitrogen</h3>
                        <div class="parameter-value">
                            <?php 
                                if (isset($farmerData['avg_nitrogen']) && $farmerData['avg_nitrogen'] !== null) {
                                    echo number_format($farmerData['avg_nitrogen'], 1) . ' ppm';
                                } else {
                                    echo 'No data';
                                }
                            ?>
                        </div>
                        <?php if (isset($analysis['conditions']['nitrogen_content'])): ?>
                            <span class="status-indicator status-<?php echo $analysis['conditions']['nitrogen_content']['status']; ?>">
                                <?php echo ucfirst($analysis['conditions']['nitrogen_content']['status']); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="analysis-card">
                        <div class="parameter-icon">
                            <i class="fas fa-seedling"></i>
                        </div>
                        <h3>Phosphorus</h3>
                        <div class="parameter-value">
                            <?php 
                                if (isset($farmerData['avg_phosphorus']) && $farmerData['avg_phosphorus'] !== null) {
                                    echo number_format($farmerData['avg_phosphorus'], 1) . ' ppm';
                                } else {
                                    echo 'No data';
                                }
                            ?>
                        </div>
                        <?php if (isset($analysis['conditions']['phosphorus_content'])): ?>
                            <span class="status-indicator status-<?php echo $analysis['conditions']['phosphorus_content']['status']; ?>">
                                <?php echo ucfirst($analysis['conditions']['phosphorus_content']['status']); ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="analysis-card">
                        <div class="parameter-icon">
                            <i class="fas fa-mountain"></i>
                        </div>
                        <h3>Potassium</h3>
                        <div class="parameter-value">
                            <?php 
                                if (isset($farmerData['avg_potassium']) && $farmerData['avg_potassium'] !== null) {
                                    echo number_format($farmerData['avg_potassium'], 1) . ' ppm';
                                } else {
                                    echo 'No data';
                                }
                            ?>
                        </div>
                        <?php if (isset($analysis['conditions']['potassium_content'])): ?>
                            <span class="status-indicator status-<?php echo $analysis['conditions']['potassium_content']['status']; ?>">
                                <?php echo ucfirst($analysis['conditions']['potassium_content']['status']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recommendations -->
            <div class="analysis-card recommendations-card">
                <h2><i class="fas fa-lightbulb"></i> Recommendations</h2>
                <ul class="recommendations-list">
                    <?php if (!empty($analysis['recommendations'])): ?>
                        <?php foreach ($analysis['recommendations'] as $recommendation): ?>
                            <li><?php echo $recommendation; ?></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>No specific recommendations at this time. Continue maintaining current conditions.</li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Add Pesticide and Fertilizer Recommendations Section -->
            <div class="recommendations-section fade-in">
                <div class="section-title">
                    <i class="fas fa-flask"></i>
                    <h2>Pesticide & Fertilizer Recommendations</h2>
                </div>
                <div class="recommendations-container">
                    <?php if (!empty($pesticide_fertilizer_recommendations)): ?>
                        <?php foreach ($pesticide_fertilizer_recommendations as $rec): ?>
                            <div class="recommendation-card <?php echo $rec['type']; ?>">
                                <div class="recommendation-icon">
                                    <i class="fas <?php echo $rec['type'] === 'pesticide' ? 'fa-bug-slash' : 'fa-seedling'; ?>"></i>
                                </div>
                                <div class="recommendation-content">
                                    <h3><?php echo htmlspecialchars($rec['title']); ?></h3>
                                    <p><?php echo htmlspecialchars($rec['description']); ?></p>
                                    <div class="timing">
                                        <i class="fas fa-clock"></i>
                                        <span><?php echo htmlspecialchars($rec['timing']); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-recommendations">
                            <i class="fas fa-info-circle"></i>
                            <p>No specific recommendations at this time. Continue with regular maintenance.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Remove loading overlay after page loads
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.getElementById('loadingOverlay').style.display = 'none';
            }, 1500); // Show loading for 1.5 seconds
        });
    </script>
</body>
</html> 