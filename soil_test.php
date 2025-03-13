<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

// Initialize database connection
$dbConfig = new DatabaseConfig();
$conn = $dbConfig->getConnection();
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Check if user is logged in and is a farmer
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'farmer') {
    header("Location: login.php");
    exit();
}

// Get soil tests data from database
$soil_tests = [];
$query = "SELECT st.*, u.username as farmer_name 
          FROM soil_tests st 
          JOIN users u ON st.farmer_id = u.id 
          WHERE st.farmer_id = ? 
          ORDER BY st.test_date DESC";

$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $soil_tests[] = $row;
    }
    mysqli_free_result($result);
}

// Get farmers for dropdown
$farmers = [];
$query = "SELECT id, username FROM users WHERE id = ? AND role = 'farmer' AND status = 1";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['user_id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $farmers[] = $row;
    }
    mysqli_free_result($result);
}

// Form processing
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_soil_test'])) {
        // Validate input data
        if (empty($_POST['ph_level']) || 
            empty($_POST['nitrogen_content']) || 
            empty($_POST['phosphorus_content']) || 
            empty($_POST['potassium_content'])) {
            $message = 'All fields are required';
            error_log("Validation failed: Missing required fields");
        } else {
            $farmer_id = $_SESSION['user_id'];
            $ph_level = floatval($_POST['ph_level']);
            $nitrogen_content = floatval($_POST['nitrogen_content']);
            $phosphorus_content = floatval($_POST['phosphorus_content']);
            $potassium_content = floatval($_POST['potassium_content']);
            $test_date = date('Y-m-d');
            
            // Modified insert query to explicitly list columns
            $insert_query = "INSERT INTO soil_tests (farmer_id, ph_level, nitrogen_content, phosphorus_content, potassium_content, test_date) 
                            VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = mysqli_prepare($conn, $insert_query);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "idddds", $farmer_id, $ph_level, $nitrogen_content, $phosphorus_content, $potassium_content, $test_date);
                
                if (mysqli_stmt_execute($stmt)) {
                    $message = 'Soil test added successfully!';
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    $message = 'Error adding soil test: ' . mysqli_stmt_error($stmt);
                    error_log("Insert failed: " . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// Add helper functions at the top of the file
function getPHColor($ph) {
    if ($ph < 6.0) return '#ff6b6b';
    if ($ph > 7.5) return '#4d96ff';
    return '#69db7c';
}

function getPHStatus($ph) {
    if ($ph < 6.0) return 'Acidic';
    if ($ph > 7.5) return 'Alkaline';
    return 'Optimal';
}

// Add these helper functions at the top with the other functions
function getNitrogenStatus($value) {
    if ($value < 0.5) return ['Low', '#ff6b6b'];
    if ($value > 1.0) return ['High', '#4d96ff'];
    return ['Optimal', '#69db7c'];
}

function getPhosphorusStatus($value) {
    if ($value < 0.05) return ['Low', '#ff6b6b'];
    if ($value > 0.2) return ['High', '#4d96ff'];
    return ['Optimal', '#69db7c'];
}

function getPotassiumStatus($value) {
    if ($value < 1.0) return ['Low', '#ff6b6b'];
    if ($value > 2.0) return ['High', '#4d96ff'];
    return ['Optimal', '#69db7c'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Soil Tests</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary-color: #2D5A27;  /* Cardamom green */
            --primary-dark: #1A3A19;   /* Darker cardamom */
            --accent-color: #8B9D83;   /* Muted cardamom */
            --error-color: #dc3545;
            --success-color: #28a745;
            --button-color: #4A7A43;   /* Cardamom button */
            --button-hover: #3D6337;   /* Darker cardamom button */
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #2D5A27; /* Changed to green background */
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            position: relative;
            min-height: 100vh;
        }

        /* Updated overlay to be slightly darker for better readability */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(45, 90, 39, 0.8); /* Green tinted overlay */
            z-index: 0;
        }

        .content {
            position: relative; /* Added */
            z-index: 1; /* Added */
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
            background: rgba(255, 255, 255, 0.95);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .form-group {
            position: relative;
            margin-bottom: 40px; /* Increased to accommodate the info box */
            padding: 20px;
            border-radius: 15px;
            background: linear-gradient(145deg, #ffffff, #f3f3f3);
            border: 1px solid #e0e0e0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
            animation: slideIn 0.5s ease-out;
        }

        .form-group:hover {
            transform: translateY(-5px);
        }

        .form-group i {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 24px;
            color: var(--primary-color);
            animation: bounce 2s infinite;
        }

        .form-group label {
            display: block;
            margin-bottom: 12px;
            color: var(--primary-dark);
            font-weight: 600;
            font-size: 16px;
        }

        .form-group input, 
        .form-group select {
            width: 100%;
            padding: 15px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
            box-sizing: border-box;
            background: white;
            color: #333;
        }

        .form-group input:focus, 
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
        }

        /* Update test results styling */
        .test-results {
            margin-top: 40px;
        }

        .test-card {
            background: linear-gradient(145deg, #ffffff, #f8f9fa);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            border-left: 5px solid var(--primary-color);
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.5s ease-out;
        }

        .test-card h4 {
            color: var(--primary-dark);
            margin-top: 0;
            margin-bottom: 15px;
        }

        .test-card p {
            color: #666;
            margin: 8px 0;
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: linear-gradient(145deg, var(--success-color), #218838);
            color: white;
            border: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .error-message {
            color: var(--error-color);
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .form-group input:invalid,
        .form-group select:invalid {
            border-color: var(--error-color);
        }
        .admin-dashboard-link {
            display: flex;
            align-items: center;
            text-decoration: none;
            background-color: rgba(255, 255, 255, 0.9);
            padding: 12px 24px;
            border-radius: 8px;
            transition: all 0.3s ease;
            margin: 10px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .admin-dashboard-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .icon-container {
            margin-right: 12px;
        }

        .icon-container i {
            color: #2c3e50;
            font-size: 20px;
        }

        .text {
            color: #2c3e50;
            font-weight: 600;
            font-size: 16px;
        }

        /* Background image styling for the page/container */
        .dashboard-container {
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        /* Semi-transparent overlay */
        .dashboard-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.8); /* Adjust opacity here */
            z-index: 1;
        }

        /* Ensure content stays above the overlay */
        .dashboard-content {
            position: relative;
            z-index: 2;
        }

        /* Add submit button styling */
        button[type="submit"] {
            background-color: var(--button-color);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            display: block;
            margin: 20px auto;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            position: relative;
            overflow: hidden;
        }

        button[type="submit"]:hover {
            background-color: var(--button-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        button[type="submit"]::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }

        button[type="submit"]:hover::after {
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

        /* Add heading styles */
        h2 {
            color: var(--primary-dark);
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.5em;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }

        /* Add new icon animations */
        @keyframes bounce {
            0%, 100% { transform: translateY(-50%); }
            50% { transform: translateY(-60%); }
        }

        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translateX(-20px);
            }
            to { 
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes floatIcon {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* Add animated icons for test results */
        .test-card::before {
            content: '🌱';
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 24px;
            opacity: 0.2;
            animation: floatIcon 3s infinite ease-in-out;
        }

        .test-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Add animated value indicators */
        .value-indicator {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            margin-left: 10px;
            font-size: 0.9em;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .welcome-banner {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.95), rgba(248, 249, 250, 0.95));
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
            animation: slideDown 0.8s ease-out;
        }

        .welcome-message {
            color: var(--primary-dark);
            font-size: 2em;
            margin: 0;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .welcome-message i {
            font-size: 1.2em;
            animation: wave 2s infinite;
            color: var(--primary-color);
        }

        .farmer-name {
            color: var(--button-color);
            font-weight: bold;
            position: relative;
            display: inline-block;
            animation: highlight 3s infinite;
        }

        @keyframes wave {
            0%, 100% { transform: rotate(0deg); }
            25% { transform: rotate(20deg); }
            75% { transform: rotate(-15deg); }
        }

        @keyframes slideDown {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        @keyframes highlight {
            0%, 100% { color: var(--button-color); }
            50% { color: var(--primary-dark); }
        }

        .soil-analysis {
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 15px;
            margin-top: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .analysis-box, .recommendations {
            margin-bottom: 20px;
        }

        .solution {
            background: rgba(240, 240, 240, 0.8);
            border: 1px solid var(--primary-color);
            border-radius: 8px;
            padding: 10px;
            margin: 10px 0;
            transition: transform 0.3s;
        }

        @keyframes float {
            0% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
            100% { transform: translateY(0); }
        }

        .soil-details {
            margin-top: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 8px;
        }

        .soil-parameter {
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .soil-parameter h5 {
            color: var(--primary-dark);
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .recommendations-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }

        .recommendations-box h5 {
            color: var(--primary-dark);
            margin-top: 0;
        }

        .recommendations-box ul {
            margin: 0;
            padding-left: 20px;
        }

        .recommendations-box li {
            color: #666;
            margin-bottom: 5px;
        }

        .recommendation {
            font-style: italic;
            color: #666;
            margin-top: 5px;
        }

        .input-info {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid var(--primary-color);
            border-radius: 4px;
            font-size: 0.9em;
        }

        .input-info p {
            margin: 5px 0;
            color: #666;
        }

        .input-info strong {
            color: var(--primary-dark);
        }

        .input-info ul {
            margin: 10px 0;
            padding-left: 20px;
            color: #666;
        }

        .input-info li {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="content">
        <div class="welcome-banner">
            <h1 class="welcome-message">
                <i class="fas fa-seedling"></i>
                Welcome, <span class="farmer-name"><?php echo htmlspecialchars($farmers[0]['username']); ?></span>!
                <i class="fas fa-hand-sparkles"></i>
            </h1>
        </div>
        <div class="dashboard-container">
            <div class="dashboard-content">
                <a href="farmer.php" class="admin-dashboard-link">
                    <div class="icon-container">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <span class="text">BACK TO DASHBOARD</span>
                </a>
                <h2>Soil Test Analysis</h2>

                <div class="test-results">
                    <h3>Recent Soil Tests</h3>
                    <?php if (empty($soil_tests)): ?>
                        <p>No soil tests found.</p>
                    <?php else: ?>
                        <?php foreach ($soil_tests as $test): ?>
                            <div class="test-card">
                                <h4>
                                    <i class="fas fa-user-farmer" style="color: var(--primary-color);"></i>
                                    Farmer: <?php echo htmlspecialchars($test['farmer_name']); ?>
                                </h4>
                                <p>Test Date: <?php echo date('F j, Y', strtotime($test['test_date'])); ?></p>
                                
                                <div class="soil-details">
                                    <div class="soil-parameter">
                                        <h5>pH Level Analysis</h5>
                                        <p>Value: <?php echo $test['ph_level']; ?>
                                            <span class="value-indicator" style="background-color: <?php echo getPHColor($test['ph_level']); ?>">
                                                <?php echo getPHStatus($test['ph_level']); ?>
                                            </span>
                                        </p>
                                    </div>

                                    <div class="soil-parameter">
                                        <h5>NPK Analysis</h5>
                                        <?php 
                                            $n_status = getNitrogenStatus($test['nitrogen_content']);
                                            $p_status = getPhosphorusStatus($test['phosphorus_content']);
                                            $k_status = getPotassiumStatus($test['potassium_content']);
                                        ?>
                                        <p>Nitrogen: <?php echo $test['nitrogen_content']; ?>%
                                            <span class="value-indicator" style="background-color: <?php echo $n_status[1]; ?>">
                                                <?php echo $n_status[0]; ?>
                                            </span>
                                        </p>
                                        <p>Phosphorus: <?php echo $test['phosphorus_content']; ?>%
                                            <span class="value-indicator" style="background-color: <?php echo $p_status[1]; ?>">
                                                <?php echo $p_status[0]; ?>
                                            </span>
                                        </p>
                                        <p>Potassium: <?php echo $test['potassium_content']; ?>%
                                            <span class="value-indicator" style="background-color: <?php echo $k_status[1]; ?>">
                                                <?php echo $k_status[0]; ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>

                                <div class="recommendations-box">
                                    <h5>Solutions & Recommendations</h5>
                                    <ul>
                                        <?php if ($test['ph_level'] < 5.5): ?>
                                            <li>pH is too acidic - Add lime to increase pH level</li>
                                        <?php elseif ($test['ph_level'] > 6.5): ?>
                                            <li>pH is too alkaline - Add sulfur to decrease pH level</li>
                                        <?php endif; ?>
                                        
                                        <?php if ($n_status[0] === 'Low'): ?>
                                            <li>Add nitrogen-rich organic matter or compost</li>
                                        <?php elseif ($n_status[0] === 'High'): ?>
                                            <li>Reduce nitrogen application</li>
                                        <?php endif; ?>
                                        
                                        <?php if ($p_status[0] === 'Low'): ?>
                                            <li>Apply bone meal or rock phosphate</li>
                                        <?php elseif ($p_status[0] === 'High'): ?>
                                            <li>Reduce phosphorus application</li>
                                        <?php endif; ?>
                                        
                                        <?php if ($k_status[0] === 'Low'): ?>
                                            <li>Add wood ash or potassium-rich fertilizers</li>
                                        <?php elseif ($k_status[0] === 'High'): ?>
                                            <li>Reduce potassium application</li>
                                        <?php endif; ?>
                                        
                                        <?php if ($n_status[0] === 'Optimal' && $p_status[0] === 'Optimal' && 
                                                $k_status[0] === 'Optimal' && $test['ph_level'] >= 5.5 && 
                                                $test['ph_level'] <= 6.5): ?>
                                            <li>All soil parameters are optimal. Maintain current practices.</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="soil-analysis">
                    <h3>Soil Analysis & Recommendations</h3>
                    <div class="analysis-box">
                        <h4>Ideal Soil Conditions for Cardamom</h4>
                        <ul>
                            <li><strong>pH Level:</strong> 5.5 - 6.5 (Slightly Acidic)</li>
                            <li><strong>Nitrogen (N) %:</strong> 0.5% - 1.0%</li>
                            <li><strong>Phosphorus (P) %:</strong> 0.05% - 0.2%</li>
                            <li><strong>Potassium (K) %:</strong> 1.0% - 2.0%</li>
                        </ul>
                    </div>
                    <div class="recommendations">
                        <h4>Solutions for Cardamom Plantation</h4>
                        <div class="solution" style="animation: float 3s infinite;">
                            <h5>pH Level Adjustment</h5>
                            <p>Apply lime to raise pH if < 5.5.</p>
                        </div>
                        <div class="solution" style="animation: float 3s infinite;">
                            <h5>Nitrogen Adjustment</h5>
                            <p>Apply compost for low nitrogen.</p>
                        </div>
                        <div class="solution" style="animation: float 3s infinite;">
                            <h5>Phosphorus Adjustment</h5>
                            <p>Add bone meal for low phosphorus.</p>
                        </div>
                        <div class="solution" style="animation: float 3s infinite;">
                            <h5>Potassium Adjustment</h5>
                            <p>Apply wood ash for low potassium.</p>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>

                <form method="POST" onsubmit="return validateForm()" novalidate>
                    <div class="form-grid">
                        <input type="hidden" name="farmer_id" value="<?php echo $_SESSION['user_id']; ?>">
                        <div class="form-group">
                            <i class="fas fa-vial"></i>
                            <label>pH Level</label>
                            <input type="number" name="ph_level" id="ph_level" step="0.1" required min="0" max="14">
                            <span class="error-message">Please enter a valid pH level (0-14)</span>
                            <div class="input-info">
                                <p><strong>Optimal Range:</strong> 5.5 - 6.5</p>
                                <p><strong>About:</strong> pH measures soil acidity or alkalinity. Cardamom prefers slightly acidic soil.</p>
                                <ul>
                                    <li>Below 5.5: Too acidic - Add lime</li>
                                    <li>5.5-6.5: Optimal range</li>
                                    <li>Above 6.5: Too alkaline - Add sulfur</li>
                                </ul>
                            </div>
                        </div>
                        <div class="form-group">
                            <i class="fas fa-leaf"></i>
                            <label>Nitrogen (N) %</label>
                            <input type="number" name="nitrogen_content" id="nitrogen_content" step="0.01" required min="0">
                            <span class="error-message">Please enter a valid nitrogen percentage</span>
                            <div class="input-info">
                                <p><strong>Optimal Range:</strong> 0.5% - 1.0%</p>
                                <p><strong>About:</strong> Nitrogen is essential for leaf growth and chlorophyll production.</p>
                                <ul>
                                    <li>Below 0.5%: Add nitrogen-rich fertilizers or compost</li>
                                    <li>0.5-1.0%: Optimal range</li>
                                    <li>Above 1.0%: Reduce nitrogen application</li>
                                </ul>
                            </div>
                        </div>
                        <div class="form-group">
                            <i class="fas fa-seedling"></i>
                            <label>Phosphorus (P) %</label>
                            <input type="number" name="phosphorus_content" id="phosphorus_content" step="0.01" required min="0">
                            <span class="error-message">Please enter a valid phosphorus percentage</span>
                            <div class="input-info">
                                <p><strong>Optimal Range:</strong> 0.05% - 0.2%</p>
                                <p><strong>About:</strong> Phosphorus promotes root development and flowering.</p>
                                <ul>
                                    <li>Below 0.05%: Add bone meal or rock phosphate</li>
                                    <li>0.05-0.2%: Optimal range</li>
                                    <li>Above 0.2%: Reduce phosphorus application</li>
                                </ul>
                            </div>
                        </div>
                        <div class="form-group">
                            <i class="fas fa-flask"></i>
                            <label>Potassium (K) %</label>
                            <input type="number" name="potassium_content" id="potassium_content" step="0.01" required min="0">
                            <span class="error-message">Please enter a valid potassium percentage</span>
                            <div class="input-info">
                                <p><strong>Optimal Range:</strong> 1.0% - 2.0%</p>
                                <p><strong>About:</strong> Potassium enhances disease resistance and fruit quality.</p>
                                <ul>
                                    <li>Below 1.0%: Add wood ash or potassium fertilizers</li>
                                    <li>1.0-2.0%: Optimal range</li>
                                    <li>Above 2.0%: Reduce potassium application</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="add_soil_test">Add Soil Test</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function validateForm() {
            const fields = [
                { id: 'farmer_id', message: 'Please select a farmer' },
                { id: 'ph_level', message: 'Please enter a valid pH level (0-14)' },
                { id: 'nitrogen_content', message: 'Please enter a valid nitrogen percentage' },
                { id: 'phosphorus_content', message: 'Please enter a valid phosphorus percentage' },
                { id: 'potassium_content', message: 'Please enter a valid potassium percentage' }
            ];

            let isValid = true;

            // Hide all error messages first
            document.querySelectorAll('.error-message').forEach(error => {
                error.style.display = 'none';
            });

            // Validate each field
            fields.forEach(field => {
                const element = document.getElementById(field.id);
                const errorElement = element.nextElementSibling;

                if (!element.value) {
                    errorElement.style.display = 'block';
                    isValid = false;
                } else if (field.id === 'ph_level') {
                    const value = parseFloat(element.value);
                    if (value < 0 || value > 14) {
                        errorElement.style.display = 'block';
                        isValid = false;
                    }
                } else if (['nitrogen_content', 'phosphorus_content', 'potassium_content'].includes(field.id)) {
                    const value = parseFloat(element.value);
                    if (value < 0) {
                        errorElement.style.display = 'block';
                        isValid = false;
                    }
                }
            });

            return isValid;
        }

        // Add real-time validation
        document.querySelectorAll('input, select').forEach(element => {
            element.addEventListener('blur', function() {
                const errorElement = this.nextElementSibling;
                
                if (!this.value) {
                    errorElement.style.display = 'block';
                } else {
                    errorElement.style.display = 'none';
                }

                if (this.id === 'ph_level' && this.value) {
                    const value = parseFloat(this.value);
                    if (value < 0 || value > 14) {
                        errorElement.style.display = 'block';
                    }
                }

                if (['nitrogen_content', 'phosphorus_content', 'potassium_content'].includes(this.id) && this.value) {
                    const value = parseFloat(this.value);
                    if (value < 0) {
                        errorElement.style.display = 'block';
                    }
                }
            });
        });
    </script>
</body>
</html> 