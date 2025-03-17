<?php
session_start();
require_once 'config.php';

// Check if user is logged in and has employee role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

// Get farmer ID from URL
$farmer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$farmer_id) {
    header("Location: employe.php");
    exit();
}

// Fetch farmer details
$farmer_query = "SELECT u.*, COUNT(st.test_id) as total_soil_tests 
                FROM users u 
                LEFT JOIN soil_tests st ON u.id = st.farmer_id
                WHERE u.id = ? AND u.role = 'farmer'
                GROUP BY u.id";

$stmt = $conn->prepare($farmer_query);
if ($stmt === false) {
    die("Error preparing query: " . $conn->error);
}
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$farmer = $stmt->get_result()->fetch_assoc();

if (!$farmer) {
    header("Location: employe.php");
    exit();
}

// Fetch soil tests for this farmer
$soil_tests_query = "SELECT st.*,
                           CASE 
                               WHEN ph_level < 5.5 THEN 'Low'
                               WHEN ph_level > 6.5 THEN 'High'
                               ELSE 'Optimal'
                           END as ph_status,
                           CASE 
                               WHEN nitrogen_content < 0.5 THEN 'Deficient'
                               WHEN nitrogen_content > 1.0 THEN 'Excessive'
                               ELSE 'Optimal'
                           END as nitrogen_status,
                           CASE 
                               WHEN phosphorus_content < 0.05 THEN 'Deficient'
                               WHEN phosphorus_content > 0.15 THEN 'Excessive'
                               ELSE 'Optimal'
                           END as phosphorus_status,
                           CASE 
                               WHEN potassium_content < 1.0 THEN 'Deficient'
                               WHEN potassium_content > 2.0 THEN 'Excessive'
                               ELSE 'Optimal'
                           END as potassium_status
                    FROM soil_tests st
                    WHERE farmer_id = ?
                    ORDER BY test_date DESC";

$stmt = $conn->prepare($soil_tests_query);
$stmt->bind_param("i", $farmer_id);
$stmt->execute();
$soil_tests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get weather data for farmer's location
$weather_api_key = "cc02c9dee7518466102e748f211bca05";
$weather_data = null;
if (!empty($farmer['farm_location'])) {
    $weather_url = "https://api.openweathermap.org/data/2.5/weather?q=" . 
                   urlencode($farmer['farm_location']) . 
                   "&units=metric&appid=" . $weather_api_key;
    
    $weather_response = @file_get_contents($weather_url);
    if ($weather_response) {
        $weather_data = json_decode($weather_response, true);
    }
}

// Generate recommendations based on latest soil test
$latest_soil_test = !empty($soil_tests) ? $soil_tests[0] : null;
$recommendations = [];

if ($latest_soil_test) {
    // Fertilizer recommendations
    if ($latest_soil_test['nitrogen_content'] < 0.5) {
        $recommendations['fertilizer'][] = [
            'type' => 'Nitrogen',
            'product' => 'Urea',
            'amount' => '100-150 kg/ha',
            'frequency' => 'Split in 3-4 applications'
        ];
    }
    
    if ($latest_soil_test['phosphorus_content'] < 0.05) {
        $recommendations['fertilizer'][] = [
            'type' => 'Phosphorus',
            'product' => 'Single Super Phosphate',
            'amount' => '200-250 kg/ha',
            'frequency' => 'During soil preparation'
        ];
    }
    
    if ($latest_soil_test['potassium_content'] < 1.0) {
        $recommendations['fertilizer'][] = [
            'type' => 'Potassium',
            'product' => 'Muriate of Potash',
            'amount' => '150-200 kg/ha',
            'frequency' => 'Split in 2-3 applications'
        ];
    }
}

// Pesticide recommendations based on weather
if ($weather_data) {
    $humidity = $weather_data['main']['humidity'];
    $temp = $weather_data['main']['temp'];
    
    if ($humidity > 75 || ($temp > 25 && $temp < 30)) {
        $recommendations['pesticide'][] = [
            'type' => 'Preventive',
            'product' => 'Neem Oil Spray',
            'dosage' => '2-3 ml/L of water',
            'frequency' => 'Every 15 days'
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Details - <?php echo htmlspecialchars($farmer['username']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            min-height: 100vh;
            background-color: #f5f5f5;
        }

        .sidebar {
            width: 250px;
            background-color: #1b4332;
            color: white;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h2 {
            margin: 0;
            font-size: 1.5rem;
            color: white;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-item {
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }

        .nav-item:hover, .nav-item.active {
            background-color: rgba(255, 255, 255, 0.1);
            border-left-color: #ffffff;
        }

        .nav-item i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .nav-item span {
            font-size: 1rem;
        }

        .logout-item {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 15px;
            margin-bottom: 20px;
        }

        .logout-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-left-color: #ff4444;
        }

        /* Notification badge */
        .nav-item .badge {
            background-color: #ff4444;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.8rem;
            margin-left: auto;
        }

        /* Main content adjustment */
        .farmer-details-container {
            margin-left: 250px;
            padding: 20px;
            width: calc(100% - 250px);
            min-height: 100vh;
        }

        .farmer-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .farmer-info {
            flex-grow: 1;
        }

        .data-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .recommendations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
        }

        .status-optimal { background: #d4edda; color: #155724; }
        .status-deficient { background: #f8d7da; color: #721c24; }
        .status-excessive { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h2><i class="fas fa-seedling"></i> GrowGuide</h2>
        </div>
        <a href="dashboard.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="varieties.php" class="nav-item">
            <i class="fas fa-leaf"></i>
            <span>Varieties</span>
        </a>
        <a href="notifications.php" class="nav-item">
            <i class="fas fa-bell"></i>
            <span>Notifications</span>
            <span class="badge">4</span>
        </a>
        <a href="settings.php" class="nav-item">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
        <a href="manage-products.php" class="nav-item">
            <i class="fas fa-shopping-basket"></i>
            <span>Manage Products</span>
        </a>
        <a href="logout.php" class="nav-item logout-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>

    <div class="farmer-details-container">
        <div class="farmer-header">
            <div class="farmer-icon">
                <i class="fas fa-user-circle fa-3x"></i>
            </div>
            <div class="farmer-info">
                <h1><?php echo htmlspecialchars($farmer['username']); ?></h1>
                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($farmer['farm_location']); ?></p>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($farmer['email']); ?></p>
            </div>
            <div class="farmer-stats">
                <p><i class="fas fa-flask"></i> Total Soil Tests: <?php echo $farmer['total_soil_tests']; ?></p>
            </div>
        </div>

        <!-- Latest Soil Test Results -->
        <?php if ($latest_soil_test): ?>
        <div class="data-card">
            <h2><i class="fas fa-flask"></i> Latest Soil Test Results</h2>
            <p>Test Date: <?php echo date('F j, Y', strtotime($latest_soil_test['test_date'])); ?></p>
            <div class="soil-parameters">
                <div class="parameter">
                    <h3>pH Level: <?php echo number_format($latest_soil_test['ph_level'], 2); ?></h3>
                    <span class="status-badge status-<?php echo strtolower($latest_soil_test['ph_status']); ?>">
                        <?php echo $latest_soil_test['ph_status']; ?>
                    </span>
                </div>
                <!-- Add similar divs for N, P, K levels -->
            </div>
        </div>
        <?php endif; ?>

        <!-- Recommendations -->
        <div class="recommendations-grid">
            <!-- Fertilizer Recommendations -->
            <div class="data-card">
                <h2><i class="fas fa-leaf"></i> Fertilizer Recommendations</h2>
                <?php if (!empty($recommendations['fertilizer'])): ?>
                    <?php foreach ($recommendations['fertilizer'] as $rec): ?>
                        <div class="recommendation-item">
                            <h3><?php echo htmlspecialchars($rec['type']); ?></h3>
                            <p><strong>Product:</strong> <?php echo htmlspecialchars($rec['product']); ?></p>
                            <p><strong>Amount:</strong> <?php echo htmlspecialchars($rec['amount']); ?></p>
                            <p><strong>Frequency:</strong> <?php echo htmlspecialchars($rec['frequency']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No specific fertilizer recommendations at this time.</p>
                <?php endif; ?>
            </div>

            <!-- Pesticide Recommendations -->
            <div class="data-card">
                <h2><i class="fas fa-shield-alt"></i> Pesticide Recommendations</h2>
                <?php if (!empty($recommendations['pesticide'])): ?>
                    <?php foreach ($recommendations['pesticide'] as $rec): ?>
                        <div class="recommendation-item">
                            <h3><?php echo htmlspecialchars($rec['type']); ?></h3>
                            <p><strong>Product:</strong> <?php echo htmlspecialchars($rec['product']); ?></p>
                            <p><strong>Dosage:</strong> <?php echo htmlspecialchars($rec['dosage']); ?></p>
                            <p><strong>Frequency:</strong> <?php echo htmlspecialchars($rec['frequency']); ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No specific pesticide recommendations at this time.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Weather Information -->
        <?php if ($weather_data): ?>
        <div class="data-card">
            <h2><i class="fas fa-cloud-sun"></i> Current Weather Conditions</h2>
            <div class="weather-info">
                <p><i class="fas fa-temperature-high"></i> Temperature: <?php echo round($weather_data['main']['temp']); ?>°C</p>
                <p><i class="fas fa-tint"></i> Humidity: <?php echo $weather_data['main']['humidity']; ?>%</p>
                <p><i class="fas fa-wind"></i> Wind: <?php echo $weather_data['wind']['speed']; ?> m/s</p>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html> 