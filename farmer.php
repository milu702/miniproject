<?php
session_start();

// Ensure user is logged in and has the 'farmer' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

// Get cardamom specific data
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("
    SELECT 
        f.farmer_id,
        COALESCE(f.farm_size, 0) as farm_size,
        COALESCE(f.farm_location, 'Not Set') as farm_location,
        COALESCE(COUNT(c.crop_id), 0) as total_cardamom_plots,
        COALESCE(SUM(c.area_planted), 0) as total_cardamom_area,
        COALESCE(SUM(CASE 
            WHEN c.status = 'harvested' THEN c.harvest_yield 
            ELSE 0 
        END), 0) as total_revenue,
        COALESCE(p.soil_type, '') as soil_type,
        COALESCE(p.soil_ph, 0) as soil_ph,
        COALESCE(p.soil_moisture, 0) as soil_moisture
    FROM farmers f
    LEFT JOIN crops c ON f.farmer_id = c.farmer_id AND c.crop_name LIKE '%cardamom%'
    LEFT JOIN farmer_profiles p ON f.farmer_id = p.farmer_id
    WHERE f.user_id = ?
    GROUP BY f.farmer_id, f.farm_size, f.farm_location, p.soil_type, p.soil_ph, p.soil_moisture"
);

if ($stmt === false) {
    die("Error preparing statement: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$farmerData = $stmt->get_result()->fetch_assoc() ?: [
    'farm_size' => 0,
    'total_cardamom_plots' => 0,
    'total_cardamom_area' => 0,
    'total_revenue' => 0
];

// Get recent crops
$stmt = $conn->prepare("
    SELECT crop_name, planted_date, status, area_planted, expected_harvest_date
    FROM crops 
    WHERE farmer_id = ?
    ORDER BY planted_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $farmerData['farmer_id']);
$stmt->execute();
$recentCrops = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Farmer';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Farmer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2d6a4f;
            --secondary-color: #40916c;
            --accent-color: #95d5b2;
            --bg-color: #f0f7f4;
            --text-color: #1b4332;
            --sidebar-width: 250px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            margin: 0;
            padding: 20px;
            color: var(--text-color);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .cardamom-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .cardamom-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .cardamom-card h3 {
            color: var(--primary-color);
            margin-top: 0;
        }

        .cardamom-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-value {
            font-size: 2em;
            font-weight: bold;
            color: var(--primary-color);
        }

        .cultivation-tips {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-top: 20px;
        }

        .tip-item {
            margin-bottom: 15px;
            padding-left: 20px;
            border-left: 3px solid var(--accent-color);
        }

        .layout-container {
            display: flex;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
        }

        .sidebar-header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .farmer-profile {
            text-align: center;
            padding: 20px 0;
        }

        .farmer-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--accent-color);
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
        }

        .nav-menu {
            margin-top: 30px;
        }

        .nav-item {
            padding: 15px;
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            transition: 0.3s;
            border-radius: 8px;
            margin-bottom: 5px;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.1);
        }

        .nav-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
        }

        .farm-info-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .farm-info-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .location-badge {
            background: var(--accent-color);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="layout-container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-seedling"></i> GrowGuide</h2>
            </div>
            <div class="farmer-profile">
                <div class="farmer-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <h3><?php echo $username; ?></h3>
                <p>Cardamom Farmer</p>
            </div>
            <nav class="nav-menu">
                <a href="#" class="nav-item active">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-map-marker-alt"></i> Farm Details
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-chart-line"></i> Analytics
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-calendar"></i> Schedule
                </a>
                <a href="#" class="nav-item">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <a href="logout.php" class="nav-item">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </nav>
        </div>

        <div class="main-content">
            <div class="farm-info-card">
                <div class="farm-info-header">
                    <h2><i class="fas fa-farm"></i> Farm Overview</h2>
                    <span class="location-badge">
                        <i class="fas fa-map-marker-alt"></i> 
                        <?php echo htmlspecialchars($farmerData['farm_location']); ?>
                    </span>
                </div>
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-ruler-combined"></i>
                        <h3>Farm Size</h3>
                        <div class="stat-value"><?php echo number_format($farmerData['farm_size'], 2); ?> ha</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-chart-area"></i>
                        <h3>Total Cardamom Area</h3>
                        <div class="stat-value"><?php echo number_format($farmerData['total_cardamom_area'], 2); ?> ha</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-layer-group"></i>
                        <h3>Cardamom Plots</h3>
                        <div class="stat-value"><?php echo $farmerData['total_cardamom_plots']; ?></div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-dollar-sign"></i>
                        <h3>Total Revenue</h3>
                        <div class="stat-value">$<?php echo number_format($farmerData['total_revenue'], 2); ?></div>
                    </div>
                </div>
            </div>

            <div class="cardamom-types">
                <div class="cardamom-card">
                    <h3>Malabar Cardamom</h3>
                    <img src="images/malabar-cardamom.jpg" alt="Malabar Cardamom">
                    <p><strong>Characteristics:</strong></p>
                    <ul>
                        <li>Large, dark green pods</li>
                        <li>Strong aromatic flavor</li>
                        <li>Best for culinary use</li>
                        <li>Harvest period: 120-150 days</li>
                    </ul>
                </div>

                <div class="cardamom-card">
                    <h3>Mysore Cardamom</h3>
                    <img src="images/mysore-cardamom.jpg" alt="Mysore Cardamom">
                    <p><strong>Characteristics:</strong></p>
                    <ul>
                        <li>Medium-sized, light green pods</li>
                        <li>Mild, sweet flavor</li>
                        <li>High oil content</li>
                        <li>Harvest period: 100-120 days</li>
                    </ul>
                </div>

                <div class="cardamom-card">
                    <h3>Vazhukka Cardamom</h3>
                    <img src="images/vazhukka-cardamom.jpg" alt="Vazhukka Cardamom">
                    <p><strong>Characteristics:</strong></p>
                    <ul>
                        <li>Small, light green pods</li>
                        <li>Intense aroma</li>
                        <li>Disease resistant</li>
                        <li>Harvest period: 90-110 days</li>
                    </ul>
                </div>
            </div>

            <div class="cultivation-tips">
                <h2><i class="fas fa-book"></i> Cardamom Cultivation Guide</h2>
                <div class="tip-item">
                    <h3>Ideal Growing Conditions</h3>
                    <p>Temperature: 10-35°C<br>
                       Rainfall: 1500-4000mm/year<br>
                       Altitude: 600-1500m above sea level<br>
                       Soil pH: 6.0-6.5</p>
                </div>
                <div class="tip-item">
                    <h3>Planting Season</h3>
                    <p>Best planted during the pre-monsoon period (May-June)</p>
                </div>
                <div class="tip-item">
                    <h3>Spacing</h3>
                    <p>2m x 2m for optimal growth</p>
                </div>
                <div class="tip-item">
                    <h3>Irrigation</h3>
                    <p>Regular irrigation needed during dry spells</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

