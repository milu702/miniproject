<?php
session_start();

// Add database connection
$conn = mysqli_connect("localhost", "root", "", "growguide");

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Add any PHP logic here
$current_page = basename($_SERVER['PHP_SELF']);

// Sample data - you can replace with database queries
$total_farmers = 3;
$total_land = 0.00;
$total_varieties = 0;
$total_employees = 1;
$total_soil_tests = 0;

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
            width: 250px;
            height: 100vh;
            background-color: #2e7d32;
            color: white;
            position: fixed;
            transition: all 0.3s ease;
        }

        .sidebar.collapsed {
            width: 70px;
        }

        .logo-section {
            padding: 20px;
            border-bottom: 1px solid #43a047;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-section h1 {
            font-size: 1.5rem;
            white-space: nowrap;
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
            padding: 20px 0;
        }

        .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            color: white;
        }

        .menu-item:hover {
            background-color: #43a047;
        }

        .menu-item.active {
            background-color: #43a047;
        }

        .menu-item i {
            width: 20px;
            text-align: center;
        }

        .menu-text {
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
        }

        /* Main Content Styles */
        .main-content {
            margin-left: 250px;
            padding: 20px;
            width: calc(100% - 250px);
            transition: all 0.3s ease;
        }

        .main-content.expanded {
            margin-left: 70px;
            width: calc(100% - 70px);
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
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-chevron-left" id="toggle-icon"></i>
        </button>

        <div class="logo-section">
            <i class="fas fa-leaf"></i>
            <h1 class="menu-text">GrowGuide</h1>
        </div>

        <div class="menu-section">
            <a href="admin.php" class="menu-item active">
                <i class="fas fa-th-large"></i>
                <span class="menu-text">Dashboard</span>
            </a>
            <a href="farmers.php" class="menu-item">
                <i class="fas fa-users"></i>
                <span class="menu-text">Farmers</span>
            </a>
            <a href="ad_employee.php" class="menu-item">
                <i class="fas fa-user-tie"></i>
                <span class="menu-text">Employees</span>
            </a>
            <a href="soil_test.php" class="menu-item">
                <i class="fas fa-flask"></i>
                <span class="menu-text">Soil Tests</span>
            </a>
            <a href="#" class="menu-item">
                <i class="fas fa-seedling"></i>
                <span class="menu-text">Varieties</span>
            </a>
        </div>

        <div class="bottom-section">
            <a href="#" class="menu-item">
                <i class="fas fa-bell"></i>
                <span class="menu-text">Notifications</span>
            </a>
            <a href="#" class="menu-item">
                <i class="fas fa-cog"></i>
                <span class="menu-text">Settings</span>
            </a>
            <a href="login.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span class="menu-text">Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <div class="welcome-header">
            <div class="user-welcome">Welcome, milu jiji</div>
            <div class="icon-container">
                <i class="fas fa-user-circle"></i>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $total_farmers; ?></h3>
                <p>Total Farmers</p>
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_land; ?></h3>
                <p>Total Land Area (hectares)</p>
                <i class="fas fa-chart-area"></i>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_varieties; ?></h3>
                <p>Cardamom Varieties</p>
                <i class="fas fa-seedling"></i>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_employees; ?></h3>
                <p>Total Employees</p>
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="stat-card">
                <h3><?php echo $total_soil_tests; ?></h3>
                <p>Total Soil Tests</p>
                <i class="fas fa-flask"></i>
            </div>
        </div>

        <div class="recent-section">
            <h2>Recent Soil Tests</h2>
            <p>No recent soil tests found.</p>
        </div>

        <div class="recent-section">
            <h2>Recent Fertilizer Recommendations</h2>
            <p>No recent fertilizer recommendations found.</p>
        </div>

        <div class="recent-section">
            <h2>Recent Farmers</h2>
            <p>No recent farmers found.</p>
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
                                    <li>Nitrogen: <?php echo number_format($data['latest_n'], 2); ?>%</li>
                                    <li>Phosphorus: <?php echo number_format($data['latest_p'], 2); ?>%</li>
                                    <li>Potassium: <?php echo number_format($data['latest_k'], 2); ?>%</li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <p>No soil tests recorded yet</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
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
                toggleIcon.classList.remove('fa-chevron-left');
                toggleIcon.classList.add('fa-chevron-right');
            } else {
                toggleIcon.classList.remove('fa-chevron-right');
                toggleIcon.classList.add('fa-chevron-left');
            }
        }
    </script>
</body>
</html>