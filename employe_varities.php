<?php
session_start();

// Add database connection
$conn = mysqli_connect("localhost", "root", "", "growguide");

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Replace the hardcoded $varieties array with database query
$query = "SELECT * FROM varieties ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);
$varieties = [];
while ($row = mysqli_fetch_assoc($result)) {
    $varieties[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Cardamom Varieties</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Add sidebar styles */
        .sidebar {
            width: 250px;
            height: 100vh;
            background: #2E7D32;
            position: fixed;
            left: 0;
            top: 0;
            padding: 20px 0;
            color: white;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            padding: 0;
            margin: 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: background-color 0.3s;
        }

        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Update main-content to accommodate sidebar */
        .main-content {
            margin-left: 250px;
            width: calc(100% - 250px);
            min-height: 100vh;
            padding: 20px;
            box-sizing: border-box;
        }

        /* Remove the existing admin-dashboard-link styles as we're using sidebar now */
        .admin-dashboard-link {
            display: none;
        }

        /* Remove sidebar styles and update main-content */
        .main-content {
            width: 100%;
            min-height: 100vh;
            padding: 20px;
            box-sizing: border-box;
        }

        /* Move logout button to top-right */
        .logout-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #d32f2f;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .logout-btn:hover {
            background: #b71c1c;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(211, 47, 47, 0.2);
        }

        .varieties-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            padding: 20px;
        }

        .variety-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            animation: fadeInUp 0.6s ease-out;
            animation-fill-mode: both;
            transform-origin: center;
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .variety-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 12px 30px rgba(0,0,0,0.15);
        }

        .variety-header {
            padding: 20px;
            color: white;
            position: relative;
            overflow: hidden;
        }

        .variety-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(0,0,0,0.2), transparent);
        }

        .variety-name {
            font-size: 1.5rem;
            margin: 0;
            position: relative;
            z-index: 1;
        }

        .scientific-name {
            font-style: italic;
            opacity: 0.9;
            margin: 5px 0;
            position: relative;
            z-index: 1;
        }

        .variety-content {
            padding: 20px;
        }

        .variety-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .stat-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
        }

        .stat-value {
            font-weight: bold;
            color: #2e7d32;
        }

        .variety-description {
            color: #666;
            line-height: 1.6;
        }

        .variety-footer {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .variety-actions button {
            background: #2e7d32;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
            position: relative;
            overflow: hidden;
        }

        .variety-actions button:hover {
            background: #1b5e20;
        }

        .variety-actions button::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 150%;
            height: 150%;
            background: rgba(255,255,255,0.1);
            transform: translate(-50%, -50%) rotate(45deg) scale(0);
            transition: transform 0.6s ease;
        }

        .variety-actions button:hover::after {
            transform: translate(-50%, -50%) rotate(45deg) scale(1);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding: 0 20px;
        }

        .add-variety-btn {
            background: #2e7d32;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: background-color 0.3s;
        }

        .add-variety-btn:hover {
            background: #1b5e20;
        }

        /* Animation keyframes */
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

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* Updated variety card animations */
        .variety-card:nth-child(odd) {
            animation-delay: 0.2s;
        }

        .variety-card:nth-child(even) {
            animation-delay: 0.4s;
        }

        .variety-card:hover .fas.fa-seedling {
            animation: pulse 1s infinite;
        }

        /* Enhanced variety card interactions */
        .variety-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1), transparent);
            transform: translateX(-100%);
            transition: transform 0.6s ease;
        }

        .variety-card:hover .variety-header::after {
            transform: translateX(100%);
        }

        /* Add floating animation for seedling icon */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
            100% { transform: translateY(0px); }
        }

        .variety-footer .fa-seedling {
            transition: all 0.3s ease;
        }

        .variety-card:hover .fa-seedling {
            animation: float 2s ease-in-out infinite;
        }

        /* Add these styles in the <style> section */
        .variety-image {
            width: 100%;
            height: 200px;
            overflow: hidden;
        }

        .variety-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .variety-card:hover .variety-image img {
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <!-- Add Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-leaf"></i>
            <span>GrowGuide</span>
        </div>
        <ul class="sidebar-menu">
            <li><a href="employe.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="#" class="active"><i class="fas fa-seedling"></i> Varieties</a></li>
            <li><a href="#"><i class="fas fa-bell"></i> Notifications</a></li>
            <li><a href="#"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="#"><i class="fas fa-box"></i> Manage Products</a></li>
            <li><a href="admin.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="main-content">
        <div class="page-header">
            <h1>Cardamom Varieties</h1>
            <button class="add-variety-btn">
                <i class="fas fa-plus"></i>
                Add New Variety
            </button>
        </div>

        <div class="varieties-grid">
            <?php foreach ($varieties as $variety): ?>
                <div class="variety-card">
                    <?php if (!empty($variety['image_path'])): ?>
                        <div class="variety-image">
                            <img src="<?php echo htmlspecialchars($variety['image_path']); ?>" 
                                 alt="<?php echo htmlspecialchars($variety['variety_name']); ?>">
                        </div>
                    <?php endif; ?>
                    <div class="variety-header" style="background-color: #2E7D32">
                        <h2 class="variety-name"><?php echo htmlspecialchars($variety['variety_name']); ?></h2>
                        <p class="scientific-name">Market Price: ₹<?php echo number_format($variety['market_price'], 2); ?>/kg</p>
                    </div>
                    <div class="variety-content">
                        <div class="variety-stats">
                            <div class="stat-item">
                                <div class="stat-label">Growing Period</div>
                                <div class="stat-value"><?php echo htmlspecialchars($variety['growing_period']); ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Yield Potential</div>
                                <div class="stat-value"><?php echo htmlspecialchars($variety['yield_potential']); ?></div>
                            </div>
                        </div>
                        <p class="variety-description"><?php echo htmlspecialchars($variety['description']); ?></p>
                    </div>
                    <div class="variety-footer">
                        <div class="variety-actions">
                            <button onclick="viewDetails('<?php echo htmlspecialchars($variety['variety_name']); ?>')">
                                View Details
                            </button>
                        </div>
                        <i class="fas fa-seedling" style="color: #2E7D32"></i>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function viewDetails(varietyName) {
            // Add your detail view logic here
            alert(`Viewing details for ${varietyName}`);
        }

        function logout() {
            if(confirm('Are you sure you want to logout?')) {
                window.location.href = 'admin.php';
            }
        }
    </script>
</body>
</html> 