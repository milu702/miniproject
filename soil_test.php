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
    <title>GrowGuide - Soil Test</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2D5A27;
            --primary-dark: #1A3A19;
            --accent-color: #8B9D83;
            --text-color: #333333;
            --bg-color: #f5f5f5;
            --sidebar-width: 250px;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-color);
        }

        .layout-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color), var(--primary-dark));
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .farmer-profile {
            padding: 20px;
            text-align: center;
        }

        .farmer-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
        }

        .nav-menu {
            padding: 20px 0;
        }

        .nav-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            transition: 0.3s;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.1);
        }

        .nav-item.active {
            background: rgba(255,255,255,0.2);
        }

        .nav-item i {
            margin-right: 10px;
            width: 20px;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
        }

        .content-grid {
            display: flex;
            flex-direction: column;
            gap: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .soil-test-form {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .input-group {
            margin-bottom: 15px;
        }

        .input-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-color);
            font-weight: 500;
        }

        .input-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .results-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-height: 80vh;
            overflow-y: auto;
        }

        .test-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .test-header {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .test-date {
            color: #666;
            font-size: 0.9em;
        }

        .test-date i {
            margin-right: 5px;
            color: var(--primary-color);
        }

        .parameter-item {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .parameter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .parameter-label {
            font-weight: 500;
            color: var(--text-color);
        }

        .parameter-value {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            color: white;
            font-size: 0.8em;
            font-weight: 500;
        }

        .recommendation {
            margin-top: 10px;
            padding: 10px;
            background: white;
            border-radius: 6px;
            font-size: 0.9em;
        }

        .recommendation i {
            margin-right: 5px;
        }

        .recommendation ul {
            margin: 5px 0 0 20px;
            padding: 0;
        }

        .recommendation li {
            margin: 5px 0;
            color: #666;
        }

        .fa-check-circle {
            color: #28a745;
        }

        .fa-info-circle {
            color: #17a2b8;
        }

        .fa-exclamation-circle {
            color: #dc3545;
        }

        .optimal {
            color: #28a745;
        }

        .low, .acidic {
            color: #dc3545;
        }

        .high, .alkaline {
            color: #17a2b8;
        }

        .submit-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
        }

        .submit-btn:hover {
            background: var(--primary-dark);
        }

        /* Add responsive design */
        @media (max-width: 1024px) {
            .content-grid {
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
            .nav-item span {
                display: none;
            }
            .sidebar-header h2, 
            .farmer-profile h3, 
            .farmer-profile p {
                display: none;
            }
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper input {
            padding-right: 35px;
            transition: border-color 0.3s ease;
        }

        .input-icon {
            position: absolute;
            right: 10px;
            display: none;
        }

        .success-icon {
            color: #28a745;
            display: none;
        }

        .error-icon {
            color: #dc3545;
            display: none;
        }

        .input-group.success .success-icon {
            display: block;
        }

        .input-group.error .error-icon {
            display: block;
        }

        .input-group.success input {
            border-color: #28a745;
        }

        .input-group.error input {
            border-color: #dc3545;
        }

        .error-message {
            color: #dc3545;
            font-size: 0.8em;
            margin-top: 5px;
            display: none;
            animation: slideDown 0.3s ease-out;
        }

        .input-group.error .error-message {
            display: block;
        }

        .input-info {
            color: #666;
            font-size: 0.8em;
            margin-top: 5px;
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease-out;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        .submit-btn {
            transition: transform 0.3s ease, background-color 0.3s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
        }

        .input-group {
            transition: all 0.3s ease;
        }

        .input-group:hover {
            transform: translateY(-2px);
        }

        .input-wrapper input:focus {
            box-shadow: 0 0 0 3px rgba(45, 90, 39, 0.2);
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .print-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .print-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .table-responsive {
            overflow-x: auto;
            margin-top: 20px;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .results-table th,
        .results-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .results-table th {
            background: var(--primary-color);
            color: white;
            font-weight: 500;
        }

        .results-table tr:hover {
            background-color: #f8f9fa;
        }

        .value-with-status {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            color: white;
            font-size: 0.8em;
            font-weight: 500;
            white-space: nowrap;
        }

        @media print {
            body * {
                visibility: hidden;
            }
            #printableArea, #printableArea * {
                visibility: visible;
            }
            #printableArea {
                position: absolute;
                left: 0;
                top: 0;
            }
            .sidebar, .soil-test-form {
                display: none;
            }
            .status-badge {
                border: 1px solid #000;
            }
            .results-table th {
                background-color: #f0f0f0 !important;
                color: black !important;
            }
        }

        .test-result-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .test-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            background: var(--primary-color);
            color: white;
        }

        .test-info h4 {
            margin: 0;
            font-size: 1.1em;
        }

        .farmer-name {
            margin: 5px 0 0;
            font-size: 0.9em;
            opacity: 0.9;
        }

        .print-single-btn {
            background: white;
            color: var(--primary-color);
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
            transition: all 0.3s ease;
        }

        .print-single-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        @media print {
            .test-result-card {
                page-break-inside: avoid;
            }
            
            .test-card-header {
                background-color: #f0f0f0 !important;
                color: black !important;
            }
            
            .print-single-btn {
                display: none;
            }
        }

        .results-table th {
            cursor: pointer;
            position: relative;
            padding-right: 20px;
        }

        .results-table th i.fa-sort {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.3;
        }

        .results-table th:hover i.fa-sort {
            opacity: 1;
        }

        .print-btn-small {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .print-btn-small:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .action-btn {
            margin: 0 2px;
        }

        .value-with-status {
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        @media print {
            .action-btn {
                display: none;
            }
            
            .results-table th i.fa-sort {
                display: none;
            }
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
            <div class="farmer-profile">
                <div class="farmer-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h3><?php echo htmlspecialchars($farmers[0]['username']); ?></h3>
                <p>Cardamom Farmer</p>
            </div>
            <nav class="nav-menu">
                <a href="farmer.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="soil_test.php" class="nav-item active">
                    <i class="fas fa-flask"></i>
                    <span>Soil Test</span>
                </a>
                <a href="fertilizerrrr.php" class="nav-item">
                    <i class="fas fa-leaf"></i>
                    <span>Fertilizer Guide</span>
                </a>
                <a href="farm_analysis.php" class="nav-item">
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
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <a href="logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="content-grid">
                <!-- Results Section (Now First) -->
                <div class="results-section">
                    <div class="results-header">
                        <h3><i class="fas fa-history"></i> Soil Test History</h3>
                        <button onclick="printResults()" class="print-btn">
                            <i class="fas fa-print"></i> Print Results
                        </button>
                    </div>

                    <div class="table-responsive" id="printableArea">
                        <table class="results-table">
                            <thead>
                                <tr>
                                    <th onclick="sortTable(0)">Date <i class="fas fa-sort"></i></th>
                                    <th onclick="sortTable(1)">Farmer Name <i class="fas fa-sort"></i></th>
                                    <th onclick="sortTable(2)">pH Level <i class="fas fa-sort"></i></th>
                                    <th onclick="sortTable(3)">Nitrogen (%) <i class="fas fa-sort"></i></th>
                                    <th onclick="sortTable(4)">Phosphorus (%) <i class="fas fa-sort"></i></th>
                                    <th onclick="sortTable(5)">Potassium (%) <i class="fas fa-sort"></i></th>
                                    <th onclick="sortTable(6)">Overall Status <i class="fas fa-sort"></i></th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($soil_tests as $test): ?>
                                    <tr>
                                        <td><?php echo date('Y-m-d', strtotime($test['test_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($test['farmer_name']); ?></td>
                                        <td>
                                            <div class="value-with-status">
                                                <?php echo $test['ph_level']; ?>
                                                <span class="status-badge" style="background-color: <?php echo getPHColor($test['ph_level']); ?>">
                                                    <?php echo getPHStatus($test['ph_level']); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php $n_status = getNitrogenStatus($test['nitrogen_content']); ?>
                                            <div class="value-with-status">
                                                <?php echo $test['nitrogen_content']; ?>%
                                                <span class="status-badge" style="background-color: <?php echo $n_status[1]; ?>">
                                                    <?php echo $n_status[0]; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php $p_status = getPhosphorusStatus($test['phosphorus_content']); ?>
                                            <div class="value-with-status">
                                                <?php echo $test['phosphorus_content']; ?>%
                                                <span class="status-badge" style="background-color: <?php echo $p_status[1]; ?>">
                                                    <?php echo $p_status[0]; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php $k_status = getPotassiumStatus($test['potassium_content']); ?>
                                            <div class="value-with-status">
                                                <?php echo $test['potassium_content']; ?>%
                                                <span class="status-badge" style="background-color: <?php echo $k_status[1]; ?>">
                                                    <?php echo $k_status[0]; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $optimal_count = 0;
                                            if ($test['ph_level'] >= 5.5 && $test['ph_level'] <= 6.5) $optimal_count++;
                                            if ($test['nitrogen_content'] >= 0.5 && $test['nitrogen_content'] <= 1.0) $optimal_count++;
                                            if ($test['phosphorus_content'] >= 0.05 && $test['phosphorus_content'] <= 0.2) $optimal_count++;
                                            if ($test['potassium_content'] >= 1.0 && $test['potassium_content'] <= 2.0) $optimal_count++;
                                            
                                            $status_color = $optimal_count == 4 ? '#28a745' : 
                                                          ($optimal_count >= 2 ? '#ffc107' : '#dc3545');
                                            $status_text = $optimal_count == 4 ? 'Excellent' : 
                                                         ($optimal_count >= 2 ? 'Fair' : 'Poor');
                                            ?>
                                            <span class="status-badge" style="background-color: <?php echo $status_color; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button onclick="printSingleResult(<?php echo strtotime($test['test_date']); ?>)" 
                                                    class="action-btn print-btn-small" 
                                                    title="Print this result">
                                                <i class="fas fa-print"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Soil Test Form Section (Now Second) -->
                <div class="soil-test-form">
                    <h2><i class="fas fa-flask"></i> Soil Test Analysis</h2>
                    <?php if ($message): ?>
                        <div class="alert <?php echo strpos($message, 'success') !== false ? 'alert-success' : 'alert-error'; ?>">
                            <i class="fas <?php echo strpos($message, 'success') !== false ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
                            <?php echo $message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="soilTestForm" onsubmit="return validateSoilTestForm()">
                        <div class="form-grid">
                            <div class="input-group">
                                <label for="ph_level">
                                    <i class="fas fa-vial"></i> pH Level
                                </label>
                                <div class="input-wrapper">
                                    <input type="number" 
                                           id="ph_level" 
                                           name="ph_level" 
                                           step="0.1" 
                                           placeholder="Enter pH level"
                                           data-min="0"
                                           data-max="14">
                                    <div class="input-icon">
                                        <i class="fas fa-check-circle success-icon"></i>
                                        <i class="fas fa-exclamation-circle error-icon"></i>
                                    </div>
                                </div>
                                <small class="input-info">Optimal range: 5.5 - 6.5</small>
                                <div class="error-message"></div>
                            </div>

                            <div class="input-group">
                                <label for="nitrogen_content">
                                    <i class="fas fa-leaf"></i> Nitrogen (%)
                                </label>
                                <div class="input-wrapper">
                                    <input type="number" 
                                           id="nitrogen_content" 
                                           name="nitrogen_content" 
                                           step="0.01"
                                           placeholder="Enter nitrogen content"
                                           data-min="0"
                                           data-max="5">
                                    <div class="input-icon">
                                        <i class="fas fa-check-circle success-icon"></i>
                                        <i class="fas fa-exclamation-circle error-icon"></i>
                                    </div>
                                </div>
                                <small class="input-info">Optimal range: 0.5% - 1.0%</small>
                                <div class="error-message"></div>
                            </div>

                            <div class="input-group">
                                <label for="phosphorus_content">
                                    <i class="fas fa-seedling"></i> Phosphorus (%)
                                </label>
                                <div class="input-wrapper">
                                    <input type="number" 
                                           id="phosphorus_content" 
                                           name="phosphorus_content" 
                                           step="0.01"
                                           placeholder="Enter phosphorus content"
                                           data-min="0"
                                           data-max="1">
                                    <div class="input-icon">
                                        <i class="fas fa-check-circle success-icon"></i>
                                        <i class="fas fa-exclamation-circle error-icon"></i>
                                    </div>
                                </div>
                                <small class="input-info">Optimal range: 0.05% - 0.2%</small>
                                <div class="error-message"></div>
                            </div>

                            <div class="input-group">
                                <label for="potassium_content">
                                    <i class="fas fa-flask"></i> Potassium (%)
                                </label>
                                <div class="input-wrapper">
                                    <input type="number" 
                                           id="potassium_content" 
                                           name="potassium_content" 
                                           step="0.01"
                                           placeholder="Enter potassium content"
                                           data-min="0"
                                           data-max="5">
                                    <div class="input-icon">
                                        <i class="fas fa-check-circle success-icon"></i>
                                        <i class="fas fa-exclamation-circle error-icon"></i>
                                    </div>
                                </div>
                                <small class="input-info">Optimal range: 1.0% - 2.0%</small>
                                <div class="error-message"></div>
                            </div>
                        </div>
                        <button type="submit" name="add_soil_test" class="submit-btn">
                            <i class="fas fa-save"></i> Submit Soil Test
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const validateSoilTestForm = () => {
            let isValid = true;
            const inputs = {
                ph_level: {
                    min: 0,
                    max: 14,
                    name: 'pH level',
                    optimal: {min: 5.5, max: 6.5}
                },
                nitrogen_content: {
                    min: 0,
                    max: 5,
                    name: 'Nitrogen content',
                    optimal: {min: 0.5, max: 1.0}
                },
                phosphorus_content: {
                    min: 0,
                    max: 1,
                    name: 'Phosphorus content',
                    optimal: {min: 0.05, max: 0.2}
                },
                potassium_content: {
                    min: 0,
                    max: 5,
                    name: 'Potassium content',
                    optimal: {min: 1.0, max: 2.0}
                }
            };

            // Reset all fields
            Object.keys(inputs).forEach(inputId => {
                const inputGroup = document.getElementById(inputId).closest('.input-group');
                inputGroup.classList.remove('success', 'error');
            });

            // Validate each field
            Object.entries(inputs).forEach(([inputId, config]) => {
                const input = document.getElementById(inputId);
                const value = parseFloat(input.value);
                const inputGroup = input.closest('.input-group');
                const errorDiv = inputGroup.querySelector('.error-message');

                if (!input.value) {
                    isValid = false;
                    inputGroup.classList.add('error');
                    errorDiv.textContent = `${config.name} is required`;
                    shakeElement(inputGroup);
                } else if (isNaN(value) || value < config.min || value > config.max) {
                    isValid = false;
                    inputGroup.classList.add('error');
                    errorDiv.textContent = `${config.name} must be between ${config.min} and ${config.max}`;
                    shakeElement(inputGroup);
                } else {
                    inputGroup.classList.add('success');
                    // Add warning if outside optimal range
                    if (value < config.optimal.min || value > config.optimal.max) {
                        errorDiv.textContent = `Warning: ${config.name} is outside optimal range (${config.optimal.min} - ${config.optimal.max})`;
                        errorDiv.style.color = '#856404';
                        errorDiv.style.display = 'block';
                    }
                }
            });

            return isValid;
        };

        const shakeElement = (element) => {
            element.style.animation = 'none';
            element.offsetHeight; // Trigger reflow
            element.style.animation = 'shake 0.5s ease-in-out';
        };

        // Add real-time validation
        document.querySelectorAll('#soilTestForm input').forEach(input => {
            input.addEventListener('input', () => {
                const inputGroup = input.closest('.input-group');
                const errorDiv = inputGroup.querySelector('.error-message');
                
                if (!input.value) {
                    inputGroup.classList.remove('success');
                    inputGroup.classList.add('error');
                    errorDiv.textContent = 'This field is required';
                } else {
                    inputGroup.classList.remove('error');
                    inputGroup.classList.add('success');
                    errorDiv.style.display = 'none';
                }
            });
        });

        // Add this CSS animation
        document.head.insertAdjacentHTML('beforeend', `
            <style>
                @keyframes shake {
                    0%, 100% { transform: translateX(0); }
                    20%, 60% { transform: translateX(-5px); }
                    40%, 80% { transform: translateX(5px); }
                }
            </style>
        `);

        function sortTable(column) {
            const table = document.querySelector('.results-table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            const sortedRows = rows.sort((a, b) => {
                let aCol = a.cells[column].textContent.trim();
                let bCol = b.cells[column].textContent.trim();
                
                // Remove % symbol and status text for numeric columns
                if (column >= 2 && column <= 5) {
                    aCol = parseFloat(aCol.replace('%', ''));
                    bCol = parseFloat(bCol.replace('%', ''));
                } else if (column === 0) {
                    // Sort dates
                    return new Date(aCol) - new Date(bCol);
                } else {
                    // Sort strings
                    return aCol.localeCompare(bCol);
                }
                
                return aCol - bCol;
            });
            
            // Clear the table
            while (tbody.firstChild) {
                tbody.removeChild(tbody.firstChild);
            }
            
            // Add sorted rows
            sortedRows.forEach(row => tbody.appendChild(row));
        }

        function printSingleResult(timestamp) {
            const row = document.querySelector(`tr[data-timestamp="${timestamp}"]`);
            const table = document.querySelector('.results-table').cloneNode(true);
            const tbody = table.querySelector('tbody');
            
            // Clear table body and add only the selected row
            tbody.innerHTML = '';
            tbody.appendChild(row.cloneNode(true));
            
            const printContent = `
                <div style="padding: 20px;">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <h2>Soil Test Report</h2>
                        <p>Generated on: ${new Date().toLocaleDateString()}</p>
                    </div>
                    ${table.outerHTML}
                </div>
            `;
            
            const printWindow = window.open('', '_blank');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.print();
        }

        function printResults() {
            window.print();
        }
    </script>
</body>
</html>