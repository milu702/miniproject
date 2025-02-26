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
        p.soil_type,
        p.soil_moisture,
        st.ph_level as soil_ph,
        st.nitrogen_content,
        st.phosphorus_content,
        st.potassium_content,
        
        st.test_date,
        u.farm_location,
        u.username as farmer_name
    FROM farmers f
    LEFT JOIN farmer_profiles p ON f.farmer_id = p.farmer_id
    LEFT JOIN users u ON f.user_id = u.id
    LEFT JOIN soil_tests st ON f.farmer_id = st.farmer_id
    WHERE f.user_id = ?
    ORDER BY st.test_date DESC LIMIT 1
");

if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$farmerData = $result->fetch_assoc();

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

    // Add location check for cardamom suitability
    if (isset($soil_data['farm_location'])) {
        $suitable_locations = ['idukki', 'wayanad'];
        $current_location = strtolower($soil_data['farm_location']);
        if (!in_array($current_location, $suitable_locations)) {
            $unsuitable_location = true;
        }
    }

    // If location is unsuitable, only return this message
    if ($unsuitable_location) {
        return [
            'conditions' => [],
            'recommendations' => [],
            'unsuitable_location' => true
        ];
    }

    // Ideal conditions for cardamom
    $ideal_conditions = [
        'temperature' => ['min' => 10, 'max' => 35],
        'humidity' => ['min' => 60, 'max' => 90],
        'soil_ph' => ['min' => 6.0, 'max' => 6.5],
        'soil_moisture' => ['min' => 60, 'max' => 80],
        'nitrogen_content' => ['min' => 120, 'max' => 160], // ppm
        'phosphorus_content' => ['min' => 20, 'max' => 30], // ppm
        'potassium_content' => ['min' => 200, 'max' => 300], // ppm
    ];

    // Weather analysis
    if ($weather_data) {
        $temp = $weather_data['main']['temp'];
        $humidity = $weather_data['main']['humidity'];
        
        $conditions['temperature'] = [
            'value' => $temp,
            'status' => ($temp >= $ideal_conditions['temperature']['min'] && 
                        $temp <= $ideal_conditions['temperature']['max']) ? 'optimal' : 'suboptimal'
        ];
        
        $conditions['humidity'] = [
            'value' => $humidity,
            'status' => ($humidity >= $ideal_conditions['humidity']['min'] && 
                        $humidity <= $ideal_conditions['humidity']['max']) ? 'optimal' : 'suboptimal'
        ];
    }

    // Soil analysis
    if ($soil_data) {
        $conditions['soil_ph'] = [
            'value' => $soil_data['soil_ph'],
            'status' => ($soil_data['soil_ph'] >= $ideal_conditions['soil_ph']['min'] && 
                        $soil_data['soil_ph'] <= $ideal_conditions['soil_ph']['max']) ? 'optimal' : 'suboptimal'
        ];
        
        // Add new soil nutrient conditions
        $conditions['nitrogen_content'] = [
            'value' => $soil_data['nitrogen_content'],
            'status' => ($soil_data['nitrogen_content'] >= $ideal_conditions['nitrogen_content']['min'] && 
                        $soil_data['nitrogen_content'] <= $ideal_conditions['nitrogen_content']['max']) ? 'optimal' : 'suboptimal'
        ];
        
        $conditions['phosphorus_content'] = [
            'value' => $soil_data['phosphorus_content'],
            'status' => ($soil_data['phosphorus_content'] >= $ideal_conditions['phosphorus_content']['min'] && 
                        $soil_data['phosphorus_content'] <= $ideal_conditions['phosphorus_content']['max']) ? 'optimal' : 'suboptimal'
        ];
        
        $conditions['potassium_content'] = [
            'value' => $soil_data['potassium_content'],
            'status' => ($soil_data['potassium_content'] >= $ideal_conditions['potassium_content']['min'] && 
                        $soil_data['potassium_content'] <= $ideal_conditions['potassium_content']['max']) ? 'optimal' : 'suboptimal'
        ];
        
        // Add recommendations based on conditions
        if ($conditions['soil_ph']['status'] === 'suboptimal') {
            if ($soil_data['soil_ph'] < $ideal_conditions['soil_ph']['min']) {
                $recommendations[] = "Consider adding agricultural lime to increase soil pH.";
            } else {
                $recommendations[] = "Add organic matter to help lower soil pH.";
            }
        }
        
        if ($conditions['nitrogen_content']['status'] === 'suboptimal') {
            if ($soil_data['nitrogen_content'] < $ideal_conditions['nitrogen_content']['min']) {
                $recommendations[] = "Consider adding nitrogen-rich fertilizers or organic matter like compost.";
            } else {
                $recommendations[] = "Reduce nitrogen application and consider planting nitrogen-consuming crops.";
            }
        }
        
        if ($conditions['phosphorus_content']['status'] === 'suboptimal') {
            if ($soil_data['phosphorus_content'] < $ideal_conditions['phosphorus_content']['min']) {
                $recommendations[] = "Add phosphorus-rich fertilizers or bone meal to improve phosphorus levels.";
            } else {
                $recommendations[] = "Reduce phosphorus application to prevent excess buildup.";
            }
        }
        
        if ($conditions['potassium_content']['status'] === 'suboptimal') {
            if ($soil_data['potassium_content'] < $ideal_conditions['potassium_content']['min']) {
                $recommendations[] = "Apply potassium-rich fertilizers or add wood ash to increase potassium levels.";
            } else {
                $recommendations[] = "Reduce potassium application and monitor levels.";
            }
        }
    }

    return [
        'conditions' => $conditions,
        'recommendations' => $recommendations
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
                            Dear <?php echo htmlspecialchars($farmerData['farmer_name'] ?? 'Farmer'); ?>, 
                            this location is not suitable for cardamom plantation. Cardamom cultivation is best suited for Idukki and Wayanad regions due to their specific climatic conditions and elevation.
                        </div>
                    </div>
                </div>
            </div>

            <?php return; // Stop displaying the rest of the analysis ?>
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
                            <?php echo isset($farmerData['soil_ph']) ? number_format($farmerData['soil_ph'], 1) : 'N/A'; ?>
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
                            <?php echo isset($farmerData['nitrogen_content']) ? $farmerData['nitrogen_content'] . ' ppm' : 'N/A'; ?>
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
                            <?php echo isset($farmerData['phosphorus_content']) ? $farmerData['phosphorus_content'] . ' ppm' : 'N/A'; ?>
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
                            <?php echo isset($farmerData['potassium_content']) ? $farmerData['potassium_content'] . ' ppm' : 'N/A'; ?>
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