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

// Get farmer ID from URL with validation
$farmer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($farmer_id <= 0) {
    die("Invalid farmer ID");
}

// Fetch farmer details with related information
$query = "SELECT u.*, f.farm_location, f.phone,
                 (SELECT COUNT(*) FROM soil_tests WHERE user_id = u.id) as soil_test_count,
                 COUNT(DISTINCT fr.recommendation_id) as total_recommendations,
                 MAX(st.test_date) as last_test_date
          FROM users u
          LEFT JOIN farmers f ON u.id = f.farmer_id
          LEFT JOIN soil_tests st ON u.id = st.user_id
          LEFT JOIN fertilizer_recommendations fr ON u.id = fr.recommendation_id
          WHERE u.id = ? AND u.role = 'farmer'
          GROUP BY u.id";

// Prepare statement with error handling
$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    die("Prepare failed: " . mysqli_error($conn));
}

// Bind parameter with error handling
if (!mysqli_stmt_bind_param($stmt, "i", $farmer_id)) {
    die("Binding parameters failed: " . mysqli_stmt_error($stmt));
}

// Execute with error handling
if (!mysqli_stmt_execute($stmt)) {
    die("Execute failed: " . mysqli_stmt_error($stmt));
}

$result = mysqli_stmt_get_result($stmt);

if (!$result || mysqli_num_rows($result) == 0) {
    die("Farmer not found");
}

$farmer = mysqli_fetch_assoc($result);

// Close statement
mysqli_stmt_close($stmt);
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Profile - GrowGuide</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Add floating animation */
        @keyframes float {
            0% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
            100% { transform: translateY(0); }
        }

        /* Add pulse animation */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Add rotate animation */
        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Add shine effect animation */
        @keyframes shine {
            0% { background-position: -100px; }
            100% { background-position: 200px; }
        }

        /* Update detail-item styling */
        .detail-item {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.1);
        }

        .detail-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        /* Add icon styling */
        .detail-item i {
            font-size: 24px;
            color: var(--primary-color);
            opacity: 0.8;
            position: absolute;
            right: 15px;
            top: 15px;
        }

        /* Add shine effect on hover */
        .detail-item::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                to right,
                rgba(255,255,255,0) 0%,
                rgba(255,255,255,0.3) 50%,
                rgba(255,255,255,0) 100%
            );
            transform: rotate(30deg);
            animation: shine 3s infinite linear;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .detail-item:hover::after {
            opacity: 1;
        }

        /* Add specific animations for each icon */
        .icon-float {
            animation: float 3s infinite ease-in-out;
        }

        .icon-pulse {
            animation: pulse 2s infinite ease-in-out;
        }

        .icon-rotate {
            animation: rotate 4s infinite linear;
        }

        /* Update profile avatar */
        .profile-avatar {
            animation: pulse 2s infinite ease-in-out;
            background: linear-gradient(145deg, var(--primary-color), var(--dark-color));
        }

        /* Add status indicator animation */
        .profile-status {
            position: relative;
            overflow: hidden;
        }

        .profile-status::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255,255,255,0.2),
                transparent
            );
            animation: shine 3s infinite linear;
        }

        .profile-container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            padding: 30px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-avatar i {
            font-size: 40px;
            color: white;
        }

        .profile-info h1 {
            margin: 0;
            color: var(--dark-color);
        }

        .profile-status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            margin-top: 5px;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        .profile-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .detail-item .label {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .detail-item .value {
            color: var(--dark-color);
            font-size: 1.1em;
            font-weight: 500;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 30px;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }

        .back-btn:hover {
            background: var(--dark-color);
            transform: translateY(-2px);
        }

        /* Header animations and styles */
        .page-header {
            background: linear-gradient(145deg, var(--primary-green), var(--dark-green));
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(43, 122, 48, 0.2);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        /* Floating leaves animation */
        @keyframes floatLeaf {
            0% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(10px, -10px) rotate(10deg); }
            100% { transform: translate(0, 0) rotate(0deg); }
        }

        /* Shine effect animation */
        @keyframes headerShine {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        .page-header::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(152, 255, 152, 0.2),
                transparent
            );
            animation: headerShine 3s infinite;
        }

        .header-title {
            display: flex;
            align-items: center;
            gap: 15px;
            color: white;
        }

        .header-title h1 {
            margin: 0;
            font-size: 24px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .leaf-icon {
            animation: floatLeaf 3s infinite ease-in-out;
            font-size: 24px;
            color: var(--mint-green);
            text-shadow: 0 0 10px rgba(152, 255, 152, 0.3);
        }

        .back-btn {
            position: relative;
            overflow: hidden;
            background: rgba(152, 255, 152, 0.1);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(152, 255, 152, 0.3);
            padding: 12px 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(152, 255, 152, 0.2);
            border-color: rgba(152, 255, 152, 0.5);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(152, 255, 152, 0.2);
        }

        .back-btn i {
            color: var(--mint-green);
            transition: transform 0.3s ease;
        }

        .back-btn:hover i {
            transform: translateX(-5px);
        }

        /* Decorative icons */
        .floating-icons {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
        }

        .floating-icon {
            position: absolute;
            opacity: 0.15;
            animation: floatLeaf 4s infinite ease-in-out;
            color: var(--leaf-green);
        }

        .floating-icon:nth-child(1) { top: 10%; left: 10%; animation-delay: 0s; }
        .floating-icon:nth-child(2) { top: 20%; right: 15%; animation-delay: 1s; }
        .floating-icon:nth-child(3) { bottom: 15%; left: 20%; animation-delay: 2s; }
        .floating-icon:nth-child(4) { bottom: 20%; right: 10%; animation-delay: 3s; }

        /* Update color variables */
        :root {
            --primary-green: #2B7A30;
            --light-green: #4CAF50;
            --dark-green: #1B4D1E;
            --leaf-green: #8BC34A;
            --mint-green: #98FF98;
        }

        /* Add green glow effect */
        .header-title h1 {
            color: white;
            text-shadow: 0 0 10px rgba(152, 255, 152, 0.3);
        }

        /* Add green particle effect */
        @keyframes greenParticle {
            0% { transform: translateY(0) rotate(0deg); opacity: 0; }
            50% { opacity: 0.5; }
            100% { transform: translateY(-50px) rotate(360deg); opacity: 0; }
        }

        .green-particles {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            width: 8px;
            height: 8px;
            background: var(--mint-green);
            border-radius: 50%;
            opacity: 0;
        }

        .particle:nth-child(1) { left: 10%; animation: greenParticle 3s infinite; }
        .particle:nth-child(2) { left: 30%; animation: greenParticle 3s infinite 0.5s; }
        .particle:nth-child(3) { left: 50%; animation: greenParticle 3s infinite 1s; }
        .particle:nth-child(4) { left: 70%; animation: greenParticle 3s infinite 1.5s; }
        .particle:nth-child(5) { left: 90%; animation: greenParticle 3s infinite 2s; }
    </style>
</head>
<body>
    <div class="content">
        <div class="page-header">
            <div class="floating-icons">
                <i class="fas fa-leaf floating-icon"></i>
                <i class="fas fa-seedling floating-icon"></i>
                <i class="fas fa-tree floating-icon"></i>
                <i class="fas fa-spa floating-icon"></i>
            </div>
            <div class="green-particles">
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
                <div class="particle"></div>
            </div>
            <div class="header-content">
                <div class="header-title">
                    <i class="fas fa-leaf leaf-icon"></i>
                    <h1>Farmer Profile</h1>
                    <i class="fas fa-seedling leaf-icon" style="animation-delay: 0.5s;"></i>
                </div>
                <a href="employe.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i>
                    <span>Back to Dashboard</span>
                </a>
            </div>
        </div>

        <div class="profile-container">
            <div class="profile-header">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($farmer['username']); ?></h1>
                    <span class="profile-status <?php echo $farmer['status'] ? 'status-active' : 'status-inactive'; ?>">
                        <?php echo $farmer['status'] ? 'Active' : 'Inactive'; ?>
                    </span>
                </div>
            </div>

            <div class="profile-details">
                <div class="detail-item">
                    <i class="fas fa-envelope icon-float"></i>
                    <div class="label">Email</div>
                    <div class="value"><?php echo htmlspecialchars($farmer['email'] ?? 'N/A'); ?></div>
                </div>
                
                <div class="detail-item">
                    <i class="fas fa-map-marker-alt icon-float"></i>
                    <div class="label">Location</div>
                    <div class="value"><?php echo htmlspecialchars($farmer['farm_location'] ?? 'N/A'); ?></div>
                </div>
               
                <div class="detail-item">
                    <i class="fas fa-flask icon-pulse"></i>
                    <div class="label">Number of Soil Tests</div>
                    <div class="value"><?php echo $farmer['soil_test_count']; ?></div>
                </div>
                <div class="detail-item">
                    <i class="fas fa-clipboard-list icon-float"></i>
                    <div class="label">Total Recommendations</div>
                    <div class="value"><?php echo $farmer['total_recommendations']; ?></div>
                </div>
               
                </div>
                <div class="detail-item">
                    <i class="fas fa-user-clock icon-rotate"></i>
                    <div class="label">Member Since</div>
                    <div class="value"><?php echo date('Y-m-d', strtotime($farmer['created_at'])); ?></div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 