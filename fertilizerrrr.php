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
    if (!isset($soil_data['nitrogen']) || $soil_data['nitrogen'] < 0.5) {
        $recommendations[] = [
            'type' => 'Urea',
            'amount' => '100-150 kg/ha',
            'frequency' => 'Split in 2-3 applications',
            'notes' => 'Apply during active growth period'
        ];
    }

    // Check phosphorus levels
    if (!isset($soil_data['phosphorus']) || $soil_data['phosphorus'] < 0.3) {
        $recommendations[] = [
            'type' => 'Single Super Phosphate',
            'amount' => '200-250 kg/ha',
            'frequency' => 'Once per year',
            'notes' => 'Apply before planting or during soil preparation'
        ];
    }

    // Check potassium levels
    if (!isset($soil_data['potassium']) || $soil_data['potassium'] < 1.0) {
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

// Add this function near the other PHP functions (after generateUPIId)
function generateUpiQrCode($upiId, $amount, $productName) {
    // Format UPI payment URL
    $upiUrl = urlencode("upi://pay?pa={$upiId}&pn=GrowGuide&tn={$productName}&am={$amount}&cu=INR");
    
    // Use Google Charts API for QR code generation
    return "https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl={$upiUrl}";
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
            --primary-green: #2e7d32;
            --light-green: #4caf50;
            --pale-green: #e8f5e9;
            --hover-green: #1b5e20;
        }

        .layout-container {
            padding: 20px;
            background-color: #f8f9fa;
        }

        .main-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .back-button {
            background-color: var(--primary-green);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .back-button:hover {
            background-color: var(--hover-green);
            transform: translateX(-5px);
        }

        .recommendation-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            border-top: 4px solid var(--light-green);
            transition: transform 0.3s ease;
        }

        .recommendation-card:hover {
            transform: translateY(-5px);
        }

        .recommendation-card h2 {
            color: var(--primary-green);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .fertilizer-table th {
            background-color: var(--primary-green);
            color: white;
        }

        .fertilizer-table tr:hover {
            background-color: var(--pale-green) !important;
        }

        .status-item {
            transition: transform 0.3s ease;
        }

        .status-item:hover {
            transform: scale(1.02);
        }

        .status-item.low {
            background-color: #ffebee;
            border-left: 4px solid #d32f2f;
        }

        .status-item.optimal {
            background-color: var(--pale-green);
            border-left: 4px solid var(--primary-green);
        }

        .status-item.high {
            background-color: #fff3e0;
            border-left: 4px solid #ef6c00;
        }

        .nutrient-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--pale-green);
            border-radius: 50%;
            margin-right: 10px;
        }

        .nutrient-icon i {
            color: var(--primary-green);
            font-size: 20px;
        }

        .alert-info {
            background-color: var(--pale-green);
            color: var(--primary-green);
            border-left: 4px solid var(--light-green);
        }

        .btn-primary {
            background-color: var(--primary-green);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: var(--hover-green);
            transform: translateY(-2px);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fadeInUp {
            animation: fadeInUp 0.5s ease forwards;
        }

        .announcement-bar {
            background: linear-gradient(90deg, var(--primary-green), var(--light-green));
            color: white;
            padding: 12px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .marquee-container {
            flex-grow: 1;
            margin-right: 20px;
        }

        .announcement-text {
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            gap: 15px;
        }

        .announcement-text i {
            animation: bounce 1s infinite;
        }

        .shop-now-btn {
            background-color: white;
            color: var(--primary-green);
            padding: 8px 20px;
            border-radius: 25px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: bold;
            transition: all 0.3s ease;
            white-space: nowrap;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .shop-now-btn:hover {
            background-color: var(--hover-green);
            color: white;
            transform: translateX(5px);
        }

        .shop-now-btn i {
            transition: transform 0.3s ease;
        }

        .shop-now-btn:hover i {
            transform: translateX(5px);
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-3px);
            }
        }

        /* Adjust layout container to account for sticky header */
        .layout-container {
            padding-top: 10px;
        }

        /* Make the announcement responsive */
        @media (max-width: 768px) {
            .announcement-bar {
                flex-direction: column;
                gap: 10px;
                padding: 10px;
            }

            .marquee-container {
                margin-right: 0;
                margin-bottom: 10px;
                width: 100%;
            }

            .shop-now-btn {
                width: 100%;
                justify-content: center;
            }
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .product-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-5px);
        }

        .product-card h3 {
            color: var(--primary-green);
            margin-bottom: 10px;
        }

        .product-card .btn-primary {
            width: 100%;
            margin-top: 10px;
            text-align: center;
        }

        .update-notification {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 15px;
            margin: 20px auto;
            max-width: 1200px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .notification-icon {
            background: #ff9800;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-icon i {
            color: white;
            font-size: 20px;
        }

        .notification-content {
            flex-grow: 1;
        }

        .notification-content h3 {
            color: #e65100;
            margin: 0 0 5px 0;
        }

        .notification-content p {
            margin: 0;
            color: #424242;
        }

        @keyframes ring {
            0% { transform: rotate(0); }
            10% { transform: rotate(30deg); }
            20% { transform: rotate(-28deg); }
            30% { transform: rotate(25deg); }
            40% { transform: rotate(-22deg); }
            50% { transform: rotate(18deg); }
            60% { transform: rotate(-15deg); }
            70% { transform: rotate(12deg); }
            80% { transform: rotate(-8deg); }
            90% { transform: rotate(5deg); }
            100% { transform: rotate(0); }
        }

        .notification-icon i {
            animation: ring 2s infinite;
        }

        .update-button {
            background-color: var(--primary-green);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .update-button:hover {
            background-color: var(--hover-green);
            transform: scale(1.05);
        }

        .update-button i {
            transition: transform 0.3s ease;
        }

        .update-button:hover i {
            transform: rotate(180deg);
        }

        .update-success {
            display: inline-block;
            margin-left: 15px;
            color: var(--primary-green);
            background-color: var(--pale-green);
            padding: 10px 20px;
            border-radius: 8px;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 20px;
            border-radius: 10px;
            width: 80%;
            max-width: 500px;
            position: relative;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from { transform: translateY(-100px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .close {
            position: absolute;
            right: 20px;
            top: 10px;
            font-size: 28px;
            cursor: pointer;
            color: #666;
        }

        .close:hover {
            color: #000;
        }

        .payment-methods {
            display: grid;
            gap: 15px;
            margin-top: 20px;
        }

        .payment-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 15px;
            border: 2px solid var(--primary-green);
            border-radius: 8px;
            background: white;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .payment-option:hover {
            background: var(--pale-green);
            transform: translateY(-2px);
        }

        .payment-option i {
            font-size: 20px;
            color: var(--primary-green);
        }

        .payment-details {
            background: var(--pale-green);
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
        }

        .payment-details p {
            margin: 5px 0;
            font-size: 16px;
        }

        .upi-payment-section {
            text-align: center;
            padding: 20px;
        }

        .upi-qr-container {
            margin: 20px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .upi-details {
            margin: 20px 0;
            padding: 15px;
            background: var(--pale-green);
            border-radius: 8px;
        }

        .app-buttons {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 10px;
        }

        .upi-app-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .upi-app-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .upi-app-btn img {
            width: 30px;
            height: 30px;
        }
    </style>
</head>
<body>
    <!-- Add this right after the <body> tag and before the layout-container div -->
    <div class="announcement-bar">
        <div class="marquee-container">
            <marquee behavior="scroll" direction="left" onmouseover="this.stop();" onmouseout="this.start();">
                <span class="announcement-text">
                    <i class="fas fa-shopping-cart"></i> 
                    High-quality Fertilizers and Pesticides available! 
                    <i class="fas fa-leaf"></i> 
                    Special offers for registered farmers! 
                    <i class="fas fa-percentage"></i> 
                    Get 10% off on your first purchase!
                </span>
            </marquee>
        </div>
        <a href="agri_store.php" class="shop-now-btn">
            <span>Shop Now</span>
            <i class="fas fa-arrow-right"></i>
        </a>
    </div>

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
        <div class="main-content">
            <!-- Add Back Button -->
            <a href="farmer.php" class="back-button animate-fadeInUp">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>

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
                        <p><strong>Nitrogen (N):</strong> <?php echo isset($soil_data['nitrogen']) ? number_format($soil_data['nitrogen'], 2) : 'Not tested'; ?>%</p>
                        <p><strong>Phosphorus (P):</strong> <?php echo isset($soil_data['phosphorus']) ? number_format($soil_data['phosphorus'], 2) : 'Not tested'; ?>%</p>
                        <p><strong>Potassium (K):</strong> <?php echo isset($soil_data['potassium']) ? number_format($soil_data['potassium'], 2) : 'Not tested'; ?>%</p>
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

            <!-- Update Fertilizer Recommendations -->
            <div class="recommendation-card animate-fadeInUp" style="animation-delay: 0.3s;">
                <h2>
                    <div class="nutrient-icon">
                        <i class="fas fa-list"></i>
                    </div>
                    Recommended Fertilizer Application
                </h2>
                <table class="fertilizer-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Frequency</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($fertilizer_recommendations as $rec): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($rec['type']); ?></td>
                                <td><?php echo htmlspecialchars($rec['amount'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($rec['frequency'] ?? 'As needed'); ?></td>
                                <td><?php echo htmlspecialchars($rec['notes']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Add Pesticide Recommendations -->
            <div class="recommendation-card animate-fadeInUp" style="animation-delay: 0.4s;">
                <h2>
                    <div class="nutrient-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    Pest Management Recommendations
                </h2>
                <?php if (!empty($pesticide_recommendations)): ?>
                    <table class="fertilizer-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Product</th>
                                <th>Dosage</th>
                                <th>Frequency</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pesticide_recommendations as $rec): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($rec['type']); ?></td>
                                    <td><?php echo htmlspecialchars($rec['name']); ?></td>
                                    <td><?php echo htmlspecialchars($rec['dosage']); ?></td>
                                    <td><?php echo htmlspecialchars($rec['frequency']); ?></td>
                                    <td><?php echo htmlspecialchars($rec['notes']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if ($weather_data && isset($weather_data['main'])): ?>
                        <div class="alert-info" style="margin-top: 15px; padding: 10px; border-radius: 5px;">
                            <i class="fas fa-info-circle"></i>
                            <?php if ($weather_data['main']['humidity'] > 75): ?>
                                <strong>High Risk Alert:</strong> Current high humidity conditions (<?php echo $weather_data['main']['humidity']; ?>%) are favorable for pest development. Monitor your plants closely.
                            <?php else: ?>
                                <strong>Low Risk Alert:</strong> Current weather conditions are less favorable for pest development. Continue regular monitoring.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="alert-info">
                        <i class="fas fa-check-circle"></i>
                        No immediate pest control measures needed. Continue regular monitoring of your plants.
                    </p>
                <?php endif; ?>
                
                <div style="margin-top: 15px;">
                    <p><strong>Important Notes:</strong></p>
                    <ul style="list-style-type: none; padding-left: 0;">
                        <li><i class="fas fa-check text-success"></i> Always use protective equipment when applying pesticides</li>
                        <li><i class="fas fa-check text-success"></i> Follow recommended dosage strictly</li>
                        <li><i class="fas fa-check text-success"></i> Maintain proper waiting period before harvest</li>
                        <li><i class="fas fa-check text-success"></i> Prefer organic/biological control methods when possible</li>
                    </ul>
                </div>
            </div>

            <!-- Add Available Products -->
            <div class="recommendation-card animate-fadeInUp" style="animation-delay: 0.5s;">
                <h2>
                    <div class="nutrient-icon">
                        <i class="fas fa-shopping-basket"></i>
                    </div>
                    Available Products
                </h2>
                <div class="products-grid">
                    <?php while ($product = mysqli_fetch_assoc($products)): ?>
                        <div class="product-card">
                            <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                            <p><strong>Type:</strong> <?php echo ucfirst($product['type']); ?></p>
                            <p><strong>Price:</strong> ₹<?php echo number_format($product['price'], 2); ?></p>
                            <p><?php echo htmlspecialchars($product['description']); ?></p>
                            <p><strong>Available Stock:</strong> <?php echo $product['stock']; ?></p>
                            <button class="btn-primary" onclick="showPaymentModal(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['price']; ?>)">
                                Order Now
                            </button>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add this payment modal HTML before the closing body tag -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2><i class="fas fa-money-bill-wave"></i> UPI Payment</h2>
            <div class="payment-details">
                <p><strong>Product:</strong> <span id="modalProductName"></span></p>
                <p><strong>Amount:</strong> ₹<span id="modalPrice"></span></p>
            </div>
            <div class="upi-payment-section">
                <div class="upi-qr-container">
                    <img id="qrCodeImage" src="" alt="UPI QR Code" style="width: 200px; height: 200px;">
                    <p style="font-size: 14px; color: #666; margin-top: 10px;">Scan with any UPI app</p>
                </div>
                <div class="upi-details">
                    <p><strong>UPI ID:</strong> <span id="upiId"></span></p>
                    <button onclick="copyUpiId()" class="btn-primary">
                        <i class="fas fa-copy"></i> Copy UPI ID
                    </button>
                </div>
                <div class="payment-apps">
                    <p><strong>Pay using:</strong></p>
                    <div class="app-buttons">
                        <button onclick="openUPIApp('gpay')" class="upi-app-btn">
                            <i class="fab fa-google-pay" style="font-size: 24px; color: #1a73e8;"></i>
                            Google Pay
                        </button>
                        <button onclick="openUPIApp('phonepe')" class="upi-app-btn">
                            <i class="fas fa-mobile-alt" style="font-size: 24px; color: #5f259f;"></i>
                            PhonePe
                        </button>
                        <button onclick="openUPIApp('paytm')" class="upi-app-btn">
                            <i class="fas fa-wallet" style="font-size: 24px; color: #00baf2;"></i>
                            Paytm
                        </button>
                    </div>
                </div>
                <div style="margin-top: 15px; padding: 10px; background-color: #fff3e0; border-radius: 8px;">
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-info-circle" style="color: #ff9800; font-size: 20px;"></i>
                        <p style="margin: 0; color: #333;">This is a dummy payment for testing purposes.</p>
                    </div>
                </div>
                <form id="paymentForm" method="POST" style="margin-top: 20px;">
                    <input type="hidden" name="product_id" id="paymentProductId">
                    <input type="hidden" name="process_upi_payment" value="1">
                    <button type="submit" class="btn-primary" style="width: 100%;">
                        <i class="fas fa-check-circle"></i> Simulate Successful Payment
                    </button>
                </form>
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

            // Add pulse animation to shop now button
            const shopNowBtn = document.querySelector('.shop-now-btn');
            setInterval(() => {
                shopNowBtn.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    shopNowBtn.style.transform = 'scale(1)';
                }, 200);
            }, 3000);

            // Smooth scroll when clicking shop now
            shopNowBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const href = this.getAttribute('href');
                document.location.href = href;
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

        let currentProductId = null;

        function showPaymentModal(productId, productName, price) {
            currentProductId = productId;
            const modal = document.getElementById('paymentModal');
            
            // Set product details
            document.getElementById('modalProductName').textContent = productName;
            document.getElementById('modalPrice').textContent = price.toFixed(2);
            document.getElementById('paymentProductId').value = productId;
            
            // Generate UPI ID if not already set
            if (!document.getElementById('upiId').textContent) {
                document.getElementById('upiId').textContent = generateRandomUpiId();
            }
            
            // Generate QR code
            const upiId = document.getElementById('upiId').textContent;
            const qrCodeUrl = generateUpiQrCode(upiId, price, encodeURIComponent(productName));
            document.getElementById('qrCodeImage').src = qrCodeUrl;
            
            modal.style.display = 'block';
        }

        function generateRandomUpiId() {
            return 'farmer' + Math.floor(Math.random() * 9000 + 1000) + '@ybl';
        }

        function generateUpiQrCode(upiId, amount, productName) {
            const upiUrl = encodeURIComponent(`upi://pay?pa=${upiId}&pn=GrowGuide&tn=${productName}&am=${amount}&cu=INR`);
            return `https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=${upiUrl}`;
        }

        function openUPIApp(app) {
            const amount = document.getElementById('modalPrice').textContent;
            const upiId = document.getElementById('upiId').textContent;
            const productName = document.getElementById('modalProductName').textContent;
            
            let upiUrl = `upi://pay?pa=${upiId}&pn=GrowGuide&tn=${encodeURIComponent(productName)}&am=${amount}&cu=INR`;
            
            // Handle different UPI apps
            if (app === 'gpay') {
                window.location.href = `gpay://upi/pay?pa=${upiId}&pn=GrowGuide&tn=${encodeURIComponent(productName)}&am=${amount}&cu=INR`;
            } else if (app === 'phonepe') {
                window.location.href = `phonepe://pay?pa=${upiId}&pn=GrowGuide&tn=${encodeURIComponent(productName)}&am=${amount}&cu=INR`;
            } else if (app === 'paytm') {
                window.location.href = `paytm://pay?pa=${upiId}&pn=GrowGuide&tn=${encodeURIComponent(productName)}&am=${amount}&cu=INR`;
            } else {
                // Fallback to generic UPI intent
                window.location.href = upiUrl;
            }
        }

        function showSuccessNotification(message) {
            // Create a notification element
            const notification = document.createElement('div');
            notification.className = 'update-notification animate-fadeInUp';
            notification.innerHTML = `
                <div class="notification-icon" style="background-color: #4CAF50;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="notification-content">
                    <h3>Payment Successful!</h3>
                    <p>${message}</p>
                </div>
            `;
            
            // Add to document
            const container = document.querySelector('.main-content');
            container.insertBefore(notification, container.firstChild);
            
            // Auto-hide after 10 seconds
            setTimeout(() => {
                notification.style.transition = 'opacity 0.5s ease';
                notification.style.opacity = '0';
                setTimeout(() => notification.remove(), 500);
            }, 10000);
        }

        // Update the window.onload function
        window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('payment') === 'success') {
                const refNumber = urlParams.get('ref');
                showSuccessNotification(`Payment successful! Reference: ${refNumber}. Your order has been confirmed.`);
            }
        };
    </script>
</body>
</html>