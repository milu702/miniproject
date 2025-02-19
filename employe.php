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
                               JOIN users u ON fr.farmer_id = u.id 
                               ORDER BY fr.recommendation_date DESC 
                               LIMIT 5";
$recent_recommendations = mysqli_query($conn, $recent_recommendations_query);
if (!$recent_recommendations) {
    die("Error fetching recent recommendations: " . mysqli_error($conn));
}

// Fetch recent farmers
$recent_farmers_query = "SELECT u.*
                        FROM users u 
                        LEFT JOIN farmers f ON u.id = f.farmer_id 
                        WHERE u.role = 'farmer' 
                        ORDER BY u.created_at DESC 
                        LIMIT 5";
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
</head>
<body>
    <div class="dashboard-container">
        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h3><?php echo $stats['total_farmers']; ?></h3>
                <p>Total Farmers</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-seedling"></i>
                <h3><?php echo $stats['total_varieties']; ?></h3>
                <p>Cardamom Varieties</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-user-tie"></i>
                <h3><?php echo $stats['total_employees']; ?></h3>
                <p>Total Employees</p>
            </div>
            <div class="stat-card">
                <i class="fas fa-flask"></i>
                <h3><?php echo $stats['total_tests']; ?></h3>
                <p>Total Soil Tests</p>
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
</body>
</html>
