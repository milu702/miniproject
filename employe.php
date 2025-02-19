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
$recent_tests_query = "SELECT st.*, u.username as farmer_name 
                      FROM soil_tests st 
                      JOIN users u ON st.user_id = u.id 
                      ORDER BY st.test_date DESC 
                      LIMIT 5";
$recent_tests = mysqli_query($conn, $recent_tests_query);
if (!$recent_tests) {
    die("Error fetching recent tests: " . mysqli_error($conn));
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

$recent_farmers_query .= " GROUP BY u.id ORDER BY u.created_at DESC LIMIT 5";

$recent_farmers = mysqli_query($conn, $recent_farmers_query);
if (!$recent_farmers) {
    die("Error fetching recent farmers: " . mysqli_error($conn));
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
    .logout-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1000;
    }

    .logout-btn {
        position: relative;
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 25px;
        background: linear-gradient(135deg, #ff6b6b, #ee5253);
        color: white;
        text-decoration: none;
        border-radius: 50px;
        font-weight: 600;
        overflow: hidden;
        transition: all 0.4s ease;
    }

    .logout-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: 0.5s;
    }

    .logout-btn:hover::before {
        left: 100%;
    }

    .logout-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(238, 82, 83, 0.4);
        background: linear-gradient(135deg, #ee5253, #ff6b6b);
    }

    .logout-btn i {
        font-size: 1.2rem;
        transition: transform 0.3s ease;
    }

    .logout-btn span {
        opacity: 1;
        transition: all 0.3s ease;
    }

    .logout-btn:hover i {
        transform: rotate(180deg);
    }

    .logout-btn:active {
        transform: scale(0.95);
    }

    @media (max-width: 768px) {
        .logout-btn span {
            display: none;
        }
        
        .logout-btn {
            padding: 12px;
            border-radius: 50%;
        }
        
        .logout-btn i {
            margin: 0;
        }
    }
    </style>
</head>
<body>
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
                                <a href="employe_soiltest.php?farmer_id=<?php echo $farmer['id']; ?>" class="btn-test">New Test</a>
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
            <h2>Recent Soil Tests</h2>
            <?php if (mysqli_num_rows($recent_tests) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Farmer</th>
                                <th>Test Date</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($test = mysqli_fetch_assoc($recent_tests)): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($test['farmer_name']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($test['test_date'])); ?></td>
                                    <td><?php echo ucfirst($test['status']); ?></td>
                                    <td>
                                        <a href="view_test.php?id=<?php echo $test['id']; ?>" class="btn-view">View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No recent soil tests found.</p>
            <?php endif; ?>
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

    <!-- Add this CSS to your style.css file -->
    <style>
    .stats-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: linear-gradient(135deg, #28a745, #218838);
        color: white;
        padding: 1.5rem;
        border-radius: 10px;
        text-align: center;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-card i {
        font-size: 2.5rem;
        margin-bottom: 1rem;
    }

    .stat-card h3 {
        font-size: 2rem;
        margin: 0.5rem 0;
    }

    .farmers-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-top: 1rem;
    }

    .farmer-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .farmer-header {
        background: linear-gradient(135deg, #28a745, #218838);
        color: white;
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .farmer-header i {
        font-size: 2rem;
    }

    .farmer-details {
        padding: 1rem;
    }

    .farmer-details p {
        margin: 0.5rem 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .farmer-stats {
        display: flex;
        justify-content: space-between;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #eee;
    }

    .farmer-actions {
        padding: 1rem;
        display: flex;
        gap: 1rem;
    }

    .btn-view, .btn-test {
        padding: 0.5rem 1rem;
        border-radius: 5px;
        text-decoration: none;
        text-align: center;
        flex: 1;
    }

    .btn-view {
        background: #28a745;
        color: white;
    }

    .btn-test {
        background: #f8f9fa;
        color: #28a745;
        border: 1px solid #28a745;
    }

    .section h2 {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #28a745;
    }

    .search-container {
        margin: 20px auto;
        max-width: 600px;
    }

    .search-form {
        display: flex;
        gap: 10px;
    }

    .search-input {
        flex: 1;
        padding: 12px 20px;
        border: 2px solid #28a745;
        border-radius: 50px;
        font-size: 16px;
        outline: none;
        transition: all 0.3s ease;
    }

    .search-input:focus {
        border-color: #218838;
        box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.2);
    }

    .search-btn {
        background: #28a745;
        color: white;
        border: none;
        padding: 12px 25px;
        border-radius: 50px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .search-btn:hover {
        background: #218838;
        transform: translateY(-2px);
    }

    .search-btn:active {
        transform: translateY(0);
    }

    .search-results {
        grid-column: 1 / -1;
        padding: 10px 15px;
        background-color: #f8f9fa;
        border-radius: 5px;
        margin-bottom: 15px;
    }

    .search-results h3 {
        color: #28a745;
        margin: 0;
    }

    .no-results {
        grid-column: 1 / -1;
        text-align: center;
        padding: 30px;
        background: #f8f9fa;
        border-radius: 10px;
    }

    .no-results p {
        color: #6c757d;
        font-size: 1.1rem;
        margin: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .no-results i {
        color: #dc3545;
        font-size: 1.3rem;
    }
    </style>
</body>
</html>
