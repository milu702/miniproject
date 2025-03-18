<?php
session_start();

// Ensure user is logged in and is an employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

// Add database connection
$conn = mysqli_connect("localhost", "root", "", "growguide");

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Get employee's name from session
$username = $_SESSION['username'];

// Initialize search variable at the top of the file, after session_start()
$search = $_GET['search'] ?? '';

// Fetch dashboard statistics with error checking
$total_farmers = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'farmer' AND status = 1");
if (!$total_farmers) {
    die("Error fetching farmers count: " . mysqli_error($conn));
}

$total_varieties = mysqli_query($conn, "SELECT COUNT(DISTINCT variety_name) as count FROM cardamom_varieties");
if (!$total_varieties) {
    die("Error fetching varieties count: " . mysqli_error($conn));
}

$total_employees = mysqli_query($conn, "SELECT COUNT(*) as count FROM users WHERE role = 'employee' AND status = 1");
if (!$total_employees) {
    die("Error fetching employees count: " . mysqli_error($conn));
}

$total_tests = mysqli_query($conn, "SELECT COUNT(*) as count FROM soil_tests");
if (!$total_tests) {
    die("Error fetching tests count: " . mysqli_error($conn));
}

// Fetch unread notifications count
$unread_notifications_query = "SELECT COUNT(*) as count FROM notifications WHERE is_read = 0";
$unread_notifications = mysqli_query($conn, $unread_notifications_query);
$unread_count = mysqli_fetch_assoc($unread_notifications)['count'];

// Fetch recent notifications
$recent_notifications_query = "SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5";
$recent_notifications = mysqli_query($conn, $recent_notifications_query);

// Create statistics array
$stats = array(
    'total_farmers' => mysqli_fetch_assoc($total_farmers)['count'] ?? 0,
    'total_varieties' => mysqli_fetch_assoc($total_varieties)['count'] ?? 0,
    'total_employees' => mysqli_fetch_assoc($total_employees)['count'] ?? 0,
    'total_tests' => mysqli_fetch_assoc($total_tests)['count'] ?? 0
);

// Fetch recent soil tests
$recent_soil_tests_query = "SELECT st.*, u.username as farmer_name 
                             FROM soil_tests st 
                             JOIN users u ON st.user_id = u.id 
                             ORDER BY st.test_date DESC 
                             LIMIT 5";
$recent_soil_tests = mysqli_query($conn, $recent_soil_tests_query);
if (!$recent_soil_tests) {
    die("Error fetching recent soil tests: " . mysqli_error($conn));
}

// Fetch recent fertilizer recommendations
$recent_recommendations_query = "SELECT fr.*, u.username as farmer_name 
                               FROM fertilizer_recommendations fr 
                               JOIN users u ON fr.recommendation_id = u.id 
                               ORDER BY fr.recommendation_date DESC 
                               LIMIT 5";
$recent_recommendations = mysqli_query($conn, $recent_recommendations_query);
if (!$recent_recommendations) {
    die("Error fetching recent recommendations: " . mysqli_error($conn));
}

// Fetch recent farmers
$recent_farmers_query = "SELECT u.*, f.farm_location, f.phone, 
                               COUNT(st.user_id) as total_soil_tests,
                               COUNT(fr.recommendation_id) as total_recommendations
                        FROM users u 
                        LEFT JOIN farmers f ON u.id = f.farmer_id 
                        LEFT JOIN soil_tests st ON u.id = st.user_id
                        LEFT JOIN fertilizer_recommendations fr ON u.id = fr.recommendation_id
                        WHERE u.role = 'farmer'";

// Add search condition if search term is provided
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $recent_farmers_query .= " AND u.username LIKE '%$search%'";
}

$recent_farmers_query .= " GROUP BY u.id ORDER BY u.created_at DESC LIMIT 4";

$recent_farmers = mysqli_query($conn, $recent_farmers_query);
if (!$recent_farmers) {
    die("Error fetching recent farmers: " . mysqli_error($conn));
}

// Fetch soil tests grouped by farmer
$soil_tests_by_farmer_query = "SELECT st.*, u.username as farmer_name, u.id as farmer_id,
                                     COUNT(*) as test_count,
                                     AVG(ph_level) as avg_ph,
                                     AVG(nitrogen_content) as avg_n,
                                     AVG(phosphorus_content) as avg_p,
                                     AVG(potassium_content) as avg_k
                              FROM soil_tests st 
                              JOIN users u ON st.farmer_id = u.id 
                              WHERE u.role = 'farmer'
                              GROUP BY u.id
                              ORDER BY u.username";

$soil_tests_by_farmer = mysqli_query($conn, $soil_tests_by_farmer_query);
if (!$soil_tests_by_farmer) {
    die("Error fetching soil tests by farmer: " . mysqli_error($conn));
}

// After the existing database queries, add these new queries:
$weekly_soil_tests_query = "SELECT 
    WEEK(test_date) as week_number,
    COUNT(*) as test_count,
    AVG(ph_level) as avg_ph,
    AVG(nitrogen_content) as avg_n,
    AVG(phosphorus_content) as avg_p,
    AVG(potassium_content) as avg_k
FROM soil_tests
WHERE test_date >= DATE_SUB(NOW(), INTERVAL 4 WEEK)
GROUP BY WEEK(test_date)
ORDER BY week_number DESC
LIMIT 4";

$weekly_soil_tests = mysqli_query($conn, $weekly_soil_tests_query);

// Fetch detailed soil tests
$detailed_soil_tests_query = "SELECT 
    st.*,
    u.username as farmer_name,
    CASE 
        WHEN ph_level < 6.0 THEN 'Low pH - Acidic soil'
        WHEN ph_level > 7.5 THEN 'High pH - Alkaline soil'
        ELSE 'Optimal pH range'
    END as ph_status,
    CASE 
        WHEN nitrogen_content < 1.5 THEN 'Low nitrogen - Additional fertilization needed'
        WHEN nitrogen_content > 3.0 THEN 'High nitrogen - Reduce fertilization'
        ELSE 'Optimal nitrogen levels'
    END as nitrogen_status
FROM soil_tests st
JOIN users u ON st.user_id = u.id
ORDER BY st.test_date DESC
LIMIT 10";

$detailed_soil_tests = mysqli_query($conn, $detailed_soil_tests_query);

// Add query submission handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_query'])) {
    $query_text = mysqli_real_escape_string($conn, $_POST['query_text']);
    $employee_id = $_SESSION['user_id'];
    
    $insert_query = "INSERT INTO employee_queries (employee_id, query_text, status, created_at) 
                     VALUES (?, ?, 'pending', NOW())";
    
    $stmt = mysqli_prepare($conn, $insert_query);
    mysqli_stmt_bind_param($stmt, "is", $employee_id, $query_text);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success_message'] = "Query submitted successfully!";
    } else {
        $_SESSION['error_message'] = "Error submitting query: " . mysqli_error($conn);
    }
    
    mysqli_stmt_close($stmt);
}

// Fetch employee's previous queries
$employee_id = $_SESSION['user_id'];
$previous_queries_query = "SELECT * FROM employee_queries 
                          WHERE employee_id = ? 
                          ORDER BY created_at DESC 
                          LIMIT 5";

$stmt = mysqli_prepare($conn, $previous_queries_query);
mysqli_stmt_bind_param($stmt, "i", $employee_id);
mysqli_stmt_execute($stmt);
$previous_queries = mysqli_stmt_get_result($stmt);
mysqli_stmt_close($stmt);

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - GrowGuide</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #2B7A30;
            --secondary-color: #27ae60;
            --dark-color: #1B4D1E;
            --light-color: #ecf0f1;
        }

        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: #f5f6fa;
            display: flex;
            min-height: 100vh;
        }

        /* Enhanced Sidebar Styles */
        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #1B4D1E 0%, #2B7A30 100%);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding: 20px 0;
            color: white;
            transition: all 0.3s ease;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            padding: 0 20px;
            margin-bottom: 30px;
        }

        .sidebar-logo i {
            font-size: 24px;
            margin-right: 10px;
        }

        .sidebar-logo h1 {
            font-size: 20px;
            margin: 0;
        }

        .sidebar-nav {
            padding: 0 10px;
        }

        .sidebar-btn {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            border-radius: 8px;
            margin-bottom: 5px;
        }

        .sidebar-btn i {
            width: 24px;
            margin-right: 10px;
            font-size: 18px;
        }

        .sidebar-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .sidebar-btn.active {
            background: rgba(255, 255, 255, 0.2);
        }

        .sidebar-btn.logout {
            margin-top: auto;
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .sidebar-btn.logout:hover {
            background: rgba(220, 53, 69, 0.2);
        }

        .notification-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 12px;
            margin-left: auto;
        }

        /* Updated Content Area */
        .content {
            margin-left: 250px;
            padding: 20px;
            width: 100%;
        }

        /* Updated Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            text-align: left;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            font-size: 32px;
            color: var(--dark-color);
            margin: 10px 0;
        }

        /* Updated Running Message */
        .running-message {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-color));
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .message-content {
            animation: slideIn 0.5s ease-out;
        }

        .welcome-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .welcome-header i {
            font-size: 2em;
            color: #fff;
        }

        .welcome-header h2 {
            margin: 0;
            font-size: 1.5em;
            color: #fff;
        }

        .message-body p {
            margin: 5px 0;
            font-size: 1.1em;
            opacity: 0.9;
        }

        .quick-actions {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .quick-actions span {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
        }

        .quick-actions i {
            font-size: 1.1em;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        /* Updated Section Headers */
        .section h2 {
            color: var(--dark-color);
            margin: 30px 0 20px;
        }

        /* Updated Cards and Tables */
        .farmer-card, .soil-test-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .table-responsive table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        .table-responsive th {
            background: var(--primary-color);
            color: white;
            padding: 12px;
            text-align: left;
        }

        .table-responsive td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        /* Logo Section */
        .logo-section {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 20px;
        }

        .logo-section i {
            font-size: 24px;
            color: var(--primary-color);
        }

        /* Updated Search and Logout Styles */
        .search-container {
            margin-bottom: 20px;
        }

        .search-form {
            display: flex;
            gap: 10px;
            max-width: 500px;
        }

        .search-input {
            flex: 1;
            padding: 12px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 30px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .search-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 10px rgba(43, 122, 48, 0.1);
            outline: none;
        }

        .search-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 30px;
            cursor: pointer;
            transition: all 0.5s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
        }

        .search-btn:hover {
            background: var(--dark-color);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(43, 122, 48, 0.2);
        }

        .logout-container {
            position: absolute;
            top: 20px;
            right: 20px;
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #dc3545;
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .logout-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.2);
        }

        .logout-btn i {
            font-size: 18px;
        }

        /* Updated Farmers Grid Styles */
        .farmers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .farmer-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.5s ease;
            position: relative;
            overflow: hidden;
        }

        .farmer-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(43, 122, 48, 0.2);
        }

        .farmer-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }

        .farmer-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .farmer-header i {
            font-size: 1.8em;
            color: var(--primary-color);
        }

        .farmer-header h3 {
            font-size: 1.1em;
            margin: 0;
        }

        .farmer-details {
            padding: 10px 0;
        }

        .farmer-details p {
            font-size: 0.9em;
            margin: 5px 0;
        }

        .farmer-stats {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid #eee;
            flex-wrap: wrap;
        }

        .farmer-stats span {
            font-size: 0.8em;
            padding: 4px 8px;
        }

        .farmer-actions {
            margin-top: 10px;
        }

        .btn-view {
            padding: 6px 15px;
            font-size: 0.9em;
        }

        .no-results {
            grid-column: 1 / -1;
            text-align: center;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 10px;
            color: #666;
        }

        .no-results i {
            font-size: 2em;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .farmer-card {
            animation: fadeIn 0.5s ease forwards;
        }

        .farmer-soil-tests {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.5s ease;
        }

        .farmer-soil-tests:hover {
            transform: translateY(-3px);
        }

        .farmer-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .farmer-header h3 {
            margin: 0;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .test-count {
            background: var(--primary-color);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9em;
        }

        .soil-averages {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .soil-averages h4 {
            margin: 0 0 10px 0;
            color: var(--primary-dark);
        }

        .averages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
        }

        .average-item {
            text-align: center;
            padding: 10px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .average-item .label {
            display: block;
            font-size: 0.9em;
            color: #666;
            margin-bottom: 5px;
        }

        .average-item .value {
            display: block;
            font-size: 1.2em;
            color: var(--primary-color);
            font-weight: bold;
        }

        .farmer-actions {
            text-align: right;
        }

        .btn-view {
            display: inline-block;
            padding: 8px 20px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .btn-view:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .notification-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.8em;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
        }

        /* Welcome Banner Styles */
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary-color), var(--dark-color));
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            animation: slideDown 0.8s ease-out;
        }

        .profile-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .profile-icon {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: pulse 2s infinite;
        }

        .profile-icon i {
            font-size: 40px;
            color: white;
        }

        .welcome-text h1 {
            margin: 0;
            font-size: 28px;
            animation: fadeInRight 0.5s ease-out;
        }

        .typing-text {
            margin: 5px 0 0;
            font-size: 16px;
            opacity: 0.9;
            position: relative;
            animation: typing 8s steps(40) infinite;
        }

        .quick-stats {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(255, 255, 255, 0.1);
            padding: 10px 15px;
            border-radius: 30px;
            font-size: 14px;
            animation: fadeInUp 0.5s ease-out;
        }

        .stat-item i {
            font-size: 16px;
        }

        /* Animations */
        @keyframes slideDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes fadeInRight {
            from {
                transform: translateX(-20px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes fadeInUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(255, 255, 255, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(255, 255, 255, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(255, 255, 255, 0);
            }
        }

        @keyframes typing {
            from {
                width: 0;
            }
            to {
                width: 100%;
            }
        }

        /* Add these new styles */
        .analysis-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin: 20px 0;
        }

        .analysis-message {
            background: #f8f9fa;
            border-left: 4px solid var(--primary-color);
            padding: 15px;
            margin: 20px 0;
            border-radius: 0 8px 8px 0;
        }

        .soil-test-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 20px 0;
        }

        .soil-test-table th {
            background: var(--primary-color);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 500;
        }

        .soil-test-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        .soil-test-table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .status-optimal {
            background: #28a745;
            color: white;
        }

        .status-warning {
            background: #ffc107;
            color: #000;
        }

        .status-alert {
            background: #dc3545;
            color: white;
        }

        .farmer-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .farmer-info i {
            color: var(--primary-color);
            font-size: 1.2em;
        }

        /* Soil Tests Table Styles */
        .soil-tests-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin: 20px 0;
        }

        .soil-tests-table thead th {
            background: var(--primary-color);
            color: white;
            padding: 15px;
            font-weight: 500;
            text-align: left;
            white-space: nowrap;
        }

        .soil-tests-table thead th i {
            margin-right: 8px;
        }

        .soil-tests-table tbody tr {
            transition: all 0.3s ease;
        }

        .soil-tests-table tbody tr:hover {
            background: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .soil-tests-table td {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }

        /* Farmer Cell Styles */
        .farmer-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .farmer-cell i {
            font-size: 1.5em;
            color: var(--primary-color);
        }

        /* Test Count Badge */
        .test-count-badge {
            background: var(--primary-color);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            display: inline-block;
            font-weight: 500;
            text-align: center;
            min-width: 30px;
        }

        /* pH Level Styles */
        .ph-level {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .ph-value {
            padding: 4px 10px;
            border-radius: 15px;
            font-weight: 500;
        }

        .ph-value.optimal {
            background: #d4edda;
            color: #155724;
        }

        .ph-value.warning {
            background: #fff3cd;
            color: #856404;
        }

        /* NPK Levels Styles */
        .npk-levels {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .nutrient {
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.9em;
            color: #495057;
        }

        /* Status Badge Styles */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 500;
        }

        .status-badge i {
            font-size: 0.8em;
        }

        .status-badge.optimal {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.warning {
            background: #fff3cd;
            color: #856404;
        }

        /* Action Buttons Styles */
        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: white;
            transition: all 0.3s ease;
        }

        .btn-action:hover {
            transform: translateY(-2px);
        }

        .btn-action.view {
            background: var(--primary-color);
        }

        .btn-action.add {
            background: #17a2b8;
        }

        .btn-action:hover {
            opacity: 0.9;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* No Results Styles */
        .no-results {
            text-align: center;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 10px;
            color: #6c757d;
        }

        .no-results i {
            font-size: 2em;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        /* Hidden Row Styles */
        .hidden-row {
            display: none;
        }

        /* Show More Button Styles */
        .show-more-container {
            text-align: center;
            margin-top: 20px;
        }

        .show-more-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 auto;
        }

        .show-more-btn:hover {
            background: var(--dark-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(43, 122, 48, 0.2);
        }

        .show-more-btn i {
            transition: transform 0.3s ease;
        }

        .show-more-btn.active i {
            transform: rotate(180deg);
        }

        .query-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .query-form-container {
            margin: 20px 0;
        }

        .query-form {
            max-width: 800px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            resize: vertical;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        .form-group textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 10px rgba(43, 122, 48, 0.1);
            outline: none;
        }

        .submit-query-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .submit-query-btn:hover {
            background: var(--dark-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(43, 122, 48, 0.2);
        }

        .queries-list {
            margin-top: 20px;
        }

        .query-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid var(--primary-color);
        }

        .query-content p {
            margin: 0 0 10px 0;
            color: #333;
        }

        .query-meta {
            display: flex;
            gap: 15px;
            font-size: 0.9em;
            color: #666;
        }

        .query-date, .query-status {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .query-status {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8em;
        }

        .query-status.pending {
            background: #fff3cd;
            color: #856404;
        }

        .query-status.answered {
            background: #d4edda;
            color: #155724;
        }

        .query-response {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
        }

        .query-response h4 {
            color: var(--primary-color);
            margin: 0 0 10px 0;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .query-response p {
            margin: 0;
            color: #666;
            font-size: 0.9em;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .no-queries {
            text-align: center;
            color: #666;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .section-navigation {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
            padding: 0 10px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 0.9em;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .nav-link i {
            font-size: 1em;
        }

        /* Add smooth scrolling to the page */
        html {
            scroll-behavior: smooth;
        }

        /* Add padding-top to sections to account for fixed header */
        .section {
            scroll-margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-logo">
            <i class="fas fa-leaf"></i>
            <h1>GrowGuide</h1>
        </div>
        
        <div class="sidebar-nav">
            <a href="employe.php" class="sidebar-btn active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <a href="employe_varities.php" class="sidebar-btn">
                <i class="fas fa-seedling"></i>
                <span>Varieties</span>
            </a>
            
            <a href="notifications.php" class="sidebar-btn">
                <i class="fas fa-bell"></i>
                <span>Notifications</span>
                <?php if ($unread_count > 0): ?>
                    <span class="notification-badge"><?php echo $unread_count; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="employee_settings.php" class="sidebar-btn">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            
            <a href="manage_products.php" class="sidebar-btn">
                <i class="fas fa-shopping-basket"></i>
                <span>Manage Products</span>
            </a>
            
            <a href="logout.php" class="sidebar-btn logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <div class="content">
        <!-- Add Welcome Message Section -->
        <div class="welcome-banner">
            <div class="profile-section">
                <div class="profile-icon">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="welcome-text">
                    <h1>Welcome back, <?php echo htmlspecialchars($username); ?>! 👋</h1>
                    <p class="typing-text">Ready to make a difference in farming today?</p>
                </div>
            </div>
            <div class="quick-stats">
                <div class="stat-item">
                    <i class="fas fa-bell"></i>
                    <span><?php echo $unread_count; ?> new notifications</span>
                </div>
                <div class="stat-item">
                    <i class="fas fa-calendar"></i>
                    <span><?php echo date('l, F j, Y'); ?></span>
                </div>
            </div>
            <div class="section-navigation">
                <a href="#recent-farmers" class="nav-link">
                    <i class="fas fa-users"></i>
                    Recent Farmers
                </a>
                <a href="#soil-tests" class="nav-link">
                    <i class="fas fa-flask"></i>
                    Soil Tests
                </a>
                <a href="#fertilizer-recommendations" class="nav-link">
                    <i class="fas fa-leaf"></i>
                    Recommendations
                </a>
                <a href="#soil-analysis" class="nav-link">
                    <i class="fas fa-chart-bar"></i>
                    Analysis
                </a>
                <a href="#submit-query" class="nav-link">
                    <i class="fas fa-question-circle"></i>
                    Submit Query
                </a>
            </div>
        </div>

        <!-- Top Section -->
        <div class="search-and-profile">
            <div class="search-container">
                <form action="" method="GET" class="search-form">
                    <input type="text" name="search" placeholder="Search farmers..." 
                           value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-container">
                <div class="stat-card">
                    <i class="fas fa-tractor"></i>
                    <h3><?php echo $stats['total_farmers']; ?></h3>
                    <p>Total Farmers</p>
                </div>
                
                <div class="stat-card">
                    <i class="fas fa-leaf"></i>
                    <h3><?php echo $stats['total_varieties']; ?></h3>
                    <p>Cardamom Varieties</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-id-badge"></i>
                    <h3><?php echo $stats['total_employees']; ?></h3>
                    <p>Total Employees</p>
                </div>
                <div class="stat-card">
                    <i class="fas fa-vial"></i>
                    <h3><?php echo $stats['total_tests']; ?></h3>
                    <p>Total Soil Tests</p>
                </div>
            </div>

            <!-- Add Recent Farmers Section with detailed cards -->
            <div id="recent-farmers" class="section">
                <h2><i class="fas fa-users-gear"></i> Recent Farmers</h2>
                <div class="farmers-grid">
                    <?php if (!empty($search)): ?>
                        <div class="search-results">
                            <h3>Search Results for: "<?php echo htmlspecialchars($search); ?>"</h3>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (mysqli_num_rows($recent_farmers) > 0): ?>
                        <?php while ($farmer = mysqli_fetch_assoc($recent_farmers)): ?>
                            <div class="farmer-card">
                                <div class="farmer-header">
                                    <i class="fas fa-user-circle"></i>
                                    <h3><?php echo htmlspecialchars($farmer['username']); ?></h3>
                                </div>
                                <div class="farmer-details">
                                    <div class="farmer-stats">
                                        <span><i class="fas fa-flask"></i> <?php echo $farmer['total_soil_tests']; ?> Tests</span>
                                        <span><i class="fas fa-clipboard-list"></i> <?php echo $farmer['total_recommendations']; ?> Recommendations</span>
                                    </div>
                                </div>
                                <div class="farmer-actions">
                                    <a href="view_farmer_details.php?id=<?php echo $farmer['id']; ?>" class="btn-view">View Details</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="no-results">
                            <?php if (!empty($search)): ?>
                                <p><i class="fas fa-exclamation-circle"></i> No farmers found matching "<?php echo htmlspecialchars($search); ?>"</p>
                            <?php else: ?>
                                <p>No farmers found.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Soil Tests Section -->
            <div id="soil-tests" class="section">
                <h2><i class="fas fa-flask"></i> Soil Tests by Farmer</h2>
                <?php if (mysqli_num_rows($soil_tests_by_farmer) > 0): ?>
                    <div class="table-responsive">
                        <table class="soil-tests-table">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-user"></i> Farmer</th>
                                    <th><i class="fas fa-vials"></i> Total Tests</th>
                                    <th><i class="fas fa-flask"></i> Average pH</th>
                                    <th><i class="fas fa-leaf"></i> NPK Levels</th>
                                    <th><i class="fas fa-chart-line"></i> Status</th>
                                    <th><i class="fas fa-cog"></i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $row_count = 0;
                                while ($farmer_tests = mysqli_fetch_assoc($soil_tests_by_farmer)): 
                                    $row_class = $row_count >= 5 ? 'hidden-row' : '';
                                ?>
                                    <tr class="<?php echo $row_class; ?>">
                                        <td>
                                            <div class="farmer-cell">
                                                <i class="fas fa-user-circle"></i>
                                                <span><?php echo htmlspecialchars($farmer_tests['farmer_name']); ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="test-count-badge">
                                                <?php echo $farmer_tests['test_count']; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="ph-level">
                                                <?php 
                                                $ph = number_format($farmer_tests['avg_ph'], 2);
                                                $ph_class = ($ph < 6.0 || $ph > 7.5) ? 'warning' : 'optimal';
                                                ?>
                                                <span class="ph-value <?php echo $ph_class; ?>">
                                                    <?php echo $ph; ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="npk-levels">
                                                <span class="nutrient">N: <?php echo number_format($farmer_tests['avg_n'], 2); ?>%</span>
                                                <span class="nutrient">P: <?php echo number_format($farmer_tests['avg_p'], 2); ?>%</span>
                                                <span class="nutrient">K: <?php echo number_format($farmer_tests['avg_k'], 2); ?>%</span>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $status = 'optimal';
                                            $status_text = 'Optimal';
                                            if ($ph < 6.0) {
                                                $status = 'warning';
                                                $status_text = 'Low pH';
                                            } elseif ($ph > 7.5) {
                                                $status = 'warning';
                                                $status_text = 'High pH';
                                            }
                                            ?>
                                            <div class="status-badge <?php echo $status; ?>">
                                                <i class="fas fa-circle"></i>
                                                <?php echo $status_text; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="view_farmer_tests.php?farmer_id=<?php echo $farmer_tests['farmer_id']; ?>" 
                                                   class="btn-action view" title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="new_test.php?farmer_id=<?php echo $farmer_tests['farmer_id']; ?>" 
                                                   class="btn-action add" title="Add New Test">
                                                    <i class="fas fa-plus"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php 
                                    $row_count++;
                                endwhile; 
                                ?>
                            </tbody>
                        </table>
                        <?php if ($row_count > 5): ?>
                            <div class="show-more-container">
                                <button id="showMoreBtn" class="show-more-btn">
                                    <i class="fas fa-chevron-down"></i> Show More
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="no-results">
                        <i class="fas fa-info-circle"></i>
                        <p>No soil tests found for any farmer.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Fertilizer Recommendations Section -->
            <div id="fertilizer-recommendations" class="section">
                <h2>Recent Fertilizer Recommendations</h2>
                <?php if (mysqli_num_rows($recent_recommendations) > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Farmer</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($rec = mysqli_fetch_assoc($recent_recommendations)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($rec['farmer_name']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($rec['recommendation_date'])); ?></td>
                                        <td><?php echo ucfirst($rec['status']); ?></td>
                                        <td>
                                            <a href="view_recommendation.php?id=<?php echo $rec['id']; ?>" class="btn-view">View</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No recent fertilizer recommendations found.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="logo-section">
            <i class="fas fa-leaf"></i>
            <h1 class="menu-text">GrowGuide</h1>
        </div>

        <!-- Add this section before the closing </div> of content -->
        <div id="soil-analysis" class="section analysis-section">
            <h2><i class="fas fa-chart-bar"></i> Soil Test Analysis</h2>
            
            <div class="chart-container">
                <canvas id="soilTestChart"></canvas>
            </div>

            <div class="analysis-message">
                <h4><i class="fas fa-info-circle"></i> Weekly Analysis Summary</h4>
                <?php
                $total_tests = 0;
                $avg_ph = 0;
                $weeks_data = [];
                
                while ($week = mysqli_fetch_assoc($weekly_soil_tests)) {
                    $total_tests += $week['test_count'];
                    $avg_ph += $week['avg_ph'];
                    $weeks_data[] = $week;
                }
                
                $avg_ph = $total_tests > 0 ? $avg_ph / count($weeks_data) : 0;
                
                echo "<p>Total soil tests conducted in the last 4 weeks: <strong>$total_tests</strong></p>";
                echo "<p>Average pH level: <strong>" . number_format($avg_ph, 2) . "</strong></p>";
                
                if ($total_tests > 0) {
                    if ($avg_ph < 6.0) {
                        echo "<p class='alert alert-warning'>Overall soil conditions tend to be acidic. Consider lime application recommendations.</p>";
                    } elseif ($avg_ph > 7.5) {
                        echo "<p class='alert alert-warning'>Overall soil conditions tend to be alkaline. Consider sulfur application recommendations.</p>";
                    } else {
                        echo "<p class='alert alert-success'>Overall soil pH levels are within optimal range.</p>";
                    }
                }
                ?>
            </div>

            <h3><i class="fas fa-list"></i> Recent Soil Test Results</h3>
            <div class="table-responsive">
                <table class="soil-test-table">
                    <thead>
                        <tr>
                            <th>Farmer</th>
                            <th>Test Date</th>
                            <th>pH Level</th>
                            <th>Nitrogen (%)</th>
                            <th>Phosphorus (%)</th>
                            <th>Potassium (%)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($test = mysqli_fetch_assoc($detailed_soil_tests)): ?>
                            <tr>
                                <td>
                                    <div class="farmer-info">
                                        <i class="fas fa-user-circle"></i>
                                        <?php echo htmlspecialchars($test['farmer_name']); ?>
                                    </div>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($test['test_date'])); ?></td>
                                <td><?php echo number_format($test['ph_level'], 2); ?></td>
                                <td><?php echo number_format($test['nitrogen_content'], 2); ?></td>
                                <td><?php echo number_format($test['phosphorus_content'], 2); ?></td>
                                <td><?php echo number_format($test['potassium_content'], 2); ?></td>
                                <td>
                                    <?php
                                    $status_class = 'status-optimal';
                                    if ($test['ph_level'] < 6.0 || $test['ph_level'] > 7.5) {
                                        $status_class = 'status-warning';
                                    }
                                    if ($test['nitrogen_content'] < 1.5) {
                                        $status_class = 'status-alert';
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo $test['ph_status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add this before the closing </div> of content -->
        <div id="submit-query" class="section query-section">
            <h2><i class="fas fa-question-circle"></i> Submit Query to Admin</h2>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php 
                        echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger">
                    <?php 
                        echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="query-form-container">
                <form method="POST" action="" class="query-form">
                    <div class="form-group">
                        <label for="query_text">Your Query:</label>
                        <textarea id="query_text" name="query_text" rows="4" required 
                            placeholder="Type your query here..."></textarea>
                    </div>
                    <button type="submit" name="submit_query" class="submit-query-btn">
                        <i class="fas fa-paper-plane"></i> Submit Query
                    </button>
                </form>
            </div>

            <div class="previous-queries">
                <h3><i class="fas fa-history"></i> Previous Queries</h3>
                <?php if (mysqli_num_rows($previous_queries) > 0): ?>
                    <div class="queries-list">
                        <?php while ($query = mysqli_fetch_assoc($previous_queries)): ?>
                            <div class="query-card">
                                <div class="query-content">
                                    <p><?php echo htmlspecialchars($query['query_text']); ?></p>
                                    <div class="query-meta">
                                        <span class="query-date">
                                            <i class="fas fa-calendar-alt"></i>
                                            <?php echo date('M d, Y H:i', strtotime($query['created_at'])); ?>
                                        </span>
                                        <span class="query-status <?php echo $query['status']; ?>">
                                            <i class="fas fa-circle"></i>
                                            <?php echo ucfirst($query['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if ($query['response']): ?>
                                    <div class="query-response">
                                        <h4><i class="fas fa-reply"></i> Admin Response:</h4>
                                        <p><?php echo htmlspecialchars($query['response']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="no-queries">No previous queries found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    // Add this before the closing </body> tag
    const ctx = document.getElementById('soilTestChart').getContext('2d');
    const weekLabels = <?php 
        echo json_encode(array_map(function($week) {
            return 'Week ' . $week['week_number'];
        }, array_reverse($weeks_data)));
    ?>;

    const testCounts = <?php 
        echo json_encode(array_map(function($week) {
            return $week['test_count'];
        }, array_reverse($weeks_data)));
    ?>;

    const avgPHLevels = <?php 
        echo json_encode(array_map(function($week) {
            return $week['avg_ph'];
        }, array_reverse($weeks_data)));
    ?>;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: weekLabels,
            datasets: [{
                label: 'Number of Tests',
                data: testCounts,
                backgroundColor: 'rgba(43, 122, 48, 0.2)',
                borderColor: 'rgba(43, 122, 48, 1)',
                borderWidth: 1,
                yAxisID: 'y'
            }, {
                label: 'Average pH Level',
                data: avgPHLevels,
                type: 'line',
                borderColor: '#27ae60',
                borderWidth: 2,
                fill: false,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Number of Tests'
                    }
                },
                y1: {
                    position: 'right',
                    title: {
                        display: true,
                        text: 'pH Level'
                    },
                    grid: {
                        drawOnChartArea: false
                    }
                }
            },
            plugins: {
                title: {
                    display: true,
                    text: 'Weekly Soil Test Analysis'
                }
            }
        }
    });

    document.addEventListener('DOMContentLoaded', function() {
        const showMoreBtn = document.getElementById('showMoreBtn');
        if (showMoreBtn) {
            showMoreBtn.addEventListener('click', function() {
                const hiddenRows = document.querySelectorAll('.hidden-row');
                hiddenRows.forEach(row => {
                    row.classList.toggle('hidden-row');
                });
                
                // Toggle button text and icon
                const isExpanded = !hiddenRows[0].classList.contains('hidden-row');
                showMoreBtn.innerHTML = isExpanded ? 
                    '<i class="fas fa-chevron-up"></i> Show Less' : 
                    '<i class="fas fa-chevron-down"></i> Show More';
                showMoreBtn.classList.toggle('active');
            });
        }

        // Add active class to navigation links when scrolling
        const sections = document.querySelectorAll('.section');
        const navLinks = document.querySelectorAll('.nav-link');

        function changeLinkState() {
            let index = sections.length;

            while(--index && window.scrollY + 100 < sections[index].offsetTop) {}

            navLinks.forEach((link) => link.classList.remove('active'));
            navLinks[index].classList.add('active');
        }

        window.addEventListener('scroll', changeLinkState);
    });
    </script>
</body>
</html>
