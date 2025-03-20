<?php
session_start();

// Modify the authentication check to be more specific
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    session_unset();     // Clear all session variables
    session_destroy();   // Destroy the session
    header("Location: login.php");
    exit();
}

// Add session timeout check (e.g., 30 minutes)
$timeout = 1800; // 30 minutes in seconds
if (time() - $_SESSION['last_activity'] > $timeout) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time(); // Update last activity time stamp

// After successful login, check if there's a stored URL to redirect to
if (isset($_SESSION['redirect_url'])) {
    $redirect_url = $_SESSION['redirect_url'];
    unset($_SESSION['redirect_url']); // Clear the stored URL
    header("Location: " . $redirect_url);
    exit();
}

// Add these headers to prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

// Add database connection
$conn = mysqli_connect("localhost", "root", "", "growguide");

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Add any PHP logic here
$current_page = basename($_SERVER['PHP_SELF']);

// Sample data - you can replace with database queries
$total_farmers = 3; // Update this with the actual count from the database
$total_land = 0.00;
$total_varieties = 0;
$total_employees = 1;
$total_soil_tests = 0;

// Add this query to count farmers
$farmers_count_query = "SELECT COUNT(*) as total_farmers FROM users WHERE role = 'farmer' AND status = 1";
$farmers_count_result = mysqli_query($conn, $farmers_count_query);
$total_farmers = mysqli_fetch_assoc($farmers_count_result)['total_farmers'];

// Get dashboard data with soil tests
$dashboard_data = [];
$query = "SELECT 
            u.username as farmer_name,
            u.id as farmer_id,
            COUNT(st.id) as total_soil_tests,
            MAX(st.test_date) as latest_test_date,
            st.ph_level as latest_ph,
            st.nitrogen as latest_n,
            st.phosphorus as latest_p,
            st.potassium as latest_k
          FROM users u
          LEFT JOIN soil_tests st ON u.id = st.farmer_id
          WHERE u.role = 'farmer' AND u.status = 1
          GROUP BY u.id, u.username
          ORDER BY latest_test_date DESC";

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $dashboard_data[] = $row;
    }
    mysqli_free_result($result);
}

// Add this query to fetch recent soil tests
$recent_soil_tests = [];
$query = "SELECT st.*, u.username as farmer_name 
          FROM soil_tests st 
          JOIN users u ON st.farmer_id = u.id 
          ORDER BY st.test_date DESC 
          LIMIT 5"; // Show last 5 tests

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $recent_soil_tests[] = $row;
    }
    mysqli_free_result($result);
}

// Add this query near the top PHP section where other queries are
$farmers_query = "SELECT id, username, email, phone, status, created_at 
                 FROM users 
                 WHERE role = 'farmer' 
                 ORDER BY created_at DESC";
$farmers_result = mysqli_query($conn, $farmers_query);
$farmers_list = [];
if ($farmers_result) {
    while ($row = mysqli_fetch_assoc($farmers_result)) {
        $farmers_list[] = $row;
    }
    mysqli_free_result($farmers_result);
}

// Modify the soil tests query to get all results with LIMIT
$soil_tests_by_farmer = mysqli_query($conn, "
    SELECT 
        u.id as farmer_id,
        u.username as farmer_name,
        COUNT(st.test_id) as test_count,
        MAX(st.test_date) as latest_test_date,
        AVG(st.ph_level) as avg_ph,
        AVG(st.nitrogen_content) as avg_n,
        AVG(st.phosphorus_content) as avg_p,
        AVG(st.potassium_content) as avg_k
    FROM users u
    LEFT JOIN soil_tests st ON u.id = st.farmer_id
    WHERE u.role = 'farmer' AND u.status = 1
    GROUP BY u.id, u.username
    ORDER BY latest_test_date DESC
");

// Add error handling
if (!$soil_tests_by_farmer) {
    die("Query failed: " . mysqli_error($conn));
}

// Add these queries near the top PHP section
// Query for soil tests by week
$soil_tests_by_week_query = "
    SELECT 
        WEEK(test_date) as week_number,
        COUNT(*) as test_count
    FROM soil_tests
    WHERE test_date >= DATE_SUB(NOW(), INTERVAL 8 WEEK)
    GROUP BY WEEK(test_date)
    ORDER BY week_number ASC";

$soil_tests_by_week_result = mysqli_query($conn, $soil_tests_by_week_query);
$weekly_tests = array_fill(0, 8, 0); // Initialize array with zeros for 8 weeks

while ($row = mysqli_fetch_assoc($soil_tests_by_week_result)) {
    $week_index = $row['week_number'] % 8; // Get relative week index
    $weekly_tests[$week_index] = $row['test_count'];
}

// Query for new farmers by status
$new_farmers_query = "
    SELECT 
        status,
        COUNT(*) as farmer_count
    FROM users
    WHERE role = 'farmer'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY status";

$new_farmers_result = mysqli_query($conn, $new_farmers_query);
$farmer_status = [
    'active' => 0,
    'inactive' => 0
];

while ($row = mysqli_fetch_assoc($new_farmers_result)) {
    $status = $row['status'] ? 'active' : 'inactive';
    $farmer_status[$status] = $row['farmer_count'];
}

// Add this query near the top PHP section where other queries are
$fertilizer_recommendations_query = "
    SELECT 
        u.username as farmer_name,
        st.test_date,
        st.ph_level,
        st.nitrogen_content,
        st.phosphorus_content,
        st.potassium_content,
        CASE
            WHEN st.nitrogen_content < 0.5 THEN 'Urea (46-0-0)'
            ELSE NULL
        END as n_recommendation,
        CASE
            WHEN st.phosphorus_content < 0.05 THEN 'Single Super Phosphate'
            ELSE NULL
        END as p_recommendation,
        CASE
            WHEN st.potassium_content < 1.0 THEN 'Muriate of Potash'
            ELSE NULL
        END as k_recommendation
    FROM soil_tests st
    JOIN users u ON st.farmer_id = u.id
    WHERE st.test_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY st.test_date DESC
    LIMIT 10";

$fertilizer_result = mysqli_query($conn, $fertilizer_recommendations_query);
$fertilizer_data = [];
while ($row = mysqli_fetch_assoc($fertilizer_result)) {
    $fertilizer_data[] = $row;
}

// Add this query near the top PHP section
$fertilizer_trends_query = "
    SELECT 
        DATE_FORMAT(test_date, '%Y-%m') as month,
        AVG(nitrogen_content) as avg_nitrogen,
        AVG(phosphorus_content) as avg_phosphorus,
        AVG(potassium_content) as avg_potassium
    FROM soil_tests
    WHERE test_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(test_date, '%Y-%m')
    ORDER BY month ASC";

$fertilizer_trends_result = mysqli_query($conn, $fertilizer_trends_query);
$fertilizer_trends = [
    'labels' => [],
    'nitrogen' => [],
    'phosphorus' => [],
    'potassium' => []
];

while ($row = mysqli_fetch_assoc($fertilizer_trends_result)) {
    $fertilizer_trends['labels'][] = date('M Y', strtotime($row['month']));
    $fertilizer_trends['nitrogen'][] = round($row['avg_nitrogen'], 2);
    $fertilizer_trends['phosphorus'][] = round($row['avg_phosphorus'], 2);
    $fertilizer_trends['potassium'][] = round($row['avg_potassium'], 2);
}

// Add near the top with other database queries
$pending_queries_query = "SELECT eq.*, u.username as employee_name 
                         FROM employee_queries eq
                         JOIN users u ON eq.employee_id = u.id
                         ORDER BY eq.created_at DESC";
$pending_queries = mysqli_query($conn, $pending_queries_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Cardamom Plantation Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Update the color scheme to green theme */
        :root {
            --primary-color: #1b5e20;      /* Dark green */
            --secondary-color: #2e7d32;     /* Medium green */
            --accent-color: #43a047;        /* Light green */
            --background-color: #f1f8e9;    /* Very light green background */
            --card-color: #ffffff;
            --text-primary: #1b5e20;        /* Dark green text */
            --text-secondary: #33691e;      /* Medium green text */
            --success-color: #43a047;       /* Success green */
            --shadow: 0 4px 6px rgba(46, 125, 50, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        body {
            background-color: var(--background-color);
            color: var(--text-primary);
        }

        /* Update Sidebar Styles */
        .sidebar {
            width: 280px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            box-shadow: var(--shadow);
            color: white;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            z-index: 1000;
            transition: all 0.3s ease;
        }

        .sidebar.collapsed {
            width: 80px;
        }

        .logo-section {
            min-height: 80px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-bottom: 1px solid var(--accent-color);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo-section h1 {
            font-size: 1.8rem;
            white-space: nowrap;
        }

        .logo-section i {
            font-size: 1.8rem;
            animation: pulse 2s infinite;
        }

        .toggle-btn {
            position: absolute;
            right: -12px;
            top: 30px;
            background: var(--accent-color);
            border: none;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .menu-section {
            flex: 1;
            overflow-y: auto;
            padding: 20px 0;
            /* Add custom scrollbar styling */
            scrollbar-width: thin;
            scrollbar-color: var(--accent-color) transparent;
        }

        /* Custom scrollbar for Chrome/Safari/Edge */
        .menu-section::-webkit-scrollbar {
            width: 6px;
        }

        .menu-section::-webkit-scrollbar-track {
            background: transparent;
        }

        .menu-section::-webkit-scrollbar-thumb {
            background-color: var(--accent-color);
            border-radius: 3px;
        }

        .bottom-section {
            padding: 20px 0;
            border-top: 1px solid var(--accent-color);
            background: rgba(0, 0, 0, 0.1);
        }

        .bottom-section .menu-item {
            margin: 5px 0;
        }

        /* Update Main Content margin */
        .main-content {
            margin-left: 280px;
            padding: 20px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 80px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .recent-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .recent-section h2 {
            color: #2e7d32;
            margin-bottom: 15px;
        }

        .icon-container {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--secondary-color);
        }

        .icon-container i {
            font-size: 1.2rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .soil-details {
            margin-top: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }

        .soil-details h4 {
            margin: 0 0 10px 0;
            color: #333;
        }

        .soil-details ul {
            list-style: none;
            padding-left: 0;
            margin: 5px 0;
        }

        .soil-details li {
            margin: 5px 0;
            color: #666;
        }

        .soil-tests-list {
            margin-top: 15px;
        }

        .soil-test-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .farmer-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .test-date {
            color: #666;
            font-size: 0.9em;
        }

        .test-values {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
            gap: 10px;
        }

        .test-values span {
            background: #f5f5f5;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .farmers-list-container {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 90%;
            max-width: 1200px;
            max-height: 80vh;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            z-index: 1000;
            overflow-y: auto;
            padding: 20px;
        }

        .farmers-list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            transition: color 0.3s;
        }

        .close-btn:hover {
            color: #2e7d32;
        }

        .farmers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }

        .farmer-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .farmer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .farmer-icon {
            font-size: 2.5rem;
            color: var(--secondary-color);
        }

        .farmer-details h3 {
            margin: 0 0 10px 0;
            color: var(--secondary-color);
        }

        .farmer-details p {
            margin: 5px 0;
            color: #666;
        }

        .farmer-details i {
            width: 20px;
            color: var(--secondary-color);
        }

        .slide-in {
            opacity: 0;
            transform: translateY(20px);
            animation: slideIn 0.5s forwards;
        }

        @keyframes slideIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Add styles for animated boxes */
        .stat-card, .dashboard-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            position: relative; /* Allow positioning of icon */
        }

        .stat-card:hover, .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(46, 125, 50, 0.2);
        }

        .stat-card i, .dashboard-card i {
            position: absolute; /* Position icon */
            top: 10px; /* Adjust as needed */
            left: 10px; /* Adjust as needed */
            transition: transform 0.3s ease; /* Add transition for icon movement */
        }

        .stat-card:hover i, .dashboard-card:hover i {
            transform: translateY(-5px); /* Move icon on hover */
        }

        /* Update sidebar buttons */
        .sidebar-btn {
            margin: 5px 15px;
            padding: 12px 20px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 10px;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            text-decoration: none;
            white-space: nowrap;
        }

        .sidebar-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }

        .sidebar-btn i {
            min-width: 24px;
            text-align: center;
        }

        .sidebar.collapsed .sidebar-btn {
            padding: 12px;
            margin: 5px 10px;
            justify-content: center;
        }

        .sidebar.collapsed .menu-text {
            display: none;
        }

        /* Add animations */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .soil-tests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }

        .farmer-soil-tests {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .farmer-soil-tests:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .farmer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .test-count {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
        }

        .averages-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 10px;
        }

        .average-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 6px;
        }

        .average-item .label {
            display: block;
            color: #666;
            font-size: 0.9em;
        }

        .average-item .value {
            display: block;
            font-size: 1.2em;
            color: var(--secondary-color);
            font-weight: bold;
        }

        .farmer-actions {
            margin-top: 15px;
            text-align: right;
        }

        .no-results {
            text-align: center;
            padding: 20px;
            color: #666;
        }

        /* Update Link Colors */
        a {
            color: var(--secondary-color);
        }

        a:hover {
            color: var(--primary-color);
        }

        .charts-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .chart-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .chart-section:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .chart-section h2 {
            color: var(--secondary-color);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.4rem;
        }

        .chart-section h2 i {
            background: var(--primary-color);
            color: white;
            padding: 8px;
            border-radius: 8px;
            font-size: 1.2rem;
        }

        .chart-info {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .info-card {
            background: var(--background-color);
            padding: 10px 15px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex: 1;
            min-width: 200px;
        }

        .info-card i {
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .info-card span {
            color: var(--text-primary);
            font-weight: 500;
        }

        canvas {
            margin-top: 10px;
            width: 100% !important;
            height: 300px !important;
        }

        .recommendation-section {
            margin-top: 2rem;
            padding: 1.5rem;
            background: var(--card-background);
            border-radius: 12px;
            box-shadow: var(--shadow);
        }

        .recommendation-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .recommendation-card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .farmer-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .farmer-header i {
            font-size: 2rem;
            color: var(--primary-color);
        }

        .test-date {
            margin-left: auto;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .soil-levels {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .nutrient-levels {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .level-item {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .level-item .label {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .level-item .value {
            font-size: 1.2rem;
            font-weight: 600;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }

        .value.low { color: var(--danger-color); }
        .value.high { color: var(--warning-color); }
        .value.optimal { color: var(--success-color); }

        .recommendations {
            border-top: 1px solid var(--border-color);
            padding-top: 1rem;
        }

        .recommendations ul {
            list-style: none;
            padding: 0;
            margin: 0.5rem 0;
        }

        .recommendations li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .recommendations .dosage {
            margin-left: auto;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .optimal-message {
            color: var(--success-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .no-data {
            text-align: center;
            color: var(--text-secondary);
            padding: 2rem;
        }

        .hidden-recommendation {
            display: none;
        }

        .show-more-container {
            text-align: center;
            margin-top: 20px;
        }

        .show-more-recommendations-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 auto;
            transition: background-color 0.3s ease;
        }

        .show-more-recommendations-btn:hover {
            background: var(--secondary-color);
        }

        .show-more-recommendations-btn i {
            transition: transform 0.3s ease;
        }

        .farmers-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .farmers-table th {
            background-color: var(--primary-color);
            color: white;
            padding: 12px;
            text-align: left;
        }

        .farmers-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        .farmers-table tr:hover {
            background-color: #f5f5f5;
        }

        .hidden-farmer-row {
            display: none;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.9em;
        }

        .status-badge.active {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.inactive {
            background-color: #ffebee;
            color: #c62828;
        }

        .show-more-farmers-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 20px auto 0;
            transition: background-color 0.3s ease;
        }

        .show-more-farmers-btn:hover {
            background: var(--secondary-color);
        }

        .queries-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .query-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .query-card:hover {
            transform: translateY(-5px);
        }

        .query-card.pending {
            border-left: 4px solid #ffc107;
        }

        .query-card.answered {
            border-left: 4px solid #28a745;
        }

        .query-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .employee-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .employee-info i {
            font-size: 1.5em;
            color: var(--primary-color);
        }

        .employee-info h3 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.1em;
        }

        .query-date {
            color: #666;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .query-text {
            color: #333;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .response-form textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
            min-height: 100px;
            resize: vertical;
            font-family: inherit;
        }

        .response-form textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(43, 122, 48, 0.1);
        }

        .btn-respond {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9em;
            transition: all 0.3s ease;
        }

        .btn-respond:hover {
            background: var(--dark-color);
            transform: translateY(-2px);
        }

        .query-response {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
        }

        .query-response h4 {
            color: var(--primary-color);
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .response-date {
            display: block;
            color: #666;
            font-size: 0.85em;
            margin-top: 10px;
        }

        .soil-tests-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 20px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .soil-tests-table th {
            background: var(--primary-color);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 500;
            font-size: 0.95rem;
            border-bottom: 2px solid rgba(255,255,255,0.1);
        }

        .soil-tests-table th i {
            margin-right: 8px;
        }

        .soil-tests-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            font-size: 0.9rem;
        }

        .soil-tests-table tr:hover {
            background-color: #f8f9fa;
        }

        .soil-tests-table tr:last-child td {
            border-bottom: none;
        }

        .nutrient-header {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .nutrient-header .tooltip {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .nutrient-header:hover .tooltip {
            opacity: 1;
            visibility: visible;
        }

        .farmer-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .farmer-info i {
            font-size: 1.2rem;
            color: var(--primary-color);
        }

        .test-count-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: var(--background-color);
            padding: 4px 10px;
            border-radius: 15px;
            color: var(--primary-color);
        }

        .date-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #666;
        }

        .value-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 500;
            min-width: 60px;
        }

        .value-badge.low {
            background-color: #ffebee;
            color: #c62828;
        }

        .value-badge.high {
            background-color: #fff3e0;
            color: #ef6c00;
        }

        .value-badge.optimal {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: flex-start;
        }

        .action-buttons a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .btn-view {
            background: var(--primary-color);
            color: white;
        }

        .btn-export {
            background: #2196F3;
            color: white;
        }

        .btn-view:hover, .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .show-more-soil-tests-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

        .show-more-soil-tests-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }

        .hidden-soil-test {
            display: none;
        }

        .fertilizer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .fertilizer-table th {
            background-color: var(--primary-color);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 500;
        }

        .fertilizer-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
        }

        .fertilizer-table tr:hover {
            background-color: #f8f9fa;
        }

        .fertilizer-table .farmer-name {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .fertilizer-table .farmer-name i {
            color: var(--primary-color);
            font-size: 1.2em;
        }

        .fertilizer-table .value {
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: 500;
        }

        .fertilizer-table .value.low {
            background-color: #ffebee;
            color: #c62828;
        }

        .fertilizer-table .value.high {
            background-color: #fff3e0;
            color: #ef6c00;
        }

        .fertilizer-table .value.optimal {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .recommendations-list ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .recommendations-list li {
            color: var(--primary-color);
            font-size: 0.9em;
            margin-bottom: 3px;
        }

        .optimal-message {
            color: var(--success-color);
            font-size: 0.9em;
        }

        .hidden-recommendation {
            display: none;
        }

        .show-more-container {
            text-align: center;
            margin-top: 20px;
        }

        .show-more-recommendations-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .show-more-recommendations-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-angle-left" id="toggle-icon"></i>
        </button>

        <div class="logo-section">
            <i class="fas fa-leaf"></i>
            <h1 class="menu-text">GrowGuide</h1>
        </div>

        <div class="menu-section">
            <a href="admin.php" class="sidebar-btn active">
                <i class="fas fa-th-large"></i>
                <span class="menu-text">Dashboard</span>
            </a>
            
            <!-- Add section navigation links -->
            <div class="section-nav">
                <a href="#fertilizer-recommendations" class="sidebar-btn">
                    <i class="fas fa-flask"></i>
                    <span class="menu-text">Fertilizer Recommendations</span>
                </a>
                <a href="#recent-farmers" class="sidebar-btn">
                    <i class="fas fa-users"></i>
                    <span class="menu-text">Recent Farmers</span>
                </a>
                <a href="#soil-tests-farmer" class="sidebar-btn">
                    <i class="fas fa-vial"></i>
                    <span class="menu-text">Soil Tests by Farmer</span>
                </a>
                <a href="#soil-tests-week" class="sidebar-btn">
                    <i class="fas fa-chart-bar"></i>
                    <span class="menu-text">Soil Tests by Week</span>
                </a>
                <a href="#farmers-status" class="sidebar-btn">
                    <i class="fas fa-chart-pie"></i>
                    <span class="menu-text">New Farmers Status</span>
                </a>
                <a href="#npk-trends" class="sidebar-btn">
                    <i class="fas fa-chart-line"></i>
                    <span class="menu-text">NPK Trends</span>
                </a>
                <a href="#employee-queries" class="sidebar-btn">
                    <i class="fas fa-question-circle"></i>
                    <span class="menu-text">Employee Queries</span>
                </a>
            </div>

            <a href="farmers.php" class="sidebar-btn">
                <i class="fas fa-users"></i>
                <span class="menu-text">Farmers</span>
            </a>
            <a href="ad_employee.php" class="sidebar-btn">
                <i class="fas fa-user-tie"></i>
                <span class="menu-text">Employees</span>
            </a>
            <a href="varieties.php" class="sidebar-btn">
                <i class="fas fa-seedling"></i>
                <span class="menu-text">Varieties</span>
            </a>
        </div>

        <div class="bottom-section">
            <a href="admin_notifications.php" class="sidebar-btn">
                <i class="fas fa-bell"></i>
                <span class="menu-text">Notifications</span>
            </a>
            <a href="admin_setting.php" class="sidebar-btn">
                <i class="fas fa-cog"></i>
                <span class="menu-text">Settings</span>
            </a>
            <a href="logout.php" class="sidebar-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span class="menu-text">Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content fade-in" id="main-content">
        <div class="welcome-header">
            <div class="user-welcome">
                WELCOME, MILU JIJI! <i class="fas fa-star"></i> 
                YOU HAVE <strong><i class="fas fa-users"></i> <?php echo $total_farmers; ?></strong> FARMERS, 
                <strong><i class="fas fa-seedling"></i> <?php echo "7"; ?></strong> VARIETIES, 
                AND <strong><i class="fas fa-user-tie"></i> <?php echo $total_employees; ?></strong> EMPLOYEES. 
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="icon-container">
                <i class="fas fa-user-circle"></i>
            </div>
        </div>

        <div class="stats-grid fade-in">
            <div class="stat-card animated-box">
                <h3><?php echo $total_farmers; ?></h3>
                <p>Total Farmers</p>
                <i class="fas fa-users"></i>
            </div>
            
            <div class="stat-card animated-box">
                <h3><?php echo "7"; ?></h3>
                <p>Cardamom Varieties</p>
                <i class="fas fa-seedling"></i>
            </div>
            <div class="stat-card animated-box">
                <h3><?php echo $total_employees; ?></h3>
                <p>Total Employees</p>
                <i class="fas fa-user-tie"></i>
            </div>
            
        </div>

        <div id="farmers-list" class="farmers-list-container" style="display: none;">
            <div class="farmers-list-header">
                <h2>Farmers List</h2>
                <button onclick="toggleFarmersList()" class="close-btn">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="farmers-grid">
                <?php foreach ($farmers_list as $farmer): ?>
                    <div class="farmer-card slide-in">
                        <div class="farmer-icon">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <div class="farmer-details">
                            <h3><?php echo htmlspecialchars($farmer['username']); ?></h3>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($farmer['email']); ?></p>
                            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($farmer['phone']); ?></p>
                            <p><i class="fas fa-calendar-alt"></i> Joined: <?php echo date('M d, Y', strtotime($farmer['created_at'])); ?></p>
                            <span class="status-badge <?php echo $farmer['status'] ? 'active' : 'inactive'; ?>">
                                <?php echo $farmer['status'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div id="fertilizer-recommendations" class="recent-section">
            <h2><i class="fas fa-flask"></i> Recent Fertilizer Recommendations</h2>
            <?php if (!empty($fertilizer_data)): ?>
                <table class="fertilizer-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> Farmer</th>
                            <th><i class="fas fa-calendar"></i> Test Date</th>
                            <th><i class="fas fa-vial"></i> pH Level</th>
                            <th>N (%)</th>
                            <th>P (%)</th>
                            <th>K (%)</th>
                            <th><i class="fas fa-flask"></i> Recommendations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach ($fertilizer_data as $index => $recommendation): 
                            $hideClass = $index >= 5 ? 'hidden-recommendation' : '';
                        ?>
                            <tr class="<?php echo $hideClass; ?>">
                                <td>
                                    <div class="farmer-name">
                                        <i class="fas fa-user-circle"></i>
                                        <?php echo htmlspecialchars($recommendation['farmer_name']); ?>
                                    </div>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($recommendation['test_date'])); ?></td>
                                <td>
                                    <span class="value <?php echo $recommendation['ph_level'] < 5.5 ? 'low' : ($recommendation['ph_level'] > 6.5 ? 'high' : 'optimal'); ?>">
                                        <?php echo number_format($recommendation['ph_level'], 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="value <?php echo $recommendation['nitrogen_content'] < 0.5 ? 'low' : 'optimal'; ?>">
                                        <?php echo number_format($recommendation['nitrogen_content'], 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="value <?php echo $recommendation['phosphorus_content'] < 0.05 ? 'low' : 'optimal'; ?>">
                                        <?php echo number_format($recommendation['phosphorus_content'], 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="value <?php echo $recommendation['potassium_content'] < 1.0 ? 'low' : 'optimal'; ?>">
                                        <?php echo number_format($recommendation['potassium_content'], 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="recommendations-list">
                                        <?php if ($recommendation['n_recommendation'] || 
                                                $recommendation['p_recommendation'] || 
                                                $recommendation['k_recommendation']): ?>
                                            <ul>
                                                <?php if ($recommendation['n_recommendation']): ?>
                                                    <li><?php echo htmlspecialchars($recommendation['n_recommendation']); ?></li>
                                                <?php endif; ?>
                                                <?php if ($recommendation['p_recommendation']): ?>
                                                    <li><?php echo htmlspecialchars($recommendation['p_recommendation']); ?></li>
                                                <?php endif; ?>
                                                <?php if ($recommendation['k_recommendation']): ?>
                                                    <li><?php echo htmlspecialchars($recommendation['k_recommendation']); ?></li>
                                                <?php endif; ?>
                                            </ul>
                                        <?php else: ?>
                                            <span class="optimal-message">All levels optimal</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (count($fertilizer_data) > 5): ?>
                    <div class="show-more-container">
                        <button class="show-more-recommendations-btn" onclick="toggleRecommendations()">
                            <span id="recommendations-text">Show More</span>
                            <i class="fas fa-chevron-down" id="recommendations-icon"></i>
                        </button>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-info-circle"></i>
                    <p>No recent fertilizer recommendations available</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="recent-farmers" class="recent-section">
            <h2><i class="fas fa-users"></i> Recent Farmers</h2>
            <?php if (!empty($farmers_list)): ?>
                <table class="farmers-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> Username</th>
                            <th><i class="fas fa-envelope"></i> Email</th>
                            <th><i class="fas fa-phone"></i> Phone</th>
                            <th><i class="fas fa-calendar-alt"></i> Joined Date</th>
                            <th><i class="fas fa-toggle-on"></i> Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        foreach ($farmers_list as $index => $farmer): 
                            $rowClass = $index >= 5 ? 'hidden-farmer-row' : '';
                        ?>
                            <tr class="<?php echo $rowClass; ?>">
                                <td><?php echo htmlspecialchars($farmer['username']); ?></td>
                                <td><?php echo htmlspecialchars($farmer['email']); ?></td>
                                <td><?php echo htmlspecialchars($farmer['phone']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($farmer['created_at'])); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $farmer['status'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $farmer['status'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (count($farmers_list) > 5): ?>
                    <div class="show-more-container">
                        <button class="show-more-farmers-btn" onclick="toggleFarmerRows()">
                            <span id="farmers-text">Show More</span>
                            <i class="fas fa-chevron-down" id="farmers-icon"></i>
                        </button>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-info-circle"></i>
                    <p>No farmers found.</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="soil-tests-farmer" class="recent-section">
            <h2><i class="fas fa-flask"></i> Soil Tests by Farmer</h2>
            <?php if (mysqli_num_rows($soil_tests_by_farmer) > 0): ?>
                <table class="soil-tests-table">
                    <thead>
                        <tr>
                            <th><i class="fas fa-user"></i> Farmer</th>
                            <th><i class="fas fa-vials"></i> Total Tests</th>
                            <th><i class="fas fa-calendar-alt"></i> Latest Test</th>
                            <th><i class="fas fa-tint"></i> Avg. pH</th>
                            <th>
                                <div class="nutrient-header">
                                    <i class="fas fa-flask"></i> N
                                    <span class="tooltip">Average Nitrogen Level</span>
                                </div>
                            </th>
                            <th>
                                <div class="nutrient-header">
                                    <i class="fas fa-flask"></i> P
                                    <span class="tooltip">Average Phosphorus Level</span>
                                </div>
                            </th>
                            <th>
                                <div class="nutrient-header">
                                    <i class="fas fa-flask"></i> K
                                    <span class="tooltip">Average Potassium Level</span>
                                </div>
                            </th>
                            <th><i class="fas fa-cogs"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $count = 0;
                        while ($farmer = mysqli_fetch_assoc($soil_tests_by_farmer)): 
                            $hideClass = $count >= 5 ? 'hidden-soil-test' : '';
                            $count++;
                        ?>
                            <tr class="<?php echo $hideClass; ?>">
                                <td>
                                    <div class="farmer-info">
                                        <i class="fas fa-user-circle"></i>
                                        <span><?php echo htmlspecialchars($farmer['farmer_name']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="test-count-badge">
                                        <i class="fas fa-vial"></i>
                                        <span><?php echo $farmer['test_count']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="date-info">
                                        <i class="fas fa-calendar-check"></i>
                                        <span><?php echo $farmer['latest_test_date'] ? date('M d, Y', strtotime($farmer['latest_test_date'])) : 'No tests'; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $phClass = $farmer['avg_ph'] < 5.5 ? 'low' : 
                                             ($farmer['avg_ph'] > 6.5 ? 'high' : 'optimal');
                                    ?>
                                    <div class="value-badge <?php echo $phClass; ?>">
                                        <?php echo number_format($farmer['avg_ph'], 2); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php $nClass = $farmer['avg_n'] < 0.5 ? 'low' : 'optimal'; ?>
                                    <div class="value-badge <?php echo $nClass; ?>">
                                        <?php echo number_format($farmer['avg_n'], 2); ?>%
                                    </div>
                                </td>
                                <td>
                                    <?php $pClass = $farmer['avg_p'] < 0.05 ? 'low' : 'optimal'; ?>
                                    <div class="value-badge <?php echo $pClass; ?>">
                                        <?php echo number_format($farmer['avg_p'], 2); ?>%
                                    </div>
                                </td>
                                <td>
                                    <?php $kClass = $farmer['avg_k'] < 1.0 ? 'low' : 'optimal'; ?>
                                    <div class="value-badge <?php echo $kClass; ?>">
                                        <?php echo number_format($farmer['avg_k'], 2); ?>%
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="view_farmer_tests.php?farmer_id=<?php echo $farmer['farmer_id']; ?>" 
                                           class="btn-view" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="export_tests.php?farmer_id=<?php echo $farmer['farmer_id']; ?>" 
                                           class="btn-export" title="Export Data">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php if ($count > 5): ?>
                    <div class="show-more-container">
                        <button class="show-more-soil-tests-btn" onclick="toggleSoilTests()">
                            <span id="soil-tests-text">Show More</span>
                            <i class="fas fa-chevron-down" id="soil-tests-icon"></i>
                        </button>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-info-circle"></i>
                    <p>No soil tests found for any farmer.</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="soil-tests-week" class="chart-section">
            <h2><i class="fas fa-chart-bar"></i> Soil Tests by Week</h2>
            <div class="chart-info">
                <div class="info-card">
                    <i class="fas fa-calendar-check"></i>
                    <span>Last 8 Weeks Analysis</span>
                </div>
                <div class="info-card">
                    <i class="fas fa-vial"></i>
                    <span>Total Tests: <?php echo array_sum($weekly_tests); ?></span>
                </div>
            </div>
            <canvas id="soilTestsChart"></canvas>
        </div>

        <div id="farmers-status" class="chart-section" style="width: calc(100vw - 280px); margin-left: 0; margin-right: calc(-50vw + 50%); padding: 30px 50px;">
            <h2><i class="fas fa-chart-pie"></i> New Farmers Status</h2>
            <div class="chart-info">
                <div class="info-card">
                    <i class="fas fa-user-check"></i>
                    <span>Active: <?php echo $farmer_status['active']; ?></span>
                </div>
                <div class="info-card">
                    <i class="fas fa-user-clock"></i>
                    <span>Inactive: <?php echo $farmer_status['inactive']; ?></span>
                </div>
            </div>
            <canvas id="farmersPieChart"></canvas>
        </div>

        <div id="npk-trends" class="chart-section" style="width: calc(100vw - 280px); margin-left: 0; margin-right: calc(-50vw + 50%); padding: 30px 50px;">
            <h2><i class="fas fa-chart-line"></i> NPK Trends</h2>
            <div class="chart-info">
                <div class="info-card">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Last 6 Months Analysis</span>
                </div>
            </div>
            <canvas id="fertilizerTrendsChart"></canvas>
        </div>

        <div id="employee-queries" class="recent-section" style="width: calc(100vw - 280px); margin-left: 0; margin-right: calc(-50vw + 50%); padding: 30px 50px;">
            <h2><i class="fas fa-question-circle"></i> Employee Queries</h2>
            <?php if (mysqli_num_rows($pending_queries) > 0): ?>
                <div class="queries-grid">
                    <?php while ($query = mysqli_fetch_assoc($pending_queries)): ?>
                        <div class="query-card <?php echo $query['status']; ?>">
                            <div class="query-header">
                                <div class="employee-info">
                                    <i class="fas fa-user-circle"></i>
                                    <h3><?php echo htmlspecialchars($query['employee_name']); ?></h3>
                                </div>
                                <span class="query-date">
                                    <i class="fas fa-calendar-alt"></i>
                                    <?php echo date('M d, Y H:i', strtotime($query['created_at'])); ?>
                                </span>
                            </div>
                            <div class="query-content">
                                <p class="query-text"><?php echo htmlspecialchars($query['query_text']); ?></p>
                                <?php if ($query['status'] === 'pending'): ?>
                                    <form class="response-form" method="POST" action="admin_respond_query.php">
                                        <input type="hidden" name="query_id" value="<?php echo $query['id']; ?>">
                                        <textarea name="response" placeholder="Type your response..." required></textarea>
                                        <button type="submit" class="btn-respond">
                                            <i class="fas fa-reply"></i> Send Response
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="query-response">
                                        <h4>Response:</h4>
                                        <p><?php echo htmlspecialchars($query['response']); ?></p>
                                        <span class="response-date">
                                            <i class="fas fa-clock"></i>
                                            <?php echo date('M d, Y H:i', strtotime($query['response_date'])); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-check-circle"></i>
                    <p>No queries from employees</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('main-content');
            const toggleIcon = document.getElementById('toggle-icon');
            
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
            
            if (sidebar.classList.contains('collapsed')) {
                toggleIcon.classList.remove('fa-angle-left');
                toggleIcon.classList.add('fa-angle-right');
            } else {
                toggleIcon.classList.remove('fa-angle-right');
                toggleIcon.classList.add('fa-angle-left');
            }
            
            // Add animation for sidebar movement
            sidebar.style.transition = 'width 0.3s ease'; // Add transition effect
            
            // Update the section width when sidebar is toggled
            const fullWidthSections = document.querySelectorAll('.recent-section[style*="width"], .chart-section[style*="width"]');
            fullWidthSections.forEach(section => {
                if (sidebar.classList.contains('collapsed')) {
                    section.style.width = 'calc(100vw - 80px)'; // 80px is collapsed sidebar width
                } else {
                    section.style.width = 'calc(100vw - 280px)'; // 280px is expanded sidebar width
                }
            });
        }

        // Add a function to create a running welcome message
        function startMarquee() {
            const welcomeMessage = document.querySelector('.user-welcome');
            const message = welcomeMessage.textContent;
            welcomeMessage.innerHTML = `<marquee>${message}</marquee>`;
        }

        // Call the function to start the marquee
        startMarquee();

        function toggleFarmersList() {
            const farmersList = document.getElementById('farmers-list');
            if (farmersList.style.display === 'none') {
                farmersList.style.display = 'block';
                // Add staggered animation to cards
                const cards = document.querySelectorAll('.farmer-card');
                cards.forEach((card, index) => {
                    card.style.animationDelay = `${index * 0.1}s`;
                });
            } else {
                farmersList.style.display = 'none';
            }
        }
    </script>

    <!-- Add Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        // Soil Tests Bar Chart
        const soilTestsCtx = document.getElementById('soilTestsChart').getContext('2d');
        new Chart(soilTestsCtx, {
            type: 'bar',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4', 'Week 5', 'Week 6', 'Week 7', 'Week 8'],
                datasets: [{
                    label: 'Number of Soil Tests',
                    data: <?php echo json_encode(array_values($weekly_tests)); ?>,
                    backgroundColor: 'rgba(46, 125, 50, 0.7)',
                    borderColor: 'rgba(46, 125, 50, 1)',
                    borderWidth: 1,
                    borderRadius: 5,
                    barThickness: 25
                }]
            },
            options: {
                animation: {
                    duration: 2000,
                    easing: 'easeInOutQuart',
                    delay: (context) => context.dataIndex * 100
                },
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: { size: 12 }
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 12 } }
                    }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(46, 125, 50, 0.9)',
                        titleFont: { size: 14 },
                        bodyFont: { size: 13 },
                        padding: 10,
                        cornerRadius: 5,
                        animation: {
                            duration: 200
                        }
                    }
                },
                interaction: {
                    mode: 'index',
                    intersect: false,
                    animationDuration: 400
                },
                onHover: (event, elements) => {
                    const chart = event.chart;
                    chart.data.datasets[0].backgroundColor = chart.data.datasets[0].backgroundColor.map((color, index) => 
                        elements[0]?.index === index ? 'rgba(46, 125, 50, 0.9)' : 'rgba(46, 125, 50, 0.7)'
                    );
                    chart.update('none');
                }
            }
        });

        // Farmers Pie Chart
        const farmersPieCtx = document.getElementById('farmersPieChart').getContext('2d');
        new Chart(farmersPieCtx, {
            type: 'doughnut',
            data: {
                labels: ['Active Farmers', 'Inactive Farmers'],
                datasets: [{
                    data: [
                        <?php echo $farmer_status['active']; ?>,
                        <?php echo $farmer_status['inactive']; ?>
                    ],
                    backgroundColor: [
                        'rgba(67, 160, 71, 0.8)',
                        'rgba(211, 47, 47, 0.8)'
                    ],
                    borderColor: [
                        'rgba(67, 160, 71, 1)',
                        'rgba(211, 47, 47, 1)'
                    ],
                    borderWidth: 2,
                    hoverOffset: 15,
                    hoverBorderWidth: 3
                }]
            },
            options: {
                animation: {
                    animateRotate: true,
                    animateScale: true,
                    duration: 2000,
                    easing: 'easeInOutQuart'
                },
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: { size: 13 },
                            generateLabels: (chart) => {
                                const datasets = chart.data.datasets;
                                return datasets[0].data.map((value, i) => ({
                                    text: `${chart.data.labels[i]} (${value})`,
                                    fillStyle: datasets[0].backgroundColor[i],
                                    strokeStyle: datasets[0].borderColor[i],
                                    lineWidth: 2,
                                    hidden: false,
                                    index: i
                                }));
                            }
                        },
                        onClick: (event, legendItem, legend) => {
                            const chart = legend.chart;
                            const index = legendItem.index;
                            
                            // Animate segment
                            const dataset = chart.data.datasets[0];
                            dataset.backgroundColor[index] = dataset.backgroundColor[index].includes('0.8') 
                                ? dataset.backgroundColor[index].replace('0.8', '1')
                                : dataset.backgroundColor[index].replace('1', '0.8');
                            
                            chart.update();
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        cornerRadius: 5,
                        titleFont: { size: 14 },
                        bodyFont: { size: 13 },
                        animation: {
                            duration: 200
                        }
                    }
                },
                cutout: '65%'
            }
        });

        // Fertilizer Trends Line Chart
        const fertilizerCtx = document.getElementById('fertilizerTrendsChart').getContext('2d');
        new Chart(fertilizerCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($fertilizer_trends['labels']); ?>,
                datasets: [
                    {
                        label: 'Nitrogen (N)',
                        data: <?php echo json_encode($fertilizer_trends['nitrogen']); ?>,
                        borderColor: '#2196F3',
                        backgroundColor: 'rgba(33, 150, 243, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#2196F3',
                        pointHoverRadius: 8,
                        pointHoverBorderWidth: 3
                    },
                    {
                        label: 'Phosphorus (P)',
                        data: <?php echo json_encode($fertilizer_trends['phosphorus']); ?>,
                        borderColor: '#4CAF50',
                        backgroundColor: 'rgba(76, 175, 80, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#4CAF50',
                        pointHoverRadius: 8,
                        pointHoverBorderWidth: 3
                    },
                    {
                        label: 'Potassium (K)',
                        data: <?php echo json_encode($fertilizer_trends['potassium']); ?>,
                        borderColor: '#FFC107',
                        backgroundColor: 'rgba(255, 193, 7, 0.1)',
                        tension: 0.4,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#FFC107',
                        pointHoverRadius: 8,
                        pointHoverBorderWidth: 3
                    }
                ]
            },
            options: {
                animation: {
                    duration: 2000,
                    easing: 'easeInOutQuart',
                    delay: (context) => context.dataIndex * 100
                },
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: { size: 12 }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(255, 255, 255, 0.9)',
                        titleColor: '#333',
                        bodyColor: '#666',
                        borderColor: '#ddd',
                        borderWidth: 1,
                        padding: 12,
                        boxPadding: 6,
                        animation: {
                            duration: 200
                        },
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y + '%';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 } }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        ticks: {
                            font: { size: 11 },
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                },
                transitions: {
                    show: {
                        animations: {
                            x: { from: 0 },
                            y: { from: 0 }
                        }
                    },
                    hide: {
                        animations: {
                            x: { to: 0 },
                            y: { to: 0 }
                        }
                    }
                }
            }
        });
    </script>

    <script>
        function toggleFarmerRows() {
            const hiddenRows = document.querySelectorAll('.hidden-farmer-row');
            const showMoreText = document.getElementById('farmers-text');
            const showMoreIcon = document.getElementById('farmers-icon');

            hiddenRows.forEach(row => {
                row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
            });

            if (showMoreText.textContent === 'Show More') {
                showMoreText.textContent = 'Show Less';
                showMoreIcon.classList.remove('fa-chevron-down');
                showMoreIcon.classList.add('fa-chevron-up');
            } else {
                showMoreText.textContent = 'Show More';
                showMoreIcon.classList.remove('fa-chevron-up');
                showMoreIcon.classList.add('fa-chevron-down');
            }
        }
    </script>

    <script>
        function toggleRecommendations() {
            const hiddenRows = document.querySelectorAll('.hidden-recommendation');
            const showMoreText = document.getElementById('recommendations-text');
            const showMoreIcon = document.getElementById('recommendations-icon');

            hiddenRows.forEach(row => {
                row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
            });

            if (showMoreText.textContent === 'Show More') {
                showMoreText.textContent = 'Show Less';
                showMoreIcon.classList.remove('fa-chevron-down');
                showMoreIcon.classList.add('fa-chevron-up');
            } else {
                showMoreText.textContent = 'Show More';
                showMoreIcon.classList.remove('fa-chevron-up');
                showMoreIcon.classList.add('fa-chevron-down');
            }
        }
    </script>

    <script>
        // Add active class to section navigation links when scrolling
        document.addEventListener('DOMContentLoaded', function() {
            const sections = document.querySelectorAll('[id]');
            const navLinks = document.querySelectorAll('.section-nav .sidebar-btn');

            function highlightNavigation() {
                const scrollPosition = window.scrollY;

                sections.forEach(section => {
                    const sectionTop = section.offsetTop - 100;
                    const sectionHeight = section.offsetHeight;
                    const sectionId = section.getAttribute('id');

                    if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                        navLinks.forEach(link => {
                            link.classList.remove('active');
                            if (link.getAttribute('href') === '#' + sectionId) {
                                link.classList.add('active');
                            }
                        });
                    }
                });
            }

            window.addEventListener('scroll', highlightNavigation);
        });
    </script>

    <script>
        function toggleSoilTests() {
            const hiddenRows = document.querySelectorAll('.hidden-soil-test');
            const showMoreText = document.getElementById('soil-tests-text');
            const showMoreIcon = document.getElementById('soil-tests-icon');

            hiddenRows.forEach(row => {
                row.style.display = row.style.display === 'none' ? 'table-row' : 'none';
            });

            if (showMoreText.textContent === 'Show More') {
                showMoreText.textContent = 'Show Less';
                showMoreIcon.classList.remove('fa-chevron-down');
                showMoreIcon.classList.add('fa-chevron-up');
            } else {
                showMoreText.textContent = 'Show More';
                showMoreIcon.classList.remove('fa-chevron-up');
                showMoreIcon.classList.add('fa-chevron-down');
            }
        }
    </script>
</body>
</html>