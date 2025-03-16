<?php
session_start();

// Add database connection
$conn = mysqli_connect("localhost", "root", "", "growguide");

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Sample variety data - In production, this would come from database
$varieties = [
    [
        'name' => 'Malabar Excel',
        'scientific_name' => 'Elettaria cardamomum var. malabar',
        'yield' => '750-900 kg/ha',
        'maturity' => '2.5-3 years',
        'description' => 'Known for its robust flavor and high oil content. Excellent for high-altitude cultivation.',
        'color' => '#2E7D32'
    ],
    [
        'name' => 'Mysore Green Gold',
        'scientific_name' => 'Elettaria cardamomum var. mysore',
        'yield' => '800-950 kg/ha',
        'maturity' => '2-2.5 years',
        'description' => 'Early maturing variety with distinctive aroma. Performs well in moderate climates.',
        'color' => '#1B5E20'
    ],
    [
        'name' => 'IISR Vijetha',
        'scientific_name' => 'Elettaria cardamomum var. vijetha',
        'yield' => '850-1000 kg/ha',
        'maturity' => '3 years',
        'description' => 'Disease-resistant variety with high yield potential. Suitable for organic farming.',
        'color' => '#388E3C'
    ],
    [
        'name' => 'PDP Vazhukka',
        'scientific_name' => 'Elettaria cardamomum var. vazhukka',
        'yield' => '700-850 kg/ha',
        'maturity' => '2.5 years',
        'description' => 'Traditional variety known for its adaptability to different soil conditions.',
        'color' => '#43A047'
    ],
    [
        'name' => 'Njallani Green Gold',
        'scientific_name' => 'Elettaria cardamomum var. njallani',
        'yield' => '900-1200 kg/ha',
        'maturity' => '3 years',
        'description' => 'High-yielding variety with excellent market value. Known for large capsules.',
        'color' => '#4CAF50'
    ],
    [
        'name' => 'ICRI-1',
        'scientific_name' => 'Elettaria cardamomum var. icri',
        'yield' => '800-900 kg/ha',
        'maturity' => '2.5 years',
        'description' => 'Research-developed variety with balanced oil composition.',
        'color' => '#66BB6A'
    ],
    [
        'name' => 'Avinash',
        'scientific_name' => 'Elettaria cardamomum var. avinash',
        'yield' => '750-850 kg/ha',
        'maturity' => '2-2.5 years',
        'description' => 'Quick maturing variety with good drought tolerance.',
        'color' => '#81C784'
    ],
    [
        'name' => 'PDP Highland',
        'scientific_name' => 'Elettaria cardamomum var. highland',
        'yield' => '850-950 kg/ha',
        'maturity' => '3 years',
        'description' => 'Specially developed for high-altitude regions. Strong disease resistance.',
        'color' => '#A5D6A7'
    ],
    [
        'name' => 'Mudigere-1',
        'scientific_name' => 'Elettaria cardamomum var. mudigere',
        'yield' => '700-800 kg/ha',
        'maturity' => '2.5 years',
        'description' => 'Hardy variety with good adaptation to various climatic conditions.',
        'color' => '#C8E6C9'
    ],
    [
        'name' => 'IISR Avinash',
        'scientific_name' => 'Elettaria cardamomum var. iisr',
        'yield' => '800-1000 kg/ha',
        'maturity' => '3 years',
        'description' => 'Modern hybrid with excellent disease resistance and yield potential.',
        'color' => '#2E7D32'
    ]
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Cardamom Varieties</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* Add these root variables at the start of your existing styles */
        :root {
            --primary-color: #2E7D32;
            --primary-dark: #1B5E20;
            --accent-color: #81C784;
            --text-color: #333333;
            --sidebar-width: 250px;
        }

        /* Add the new sidebar styles */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(180deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 20px 0;
            box-shadow: 4px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 20px;
            margin-bottom: 30px;
        }

        .sidebar-header i {
            font-size: 24px;
        }

        .sidebar-header h2 {
            margin: 0;
            font-size: 20px;
        }

        .nav-menu {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: calc(100% - 100px);
        }

        .nav-menu-items {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .nav-item.active {
            background: rgba(255, 255, 255, 0.2);
            border-right: 4px solid white;
        }

        .nav-item i {
            width: 20px;
            text-align: center;
        }

        /* Update main-content styles */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            min-height: 100vh;
            box-sizing: border-box;
        }

        /* Update logout button styles */
        .nav-menu-bottom .logout-btn {
            margin: 20px;
            background: rgba(220, 53, 69, 0.1);
            border-radius: 8px;
            color: #ff6b6b;
        }

        .nav-menu-bottom .logout-btn:hover {
            background: #ff6b6b;
            color: white;
        }

        /* Hide the admin dashboard link */
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
        .admin-dashboard-link {
    position: fixed;
    top: 20px;
    right: 20px;
    background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
    color: white;
    padding: 12px 24px;
    border-radius: 50px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
    box-shadow: 0 4px 15px rgba(46, 125, 50, 0.2);
    transition: all 0.3s ease;
    z-index: 1000;
    border: 2px solid rgba(255, 255, 255, 0.1);
}

.admin-dashboard-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(46, 125, 50, 0.3);
    background: linear-gradient(135deg, #33873b 0%, #1e6823 100%);
}

.admin-dashboard-link i {
    font-size: 20px;
}

.admin-dashboard-link .icon-container {
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    width: 32px;
    height: 32px;
    transition: all 0.3s ease;
}

.admin-dashboard-link:hover .icon-container {
    transform: rotate(360deg);
    background: rgba(255, 255, 255, 0.2);
}

.admin-dashboard-link .text {
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

@media (max-width: 768px) {
    .admin-dashboard-link {
        padding: 10px 16px;
    }
    
    .admin-dashboard-link .text {
        display: none;
    }
    
    .admin-dashboard-link .icon-container {
        width: 28px;
        height: 28px;
    }
}
        
        
    </style>
</head>
<body>
    <!-- Add sidebar HTML right after body tag -->
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-seedling"></i>
            <h2>GrowGuide</h2>
        </div>
        
        <nav class="nav-menu">
            <div class="nav-menu-items">
                <a href="employe.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                <a href="varieties.php" class="nav-item active">
                    <i class="fas fa-seedling"></i>
                    <span>Varieties</span>
                </a>
                <a href="notifications.php" class="nav-item">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <a href="manage_products.php" class="nav-item">
                    <i class="fas fa-shopping-basket"></i>
                    <span>Manage Products</span>
                </a>
            </div>
            <div class="nav-menu-bottom">
                <a href="logout.php" class="nav-item logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>
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
                    <div class="variety-header" style="background-color: <?php echo $variety['color']; ?>">
                        <h2 class="variety-name"><?php echo $variety['name']; ?></h2>
                        <p class="scientific-name"><?php echo $variety['scientific_name']; ?></p>
                    </div>
                    <div class="variety-content">
                        <div class="variety-stats">
                            <div class="stat-item">
                                <div class="stat-label">Yield</div>
                                <div class="stat-value"><?php echo $variety['yield']; ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-label">Maturity</div>
                                <div class="stat-value"><?php echo $variety['maturity']; ?></div>
                            </div>
                        </div>
                        <p class="variety-description"><?php echo $variety['description']; ?></p>
                    </div>
                    <div class="variety-footer">
                        <div class="variety-actions">
                            <button onclick="viewDetails('<?php echo $variety['name']; ?>')">
                                View Details
                            </button>
                        </div>
                        <i class="fas fa-seedling" style="color: <?php echo $variety['color']; ?>"></i>
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