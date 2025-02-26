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

// Add this query near the top PHP section where other queries are
$soil_tests_by_farmer = mysqli_query($conn, "
    SELECT 
        u.id as farmer_id,
        u.username as farmer_name,
        COUNT(st.test_id) as test_count,
        AVG(st.ph_level) as avg_ph,
        AVG(st.nitrogen_content) as avg_n,
        AVG(st.phosphorus_content) as avg_p,
        AVG(st.potassium_content) as avg_k
    FROM users u
    LEFT JOIN soil_tests st ON u.id = st.farmer_id
    WHERE u.role = 'farmer' AND u.status = 1
    GROUP BY u.id, u.username
    ORDER BY test_count DESC
");

// Add error handling
if (!$soil_tests_by_farmer) {
    die("Query failed: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Cardamom Plantation Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            display: flex;
            background-color: #f5f5f5;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            height: 100vh;
            background-color: #2e7d32;
            color: white;
            position: fixed;
            transition: all 0.3s ease;
        }

        .sidebar.collapsed {
            width: 80px;
        }

        .logo-section {
            padding: 25px;
            border-bottom: 1px solid #43a047;
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
            background: #43a047;
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
            padding: 25px 0;
        }

        .menu-item {
            padding: 16px 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            color: white;
            border-radius: 0 25px 25px 0;
            margin: 8px 0;
            position: relative;
            overflow: hidden;
        }

        .menu-item:hover {
            background-color: #43a047;
            padding-left: 35px;
        }

        .menu-item.active {
            background-color: #43a047;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .menu-item i {
            width: 24px;
            height: 24px;
            font-size: 1.4rem;
            text-align: center;
            transition: transform 0.3s;
        }

        .menu-item:hover i {
            transform: scale(1.2) rotate(5deg);
        }

        .menu-text {
            font-size: 1.1rem;
            white-space: nowrap;
            opacity: 1;
            transition: opacity 0.3s;
        }

        .collapsed .menu-text {
            opacity: 0;
            width: 0;
        }

        .bottom-section {
            position: absolute;
            bottom: 0;
            width: 100%;
            border-top: 1px solid #43a047;
            padding: 20px 0;
            background: rgba(0, 0, 0, 0.1);
        }

        .bottom-section .menu-item {
            margin: 5px 0;
        }

        /* Main Content Styles */
        .main-content {
            margin-left: 280px;
            width: calc(100% - 280px);
            transition: all 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 80px;
            width: calc(100% - 80px);
        }

        .welcome-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            font-size: 1.8rem;
            margin-bottom: 5px;
        }

        .stat-card p {
            color: #666;
            font-size: 0.9rem;
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

        .user-welcome {
            font-size: 1.2rem;
            color: #333;
        }

        .icon-container {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2e7d32;
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

        .dashboard-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .dashboard-card h3 {
            color: var(--primary-color);
            margin-top: 0;
            margin-bottom: 15px;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 10px;
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

        /* Add animations */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
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
            color: #2e7d32;
        }

        .farmer-details h3 {
            margin: 0 0 10px 0;
            color: #2e7d32;
        }

        .farmer-details p {
            margin: 5px 0;
            color: #666;
        }

        .farmer-details i {
            width: 20px;
            color: #2e7d32;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            margin-top: 10px;
        }

        .status-badge.active {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .status-badge.inactive {
            background: #ffebee;
            color: #c62828;
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
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
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

        /* Add these new button styles */
        .sidebar-btn {
            margin: 10px 20px;
            padding: 12px 20px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 25px;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            text-decoration: none;
        }

        .sidebar-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .sidebar-btn i {
            font-size: 1.2rem;
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
            color: #2e7d32;
            font-weight: bold;
        }

        .farmer-actions {
            margin-top: 15px;
            text-align: right;
        }

        .btn-view {
            display: inline-block;
            padding: 8px 15px;
            background: #2e7d32;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .btn-view:hover {
            background: #1b5e20;
        }

        .no-results {
            text-align: center;
            padding: 20px;
            color: #666;
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
            <a href="notifications.php" class="sidebar-btn">
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

       

        <div class="recent-section">
            <h2>Recent Fertilizer Recommendations</h2>
            <p>No recent fertilizer recommendations found.</p>
        </div>

        <div class="recent-section">
            <h2>Recent Farmers</h2>
            <?php if (!empty($farmers_list)): ?>
                <div class="farmers-grid">
                    <?php foreach ($farmers_list as $index => $farmer): ?>
                        <div class="farmer-card slide-in" style="animation-delay: <?php echo $index * 0.2; ?>s;">
                            <h3><?php echo htmlspecialchars($farmer['username']); ?></h3>
                            <p><?php echo htmlspecialchars($farmer['email']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No recent farmers found.</p>
            <?php endif; ?>
        </div>

        <div class="dashboard-grid">
            <?php foreach ($dashboard_data as $data): ?>
                <div class="dashboard-card">
                    <h3><?php echo htmlspecialchars($data['farmer_name']); ?></h3>
                    <div class="card-content">
                        <p>Total Soil Tests: <?php echo $data['total_soil_tests']; ?></p>
                        <?php if ($data['latest_test_date']): ?>
                            <p>Latest Test Date: <?php echo date('F j, Y', strtotime($data['latest_test_date'])); ?></p>
                            <div class="soil-details">
                                <h4>Latest Soil Test Results:</h4>
                                <p>pH Level: <?php echo number_format($data['latest_ph'], 1); ?></p>
                                <p>NPK Values:</p>
                                <ul>
                                    <li>N: <?php echo number_format($data['latest_n'], 2) . '%'; ?></li>
                                    <li>P: <?php echo number_format($data['latest_p'], 2) . '%'; ?></li>
                                    <li>K: <?php echo number_format($data['latest_k'], 2) . '%'; ?></li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <p>No soil tests recorded yet</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

           
        <div class="recent-section">
            <h2><i class="fas fa-flask"></i> Soil Tests by Farmer</h2>
            <div class="soil-tests-grid">
                <?php if (mysqli_num_rows($soil_tests_by_farmer) > 0): ?>
                    <?php while ($farmer_tests = mysqli_fetch_assoc($soil_tests_by_farmer)): ?>
                        <div class="farmer-soil-tests">
                            <div class="farmer-header">
                                <h3><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($farmer_tests['farmer_name']); ?></h3>
                                <span class="test-count"><?php echo $farmer_tests['test_count']; ?> Tests</span>
                            </div>
                            <div class="soil-averages">
                                <h4>Soil Test Averages</h4>
                                <div class="averages-grid">
                                    <div class="average-item">
                                        <span class="label">pH Level</span>
                                        <span class="value"><?php echo number_format($farmer_tests['avg_ph'], 2); ?></span>
                                    </div>
                                    <div class="average-item">
                                        <span class="label">Nitrogen (N)</span>
                                        <span class="value"><?php echo number_format($farmer_tests['avg_n'], 2); ?>%</span>
                                    </div>
                                    <div class="average-item">
                                        <span class="label">Phosphorus (P)</span>
                                        <span class="value"><?php echo number_format($farmer_tests['avg_p'], 2); ?>%</span>
                                    </div>
                                    <div class="average-item">
                                        <span class="label">Potassium (K)</span>
                                        <span class="value"><?php echo number_format($farmer_tests['avg_k'], 2); ?>%</span>
                                    </div>
                                </div>
                            </div>
                            <div class="farmer-actions">
                                <a href="view_farmer_tests.php?farmer_id=<?php echo $farmer_tests['farmer_id']; ?>" 
                                   class="btn-view">View All Tests</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-results">
                        <p><i class="fas fa-info-circle"></i> No soil tests found for any farmer.</p>
                    </div>
                <?php endif; ?>
            </div>
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
</body>
</html>