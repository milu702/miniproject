<?php
session_start();
require_once 'config.php';

// Add authentication check at the top
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    // Store the current URL in the session before redirecting
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: login.php");
    exit();
}

// Add these headers to prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

// Initialize database connection
$dbConfig = new DatabaseConfig();
$conn = $dbConfig->getConnection();

// Get farmers data from database
$farmers = [];
$query = "SELECT id, username, email, status 
          FROM users 
          WHERE role = 'farmer'
          ORDER BY username";

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $farmers[] = $row;
    }
    mysqli_free_result($result);
}

// Form processing
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_farmer'])) {
        // Validate required fields
        if (empty($_POST['username']) || empty($_POST['email']) || empty($_POST['password'])) {
            $message = 'All fields are required!';
        } else {
            $username = mysqli_real_escape_string($conn, $_POST['username']);
            $email = mysqli_real_escape_string($conn, $_POST['email']);
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            
            // Start transaction
            mysqli_begin_transaction($conn);
            try {
                // Check if username or email already exists
                $check_query = "SELECT id FROM users WHERE username = ? OR email = ?";
                $check_stmt = mysqli_prepare($conn, $check_query);
                
                if ($check_stmt === false) {
                    throw new Exception('Error preparing statement: ' . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($check_stmt, "ss", $username, $email);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);
                
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    throw new Exception('Username or email already exists!');
                }
                
                // First insert into users table
                $insert_query = "INSERT INTO users (username, email, password, role, status) VALUES (?, ?, ?, 'farmer', 1)";
                $insert_stmt = mysqli_prepare($conn, $insert_query);
                
                if ($insert_stmt === false) {
                    throw new Exception('Error preparing insert statement: ' . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($insert_stmt, "sss", $username, $email, $password);
                
                if (!mysqli_stmt_execute($insert_stmt)) {
                    throw new Exception('Error adding farmer to users table: ' . mysqli_error($conn));
                }
                
                // Get the user_id of the newly inserted user
                $user_id = mysqli_insert_id($conn);
                
                // Then insert into farmers table with minimal required fields
                $insert_farmer_query = "INSERT INTO farmers (user_id, farm_location, farm_size) VALUES (?, 'Not specified', 0.00)";
                $insert_farmer_stmt = mysqli_prepare($conn, $insert_farmer_query);
                
                if ($insert_farmer_stmt === false) {
                    throw new Exception('Error preparing farmer insert statement: ' . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($insert_farmer_stmt, "i", $user_id);
                
                if (!mysqli_stmt_execute($insert_farmer_stmt)) {
                    throw new Exception('Error adding record to farmers table: ' . mysqli_error($conn));
                }
                
                // If we get here, commit the transaction
                mysqli_commit($conn);
                $_SESSION['success_message'] = 'Farmer added successfully!';
                
                // Clear the form data from POST to prevent resubmission
                unset($_POST);
                
                // Refresh the farmers list
                $query = "SELECT u.id, u.username, u.email, u.status 
                         FROM users u 
                         WHERE u.role = 'farmer'
                         ORDER BY u.username";
                $result = mysqli_query($conn, $query);
                $farmers = [];
                if ($result) {
                    while ($row = mysqli_fetch_assoc($result)) {
                        $farmers[] = $row;
                    }
                    mysqli_free_result($result);
                }
                
                // Redirect to prevent form resubmission
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $message = $e->getMessage();
            } finally {
                if (isset($check_stmt)) mysqli_stmt_close($check_stmt);
                if (isset($insert_stmt)) mysqli_stmt_close($insert_stmt);
                if (isset($insert_farmer_stmt)) mysqli_stmt_close($insert_farmer_stmt);
            }
        }
    }

    if (isset($_POST['update_status'])) {
        $farmer_id = $_POST['farmer_id'];
        $new_status = $_POST['status'];
        
        $update_query = "UPDATE users SET status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $update_query);
        mysqli_stmt_bind_param($stmt, "ii", $new_status, $farmer_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = 'Farmer status updated successfully!';
        } else {
           // $message = 'Error updating farmer status!';
        }
        mysqli_stmt_close($stmt);
        
        // Redirect to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Display success message from session if it exists
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Add weather analysis
$weather_info = [
    'temperature' => null,
    'condition' => null,
    'icon' => null,
    'message' => null
];

try {
    // Replace with your actual API key
    $api_key = 'YOUR_OPENWEATHER_API_KEY';
    $city = 'Your_City'; // Replace with desired city
    
    $weather_url = "http://api.openweathermap.org/data/2.5/weather?q={$city}&appid={$api_key}&units=metric";
    $weather_data = @file_get_contents($weather_url);
    
    if ($weather_data) {
        $weather = json_decode($weather_data, true);
        
        $weather_info['temperature'] = round($weather['main']['temp']);
        $weather_info['condition'] = $weather['weather'][0]['main'];
        
        // Map weather conditions to Font Awesome icons and farming messages
        $weather_mappings = [
            'Clear' => [
                'icon' => 'fa-sun',
                'message' => 'Perfect day for outdoor farming activities!'
            ],
            'Rain' => [
                'icon' => 'fa-cloud-rain',
                'message' => 'Indoor tasks recommended. Check drainage systems.'
            ],
            'Clouds' => [
                'icon' => 'fa-cloud',
                'message' => 'Good conditions for most farming activities.'
            ],
            'Snow' => [
                'icon' => 'fa-snowflake',
                'message' => 'Protect sensitive crops from frost.'
            ],
            'Thunderstorm' => [
                'icon' => 'fa-bolt',
                'message' => 'Take shelter. Secure equipment and livestock.'
            ]
        ];
        
        $condition = $weather['weather'][0]['main'];
        $weather_info['icon'] = $weather_mappings[$condition]['icon'] ?? 'fa-cloud';
        $weather_info['message'] = $weather_mappings[$condition]['message'] ?? 'Check local weather for detailed conditions.';
    }
} catch (Exception $e) {
    // Weather service unavailable - set defaults
    $weather_info['icon'] = 'fa-cloud';
    $weather_info['message'] = 'Weather information temporarily unavailable';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary-color: #2D5A27;
            --primary-dark: #1B4D1B;
            --accent-color: #8B9D83;
            --text-color: #333333;
            --bg-color: #f5f5f5;
            --sidebar-width: 250px;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            background: #f5f5f5;
        }

        .sidebar {
            background-color: #1B4D1B;
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding: 20px 0;
            color: white;
        }

        .logo {
            padding: 0 20px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            font-size: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .logo i {
            font-size: 24px;
        }

        .menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            margin: 5px 0;
        }

        .nav-item:hover, .nav-item.active {
            background-color: #2B7A30;
        }

        .nav-item i {
            width: 24px;
            margin-right: 10px;
        }

        .nav-item span {
            font-size: 16px;
        }

        .logout-btn {
            position: absolute;
            bottom: 20px;
            width: 100%;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .logout-btn i {
            width: 24px;
            margin-right: 10px;
        }

        .content {
            margin-left: 260px;
            padding: 20px;
            transition: margin-left 0.3s;
            width: calc(100% - 260px);
            animation: fadeIn 0.5s ease-in-out;
        }

        .collapsed + .content {
            margin-left: 90px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transform-origin: top;
            animation: slideDown 0.6s ease-out;
            transition: all 0.3s ease;
        }

        .form-grid:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        .form-group {
            position: relative;
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px 40px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-sizing: border-box;
        }

        .form-group i {
            position: absolute;
            left: 12px;
            top: 42px;
            color: #666;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
            transform: scale(1.02);
        }

        .error-message {
            color: var(--error-color);
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .form-group.error input {
            border-color: var(--error-color);
        }

        .form-group.error .error-message {
            display: block;
        }

        button[type="submit"] {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-top: 20px;
            position: relative;
            overflow: hidden;
        }

        button[type="submit"]:hover {
            background: var(--primary-dark);
        }

        button[type="submit"]:after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255,255,255,.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }

        button[type="submit"]:hover:after {
            animation: ripple 1s ease-out;
        }

        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            100% {
                transform: scale(100, 100);
                opacity: 0;
            }
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }

        td button {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
            transition: background 0.3s;
        }

        td button:first-child {
            background: #4caf50;
            color: white;
        }

        td button:last-child {
            background: #f44336;
            color: white;
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .toggle-btn {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 10px;
            margin-top: 20px;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .status-badge.active {
            background-color: #d4edda;
            color: #155724;
            animation: pulse 2s infinite;
        }
        
        .status-badge.inactive {
            background-color: #f8d7da;
            color: #721c24;
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
    animation: slideInTop 0.5s ease-out;
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

.weather-banner {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
    color: white;
    padding: 15px 25px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 4px 15px rgba(46, 125, 50, 0.2);
    animation: float 6s ease-in-out infinite;
}

.weather-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.weather-info i {
    font-size: 24px;
}

.weather-info .temperature {
    font-size: 18px;
    font-weight: bold;
}

.weather-info .weather-message {
    font-size: 16px;
    opacity: 0.9;
}

@media (max-width: 768px) {
    .weather-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}

.status-toggle {
    padding: 8px 15px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    margin-right: 5px;
    transition: background 0.3s;
    color: white;
}

.status-toggle.activate {
    background: #4caf50;
}

.status-toggle.deactivate {
    background: #ff9800;
}

.delete-btn {
    padding: 8px 15px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    background: #f44336;
    color: white;
    transition: background 0.3s;
}

.status-toggle:hover.activate {
    background: #45a049;
}

.status-toggle:hover.deactivate {
    background: #f57c00;
}

.delete-btn:hover {
    background: #d32f2f;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes slideDown {
    from { transform: scaleY(0); opacity: 0; }
    to { transform: scaleY(1); opacity: 1; }
}

@keyframes float {
    0% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
    100% { transform: translateY(0px); }
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(21, 87, 36, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(21, 87, 36, 0); }
    100% { box-shadow: 0 0 0 0 rgba(21, 87, 36, 0); }
}

@keyframes slideInTop {
    from { transform: translateY(-100%); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.search-container {
    margin: 20px 0;
}

.search-input-group {
    position: relative;
    max-width: 500px;
}

.search-input-group input {
    width: 100%;
    padding: 12px 20px 12px 45px;
    border: 2px solid #ddd;
    border-radius: 30px;
    font-size: 16px;
    transition: all 0.3s ease;
    background: white;
}

.search-input-group input:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
    outline: none;
}

.search-input-group i {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: #666;
    font-size: 18px;
    transition: color 0.3s ease;
}

.search-input-group input:focus + i {
    color: var(--primary-color);
}

.highlight {
    background-color: #fff3cd;
    transition: background-color 0.3s ease;
}

.no-results {
    text-align: center;
    padding: 20px;
    color: #666;
    font-style: italic;
    animation: fadeIn 0.3s ease-in-out;
}

    </style>
</head>
<body>
<a href="admin.php" class="admin-dashboard-link">
    <div class="icon-container">
        <i class="fas fa-user-shield"></i>
    </div>
    <span class="text">Admin Dashboard</span>
</a>
<div class="sidebar">
    <div class="logo">
        <i class="fas fa-leaf"></i>
        <span>GROWGUIDE</span>
    </div>
    
    <div class="menu">
        <a href="admin.php" class="nav-item">
            <i class="fas fa-th-large"></i>
            <span>DASHBOARD</span>
        </a>
        <a href="farmers.php" class="nav-item">
            <i class="fas fa-users"></i>
            <span>FARMERS</span>
        </a>
        <a href="ad_employee.php" class="nav-item">
            <i class="fas fa-user-tie"></i>
            <span>EMPLOYEES</span>
        </a>
        <a href="varieties.php" class="nav-item">
            <i class="fas fa-seedling"></i>
            <span>VARIETIES</span>
        </a>
        <a href="admin_notifications.php" class="nav-item">
            <i class="fas fa-bell"></i>
            <span>NOTIFICATIONS</span>
        </a>
        <a href="admin_setting.php" class="nav-item">
            <i class="fas fa-cog"></i>
            <span>SETTINGS</span>
        </a>
    </div>
    
    <a href="logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i>
        <span>LOGOUT</span>
    </a>
</div>

<div class="content">
    <div class="weather-banner">
        <div class="weather-info">
            <i class="fas <?php echo $weather_info['icon']; ?>"></i>
            <?php if ($weather_info['temperature'] !== null): ?>
                <span class="temperature"><?php echo $weather_info['temperature']; ?>°C</span>
            <?php endif; ?>
            <span class="weather-message"><?php echo $weather_info['message']; ?></span>
        </div>
    </div>
    <h2>Farmers Management</h2>
    <?php if (!empty($message)): ?>
    <div class="alert <?php echo strpos($message, 'successfully') !== false ? 'alert-success' : 'alert-error'; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <div class="search-container">
        <form class="search-form">
            <div class="search-input-group">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search farmers...">
            </div>
        </form>
    </div>
    
    <form method="POST" onsubmit="return validateForm()">
        <div class="form-grid">
            <div class="form-group">
                <label><i class="fas fa-user-circle"></i> Username</label>
                <input type="text" name="username" required pattern="[a-zA-Z0-9_]{3,20}">
                <i class="fas fa-user-circle"></i>
                <div class="error-message"></div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email</label>
                <input type="email" name="email" required>
                <i class="fas fa-envelope"></i>
                <div class="error-message"></div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <input type="password" name="password" required minlength="8">
                <i class="fas fa-lock"></i>
                <div class="error-message"></div>
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Confirm Password</label>
                <input type="password" name="confirm_password" required minlength="8">
                <i class="fas fa-lock"></i>
                <div class="error-message"></div>
            </div>
        </div>
        
        <button type="submit" name="add_farmer">Add Farmer</button>
    </form>
    
    <table id="farmersTable">
        <thead>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($farmers)): ?>
                <tr>
                    <td colspan="4" style="text-align: center;">No farmers registered yet</td>
                </tr>
            <?php else: ?>
                <?php foreach ($farmers as $farmer): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($farmer['username']); ?></td>
                        <td><?php echo htmlspecialchars($farmer['email']); ?></td>
                        <td>
                            <span class="status-badge <?php echo $farmer['status'] ? 'active' : 'inactive'; ?>">
                                <?php echo $farmer['status'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="farmer_id" value="<?php echo $farmer['id']; ?>">
                                <input type="hidden" name="status" value="<?php echo $farmer['status'] ? '0' : '1'; ?>">
                                <button type="submit" name="update_status" class="status-toggle <?php echo $farmer['status'] ? 'deactivate' : 'activate'; ?>">
                                    <?php echo $farmer['status'] ? 'Deactivate' : 'Activate'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function validateForm() {
    let isValid = true;
    const formGroups = document.querySelectorAll('.form-group');
    
    formGroups.forEach(group => {
        const input = group.querySelector('input');
        const errorMessage = group.querySelector('.error-message');
        
        group.classList.remove('error');
        
        if (!input.value) {
            group.classList.add('error');
            errorMessage.textContent = 'This field is required';
            isValid = false;
            return;
        }
        
        switch(input.name) {
            case 'username':
                if (!/^[a-zA-Z0-9_]{3,20}$/.test(input.value)) {
                    group.classList.add('error');
                    errorMessage.textContent = 'Username must be 3-20 characters and can only contain letters, numbers, and underscores';
                    isValid = false;
                }
                break;
                
            case 'email':
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(input.value)) {
                    group.classList.add('error');
                    errorMessage.textContent = 'Please enter a valid email address';
                    isValid = false;
                }
                break;
                
            case 'password':
                if (input.value.length < 8) {
                    group.classList.add('error');
                    errorMessage.textContent = 'Password must be at least 8 characters long';
                    isValid = false;
                }
                break;
                
            case 'confirm_password':
                const password = document.querySelector('input[name="password"]').value;
                if (input.value !== password) {
                    group.classList.add('error');
                    errorMessage.textContent = 'Passwords do not match';
                    isValid = false;
                }
                break;
        }
    });
    
    if (!isValid) {
        const firstError = document.querySelector('.form-group.error');
        firstError.querySelector('input').focus();
    }
    
    // Additional validation for form submission
    if (isValid) {
        // Show loading state on submit button
        const submitBtn = document.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        submitBtn.disabled = true;

        // Re-enable button after short delay (in case of error)
        setTimeout(() => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }, 3000);
    }

    return isValid;
}

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('collapsed');
}

// Real-time validation
document.querySelectorAll('.form-group input').forEach(input => {
    input.addEventListener('blur', function() {
        validateField(this);
    });
});

function validateField(input) {
    const formGroup = input.closest('.form-group');
    const errorMessage = formGroup.querySelector('.error-message');
    
    formGroup.classList.remove('error');
    
    if (!input.value) {
        formGroup.classList.add('error');
        errorMessage.textContent = 'This field is required';
        return false;
    }
    
    // Trigger the same validation as the form submission
    const event = new Event('submit', { cancelable: true });
    input.form.dispatchEvent(event);
    
    return true;
}

function deleteFarmer(farmerId) {
    if (confirm('Are you sure you want to delete this farmer?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        const farmerIdInput = document.createElement('input');
        farmerIdInput.type = 'hidden';
        farmerIdInput.name = 'farmer_id';
        farmerIdInput.value = farmerId;

        const deleteInput = document.createElement('input');
        deleteInput.type = 'hidden';
        deleteInput.name = 'delete_farmer';
        deleteInput.value = '1';

        form.appendChild(farmerIdInput);
        form.appendChild(deleteInput);

        document.body.appendChild(form);
        form.submit();
    }
}

function searchFarmers() {
    const input = document.getElementById('searchInput');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('farmersTable');
    const rows = table.getElementsByTagName('tr');
    let hasResults = false;

    // Start from index 1 to skip the header row
    for (let i = 1; i < rows.length; i++) {
        const row = rows[i];
        const username = row.cells[0]?.textContent || '';
        const email = row.cells[1]?.textContent || '';
        
        if (username.toLowerCase().includes(filter) || 
            email.toLowerCase().includes(filter)) {
            row.style.display = '';
            row.classList.add('highlight');
            setTimeout(() => row.classList.remove('highlight'), 1500);
            hasResults = true;
        } else {
            row.style.display = 'none';
        }
    }

    // Show no results message if needed
    const existingNoResults = table.querySelector('.no-results');
    if (existingNoResults) {
        existingNoResults.remove();
    }

    if (!hasResults && filter) {
        const noResults = document.createElement('tr');
        noResults.className = 'no-results';
        noResults.innerHTML = `
            <td colspan="4">
                <i class="fas fa-search"></i> 
                No farmers found matching "${filter}"
            </td>
        `;
        table.querySelector('tbody').appendChild(noResults);
    }
}

// Add event listener for search input
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', debounce(searchFarmers, 300));
    }
});

// Debounce function to improve search performance
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
</script>
</body>
</html>