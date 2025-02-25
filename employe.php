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

        /* Updated Sidebar */
        .sidebar {
            width: 250px;
            background: var(--dark-color);
            padding: 20px 0;
            color: white;
            position: fixed;
            height: 100vh;
        }

        .sidebar h1 {
            color: white;
            font-size: 24px;
            padding: 0 20px;
            margin-bottom: 30px;
        }

        .sidebar-btn {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .sidebar-btn i {
            margin-right: 15px;
            font-size: 20px;
        }

        .sidebar-btn:hover, .sidebar-btn.active {
            background: var(--primary-color);
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
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
        }

        .search-btn:hover {
            background: var(--dark-color);
            transform: translateY(-2px);
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
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .farmer-card:hover {
            transform: translateY(-5px);
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
            transition: transform 0.3s ease;
        }

        .farmer-soil-tests:hover {
            transform: translateY(-5px);
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
    </style>
</head>
<body>
    <div class="sidebar">
        <h1>GrowGuide</h1>
        <a href="employe.php" class="sidebar-btn active">Dashboard</a>
        <a href="employee_farmer.php" class="sidebar-btn">Farmers</a>
        <a href="employe_varities.php" class="sidebar-btn">Varieties</a>
        <a href="notifications.php" class="sidebar-btn">Notifications</a>
        <a href="admin_setting.php" class="sidebar-btn">Settings</a>
        <a href="logout.php" class="sidebar-btn">Logout</a>
    </div>

    <div class="content">
        <!-- Running Message Section -->
        <div class="running-message">
            <div class="message-content">
                <div class="welcome-header">
                    <i class="fas fa-user-md"></i>
                    <h2>Welcome, Dr. <?php echo htmlspecialchars($username); ?>!</h2>
                </div>
                <div class="message-body">
                    <p>Your Cardamom Care Dashboard is ready 🌿</p>
                    <div class="quick-actions">
                        <span><i class="fas fa-flask"></i> Analyze Soil & Weather</span>
                        <span><i class="fas fa-leaf"></i> Fertilizer Recommendations</span>
                        <span><i class="fas fa-comments"></i> Farmer Queries</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="dashboard-container">
            <!-- Updated logout button -->
            <div class="logout-container">
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-power-off"></i>
                    <span>Sign Out</span>
                </a>
            </div>
            
            <!-- Search Form -->
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
            <div class="section">
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
                                    <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($farmer['location'] ?? 'N/A'); ?></p>
                                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($farmer['phone'] ?? 'N/A'); ?></p>
                                    <p><i class="fas fa-ruler-combined"></i> Land: <?php echo htmlspecialchars($farmer['land_area'] ?? '0'); ?> hectares</p>
                                    <div class="farmer-stats">
                                        <span><i class="fas fa-flask"></i> <?php echo $farmer['total_soil_tests']; ?> Tests</span>
                                        <span><i class="fas fa-clipboard-list"></i> <?php echo $farmer['total_recommendations']; ?> Recommendations</span>
                                    </div>
                                </div>
                                <div class="farmer-actions">
                                    <a href="view_farmer.php?id=<?php echo $farmer['id']; ?>" class="btn-view">View Profile</a>
                                   
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
            <div class="section">
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

            <!-- Recent Fertilizer Recommendations Section -->
            <div class="section">
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
    </div>
</body>
</html>
