<?php
session_start();

// Ensure user is logged in and is an employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = mysqli_connect("localhost", "root", "", "growguide");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Get farmer ID from URL if provided
$farmer_id = isset($_GET['farmer_id']) ? intval($_GET['farmer_id']) : null;

// Enhanced function to get comprehensive farmer details
function getFarmerDetails($conn, $farmer_id) {
    if (!$farmer_id) return null;
    
    $query = "SELECT u.*, f.farm_location, f.phone, f.farm_size, f.plantation_age,
                     f.yield_history, f.irrigation_type,
                     COUNT(st.id) as total_soil_tests,
                     COUNT(fr.id) as total_recommendations
              FROM users u
              LEFT JOIN farmers f ON u.id = f.farmer_id
              LEFT JOIN soil_tests st ON u.id = st.farmer_id
              LEFT JOIN fertilizer_recommendations fr ON u.id = fr.farmer_id
              WHERE u.id = ? AND u.role = 'farmer'
              GROUP BY u.id";
              
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        error_log("Prepare failed: " . mysqli_error($conn));
        return null;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $farmer_id);
    if (!mysqli_stmt_execute($stmt)) {
        error_log("Execute failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return null;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $data;
}

// Enhanced function to get soil and weather data
function getSoilAndWeatherData($conn, $farmer_id) {
    if (!$farmer_id) return null;
    
    $query = "SELECT st.*, w.temperature, w.humidity, w.rainfall, w.soil_moisture,
                     w.wind_speed, w.sunlight_hours
              FROM soil_tests st
              LEFT JOIN weather_data w ON st.farmer_id = w.farmer_id
              WHERE st.farmer_id = ?
              ORDER BY st.test_date DESC, w.recorded_date DESC
              LIMIT 1";
              
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        error_log("Prepare failed: " . mysqli_error($conn));
        return null;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $farmer_id);
    if (!mysqli_stmt_execute($stmt)) {
        error_log("Execute failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return null;
    }
    
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    return $data;
}

// Enhanced recommendation generation function
function generateComprehensiveRecommendations($farmerData, $soilWeatherData) {
    $recommendations = [
        'fertilizers' => [],
        'pesticides' => [],
        'amendments' => [],
        'cultural_practices' => [],
        'warnings' => []
    ];
    
    // Soil pH based recommendations
    if ($soilWeatherData['ph_level'] < 5.5) {
        $recommendations['amendments'][] = [
            'type' => 'pH Amendment',
            'product' => 'Agricultural Lime',
            'amount' => '2-3 tonnes/ha',
            'application' => 'Apply evenly and incorporate into soil',
            'notes' => 'Will help increase soil pH and improve nutrient availability'
        ];
    } elseif ($soilWeatherData['ph_level'] > 7.5) {
        $recommendations['amendments'][] = [
            'type' => 'pH Amendment',
            'product' => 'Elemental Sulfur',
            'amount' => '500-1000 kg/ha',
            'application' => 'Apply evenly and incorporate into soil',
            'notes' => 'Will help decrease soil pH'
        ];
    }

    // NPK recommendations based on soil test
    if ($soilWeatherData['nitrogen_content'] < 0.5) {
        $recommendations['fertilizers'][] = [
            'type' => 'Nitrogen',
            'product' => 'Urea',
            'amount' => '200-250 kg/ha',
            'schedule' => 'Split into 3-4 applications',
            'timing' => 'Apply during pre-monsoon and post-monsoon periods',
            'notes' => 'Essential for leaf growth and overall plant development'
        ];
    }

    if ($soilWeatherData['phosphorus_content'] < 0.3) {
        $recommendations['fertilizers'][] = [
            'type' => 'Phosphorus',
            'product' => 'Single Super Phosphate',
            'amount' => '150-200 kg/ha',
            'schedule' => 'Single application',
            'timing' => 'Apply during planting or first monsoon',
            'notes' => 'Important for root development and flowering'
        ];
    }

    if ($soilWeatherData['potassium_content'] < 0.3) {
        $recommendations['fertilizers'][] = [
            'type' => 'Potassium',
            'product' => 'Muriate of Potash',
            'amount' => '150-200 kg/ha',
            'schedule' => 'Split into 2 applications',
            'timing' => 'Apply during flowering and capsule formation',
            'notes' => 'Enhances disease resistance and improves yield quality'
        ];
    }

    // Weather-based recommendations
    if ($soilWeatherData['humidity'] > 80) {
        $recommendations['pesticides'][] = [
            'type' => 'Fungicide',
            'product' => 'Copper oxychloride',
            'concentration' => '3g/L water',
            'schedule' => 'Every 15-20 days during high humidity',
            'notes' => 'Preventive spray against fungal diseases'
        ];
        
        $recommendations['cultural_practices'][] = [
            'practice' => 'Improve Air Circulation',
            'method' => 'Proper spacing and pruning',
            'notes' => 'Reduce disease pressure in high humidity conditions'
        ];
    }

    if ($soilWeatherData['rainfall'] > 300) {
        $recommendations['warnings'][] = 'High rainfall detected. Risk of nutrient leaching.';
        $recommendations['cultural_practices'][] = [
            'practice' => 'Mulching',
            'method' => 'Apply organic mulch',
            'notes' => 'Helps prevent soil erosion and nutrient leaching'
        ];
    }

    if ($soilWeatherData['soil_moisture'] < 30) {
        $recommendations['cultural_practices'][] = [
            'practice' => 'Irrigation Management',
            'method' => 'Increase irrigation frequency',
            'notes' => 'Maintain optimal soil moisture for nutrient uptake'
        ];
    }

    return $recommendations;
}

// Get farmer data and generate recommendations
$farmerDetails = getFarmerDetails($conn, $farmer_id);
$soilWeatherData = getSoilAndWeatherData($conn, $farmer_id);
$recommendations = null;

if ($farmerDetails && $soilWeatherData) {
    $recommendations = generateComprehensiveRecommendations($farmerDetails, $soilWeatherData);
}

// Save recommendation if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_recommendation'])) {
    $recommendation_text = mysqli_real_escape_string($conn, $_POST['recommendation_text']);
    $farmer_id = intval($_POST['farmer_id']);
    
    $query = "INSERT INTO fertilizer_recommendations (farmer_id, recommendation_text, recommendation_date, status) 
              VALUES (?, ?, NOW(), 'pending')";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "is", $farmer_id, $recommendation_text);
    
    if (mysqli_stmt_execute($stmt)) {
        $success_message = "Recommendation saved successfully!";
    } else {
        $error_message = "Error saving recommendation: " . mysqli_error($conn);
    }
}

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fertilizer Recommendations - GrowGuide</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .recommendation-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        .recommendation-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .soil-data {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .data-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }

        .recommendation-section {
            margin-bottom: 30px;
        }

        .recommendation-section h3 {
            color: #2B7A30;
            margin-bottom: 15px;
        }

        .recommendation-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .recommendation-table th,
        .recommendation-table td {
            padding: 12px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .recommendation-table th {
            background: #2B7A30;
            color: white;
        }

        .recommendation-table tr:nth-child(even) {
            background: #f8f9fa;
        }

        .weather-alert {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .save-form {
            margin-top: 30px;
        }

        .save-form textarea {
            width: 100%;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 15px;
            min-height: 150px;
        }

        .btn-save {
            background: #2B7A30;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn-save:hover {
            background: #1B4D1E;
        }

        .farmer-details-section {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .detail-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .data-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .recommendations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .recommendation-item {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .warning-alert {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            color: #856404;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .recommendation-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .recommendation-card h3 {
            color: #2B7A30;
            margin-bottom: 15px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #2B7A30;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .btn-back:hover {
            background: #1B4D1E;
        }
    </style>
</head>
<body>
    <div class="recommendation-container">
        <div style="margin-bottom: 20px;">
            <a href="employe.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        <h1><i class="fas fa-flask"></i> Fertilizer Recommendations</h1>
        
        <div class="farmer-details-section">
            <?php if ($farmerDetails): ?>
                <div class="farmer-profile">
                    <h2>Farmer Details</h2>
                    <div class="details-grid">
                        <div class="detail-item">
                            <span class="label">Name:</span>
                            <span class="value"><?php echo htmlspecialchars($farmerDetails['username']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Location:</span>
                            <span class="value"><?php echo htmlspecialchars($farmerDetails['farm_location']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Farm Size:</span>
                            <span class="value"><?php echo htmlspecialchars($farmerDetails['farm_size']); ?> hectares</span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Plantation Age:</span>
                            <span class="value"><?php echo htmlspecialchars($farmerDetails['plantation_age']); ?> years</span>
                        </div>
                    </div>
                </div>

                <div class="soil-weather-data">
                    <h2>Soil & Weather Analysis</h2>
                    <div class="data-grid">
                        <div class="data-card">
                            <h3>Soil Parameters</h3>
                            <ul>
                                <li>pH Level: <?php echo number_format($soilWeatherData['ph_level'], 2); ?></li>
                                <li>Nitrogen: <?php echo number_format($soilWeatherData['nitrogen_content'], 2); ?>%</li>
                                <li>Phosphorus: <?php echo number_format($soilWeatherData['phosphorus_content'], 2); ?>%</li>
                                <li>Potassium: <?php echo number_format($soilWeatherData['potassium_content'], 2); ?>%</li>
                            </ul>
                        </div>
                        <div class="data-card">
                            <h3>Weather Conditions</h3>
                            <ul>
                                <li>Temperature: <?php echo number_format($soilWeatherData['temperature'], 1); ?>°C</li>
                                <li>Humidity: <?php echo number_format($soilWeatherData['humidity'], 1); ?>%</li>
                                <li>Rainfall: <?php echo number_format($soilWeatherData['rainfall'], 1); ?> mm</li>
                                <li>Soil Moisture: <?php echo number_format($soilWeatherData['soil_moisture'], 1); ?>%</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <?php if ($recommendations): ?>
                    <div class="recommendations-section">
                        <h2>Recommendations</h2>
                        
                        <?php if (!empty($recommendations['warnings'])): ?>
                            <div class="warnings">
                                <?php foreach ($recommendations['warnings'] as $warning): ?>
                                    <div class="warning-alert"><?php echo htmlspecialchars($warning); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Fertilizer Recommendations -->
                        <?php if (!empty($recommendations['fertilizers'])): ?>
                            <div class="recommendation-card">
                                <h3>Fertilizer Recommendations</h3>
                                <div class="recommendations-grid">
                                    <?php foreach ($recommendations['fertilizers'] as $fertilizer): ?>
                                        <div class="recommendation-item">
                                            <h4><?php echo htmlspecialchars($fertilizer['product']); ?></h4>
                                            <p><strong>Amount:</strong> <?php echo htmlspecialchars($fertilizer['amount']); ?></p>
                                            <p><strong>Schedule:</strong> <?php echo htmlspecialchars($fertilizer['schedule']); ?></p>
                                            <p><strong>Notes:</strong> <?php echo htmlspecialchars($fertilizer['notes']); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Pesticide Recommendations -->
                        <?php if (!empty($recommendations['pesticides'])): ?>
                            <div class="recommendation-card">
                                <h3>Pesticide Recommendations</h3>
                                <div class="recommendations-grid">
                                    <?php foreach ($recommendations['pesticides'] as $pesticide): ?>
                                        <div class="recommendation-item">
                                            <h4><?php echo htmlspecialchars($pesticide['product']); ?></h4>
                                            <p><strong>Concentration:</strong> <?php echo htmlspecialchars($pesticide['concentration']); ?></p>
                                            <p><strong>Schedule:</strong> <?php echo htmlspecialchars($pesticide['schedule']); ?></p>
                                            <p><strong>Notes:</strong> <?php echo htmlspecialchars($pesticide['notes']); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Cultural Practices -->
                        <?php if (!empty($recommendations['cultural_practices'])): ?>
                            <div class="recommendation-card">
                                <h3>Cultural Practices</h3>
                                <div class="recommendations-grid">
                                    <?php foreach ($recommendations['cultural_practices'] as $practice): ?>
                                        <div class="recommendation-item">
                                            <h4><?php echo htmlspecialchars($practice['practice']); ?></h4>
                                            <p><strong>Method:</strong> <?php echo htmlspecialchars($practice['method']); ?></p>
                                            <p><strong>Notes:</strong> <?php echo htmlspecialchars($practice['notes']); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <p>Please select a farmer to view recommendations.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 