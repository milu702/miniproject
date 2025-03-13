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
        COALESCE(p.soil_type, '') as soil_type,
        COALESCE(p.soil_moisture, 0) as soil_moisture,
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

// Get weather data
$weather_api_key = "cc02c9dee7518466102e748f211bca05";
$weather_data = null;
if ($farmerData['farm_location']) {
    $weather_url = "https://api.openweathermap.org/data/2.5/weather?q=" . 
        urlencode($farmerData['farm_location']) . "&units=metric&appid=" . $weather_api_key;
    $weather_response = @file_get_contents($weather_url);
    if ($weather_response) {
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farm Analysis - GrowGuide</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Copy the common styles from farmer.php */
        /* Add these specific styles for the analysis page */
        .analysis-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);  /* Changed to always show 3 columns */
            gap: 30px;
            margin: 30px 0;
        }

        .analysis-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .parameter-value {
            font-size: 2em;
            font-weight: bold;
            color: var(--primary-color);
            margin: 15px 0;
        }

        .status-indicator {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            margin-top: 10px;
        }

        .status-optimal {
            background: #d4edda;
            color: #155724;
        }

        .status-suboptimal {
            background: #f8d7da;
            color: #721c24;
        }

        .recommendations-card {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, #2d6a4f, #40916c);
            color: white;
        }

        .recommendations-list {
            list-style: none;
            padding: 0;
        }

        .recommendations-list li {
            margin: 15px 0;
            padding-left: 25px;
            position: relative;
        }

        .recommendations-list li:before {
            content: "•";
            position: absolute;
            left: 0;
            color: var(--accent-color);
        }

        .parameter-icon {
            font-size: 2em;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .loading-content {
            text-align: center;
            color: var(--primary-color);
        }

        .loading-spinner {
            animation: spin 2s linear infinite;
            font-size: 3em;
            margin-bottom: 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .welcome-header {
            margin-bottom: 30px;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .analysis-section {
            margin-bottom: 40px;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            color: var(--primary-color);
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background-color: #2d6a4f;
            color: white;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            margin-bottom: 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background-color: #40916c;
            transform: translateX(-5px);
        }

        .back-button i {
            font-size: 1.1em;
        }

        .analysis-summary {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin: 20px 0;
        }

        .summary-message {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            font-size: 1.1em;
            line-height: 1.6;
            color: #2d3748;
        }

        .summary-icon {
            font-size: 1.8em;
            color: var(--primary-color);
            flex-shrink: 0;
        }

        .summary-text {
            flex-grow: 1;
        }

        .highlight {
            color: var(--primary-color);
            font-weight: 600;
        }

        .running-message {
            animation: runningText 20s linear infinite;
        }
        
        @keyframes runningText {
            0% { transform: translateX(100%); }
            100% { transform: translateX(-100%); }
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
        <!-- Copy sidebar from farmer.php -->
        
        <div class="main-content">
            <a href="farmer.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>

            <div class="welcome-header fade-in">
                <i class="fas fa-user-farmer"></i>
                <h1>Welcome, <?php echo htmlspecialchars($farmerData['farmer_name'] ?? 'Farmer'); ?></h1>
            </div>

            <?php if (isset($analysis['unsuitable_location']) && $analysis['unsuitable_location']): ?>
            <div class="analysis-summary fade-in">
                <div class="summary-message" style="color: #c53030;">
                    <i class="fas fa-exclamation-triangle summary-icon" style="color: #c53030;"></i>
                    <div class="summary-text">
                        <div class="running-message" style="white-space: nowrap; overflow: hidden;">
                            ⚠️ Warning: <?php echo ucfirst($analysis['current_location']); ?>'s weather and soil conditions are not ideal for cardamom plantation. For optimal cardamom cultivation, consider locations in Idukki or Wayanad regions which offer the perfect climate and elevation. ⚠️
                        </div>
                    </div>
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