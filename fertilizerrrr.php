<?php
session_start();
require_once 'config.php';

// Ensure user is logged in and has the 'farmer' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: login.php");
    exit();
}

// Get farmer's data
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$farmer = $stmt->get_result()->fetch_assoc();

// Get soil test data
$soil_query = "SELECT * FROM soil_tests WHERE farmer_id = ? ORDER BY test_date DESC LIMIT 1";
$stmt = $conn->prepare($soil_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$soil_data = $stmt->get_result()->fetch_assoc();

// Get weather data
$weather_api_key = "cc02c9dee7518466102e748f211bca05";
$weather_data = null;
if (isset($farmer['farm_location'])) {
    $weather_url = "https://api.openweathermap.org/data/2.5/weather?q=" . 
                   urlencode($farmer['farm_location']) . 
                   "&units=metric&appid=" . $weather_api_key;
    
    $weather_response = @file_get_contents($weather_url);
    if ($weather_response) {
        $weather_data = json_decode($weather_response, true);
    }
}

// Add this after getting soil data
if ($soil_data) {
    // Ensure all required keys exist
    $required_keys = ['ph_level', 'nitrogen_content', 'phosphorus_content', 'potassium_content'];
    foreach ($required_keys as $key) {
        if (!isset($soil_data[$key])) {
            $soil_data[$key] = 0; // Set default value if key doesn't exist
        }
    }
}

// Function to get fertilizer recommendations based on soil and weather
function getFertilizerRecommendations($soil_data, $weather_data) {
    $recommendations = [];
    
    // Base fertilizer recommendation for cardamom
    $recommendations[] = [
        'type' => 'NPK',
        'ratio' => '6:6:20',
        'amount' => '250-300 kg/ha',
        'frequency' => 'Every 3-4 months',
        'notes' => 'Apply during pre-monsoon period'
    ];

    // Check nitrogen levels
    if (!isset($soil_data['nitrogen_content']) || $soil_data['nitrogen_content'] < 0.5) {
        $recommendations[] = [
            'type' => 'Urea',
            'amount' => '100-150 kg/ha',
            'frequency' => 'Split in 2-3 applications',
            'notes' => 'Apply during active growth period'
        ];
    }

    // Check phosphorus levels
    if (!isset($soil_data['phosphorus_content']) || $soil_data['phosphorus_content'] < 0.3) {
        $recommendations[] = [
            'type' => 'Single Super Phosphate',
            'amount' => '200-250 kg/ha',
            'frequency' => 'Once per year',
            'notes' => 'Apply before planting or during soil preparation'
        ];
    }

    // Check potassium levels
    if (!isset($soil_data['potassium_content']) || $soil_data['potassium_content'] < 1.0) {
        $recommendations[] = [
            'type' => 'Muriate of Potash',
            'amount' => '150-200 kg/ha',
            'frequency' => 'Split in 2-3 applications',
            'notes' => 'Essential for pod development'
        ];
    }

    // Check organic matter
    if (!isset($soil_data['organic_matter']) || $soil_data['organic_matter'] < 3.0) {
        $recommendations[] = [
            'type' => 'Organic Manure',
            'amount' => '5-10 tons/ha',
            'frequency' => 'Once per year',
            'notes' => 'Apply well-decomposed organic matter during soil preparation'
        ];
    }

    return $recommendations;
}

$fertilizer_recommendations = getFertilizerRecommendations($soil_data, $weather_data);

// Add this function after getFertilizerRecommendations function
function getPesticideRecommendations($weather_data) {
    $recommendations = [];
    $current_temp = isset($weather_data['main']['temp']) ? $weather_data['main']['temp'] : 25;
    $humidity = isset($weather_data['main']['humidity']) ? $weather_data['main']['humidity'] : 60;
    
    // High risk conditions for pests
    $high_risk = ($humidity > 75 || ($current_temp > 25 && $current_temp < 30));
    
    if ($high_risk) {
        $recommendations[] = [
            'type' => 'Preventive',
            'name' => 'Neem Oil Spray',
            'dosage' => '2-3 ml/L of water',
            'frequency' => 'Every 15 days',
            'notes' => 'Natural pesticide safe for cardamom plants. Apply early morning or evening.'
        ];
        
        $recommendations[] = [
            'type' => 'Curative',
            'name' => 'Quinalphos',
            'dosage' => '2ml/L of water',
            'frequency' => 'When pest infestation is noticed',
            'notes' => 'Use only if significant pest damage is observed.'
        ];
    } else {
        $recommendations[] = [
            'type' => 'Monitoring',
            'name' => 'Regular Inspection',
            'dosage' => 'N/A',
            'frequency' => 'Weekly',
            'notes' => 'Current conditions are not favorable for pests. Continue monitoring.'
        ];
    }
    
    return $recommendations;
}

// Add this line after getting fertilizer recommendations
$pesticide_recommendations = getPesticideRecommendations($weather_data);

// Add this query after your existing database queries
$products_query = "SELECT * FROM products WHERE stock > 0 ORDER BY type, name";
$products = mysqli_query($conn, $products_query);

// Add this after your existing database queries
$notifications_query = "SELECT * FROM notifications 
                       WHERE type = 'product_update' 
                       AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
                       ORDER BY created_at DESC";
$notifications = mysqli_query($conn, $notifications_query);

// Add this function after getPesticideRecommendations function
function updateRecommendations($user_id, $conn) {
    // Update soil test data
    $soil_query = "SELECT * FROM soil_tests WHERE farmer_id = ? ORDER BY test_date DESC LIMIT 1";
    $stmt = $conn->prepare($soil_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $soil_data = $stmt->get_result()->fetch_assoc();

    // Get farmer's location for weather update
    $location_query = "SELECT farm_location FROM users WHERE id = ?";
    $stmt = $conn->prepare($location_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $farmer = $stmt->get_result()->fetch_assoc();

    // Update weather data
    $weather_data = null;
    if (isset($farmer['farm_location'])) {
        $weather_api_key = "cc02c9dee7518466102e748f211bca05";
        $weather_url = "https://api.openweathermap.org/data/2.5/weather?q=" . 
                       urlencode($farmer['farm_location']) . 
                       "&units=metric&appid=" . $weather_api_key;
        
        $weather_response = @file_get_contents($weather_url);
        if ($weather_response) {
            $weather_data = json_decode($weather_response, true);
        }
    }

    return [
        'soil_data' => $soil_data,
        'weather_data' => $weather_data
    ];
}

// Add this near the top of the file, after session_start()
if (isset($_POST['update_recommendations'])) {
    $updated_data = updateRecommendations($_SESSION['user_id'], $conn);
    $soil_data = $updated_data['soil_data'];
    $weather_data = $updated_data['weather_data'];
    $fertilizer_recommendations = getFertilizerRecommendations($soil_data, $weather_data);
    $pesticide_recommendations = getPesticideRecommendations($weather_data);
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "?updated=true");
    exit();
}

// Add this function after getPesticideRecommendations function
function generateUPIId() {
    return 'farmer' . rand(1000, 9999) . '@ybl';
}

// Update the payment processing code near the top
if (isset($_POST['process_upi_payment'])) {
    $product_id = $_POST['product_id'];
    $upi_id = generateUPIId();
    
    // Simulate a successful payment
    $payment_reference = 'PAY' . rand(100000, 999999);
    
    // In a real application, you'd update inventory and create an order
    $_SESSION['payment_success'] = [
        'product_id' => $product_id,
        'upi_id' => $upi_id,
        'payment_reference' => $payment_reference,
        'timestamp' => time()
    ];
    
    // Redirect to prevent form resubmission
    header("Location: " . $_SERVER['PHP_SELF'] . "?payment=success&ref=" . $payment_reference);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fertilizer Recommendations - GrowGuide</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        :root {
            --primary-color: #2D5A27;
            --primary-dark: #1A3A19;
            --accent-color: #8B9D83;
            --text-color: #333333;
            --bg-color: #f5f5f5;
            --sidebar-width: 250px;
        }

        /* Sidebar Base */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--primary-color);
            min-height: 100vh;
            padding: 20px 0;
            position: fixed;
            left: 0;
            top: 0;
        }

        /* Logo Section */
        .sidebar-header {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            margin-bottom: 30px;
        }

        .sidebar-header i {
            font-size: 24px;
        }

        .sidebar-header h2 {
            font-size: 20px;
            margin: 0;
            font-weight: 500;
        }

        /* Profile Section */
        .farmer-profile {
            padding: 20px;
            text-align: center;
            margin-bottom: 30px;
        }

        .farmer-avatar {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .farmer-avatar i {
            font-size: 24px;
            color: white;
        }

        .farmer-profile h3 {
            color: white;
            font-size: 16px;
            margin: 0 0 5px 0;
            font-weight: 500;
        }

        .farmer-profile p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            margin: 0;
        }

        .farmer-location {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            color: rgba(255, 255, 255, 0.8);
            font-size: 13px;
            margin-top: 8px;
        }

        /* Navigation Menu */
        .nav-menu {
            padding: 0 15px;
        }

        .nav-menu-items {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .nav-item i {
            width: 20px;
            margin-right: 10px;
            font-size: 16px;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-item.active {
            background: rgba(255, 255, 255, 0.15);
            font-weight: 500;
        }

        /* Bottom Menu */
        .nav-menu-bottom {
            position: absolute;
            bottom: 20px;
            width: 100%;
            padding: 0 15px;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .logout-btn i {
            margin-right: 10px;
        }

        /* Custom Icons for Nav Items */
        .nav-item[href="farmer.php"] i { color: #4CAF50; }
        .nav-item[href="soil_test.php"] i { color: #2196F3; }
        .nav-item[href="fertilizerrrr.php"] i { color: #8BC34A; }
        .nav-item[href="farm_analysis.php"] i { color: #FF9800; }
        .nav-item[href="schedule.php"] i { color: #9C27B0; }
        .nav-item[href="weather.php"] i { color: #03A9F4; }
        .nav-item[href="settings.php"] i { color: #607D8B; }

        /* Active item overrides icon color */
        .nav-item.active i {
            color: white !important;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
            }

            .sidebar-header h2 span,
            .farmer-profile h3,
            .farmer-profile p,
            .farmer-location,
            .nav-item span {
                display: none;
            }

            .nav-item {
                padding: 15px;
                justify-content: center;
            }

            .nav-item i {
                margin: 0;
                font-size: 20px;
            }

            .nav-item:hover {
                padding-left: 15px;
            }

            .logout-btn {
                padding: 15px;
                justify-content: center;
            }

            .logout-btn i {
                margin: 0;
            }

            .main-content {
                margin-left: 60px;
            }
        }

        /* Update main content styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
            background-color: var(--bg-color);
            min-height: 100vh;
            margin-top: 0;
            padding-top: 20px;
        }

        /* Remove back button */
        .back-button {
            display: none;
        }

        /* Optimize layout for better space usage */
        .analysis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .recommendation-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }

        .recommendation-card h2 {
            color: var(--primary-color);
            display: flex;
            align-items: center;
                gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
            }

        /* Make tables more compact */
        .fertilizer-table {
                width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        .fertilizer-table th, 
        .fertilizer-table td {
            padding: 10px;
            font-size: 0.95em;
        }

        /* Optimize product grid */
        .products-grid {
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 15px;
        }

        .product-card {
            padding: 15px;
            margin-bottom: 0;
        }

        /* Add responsive design */
        @media (max-width: 1200px) {
            .analysis-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 60px;
            }
            
            .main-content {
                margin-left: 60px;
            }
            
            .sidebar-header h2,
            .farmer-profile h3,
            .farmer-profile p,
            .nav-item span {
                display: none;
            }
            
            .nav-item i {
                margin: 0;
            }
        }

        /* Add smooth transitions */
        .recommendation-card,
        .product-card,
        .nav-item {
            transition: all 0.3s ease;
        }

        /* Update announcement bar position */
        .announcement-bar {
            position: sticky;
            top: 0;
            z-index: 999;
            margin-left: var(--sidebar-width);
        }

        @media (max-width: 768px) {
            .announcement-bar {
                margin-left: 60px;
            }
        }

        /* Sidebar Navigation Styles */
        .nav-menu {
            padding: 20px 0;
        }

        .nav-menu-items {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 0 25px 25px 0;
            margin-right: 15px;
            position: relative;
            overflow: hidden;
        }

        .nav-item i {
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
        }

        .nav-item span {
            position: relative;
            z-index: 2;
        }

        /* Hover Effect */
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(8px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .nav-item:hover i {
            transform: rotate(10deg) scale(1.2);
            color: var(--accent-color);
        }

        /* Active State */
        .nav-item.active {
            background: linear-gradient(
                90deg,
                rgba(255, 255, 255, 0.1),
                rgba(255, 255, 255, 0.05)
            );
            font-weight: bold;
        }

        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: var(--accent-color);
            box-shadow: 0 0 8px var(--accent-color);
        }

        /* Logout Button Special Styling */
        .logout-btn {
            margin-top: 20px;
            background: rgba(255, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logout-btn:hover {
            background: rgba(255, 0, 0, 0.2);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .logout-btn i {
            color: #ff6b6b;
        }

        /* Ripple Effect */
        .nav-item::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.3);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }

        .nav-item:hover::after {
            animation: ripple 1s ease-out;
        }

        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            100% {
                transform: scale(40, 40);
                opacity: 0;
            }
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .nav-item {
                padding: 12px 15px;
                justify-content: center;
                margin-right: 5px;
            }

            .nav-item i {
                margin-right: 0;
                font-size: 18px;
            }

            .nav-item:hover {
                transform: translateX(4px);
            }
        }

        /* Icon Bounce Animation */
        @keyframes iconBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-3px); }
        }

        .nav-item:hover i {
            animation: iconBounce 0.5s ease infinite;
        }

        /* Active Item Glow */
        .nav-item.active i {
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }

        /* Recommendations Table Styling */
        .recommendations-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }

        .recommendations-table th {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        .recommendations-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .recommendations-table tr:last-child td {
            border-bottom: none;
        }

        .recommendations-table tr:hover td {
            background-color: #f8f9fa;
        }

        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .status-optimal {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .status-attention {
            background-color: #fff3e0;
            color: #f57c00;
        }

        .status-action {
            background-color: #fbe9e7;
            color: #d32f2f;
        }

        /* Info Icons */
        .info-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            font-size: 12px;
            margin-left: 5px;
            cursor: help;
        }

        /* Remove products section */
        .products-grid, 
        #paymentModal {
            display: none;
        }

        .recommendation-content {
            margin-top: 30px;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
        }

        .recommendation-content h3 {
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }

        .recommendation-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .recommendation-item {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .recommendation-item h5 {
            color: var(--primary-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .recommendation-item ul {
            margin: 0;
            padding-left: 20px;
        }

        .recommendation-item li {
            margin: 8px 0;
            color: #666;
            line-height: 1.4;
        }

        .recommendations-table {
            margin-bottom: 0;
        }

        .recommendations-table th {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 15px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .status-optimal { background-color: #28a745; color: white; }
        .status-action { background-color: #dc3545; color: white; }
        .status-attention { background-color: #ffc107; color: #000; }
    </style>
</head>
<body>
    <!-- Add this in the HTML, right after the announcement-bar div -->
    <?php if (mysqli_num_rows($notifications) > 0): ?>
        <div class="update-notification animate-fadeInUp" style="animation-delay: 0.1s;">
            <div class="notification-icon">
                <i class="fas fa-bell"></i>
            </div>
            <div class="notification-content">
                <h3>New Updates Available!</h3>
                <p>Product inventory has been updated. Please check the latest recommendations below.</p>
            </div>
        </div>
    <?php endif; ?>

    <div class="layout-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-seedling"></i>
                <h2>GrowGuide</h2>
            </div>
            
            <div class="farmer-profile">
                <div class="farmer-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h3><?php echo htmlspecialchars($farmer['username']); ?></h3>
                <p>Cardamom Farmer</p>
                <?php if (isset($farmer['farm_location'])): ?>
                    <div class="farmer-location">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($farmer['farm_location']); ?>
                    </div>
                <?php endif; ?>
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
                    <a href="fertilizerrrr.php" class="nav-item active">
                        <i class="fas fa-leaf"></i>
                        <span>Fertilizer Guide</span>
                    </a>
                    <a href="farm_analysis.php" class="nav-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Farm Analysis</span>
                    </a>
                    <a href="schedule.php" class="nav-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Schedule</span>
                    </a>
                    <a href="weather.php" class="nav-item">
                        <i class="fas fa-cloud-sun"></i>
                        <span>Weather</span>
                    </a>
                    <a href="settings.php" class="nav-item">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </div>
                
                <div class="nav-menu-bottom">
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </div>

        <!-- Update the main content wrapper -->
        <div class="main-content">
            <!-- Add this in the HTML, right after the back button -->
            <div class="update-section animate-fadeInUp" style="margin-bottom: 20px;">
                <form method="POST" style="display: inline;">
                    <button type="submit" name="update_recommendations" class="update-button">
                        <i class="fas fa-sync-alt"></i> Update Recommendations
                    </button>
                </form>
                <?php if (isset($_GET['updated'])): ?>
                    <div class="update-success">
                        <i class="fas fa-check-circle"></i> Recommendations updated successfully!
                    </div>
                <?php endif; ?>
            </div>

            <h1 class="animate-fadeInUp"><i class="fas fa-leaf"></i> Fertilizer Recommendations</h1>
            
            <div class="analysis-grid">
                <!-- Update Soil Analysis Card -->
                <div class="recommendation-card animate-fadeInUp" style="animation-delay: 0.1s;">
                    <h2>
                        <div class="nutrient-icon">
                            <i class="fas fa-flask"></i>
                        </div>
                        Soil Analysis
                    </h2>
                    <?php if ($soil_data): ?>
                        <p><strong>Last Test Date:</strong> <?php echo date('M d, Y', strtotime($soil_data['test_date'])); ?></p>
                        <p><strong>pH Level:</strong> <?php echo isset($soil_data['ph_level']) ? number_format($soil_data['ph_level'], 2) : 'Not tested'; ?></p>
                        <p><strong>Organic Matter:</strong> <?php echo isset($soil_data['organic_matter']) ? number_format($soil_data['organic_matter'], 2) : 'Not tested'; ?>%</p>
                        <p><strong>Nitrogen (N):</strong> <?php echo isset($soil_data['nitrogen_content']) ? number_format($soil_data['nitrogen_content'], 2) : 'Not tested'; ?>%</p>
                        <p><strong>Phosphorus (P):</strong> <?php echo isset($soil_data['phosphorus_content']) ? number_format($soil_data['phosphorus_content'], 2) : 'Not tested'; ?>%</p>
                        <p><strong>Potassium (K):</strong> <?php echo isset($soil_data['potassium_content']) ? number_format($soil_data['potassium_content'], 2) : 'Not tested'; ?>%</p>
                    <?php else: ?>
                        <p class="alert-info">No recent soil test data available. Please conduct a soil test.</p>
                        <a href="soil_test.php" class="btn btn-primary mt-3">
                            <i class="fas fa-flask"></i> Conduct Soil Test
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Update Weather Analysis Card -->
                <div class="recommendation-card animate-fadeInUp" style="animation-delay: 0.2s;">
                    <h2>
                        <div class="nutrient-icon">
                            <i class="fas fa-cloud-sun"></i>
                        </div>
                        Weather Analysis
                    </h2>
                    <?php if ($weather_data): ?>
                        <p><strong>Temperature:</strong> <?php echo round($weather_data['main']['temp']); ?>°C</p>
                        <p><strong>Humidity:</strong> <?php echo $weather_data['main']['humidity']; ?>%</p>
                        <p><strong>Conditions:</strong> <?php echo ucfirst($weather_data['weather'][0]['description']); ?></p>
                    <?php else: ?>
                        <p class="alert-info">Weather data unavailable</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Replace the existing recommendations section with this updated version -->
            <div class="recommendation-card">
                <h2>
                    <i class="fas fa-leaf"></i>
                    Soil Test Results & Recommendations
                </h2>
                
                <!-- Soil Test Results Table -->
                <table class="recommendations-table">
                    <thead>
                        <tr>
                            <th>Parameter</th>
                            <th>Current Level</th>
                            <th>Optimal Range</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($soil_data): ?>
                            <!-- pH Level -->
                            <tr>
                                <td><strong>pH Level</strong></td>
                                <td><?php echo number_format($soil_data['ph_level'], 2); ?></td>
                                <td>5.5 - 6.5</td>
                                <td>
                                    <?php
                                    if ($soil_data['ph_level'] < 5.5) {
                                        echo '<span class="status-badge status-action"><i class="fas fa-exclamation-circle"></i> Acidic</span>';
                                    } elseif ($soil_data['ph_level'] > 6.5) {
                                        echo '<span class="status-badge status-attention"><i class="fas fa-exclamation-triangle"></i> Alkaline</span>';
                                    } else {
                                        echo '<span class="status-badge status-optimal"><i class="fas fa-check-circle"></i> Optimal</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <!-- Nitrogen -->
                            <tr>
                                <td><strong>Nitrogen (N)</strong></td>
                                <td><?php echo number_format($soil_data['nitrogen_content'], 2); ?>%</td>
                                <td>0.5% - 1.0%</td>
                                <td>
                                    <?php
                                    if ($soil_data['nitrogen_content'] < 0.5) {
                                        echo '<span class="status-badge status-action"><i class="fas fa-exclamation-circle"></i> Deficient</span>';
                                    } elseif ($soil_data['nitrogen_content'] > 1.0) {
                                        echo '<span class="status-badge status-attention"><i class="fas fa-exclamation-triangle"></i> Excessive</span>';
                                    } else {
                                        echo '<span class="status-badge status-optimal"><i class="fas fa-check-circle"></i> Optimal</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <!-- Phosphorus -->
                            <tr>
                                <td><strong>Phosphorus (P)</strong></td>
                                <td><?php echo number_format($soil_data['phosphorus_content'], 2); ?>%</td>
                                <td>0.05% - 0.2%</td>
                                <td>
                                    <?php
                                    if ($soil_data['phosphorus_content'] < 0.05) {
                                        echo '<span class="status-badge status-action"><i class="fas fa-exclamation-circle"></i> Deficient</span>';
                                    } elseif ($soil_data['phosphorus_content'] > 0.2) {
                                        echo '<span class="status-badge status-attention"><i class="fas fa-exclamation-triangle"></i> Excessive</span>';
                                    } else {
                                        echo '<span class="status-badge status-optimal"><i class="fas fa-check-circle"></i> Optimal</span>';
                                    }
                                    ?>
                                </td>
                                </tr>
                            <!-- Potassium -->
                            <tr>
                                <td><strong>Potassium (K)</strong></td>
                                <td><?php echo number_format($soil_data['potassium_content'], 2); ?>%</td>
                                <td>1.0% - 2.0%</td>
                                <td>
                                    <?php
                                    if ($soil_data['potassium_content'] < 1.0) {
                                        echo '<span class="status-badge status-action"><i class="fas fa-exclamation-circle"></i> Deficient</span>';
                                    } elseif ($soil_data['potassium_content'] > 2.0) {
                                        echo '<span class="status-badge status-attention"><i class="fas fa-exclamation-triangle"></i> Excessive</span>';
                                    } else {
                                        echo '<span class="status-badge status-optimal"><i class="fas fa-check-circle"></i> Optimal</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">
                                    <p class="alert-info">No recent soil test data available. Please conduct a soil test.</p>
                                    <a href="soil_test.php" class="btn btn-primary mt-3">
                                        <i class="fas fa-flask"></i> Conduct Soil Test
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                    
                <!-- Fertilizer Recommendations -->
                <?php if ($soil_data): ?>
                <div class="recommendation-content">
                    <h3><i class="fas fa-lightbulb"></i> Fertilizer Recommendations</h3>
                    <div class="recommendation-grid">
                        <!-- Base Recommendation -->
                        <div class="recommendation-item">
                            <h5><i class="fas fa-seedling"></i> Base Fertilizer Plan</h5>
                            <ul>
                                <li>Apply NPK (6:6:20) at 250-300 kg/ha as base fertilizer</li>
                                <li>Split application into 3-4 doses throughout the growing season</li>
                                <li>First application: Pre-monsoon period</li>
                                <li>Subsequent applications: Every 3-4 months</li>
                            </ul>
                        </div>

                        <!-- pH Specific Recommendations -->
                        <div class="recommendation-item">
                            <h5><i class="fas fa-vial"></i> pH Management</h5>
                            <?php if ($soil_data['ph_level'] < 5.5): ?>
                                <ul>
                                    <li>Apply agricultural lime (2-3 tons/ha)</li>
                                    <li>Mix lime thoroughly with soil</li>
                                    <li>Allow 2-3 weeks before fertilizer application</li>
                                    <li>Consider dolomitic lime for additional Mg</li>
                                </ul>
                            <?php elseif ($soil_data['ph_level'] > 6.5): ?>
                                <ul>
                                    <li>Apply elemental sulfur (1-2 tons/ha)</li>
                                    <li>Incorporate organic matter</li>
                                    <li>Use acidifying fertilizers</li>
                                    <li>Monitor pH changes monthly</li>
                                </ul>
                            <?php else: ?>
                                <ul>
                                    <li>Maintain current pH management practices</li>
                                    <li>Monitor pH quarterly</li>
                                </ul>
                            <?php endif; ?>
                        </div>

                        <!-- Nutrient Specific Recommendations -->
                        <div class="recommendation-item">
                            <h5><i class="fas fa-flask"></i> Nutrient Management</h5>
                            <ul>
                                <?php if ($soil_data['nitrogen_content'] < 0.5): ?>
                                    <li>Apply Urea (46-0-0) at 100-150 kg/ha</li>
                                    <li>Split into 3 applications</li>
                    <?php endif; ?>
                                
                                <?php if ($soil_data['phosphorus_content'] < 0.05): ?>
                                    <li>Apply Single Super Phosphate at 200-250 kg/ha</li>
                                    <li>Apply during soil preparation</li>
                <?php endif; ?>
                
                                <?php if ($soil_data['potassium_content'] < 1.0): ?>
                                    <li>Apply Muriate of Potash at 150-200 kg/ha</li>
                                    <li>Split into 2-3 applications</li>
                                <?php endif; ?>
                                
                                <?php if ($soil_data['nitrogen_content'] >= 0.5 && 
                                         $soil_data['phosphorus_content'] >= 0.05 && 
                                         $soil_data['potassium_content'] >= 1.0): ?>
                                    <li>Maintain current fertilization program</li>
                                    <li>Monitor nutrient levels quarterly</li>
                                <?php endif; ?>
                    </ul>
            </div>

                        <!-- Application Schedule -->
                        <div class="recommendation-item">
                            <h5><i class="fas fa-calendar-alt"></i> Application Schedule</h5>
                            <ul>
                                <li>First Application: Pre-monsoon (May-June)</li>
                                <li>Second Application: Post-monsoon (September-October)</li>
                                <li>Third Application: Winter (December-January)</li>
                                <li>Final Application: Spring (March-April)</li>
                                <li>Note: Adjust timing based on local weather conditions</li>
                            </ul>
                    </div>
                        </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Add Weather Impact Section -->
            <div class="recommendation-card">
                <h2>
                    <i class="fas fa-cloud-sun"></i>
                    Weather Impact & Recommendations
                </h2>
                <table class="recommendations-table">
                    <thead>
                        <tr>
                            <th>Factor</th>
                            <th>Current Condition</th>
                            <th>Impact</th>
                            <th>Action Required</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($weather_data): ?>
                            <tr>
                                <td><strong>Temperature</strong></td>
                                <td><?php echo round($weather_data['main']['temp']); ?>°C</td>
                                <td>
                                    <?php
                                    $temp = $weather_data['main']['temp'];
                                    if ($temp < 15) {
                                        echo '<span class="status-badge status-attention">Cold Stress Risk</span>';
                                    } elseif ($temp > 30) {
                                        echo '<span class="status-badge status-action">Heat Stress Risk</span>';
                                    } else {
                                        echo '<span class="status-badge status-optimal">Optimal</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php
                                    if ($temp < 15) {
                                        echo 'Consider protective measures against cold';
                                    } elseif ($temp > 30) {
                                        echo 'Ensure adequate irrigation and mulching';
                                    } else {
                                        echo 'Continue regular maintenance';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <!-- Add similar rows for Humidity and other weather factors -->
                        <?php endif; ?>
                    </tbody>
                </table>
    </div>

            <!-- Add Pesticide Recommendations -->
            <div class="recommendation-card">
                <h2>
                    <i class="fas fa-shield-alt"></i>
                    Pesticide Recommendations
                </h2>
                <table class="recommendations-table">
                    <thead>
                        <tr>
                            <th>Condition</th>
                            <th>Risk Level</th>
                            <th>Treatment</th>
                            <th>Application Guide</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($weather_data): ?>
                            <?php
                            $humidity = $weather_data['main']['humidity'];
                            $temp = $weather_data['main']['temp'];
                            $high_risk = ($humidity > 75 || ($temp > 25 && $temp < 30));
                            ?>
                            <tr>
                                <td><strong>Disease Risk</strong></td>
                                <td>
                                    <?php if ($high_risk): ?>
                                        <span class="status-badge status-action"><i class="fas fa-exclamation-circle"></i> High Risk</span>
                                    <?php else: ?>
                                        <span class="status-badge status-optimal"><i class="fas fa-check-circle"></i> Low Risk</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($high_risk): ?>
                                        Neem Oil Spray + Preventive Fungicide
                                    <?php else: ?>
                                        Regular Monitoring
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($high_risk): ?>
                                        Apply neem oil (2-3 ml/L) early morning or evening. Monitor for disease symptoms daily.
                                    <?php else: ?>
                                        Continue weekly inspections. No immediate action required.
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Pest Control</strong></td>
                                <td>
                                    <?php if ($humidity > 75): ?>
                                        <span class="status-badge status-attention"><i class="fas fa-exclamation-triangle"></i> Medium Risk</span>
                                    <?php else: ?>
                                        <span class="status-badge status-optimal"><i class="fas fa-check-circle"></i> Low Risk</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($humidity > 75): ?>
                                        Biological Control + Sticky Traps
                                    <?php else: ?>
                                        Preventive Measures
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($humidity > 75): ?>
                                        Install yellow sticky traps (1 trap/100 sq.m). Apply neem cake to soil (100 kg/ha).
                                    <?php else: ?>
                                        Maintain field hygiene and monitor pest populations.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation classes to elements as they scroll into view
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-fadeInUp');
                    }
                });
            });

            document.querySelectorAll('.recommendation-card').forEach((card) => {
                observer.observe(card);
            });

            // Add hover effect to table rows
            document.querySelectorAll('.fertilizer-table tr').forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transition = 'background-color 0.3s ease';
                });
            });

            // Auto-hide notification after 10 seconds
            const notification = document.querySelector('.update-notification');
            if (notification) {
                setTimeout(() => {
                    notification.style.transition = 'opacity 0.5s ease';
                    notification.style.opacity = '0';
                    setTimeout(() => notification.remove(), 500);
                }, 10000);
            }
        });
    </script>
</body>
</html>