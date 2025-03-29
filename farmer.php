<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Ensure user is logged in and has the 'farmer' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: login.php");
    exit();
}

// Include database configuration
require_once 'config.php';

// Check if database connection exists
if (!$conn) {
    die("Database connection failed");
}
if (isset($_SESSION['redirect_url'])) {
    $redirect_url = $_SESSION['redirect_url'];
    unset($_SESSION['redirect_url']); // Clear the stored URL
    header("Location: " . $redirect_url);
    exit();
}

// After database connection checks, add this code
$weather_api_key = "cc02c9dee7518466102e748f211bca05";

// Get cardamom specific data
$user_id = $_SESSION['user_id'];

// First, verify the database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Add debugging to see the actual SQL and session value
$user_id = $_SESSION['user_id'] ?? 0; // Add default value
if ($user_id === 0) {
    die("No user ID found in session");
}

// Modify the location query with proper error handling
$location_query = "SELECT farm_location FROM users WHERE id = ?";
$location_stmt = mysqli_prepare($conn, $location_query);

// Check if prepare was successful
if ($location_stmt === false) {
    die("Prepare failed: " . mysqli_error($conn) . " for query: " . $location_query);
}

// Bind parameter with error checking
if (!mysqli_stmt_bind_param($location_stmt, "i", $user_id)) {
    die("Binding parameters failed: " . mysqli_stmt_error($location_stmt));
}

// Execute with error checking
if (!mysqli_stmt_execute($location_stmt)) {
    die("Execute failed: " . mysqli_stmt_error($location_stmt));
}

$location_result = mysqli_stmt_get_result($location_stmt);
if ($location_result === false) {
    die("Getting result failed: " . mysqli_stmt_error($location_stmt));
}

$location_data = mysqli_fetch_assoc($location_result);
mysqli_stmt_close($location_stmt);

// First check if the notifications table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
if (mysqli_num_rows($table_check) == 0) {
    // Create the notifications table if it doesn't exist
    $create_table = "CREATE TABLE IF NOT EXISTS notifications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        type VARCHAR(50) NOT NULL,
        user_id INT,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )";
    
    if (!mysqli_query($conn, $create_table)) {
        die("Error creating notifications table: " . mysqli_error($conn));
    }
}

// Check if the farmers table exists first
$farmers_table_check = mysqli_query($conn, "SHOW TABLES LIKE 'farmers'");
if (mysqli_num_rows($farmers_table_check) == 0) {
    // Create the farmers table if it doesn't exist
    $create_farmers_table = "CREATE TABLE IF NOT EXISTS farmers (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        farmer_id INT,
        farm_size DECIMAL(10,2),
        farm_location VARCHAR(255),
        notification_preferences JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )";
    
    if (!mysqli_query($conn, $create_farmers_table)) {
        die("Error creating farmers table: " . mysqli_error($conn));
    }
}

// Now check if the farmer_profiles table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'farmer_profiles'");
if (mysqli_num_rows($table_check) == 0) {
    // Create the farmer_profiles table if it doesn't exist - with proper foreign key
    $create_table = "CREATE TABLE IF NOT EXISTS farmer_profiles (
        id INT PRIMARY KEY AUTO_INCREMENT,
        farmer_id INT NOT NULL,
        experience_years INT,
        farm_type VARCHAR(100),
        specialization VARCHAR(100),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (farmer_id) REFERENCES farmers(id)
    )";
    
    // Try to create with foreign key
    if (!mysqli_query($conn, $create_table)) {
        // If the foreign key fails, try creating without it for now
        $create_table_without_fk = "CREATE TABLE IF NOT EXISTS farmer_profiles (
            id INT PRIMARY KEY AUTO_INCREMENT,
            farmer_id INT NOT NULL,
            experience_years INT,
            farm_type VARCHAR(100),
            specialization VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if (!mysqli_query($conn, $create_table_without_fk)) {
            die("Error creating farmer_profiles table: " . mysqli_error($conn));
        }
        
        // Log that we created without foreign key
        error_log("Created farmer_profiles table without foreign key constraint");
    }
}

// Simplify the notifications query and add error handling
try {
    // First, just get all notifications without any WHERE clause
    $notifications_query = "SELECT * FROM notifications ORDER BY created_at DESC";
    $notifications = mysqli_query($conn, $notifications_query);
    
    if ($notifications === false) {
        throw new Exception("Error fetching notifications: " . mysqli_error($conn));
    }
    
    // No need for prepare/bind_param for this simple query
    $notifications_result = [];
    while ($row = mysqli_fetch_assoc($notifications)) {
        $notifications_result[] = $row;
    }
    
} catch (Exception $e) {
    // Log the error but don't stop the page from loading
    error_log("Notifications error: " . $e->getMessage());
    $notifications_result = []; // Empty array if there's an error
}

// Use the results in your HTML section
if (!empty($notifications_result)) {
    foreach ($notifications_result as $notification) {
        // Process each notification
        // You can access fields like $notification['message'], $notification['created_at'], etc.
    }
} else {
    // Handle case when there are no notifications
}

// Check if the required tables exist
$tables_check = $conn->query("
    SELECT TABLE_NAME 
    FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('users', 'farmers', 'crops', 'farmer_profiles')
");

$existing_tables = [];
while ($row = $tables_check->fetch_assoc()) {
    $existing_tables[] = $row['TABLE_NAME'];
}

// Print missing tables for debugging
$required_tables = ['users', 'farmers', 'crops', 'farmer_profiles'];
$missing_tables = array_diff($required_tables, $existing_tables);
if (!empty($missing_tables)) {
    die("Missing required tables: " . implode(", ", $missing_tables));
}

// Simplify the query to debug the issue
$query = "
    SELECT 
        u.id as user_id,
        u.username,
        COALESCE(f.farmer_id, 0) as farmer_id,
        COALESCE(f.farm_size, 0) as farm_size,
        COALESCE(f.farm_location, 'Not Set') as farm_location
    FROM users u
    LEFT JOIN farmers f ON u.id = f.user_id
    WHERE u.id = ?";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Error preparing statement: " . $conn->error . "<br>Query: " . $query);
}

$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
    die("Error executing statement: " . $stmt->error);
}

$result = $stmt->get_result();
if (!$result) {
    die("Error getting result: " . $stmt->error);
}

$farmerData = $result->fetch_assoc() ?: [
    'farmer_id' => 0,
    'farm_size' => 0,
    'farm_location' => 'Not Set',
    'username' => isset($_SESSION['username']) ? $_SESSION['username'] : 'Farmer'
];

// Get recent crops - remove debug information
$table_check = $conn->query("SHOW TABLES LIKE 'crops'");
if ($table_check->num_rows == 0) {
    die("Error: 'crops' table does not exist in the database");
}

// Remove debug: Print connection status
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Remove debug: Check table structure
$structure_check = $conn->query("DESCRIBE crops");
if (!$structure_check) {
    die("Error checking table structure: " . $conn->error);
}

$recent_crops_query = "
    SELECT crop_name, planted_date, status, area_planted, expected_harvest_date
    FROM crops 
    WHERE farmer_id = ?
    ORDER BY planted_date DESC
    LIMIT 5";

// Remove debug: Print the query and farmer_id
$stmt = $conn->prepare($recent_crops_query);
if ($stmt === false) {
    die("Error preparing recent crops statement: " . $conn->error);
}

// Initialize $recentCrops as empty array by default
$recentCrops = [];

// Only try to execute if we have a valid statement and farmer_id
if ($stmt && isset($farmerData['farmer_id'])) {
    if (!$stmt->bind_param("i", $farmerData['farmer_id'])) {
        die("Error binding parameters: " . $stmt->error);
    }
    
    if (!$stmt->execute()) {
        die("Error executing statement: " . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result) {
        $recentCrops = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        die("Error getting result: " . $stmt->error);
    }
} else {
    echo "No valid farmer_id found or statement preparation failed<br>";
}

$username = isset($farmerData['username']) && !empty($farmerData['username']) 
    ? htmlspecialchars($farmerData['username']) 
    : (isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Farmer');

// Get farmer's location from users table instead of farmers table
$location_stmt = $conn->prepare("
    SELECT farm_location 
    FROM users 
    WHERE id = ?
");
$location_stmt->bind_param("i", $_SESSION['user_id']);
$location_stmt->execute();
$location_result = $location_stmt->get_result()->fetch_assoc();

// Handle location form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_location'])) {
    $new_location = $_POST['farm_location'];
    
    // Add validation for empty location
    if (empty($new_location)) {
        $_SESSION['error'] = "Please select a valid location.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    // Update the users table with the new location
    $update_stmt = $conn->prepare("UPDATE users SET farm_location = ? WHERE id = ?");
    if ($update_stmt === false) {
        $_SESSION['error'] = "Error preparing statement: " . $conn->error;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    
    $update_stmt->bind_param("si", $new_location, $_SESSION['user_id']);
    
    if ($update_stmt->execute()) {
        $_SESSION['success'] = "Location updated successfully!";
        
        // Update the location_result variable for immediate display
        $location_result['farm_location'] = $new_location;
        
        // Fetch new weather data for the updated location
        $weather_data = getWeatherData($new_location);
    } else {
        $_SESSION['error'] = "Error updating location: " . $update_stmt->error;
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get weather data if location exists
$weather_data = null;
if ($location_result && isset($location_result['farm_location'])) {
    $weather_url = "https://api.openweathermap.org/data/2.5/weather?q=" . 
                   urlencode($location_result['farm_location']) . 
                   "&units=metric&appid=" . $weather_api_key;
    
    $weather_response = @file_get_contents($weather_url);
    if ($weather_response) {
        $weather_data = json_decode($weather_response, true);
    }
}

// Original getWeatherData function
function getWeatherData($location) {
    $api_key = "cc02c9dee7518466102e748f211bca05";
    $url = "https://api.openweathermap.org/data/2.5/weather?q=" . 
           urlencode($location) . 
           "&units=metric&appid=" . $api_key;
    
    $response = @file_get_contents($url);
    if ($response) {
        $weather_data = json_decode($response, true);
        
        // Add weather condition handling
        $weather_condition = $_POST['weather_condition'] ?? 'sunny'; // default to sunny if not set
        
        return [
            'location' => $location,
            'weather' => $weather_condition
        ];
    }
    return null;
}

// Handle query submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_query'])) {
    $query_text = trim($_POST['query_text']);
    $query_type = trim($_POST['query_type']);
    
    if (!empty($query_text) && !empty($query_type)) {
        $query_stmt = $conn->prepare("
            INSERT INTO farmer_queries (farmer_id, query_type, query_text, status, created_at)
            VALUES (?, ?, ?, 'pending', NOW())
        ");
        
        if ($query_stmt === false) {
            die("Error preparing query statement: " . $conn->error);
        }

        // Use user_id instead of farmer_id
        $user_id = $_SESSION['user_id'];

        // Bind parameters with error checking
        if (!$query_stmt->bind_param("iss", $user_id, $query_type, $query_text)) {
            die("Error binding parameters: " . $query_stmt->error);
        }
        
        if ($query_stmt->execute()) {
            $_SESSION['success'] = "Your query has been submitted successfully!";
            
            // Get farmer name
            $farmer_name = getFarmerName($conn, $_SESSION['user_id']);
            
            // Create notification message
            $notification_message = "<div class='notification-content'>
                <strong>New Farmer Query</strong><br>
                Farmer: {$farmer_name}<br>
                Type: {$query_type}<br>
                Query: {$query_text}<br>
                Date: " . date('Y-m-d H:i:s') . "
            </div>";
            
            // Insert notification directly into the database
            $notify_stmt = $conn->prepare("
                INSERT INTO notifications (type, message, user_id, created_at, is_read) 
                VALUES ('query', ?, ?, NOW(), 0)
            ");
            
            if ($notify_stmt) {
                $notify_stmt->bind_param("si", $notification_message, $user_id);
                if (!$notify_stmt->execute()) {
                    error_log("Failed to create notification: " . $notify_stmt->error);
                }
                $notify_stmt->close();
            } else {
                error_log("Failed to prepare notification statement: " . $conn->error);
            }
            
        } else {
            $_SESSION['error'] = "Error submitting query: " . $query_stmt->error;
        }
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $_SESSION['error'] = "Please fill in all fields.";
    }
}

// Get recent queries - modify to use user_id instead of farmer_id
$recent_queries_stmt = $conn->prepare("
    SELECT query_type, query_text, status, created_at, response_text, responded_at
    FROM farmer_queries
    WHERE farmer_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");

// Add error handling
if ($recent_queries_stmt === false) {
    die("Error preparing recent queries statement: " . $conn->error);
}

// Initialize $recent_queries as empty array by default
$recent_queries = [];

// Only try to execute if we have a valid statement and farmer_id
if ($recent_queries_stmt && isset($farmerData['farmer_id'])) {
    if (!$recent_queries_stmt->bind_param("i", $farmerData['farmer_id'])) {
        die("Error binding parameters: " . $recent_queries_stmt->error);
    }
    
    if (!$recent_queries_stmt->execute()) {
        die("Error executing recent queries statement: " . $recent_queries_stmt->error);
    }
    
    $result = $recent_queries_stmt->get_result();
    if ($result) {
        $recent_queries = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        die("Error getting result: " . $recent_queries_stmt->error);
    }
}

$username = isset($farmerData['username']) && !empty($farmerData['username']) 
    ? htmlspecialchars($farmerData['username']) 
    : (isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Farmer');

// Get farmer's location from users table instead of farmers table
$location_stmt = $conn->prepare("
    SELECT farm_location 
    FROM users 
    WHERE id = ?
");
$location_stmt->bind_param("i", $_SESSION['user_id']);
$location_stmt->execute();
$location_result = $location_stmt->get_result()->fetch_assoc();

// Get notifications - Add error handling
$notifications_sql = "SELECT * FROM notifications 
                     WHERE user_id = ? AND is_read = 0 
                     ORDER BY created_at DESC";
$notify_stmt = mysqli_prepare($conn, $notifications_sql);
if ($notify_stmt === false) {
    // Handle prepare error
    error_log("Prepare failed: " . mysqli_error($conn));
    $notifications = []; // Set empty array as fallback
} else {
    mysqli_stmt_bind_param($notify_stmt, "i", $_SESSION['user_id']);
    mysqli_stmt_execute($notify_stmt);
    $notifications = mysqli_stmt_get_result($notify_stmt);
}

// Add this near the top of the file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $notification_id = $_POST['notification_id'];
    $update_sql = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
    $update_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($update_stmt, "ii", $notification_id, $_SESSION['user_id']);
    mysqli_stmt_execute($update_stmt);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Add this array before the HTML
$kerala_districts = [
    'Alappuzha',
    'Ernakulam',
    'Idukki',
    'Kannur',
    'Kasaragod',
    'Kollam',
    'Kottayam',
    'Kozhikode',
    'Malappuram',
    'Palakkad',
    'Pathanamthitta',
    'Thiruvananthapuram',
    'Thrissur',
    'Wayanad'
];

// Add this array after the $kerala_districts array
$district_places = [
    'Idukki' => [
        'Munnar',
        'Thekkady',
        'Vagamon',
        'Peermade',
        'Adimali',
        'Thodupuzha',
        'Kattappana',
        'Nedumkandam'
    ],
    'Wayanad' => [
        'Sulthan Bathery',
        'Kalpetta',
        'Mananthavady',
        'Vythiri',
        'Meenangadi',
        'Ambalavayal',
        'Pulpally',
        'Meppadi'
    ]
];

// Add this function near the top of the file, after the database connection code
function getFarmerName($conn, $user_id) {
    $query = "SELECT username FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row['username'] ?? 'Unknown Farmer';
    }
    return 'Unknown Farmer';
}

// Add this function to parse and format the cardamom price data
function getLatestCardamomPrices() {
    // In a real implementation, this would fetch data from the Spices Board API or scrape the website
    // For now, we'll use the latest data from the website as of 25-Mar-2025
    
    // Last 3 days of price data (most recent first)
    return [
        [
            'date' => '25-Mar-2025',
            'small' => [
                'max' => '3,103.00',
                'avg' => '2,532.68',
                'auctioneer' => 'SUGANDHAGIRI SPICES PROMOTERS & TRADERS Pvt Ltd'
            ],
            'large' => [
                'badadana' => '1,800.00', // Highest price from Siliguri market
                'chotadana' => '1,588.00', // From Siliguri market
                'market' => 'Siliguri'
            ]
        ],
        [
            'date' => '24-Mar-2025',
            'small' => [
                'max' => '3,090.00',
                'avg' => '2,510.45',
                'auctioneer' => 'CARDAMOM PLANTERS MARKETING CO-OP SOCIETY'
            ],
            'large' => [
                'badadana' => '1,750.00',
                'chotadana' => '1,570.00',
                'market' => 'Siliguri'
            ]
        ],
        [
            'date' => '23-Mar-2025',
            'small' => [
                'max' => '2,963.00',
                'avg' => '2,480.30',
                'auctioneer' => 'MAS ENTERPRISES LTD'
            ],
            'large' => [
                'badadana' => '1,720.00',
                'chotadana' => '1,550.00',
                'market' => 'Gangtok'
            ]
        ]
    ];
}

// Add this new function to display formatted cardamom price history
function displayCardamomPriceHistory($prices) {
    $html = '<div class="price-history">';
    
    foreach ($prices as $index => $day) {
        $html .= '<div class="price-day ' . ($index === 0 ? 'latest' : '') . '">';
        $html .= '<div class="price-date">' . $day['date'] . '</div>';
        
        $html .= '<div class="price-type small">';
        $html .= '<h4>Small Cardamom</h4>';
        $html .= '<p>Max: ₹' . $day['small']['max'] . '/kg</p>';
        $html .= '<p>Avg: ₹' . $day['small']['avg'] . '/kg</p>';
        $html .= '<small>' . $day['small']['auctioneer'] . '</small>';
        $html .= '</div>';
        
        $html .= '<div class="price-type large">';
        $html .= '<h4>Large Cardamom</h4>';
        $html .= '<p>Badadana: ₹' . $day['large']['badadana'] . '/kg</p>';
        $html .= '<p>Chotadana: ₹' . $day['large']['chotadana'] . '/kg</p>';
        $html .= '<small>Market: ' . $day['large']['market'] . '</small>';
        $html .= '</div>';
        
        $html .= '</div>';
    }
    
    $html .= '<div class="price-footer">';
    $html .= '<small>Source: <a href="https://www.indianspices.com/indianspices/marketing/price/domestic/daily-price.html" target="_blank">Spices Board India</a></small>';
    $html .= '</div>';
    
    $html .= '</div>';
    return $html;
}

// In the notifications section, replace the standalone case with a proper switch statement
if ($notifications && mysqli_num_rows($notifications) > 0) {
    while ($notification = mysqli_fetch_assoc($notifications)) {
        switch ($notification['type']) {
            case 'market_updates':
                $prices = getLatestCardamomPrices();
                $latest = $prices[0]; // Get the most recent day's data
                
                echo '<div class="notification-item market">
                        <i class="fas fa-chart-line"></i>
                        <div class="notification-content">
                            <h4>Cardamom Market Update</h4>
                            <p>Latest Prices (as of ' . $latest['date'] . '):<br>
                               Small Cardamom: ₹' . $latest['small']['max'] . '/kg (Max)<br>
                               Large Cardamom: ₹' . $latest['large']['badadana'] . '/kg (Badadana)
                            </p>
                            <button class="show-more-prices" onclick="togglePriceHistory()">Show Price History</button>
                            <div id="priceHistoryContainer" style="display: none;">
                                ' . displayCardamomPriceHistory($prices) . '
                            </div>
                            <small>Source: <a href="https://www.indianspices.com/indianspices/marketing/price/domestic/daily-price.html" target="_blank">Spices Board India</a></small>
                        </div>
                      </div>';
                break;
            // Add other notification types here if needed
            default:
                // Handle other notification types
                break;
        }
    }
}

// Add this function near the top of the file, after the database connection code
function getAIResponse($query) {
    $hour = date('H');
    $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Farmer';
    $query = strtolower($query);
    
    // Handle greetings
    if (preg_match('/^(hi|hello|hey|hai|greetings)/i', $query)) {
        $greeting = '';
        if ($hour >= 5 && $hour < 12) {
            $greeting = 'Good morning';
        } elseif ($hour >= 12 && $hour < 17) {
            $greeting = 'Good afternoon';
        } elseif ($hour >= 17 && $hour < 22) {
            $greeting = 'Good evening';
        } else {
            $greeting = 'Hello';
        }
        
        return $greeting . ", " . $username . "! 👋\n\n" .
               "I'm your AI Farming Assistant, here to help with your cardamom cultivation queries.\n\n" .
               "You can ask me about:\n" .
               "• Planting techniques and timing\n" .
               "• Irrigation and water management\n" .
               "• Disease identification and control\n" .
               "• Pest management\n" .
               "• Fertilizer recommendations\n" .
               "• Harvesting guidelines\n" .
               "• Weather impacts\n" .
               "• Market prices and trends\n\n" .
               "Example questions:\n" .
               "1. \"When is the best time to plant cardamom?\"\n" .
               "2. \"How often should I water my plants?\"\n" .
               "3. \"What are signs of capsule rot?\"\n" .
               "4. \"Which fertilizers work best for cardamom?\"\n\n" .
               "Feel free to ask any questions, and I'll provide detailed guidance based on best practices!";
    }
    
    // Default response if no greeting is matched
    return "I'm here to help with your cardamom farming questions. What would you like to know?";
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Farmer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Add Google Translate Script -->
    <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement(
                {
                    pageLanguage: 'en',
                    includedLanguages: 'en,ml', // en for English, ml for Malayalam
                    layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
                    autoDisplay: false
                },
                'google_translate_element'
            );
        }
    </script>
    <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

    <style>
        :root {
            --primary-color: #2D5A27;
            --primary-dark: #1A3A19;
            --accent-color: #8B9D83;
            --text-color: #333333;
            --bg-color: #f5f5f5;
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
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
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
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .cardamom-card:hover {
            transform: translateY(-5px);
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
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        .tip-item {
            margin-bottom: 15px;
            padding-left: 20px;
            border-left: 3px solid var(--accent-color);
            transition: transform 0.3s ease;
        }

        .tip-item:hover {
            transform: translateX(5px);
        }

        .layout-container {
            display: flex;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            position: relative;
        }

        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 20px;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .farmer-profile {
            padding: 20px;
            text-align: center;
        }

        .farmer-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
        }

        .farmer-location {
            margin-top: 10px;
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            font-size: 0.9em;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--accent-color);
        }

        .farmer-location i {
            color: #ff6b6b;
            font-size: 1em;
        }

        .nav-menu {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 250px);
            justify-content: space-between;
        }

        .nav-menu-items {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .nav-item {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            transition: 0.3s;
            border-radius: 8px;
            margin: 0 10px;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }

        .nav-item.active {
            background: rgba(255,255,255,0.2);
        }

        .nav-item i {
            margin-right: 10px;
            width: 20px;
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

        .data-form {
            padding: 20px 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .form-group {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
        }

        .input-group {
            margin-bottom: 15px;
        }

        .input-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-color);
            font-weight: 500;
        }

        .input-group input,
        .input-group select,
        .input-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .input-group input:focus,
        .input-group select:focus,
        .input-group textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.1);
        }

        .submit-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 20px;
        }

        .submit-btn:hover {
            background: var(--primary-dark);
        }

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .welcome-section {
            background: linear-gradient(180deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }

        .welcome-section h1 {
            font-size: 2.5em;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .welcome-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .welcome-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
            backdrop-filter: blur(5px);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .welcome-card:hover {
            transform: translateY(-5px);
        }

        .welcome-card i {
            font-size: 2.5em;
            margin-bottom: 15px;
            color: var(--accent-color);
        }

        .welcome-card h3 {
            font-size: 1.3em;
            margin-bottom: 10px;
            color: white;
        }

        .welcome-card p {
            font-size: 0.95em;
            line-height: 1.5;
            color: rgba(255, 255, 255, 0.9);
        }

        .quick-guide {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .guide-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .guide-item {
            text-align: center;
            padding: 20px;
            background: var(--bg-color);
            border-radius: 10px;
            transition: transform 0.3s ease;
        }

        .guide-item:hover {
            transform: translateY(-5px);
            background: rgba(139, 157, 131, 0.1);
        }

        .guide-item i {
            font-size: 2em;
            color: var(--primary-color);
            margin-bottom: 15px;
        }

        .guide-item h4 {
            color: var(--text-color);
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .guide-item p {
            color: #666;
            font-size: 0.9em;
        }

        .fas.fa-hand-wave {
            animation: wave 1s infinite;
        }

        @keyframes wave {
            0% { transform: rotate(0deg); }
            25% { transform: rotate(20deg); }
            50% { transform: rotate(0deg); }
            75% { transform: rotate(-20deg); }
            100% { transform: rotate(0deg); }
        }

        .horizontal-layout {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
        }

        .cardamom-section {
            flex: 2;
        }

        .tasks-section {
            flex: 1;
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .cardamom-types {
            display: flex;
            gap: 20px;
            overflow-x: auto;
            padding: 10px 0;
        }

        .cardamom-card {
            min-width: 300px;
            flex: 1;
        }

        .task-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .task-item {
            padding: 15px;
            background: var(--bg-color);
            border-radius: 8px;
            margin-bottom: 10px;
        }

        .task-title {
            font-weight: bold;
            color: var(--primary-color);
        }

        .task-date {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }

        .location-weather-section {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .location-card {
            padding: 20px;
        }

        .weather-info {
            margin-top: 20px;
            padding: 15px;
            background: var(--bg-color);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .weather-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .weather-details p {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1em;
            transition: transform 0.3s ease;
        }

        .weather-details i {
            color: var(--primary-color);
        }

        .weather-details p:hover {
            transform: translateX(5px);
        }

        .location-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            margin-top: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 1em;
            transition: all 0.3s ease;
        }

        .location-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .location-form {
            margin: 20px 0;
            padding: 20px;
            background: var(--bg-color);
            border-radius: 10px;
        }

        .location-form .input-group {
            margin-bottom: 15px;
        }

        .location-form input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .current-location {
            margin: 15px 0;
            padding: 10px;
            background: #e8f5e9;
            border-radius: 5px;
        }

        .weather-info {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        /* Update the soil test alert styling */
        .alert-soil-test {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 25px;
            border-radius: 15px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            transform: translateY(0);
            transition: all 0.3s ease;
            animation: pulseAlert 2s infinite;
        }

        .alert-soil-test:hover {
            transform: translateY(-5px);
        }

        .soil-test-icon {
            background: rgba(255, 255, 255, 0.2);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .soil-test-icon i {
            font-size: 28px;
            color: white;
            animation: bounce 2s infinite;
        }

        .soil-test-message {
            flex: 1;
        }

        .soil-test-message strong {
            font-size: 1.3em;
            display: block;
            margin-bottom: 8px;
        }

        .soil-test-message p {
            margin: 0;
            font-size: 1.1em;
            opacity: 0.9;
        }

        .soil-test-message a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            padding: 8px 20px;
            border-radius: 25px;
            margin-left: 15px;
            transition: all 0.3s ease;
        }

        .soil-test-message a:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(5px);
        }

        @keyframes pulseAlert {
            0% {
                box-shadow: 0 4px 20px rgba(45, 90, 39, 0.2);
            }
            50% {
                box-shadow: 0 4px 30px rgba(45, 90, 39, 0.4);
            }
            100% {
                box-shadow: 0 4px 20px rgba(45, 90, 39, 0.2);
            }
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-5px);
            }
        }

        .nav-menu-bottom {
            padding-top: 15px;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: auto;
        }

        .logout-btn {
            color: #ff6b6b !important;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background-color: #ff6b6b !important;
            color: white !important;
        }

        .weather-banner {
            background: linear-gradient(180deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .weather-banner-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .weather-info-header {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .weather-icon {
            font-size: 2.5em;
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: float 3s ease-in-out infinite;
        }

        .weather-details-header {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .weather-location {
            font-size: 1.1em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .weather-temp {
            font-size: 2em;
            font-weight: bold;
        }

        .weather-description {
            font-size: 1.1em;
            opacity: 0.9;
        }

        .weather-extra-info {
            display: flex;
            gap: 15px;
            margin-top: 5px;
            font-size: 0.9em;
        }

        .weather-extra-info span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .weather-link {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 25px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .weather-link:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        @keyframes float {
            0% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-5px);
            }
            100% {
                transform: translateY(0px);
            }
        }

        @media (max-width: 768px) {
            .weather-banner-content {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .weather-info-header {
                flex-direction: column;
            }
            
            .weather-extra-info {
                justify-content: center;
            }
            
            .weather-link {
                margin-top: 15px;
            }
        }

        .info-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-top: 30px;
        }

        @media (max-width: 992px) {
            .info-columns {
                grid-template-columns: 1fr;
            }
        }

        .cardamom-section {
            margin-bottom: 30px;
        }

        .cardamom-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .location-form select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            background-color: white;
            cursor: pointer;
        }

        .location-form select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 5px rgba(45, 106, 79, 0.2);
        }

        .location-form select option {
            padding: 10px;
        }

        .location-update-section {
            padding: 0 20px;
            margin: -15px 0 30px 0;
            position: relative;
            z-index: 10;
        }

        .location-card-wrapper {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .location-card-content {
            padding: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .location-header {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .location-icon-wrapper {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .location-icon-wrapper i {
            font-size: 24px;
            color: white;
        }

        .location-text h3 {
            margin: 0;
            color: var(--text-color);
            font-size: 1.2em;
            font-weight: 600;
        }

        .current-location {
            margin: 5px 0 0 0;
            color: #666;
            font-size: 1.1em;
        }

        .update-location-toggle {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1em;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .update-location-toggle:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .location-update-form {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            background: #f8f9fa;
        }

        .location-update-form.show {
            max-height: 200px;
        }

        .form-content {
            padding: 25px;
            display: flex;
            gap: 20px;
            align-items: center;
            border-top: 1px solid #eee;
        }

        .select-wrapper {
            position: relative;
            flex: 1;
        }

        .select-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            z-index: 1;
        }

        .select-wrapper select {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 1em;
            appearance: none;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .select-wrapper select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(45, 106, 79, 0.1);
        }

        .select-wrapper select:hover {
            border-color: var(--primary-color);
        }

        .submit-location-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1em;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .submit-location-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        @media (max-width: 768px) {
            .location-card-content {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }

            .location-header {
                flex-direction: column;
            }

            .form-content {
                flex-direction: column;
            }

            .submit-location-btn {
                width: 100%;
                justify-content: center;
            }
        }

        .query-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .query-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .query-form {
            background: var(--bg-color);
            padding: 25px;
            border-radius: 10px;
        }

        .query-form .input-group {
            margin-bottom: 20px;
        }

        .query-form label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-color);
            font-weight: 500;
        }

        .query-form select,
        .query-form textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }

        .query-form textarea {
            resize: vertical;
            min-height: 120px;
        }

        .submit-query-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .submit-query-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .recent-queries {
            background: var(--bg-color);
            padding: 25px;
            border-radius: 10px;
            max-height: 600px;
            overflow-y: auto;
        }

        .query-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }

        .query-card:hover {
            transform: translateY(-2px);
        }

        .query-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .query-type {
            background: var(--primary-color);
            color: white;
            padding: 4px 12px;
            border-radius: 25px;
            font-size: 0.9em;
        }

        .query-status {
            padding: 4px 12px;
            border-radius: 25px;
            font-size: 0.9em;
        }

        .query-status.pending {
            background: #ffd700;
            color: #856404;
        }

        .query-status.answered {
            background: #28a745;
            color: white;
        }

        .query-text {
            margin: 10px 0;
            color: var(--text-color);
        }

        .query-meta {
            font-size: 0.9em;
            color: #666;
        }

        .query-response {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .response-date {
            display: block;
            font-size: 0.9em;
            color: #666;
            margin-top: 8px;
        }

        .no-queries {
            text-align: center;
            color: #666;
            padding: 20px;
        }

        @media (max-width: 768px) {
            .query-container {
                grid-template-columns: 1fr;
            }
        }

        /* Add styles for translate widget */
        .translate-widget {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1001;
            background: white;
            padding: 10px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        /* Style the Google Translate widget */
        .goog-te-gadget {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
        }

        .goog-te-gadget-simple {
            border: none !important;
            padding: 8px !important;
            border-radius: 4px !important;
            background-color: #f8f9fa !important;
        }

        .goog-te-gadget-icon {
            display: none;
        }

        .goog-te-menu-value {
            color: var(--primary-color) !important;
            text-decoration: none !important;
        }

        .goog-te-menu-value span {
            text-decoration: none !important;
        }

        .notifications-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .notification-card {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
            animation: slideIn 0.3s ease-out;
            transition: transform 0.3s ease;
        }

        .notification-card:hover {
            transform: translateX(5px);
        }

        .notification-icon {
            background: var(--primary-color);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }

        .notification-content {
            flex: 1;
        }

        .notification-content p {
            margin: 0;
            color: var(--text-color);
        }

        .notification-content small {
            color: #666;
        }

        .mark-read-btn {
            background: none;
            border: none;
            color: var(--primary-color);
            cursor: pointer;
            padding: 5px;
            transition: transform 0.2s ease;
        }

        .mark-read-btn:hover {
            transform: scale(1.2);
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        /* Add smooth animations */
        .main-content > * {
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { 
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Update root variables to match soil test theme */
        :root {
            --primary-color: #2D5A27;
            --primary-dark: #1A3A19;
            --accent-color: #8B9D83;
            --text-color: #333333;
            --bg-color: #f5f5f5;
            --sidebar-width: 250px;
        }

        /* Update sidebar styling */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--primary-color), var(--primary-dark));
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .farmer-profile {
            padding: 20px;
            text-align: center;
        }

        .farmer-avatar {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
        }

        /* Update navigation menu */
        .nav-menu {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 250px);
            justify-content: space-between;
        }

        .nav-menu-items {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .nav-item {
            padding: 15px 20px;
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            transition: 0.3s;
            border-radius: 8px;
            margin: 0 10px;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }

        .nav-item.active {
            background: rgba(255,255,255,0.2);
        }

        .nav-item i {
            margin-right: 10px;
            width: 20px;
        }

        /* Update main content area */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
        }

        /* Update card styles */
        .recommendation-card, .weather-banner, .alert-soil-test, .quick-guide, .welcome-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        /* Update weather banner */
        .weather-banner {
            background: linear-gradient(180deg, var(--primary-color), var(--primary-dark));
        }

        /* Update buttons */
        .submit-location-btn, .update-location-toggle, .shop-now-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-location-btn:hover, .update-location-toggle:hover, .shop-now-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* Update status badges */
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            color: white;
            font-size: 0.8em;
            font-weight: 500;
        }

        /* Remove back to dashboard button */
        .back-button {
            display: none;
        }

        /* Update icons */
        .nutrient-icon {
            width: 40px;
            height: 40px;
            background: rgba(45, 90, 39, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
        }

        .nutrient-icon i {
            color: var(--primary-color);
            font-size: 20px;
        }

        /* Update welcome section */
        .welcome-section {
            background: linear-gradient(180deg, var(--primary-color), var(--primary-dark));
            color: white;
        }

        /* Update guide items */
        .guide-item {
            background: var(--bg-color);
            border-radius: 10px;
            transition: transform 0.3s ease;
        }

        .guide-item:hover {
            transform: translateY(-5px);
            background: rgba(139, 157, 131, 0.1);
        }

        /* Update logout button */
        .logout-btn {
            color: #ff6b6b !important;
            transition: all 0.3s ease;
        }A

        .logout-btn:hover {
            background-color: #ff6b6b !important;
            color: white !important;
        }

        /* Add animations */
        .animate-fadeInUp {
            animation: fadeInUp 0.5s ease forwards;
        }

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

        /* Add these styles to your existing CSS */
        .notifications-panel {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .panel-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }

        .panel-header h3 {
            margin: 0;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .notifications-content {
            padding: 15px;
        }

        .notification-item {
            display: flex;
            align-items: start;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .notification-item:hover {
            transform: translateX(5px);
            background: #f0f1f2;
        }

        .notification-item i {
            font-size: 20px;
            margin-right: 15px;
            margin-top: 3px;
        }

        .notification-content {
            flex: 1;
        }

        .notification-content h4 {
            margin: 0 0 5px 0;
            color: var(--primary-color);
        }

        .notification-content p {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
        }

        .weather i {
            color: #f39c12;
        }

        .harvest i {
            color: #27ae60;
        }

        .market i {
            color: #2980b9;
        }

        .no-notifications {
            text-align: center;
            padding: 30px;
            color: #666;
        }

        .no-notifications i {
            font-size: 40px;
            margin-bottom: 10px;
            opacity: 0.5;
        }

        .notification-item.market p {
            line-height: 1.4;
            margin: 5px 0;
        }

        .notification-item.market small {
            color: #666;
            font-size: 0.8em;
            display: block;
            margin-top: 5px;
        }

        /* Add these styles in the <style> section */
        .running-message {
            position: fixed;
            bottom: 80px; /* Increased to make room for soil test message */
            right: -300px;
            background: linear-gradient(135deg, #2980b9, #3498db);
            color: white;
            padding: 15px 25px;
            border-radius: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 999;
            animation: slideInOut 15s linear infinite;
            cursor: pointer;
        }

        .running-message i {
            font-size: 20px;
            animation: pulse 2s infinite;
        }

        .running-message-content {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .running-message-text {
            font-size: 1.1em;
            font-weight: 500;
        }

        .running-message-link {
            color: white;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.2);
            padding: 5px 15px;
            border-radius: 15px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .running-message-link:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(5px);
        }

        @keyframes slideInOut {
            0% {
                right: -300px;
            }
            10% {
                right: 20px;
            }
            90% {
                right: 20px;
            }
            100% {
                right: -300px;
            }
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.2);
            }
            100% {
                transform: scale(1);
            }
        }

        .suitability-message {
            margin: 15px 0;
            animation: fadeIn 0.3s ease-out;
        }

        .suitability-indicator {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .suitability-indicator.suitable {
            background: linear-gradient(to right, #e3f5e1, #f0f9ef);
            border-left: 4px solid #2D5A27;
        }

        .suitability-indicator.unsuitable {
            background: linear-gradient(to right, #ffe5e5, #fff0f0);
            border-left: 4px solid #dc3545;
        }

        .suitability-indicator i {
            font-size: 24px;
            margin-top: 3px;
        }

        .suitability-indicator.suitable i {
            color: #2D5A27;
        }

        .suitability-indicator.unsuitable i {
            color: #dc3545;
        }

        .suitability-content h4 {
            margin: 0 0 10px 0;
            color: var(--text-color);
            font-size: 1.1em;
        }

        .suitability-content p {
            margin: 5px 0;
            color: #666;
            font-size: 0.95em;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Also remove any related styles */
        .back-button-container,
        .dashboard-link {
            display: none !important;
        }

        .quick-nav {
            display: flex;
            gap: 20px;
            margin: 30px 0;
            padding: 0 10px;
        }

        .quick-nav-btn {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            background: white;
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .quick-nav-btn:hover {
            transform: translateY(-5px);
            background: var(--primary-color);
            color: white;
        }

        .quick-nav-btn i {
            font-size: 24px;
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .quick-nav-btn:hover i {
            color: white;
        }

        .quick-nav-btn span {
            font-size: 1.1em;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .quick-nav-btn small {
            font-size: 0.85em;
            opacity: 0.8;
        }

        @media (max-width: 768px) {
            .quick-nav {
                flex-direction: column;
                gap: 15px;
            }
        }

        .chat-widget {
            position: fixed;
            bottom: 20px;
            left: 20px;
            width: 350px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            overflow: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .chat-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            user-select: none;
        }

        .chat-header i {
            font-size: 1.5em;
            color: #fff;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px;
            border-radius: 50%;
        }

        .chat-header span {
            flex: 1;
            font-size: 1.1em;
            font-weight: 600;
        }

        .chat-minimize {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            transition: background 0.3s ease;
        }

        .chat-minimize:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .chat-body {
            height: 400px;
            display: flex;
            flex-direction: column;
            transition: height 0.3s ease;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            scroll-behavior: smooth;
        }

        .message {
            max-width: 85%;
            margin-bottom: 15px;
            padding: 12px 16px;
            border-radius: 15px;
            font-size: 0.95em;
            line-height: 1.4;
            white-space: pre-wrap;
        }

        .bot-message {
            background: #f0f4f9;
            color: #2c3e50;
            border-bottom-left-radius: 5px;
            margin-right: auto;
            /* Add styling for markdown-like formatting */
            font-family: 'SF Mono', 'Consolas', monospace;
        }

        .bot-message strong,
        .bot-message b {
            color: var(--primary-color);
        }

        .bot-message ul {
            margin: 5px 0;
            padding-left: 20px;
        }

        .bot-message li {
            margin: 3px 0;
        }

        .user-message {
            background: var(--primary-color);
            color: white;
            border-bottom-right-radius: 5px;
            margin-left: auto;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .chat-input {
            padding: 15px;
            border-top: 1px solid #eef2f5;
            display: flex;
            gap: 10px;
            background: white;
        }

        .chat-input input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #eef2f5;
            border-radius: 25px;
            font-size: 0.95em;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .chat-input input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(45, 90, 39, 0.1);
        }

        .chat-input button {
            background: var(--primary-color);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .chat-input button:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }

        .chat-widget.minimized .chat-body {
            height: 0;
        }

        /* Add styles for markdown-like formatting in bot messages */
        .bot-message h1,
        .bot-message h2,
        .bot-message h3 {
            margin: 10px 0;
            color: var(--primary-dark);
        }

        .bot-message code {
            background: rgba(0, 0, 0, 0.05);
            padding: 2px 5px;
            border-radius: 4px;
            font-family: 'SF Mono', 'Consolas', monospace;
            font-size: 0.9em;
        }

        .bot-message pre {
            background: rgba(0, 0, 0, 0.05);
            padding: 10px;
            border-radius: 8px;
            overflow-x: auto;
            margin: 10px 0;
        }

        /* Add custom scrollbar for chat messages */
        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Add loading animation for bot responses */
        .bot-message.loading {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .typing-indicator {
            display: flex;
            gap: 4px;
        }

        .typing-indicator span {
            width: 8px;
            height: 8px;
            background: var(--primary-color);
            border-radius: 50%;
            animation: typing 1s infinite;
        }

        .typing-indicator span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-indicator span:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typing {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .assistant-tips {
            text-align: left;
            margin-top: 10px;
            padding-left: 20px;
        }

        .assistant-tips li {
            margin: 5px 0;
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9em;
        }

        .sample-questions {
            text-align: left;
            padding-left: 15px;
            margin-top: 10px;
        }

        .sample-questions li {
            font-size: 0.85em;
            color: #666;
            margin: 5px 0;
            font-style: italic;
        }

        .guide-item ul {
            list-style-type: none;
        }

        .guide-item ul li::before {
            content: "•";
            color: var(--primary-color);
            font-weight: bold;
            display: inline-block;
            width: 1em;
            margin-left: -1em;
        }

        /* Update chat widget styles */
        .chat-widget {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 350px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.2);
            z-index: 1000;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .chat-header {
            background: var(--primary-color);
            color: white;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .chat-header i {
            font-size: 1.2em;
        }

        /* Add a first-time user tooltip */
        .chat-widget::before {
            content: "👋 Click here to get farming assistance!";
            position: absolute;
            top: -40px;
            right: 0;
            background: #333;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 0.9em;
            animation: bounce 2s infinite;
            display: none;
        }

        .chat-widget.first-time::before {
            display: block;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        /* Running Message Styles */
        .soil-test-running-message {
            position: fixed;
            bottom: 20px;
            right: -400px; /* Start off-screen */
            background: linear-gradient(135deg, #2D5A27, #1A3A19);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            z-index: 999;
            animation: slideInOutSoil 15s linear infinite;
            animation-delay: 1s; /* Delay after weather message */
            transition: transform 0.3s ease;
            width: auto;
            min-width: 300px;
        }

        .soil-test-running-message:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .message-icon {
            background: rgba(255, 255, 255, 0.2);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .message-icon i {
            font-size: 20px;
            color: white;
            animation: pulseIcon 2s infinite;
        }

        .message-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
        }

        .message-text {
            display: flex;
            flex-direction: column;
        }

        .primary-text {
            font-size: 1.1em;
            font-weight: 600;
        }

        .sub-text {
            font-size: 0.85em;
            opacity: 0.8;
        }

        .message-action {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .message-action:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(5px);
        }

        @keyframes slideInOutSoil {
            0% {
                right: -400px;
                opacity: 0;
            }
            5% {
                right: 20px;
                opacity: 1;
            }
            90% {
                right: 20px;
                opacity: 1;
            }
            100% {
                right: -400px;
                opacity: 0;
            }
        }

        @keyframes pulseIcon {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.2);
            }
            100% {
                transform: scale(1);
            }
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .soil-test-running-message {
                bottom: 80px; /* Position above weather message */
                min-width: 250px;
            }

            .message-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .message-action {
                font-size: 0.8em;
                padding: 6px 12px;
            }
        }

        /* Add urgency indicators */
        .soil-test-running-message.urgent {
            background: linear-gradient(135deg, #d32f2f, #b71c1c);
            animation: slideInOutSoil 12s linear infinite, urgentPulse 2s infinite;
        }

        @keyframes urgentPulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.02);
            }
        }

        /* Scroll button styles */
        .scroll-btn {
            position: fixed;
            right: 20px;
            width: 40px;
            height: 40px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            z-index: 1000;
            opacity: 0.7;
        }

        .scroll-btn:hover {
            opacity: 1;
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        .scroll-to-top {
            bottom: 80px;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }

        .scroll-to-top.visible {
            opacity: 0.7;
            visibility: visible;
        }

        .scroll-to-bottom {
            bottom: 30px;
        }

        /* Add responsive styles for mobile */
        @media (max-width: 768px) {
            .scroll-btn {
                width: 35px;
                height: 35px;
                right: 15px;
            }
            
            .scroll-to-top {
                bottom: 70px;
            }
            
            .scroll-to-bottom {
                bottom: 25px;
            }
        }

        /* Add these styles for the running messages */
        .running-message {
            position: fixed;
            left: 270px; /* Adjust based on your sidebar width */
            bottom: 20px;
            background: rgba(255, 255, 255, 0.95);
            padding: 10px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            z-index: 999;
            animation: slideInLeft 0.3s ease-out;
        }

        .weather-message {
            bottom: 80px; /* Position above soil test message */
            border-left: 4px solid #03A9F4;
        }

        .soil-message {
            border-left: 4px solid #4CAF50;
        }

        @keyframes slideInLeft {
            from { transform: translateX(-100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .running-message i {
            font-size: 16px;
        }

        .weather-message i {
            color: #03A9F4;
        }

        .soil-message i {
            color: #4CAF50;
        }

        /* Simplified styles for weather message only */
        .running-message {
            position: fixed;
            left: 270px;
            bottom: 20px;
            background: rgba(255, 255, 255, 0.95);
            padding: 10px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            z-index: 999;
            animation: slideInLeft 0.3s ease-out;
        }

        .weather-message {
            border-left: 4px solid #03A9F4;
        }

        @keyframes slideInLeft {
            from { transform: translateX(-100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .running-message i {
            font-size: 16px;
            color: #03A9F4;
        }

        /* Update the Farming Assistant positioning */
        .farming-assistant {
            position: fixed;
            right: 20px !important; /* Force right positioning */
            left: auto !important; /* Remove any left positioning */
            bottom: 20px;
            width: 300px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
        }

        /* Keep the existing green header style but update border radius */
        .farming-assistant-header {
            background: var(--primary-color);
            color: white;
            padding: 15px;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Update the chat message container */
        .farming-assistant-messages {
            padding: 15px;
            max-height: 400px;
            overflow-y: auto;
            background: #fff;
            border-radius: 0 0 12px 12px;
        }

        /* Update message bubbles */
        .assistant-message {
            background: #f0f2f5;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 10px;
            max-width: 85%;
        }

        /* Add animation for sliding from right */
        @keyframes slideInRight {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }

        .farming-assistant {
            animation: slideInRight 0.3s ease-out;
        }

        /* Make it responsive */
        @media (max-width: 768px) {
            .farming-assistant {
                width: 280px;
                right: 10px !important;
            }
        }

        /* Style for the minimize button */
        .minimize-btn {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 5px;
        }

        /* Style for the message input area */
        .message-input {
            display: flex;
            padding: 10px;
            border-top: 1px solid #eee;
        }

        .message-input input {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 20px;
            margin-right: 8px;
        }

        .message-input button {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            cursor: pointer;
        }

        /* Update the position of existing farming assistant */
        .farming-assistant, #farmingAssistant {
            position: fixed !important;
            right: 30px !important;  /* Position on right side */
            left: auto !important;   /* Remove any left positioning */
            bottom: 30px !important;
            width: 350px !important;
            z-index: 1000 !important;
        }

        /* Ensure proper animation */
        @keyframes slideInRight {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }

        .farming-assistant, #farmingAssistant {
            animation: slideInRight 0.3s ease-out;
        }

        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .farming-assistant, #farmingAssistant {
                width: 300px !important;
                right: 20px !important;
                bottom: 20px !important;
            }
        }

        .ai-assistant {
            position: fixed;
            right: 30px;  /* Changed from left positioning */
            bottom: 30px;
            width: 600px;  /* Increased from 480px */
            height: 400px; /* Added explicit height */
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.15);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            overflow: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .ai-assistant-header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 15px 25px;  /* Increased padding */
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
        }

        .ai-assistant-body {
            height: 320px;  /* Decreased from 600px */
            display: flex;
            flex-direction: column;
        }

        .ai-messages {
            flex: 1;
            overflow-y: auto;
            padding: 25px;  /* Increased padding */
        }

        .ai-message {
            display: flex;
            gap: 15px;  /* Increased gap */
            margin-bottom: 25px;  /* Increased margin */
        }

        .ai-avatar {
            width: 42px;  /* Increased size */
            height: 42px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;  /* Prevent avatar from shrinking */
        }

        .message-content {
            flex: 1;
            background: #f5f7f9;
            padding: 16px;  /* Increased padding */
            border-radius: 15px;
            border-top-left-radius: 4px;
            font-size: 14px;  /* Added font size */
            line-height: 1.6;  /* Added line height */
        }

        .ai-input {
            padding: 20px;  /* Increased padding */
            border-top: 1px solid #eee;
            display: flex;
            gap: 12px;
            background: white;  /* Added background */
        }

        .ai-input input {
            flex: 1;
            padding: 12px 20px;  /* Increased padding */
            border: 1px solid #ddd;
            border-radius: 25px;  /* Increased border radius */
            outline: none;
            font-size: 14px;  /* Added font size */
        }

        .ai-input button {
            background: var(--primary-color);
            color: white;
            border: none;
            width: 42px;  /* Increased size */
            height: 42px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        /* Add styles for better message formatting */
        .message-content p {
            margin: 0 0 12px 0;
        }

        .message-content p:last-child {
            margin-bottom: 0;
        }

        .message-content ul {
            margin: 8px 0;
            padding-left: 20px;
        }

        .message-content li {
            margin-bottom: 6px;
        }

        /* Add custom scrollbar */
        .ai-messages::-webkit-scrollbar {
            width: 8px;
        }

        .ai-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .ai-messages::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        .ai-messages::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        .greeting-message {
            margin: 10px 0;
            padding: 10px;
            background: rgba(45, 90, 39, 0.1);
            border-left: 3px solid var(--primary-color);
            border-radius: 0 8px 8px 0;
        }

        .ai-message p {
            margin-bottom: 10px;
            line-height: 1.5;
        }

        .ai-message p:first-child {
            font-size: 1.1em;
            font-weight: 500;
            color: var(--primary-color);
        }

        /* Add these new styles for horizontal quick actions and animations */
        .quick-actions {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 10px 0;
            margin-top: 15px;
            -ms-overflow-style: none;  /* Hide scrollbar for IE and Edge */
            scrollbar-width: none;  /* Hide scrollbar for Firefox */
        }

        /* Hide scrollbar for Chrome, Safari and Opera */
        .quick-actions::-webkit-scrollbar {
            display: none;
        }

        .quick-actions button {
            flex: 0 0 auto;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.3s ease, background-color 0.3s ease;
            white-space: nowrap;
        }

        .quick-actions button:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .quick-actions button i {
            animation: bounce 2s infinite;
        }

        /* Different animations for different icon types */
        .fa-seedling {
            animation: grow 2s infinite !important;
        }

        .fa-cloud-sun {
            animation: weather 4s infinite !important;
        }

        .fa-bug {
            animation: wiggle 2s infinite !important;
        }

        .fa-calendar-alt {
            animation: pulse 2s infinite !important;
        }

        .fa-robot {
            animation: wave 2s infinite !important;
        }

        /* Animation keyframes */
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        @keyframes grow {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }

        @keyframes weather {
            0% { transform: translateX(0) rotate(0); }
            25% { transform: translateX(3px) rotate(15deg); }
            75% { transform: translateX(-3px) rotate(-15deg); }
            100% { transform: translateX(0) rotate(0); }
        }

        @keyframes wiggle {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(3px); }
            75% { transform: translateX(-3px); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        @keyframes wave {
            0%, 100% { transform: rotate(0); }
            25% { transform: rotate(-20deg); }
            75% { transform: rotate(20deg); }
        }

        /* Update message content styles */
        .message-content {
            flex: 1;
            background: #f5f7f9;
            padding: 16px;
            border-radius: 15px;
            border-top-left-radius: 4px;
            font-size: 14px;
            line-height: 1.6;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Add emoji animations */
        .message-content p:first-child {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .message-content p:first-child span {
            display: inline-block;
        }

        /* Emoji specific animations */
        .message-content p:first-child span:last-child {
            animation: wave 2s infinite;
        }

        /* Update greeting message style */
        .greeting-message {
            background: rgba(45, 90, 39, 0.1);
            border-left: 3px solid var(--primary-color);
            border-radius: 0 8px 8px 0;
            padding: 12px;
            margin: 10px 0;
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Update AI assistant width and layout */
        .ai-assistant {
            position: fixed;
            right: 30px;
            bottom: 30px;
            width: 600px;  /* Increased from 480px */
            height: 400px; /* Added explicit height */
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.15);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            overflow: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .ai-assistant-body {
            height: 320px;  /* Decreased from 600px */
            display: flex;
            flex-direction: column;
        }

        /* Update quick actions to use grid layout instead of scrolling */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);  /* 3 buttons per row */
            gap: 10px;
            padding: 15px;
            margin-top: 15px;
        }

        .quick-actions button {
            width: 100%;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: transform 0.3s ease, background-color 0.3s ease;
            font-size: 0.95em;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .ai-assistant {
                width: 90%;
                height: 350px; /* Decreased height for mobile */
                right: 5%;
                left: 5%;
                bottom: 20px;
            }

            .ai-assistant-body {
                height: 270px; /* Decreased height for mobile */
            }

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);  /* 2 buttons per row on mobile */
            }
        }

        /* Keep the existing animations */
        .quick-actions button i {
            animation: bounce 2s infinite;
        }

        .fa-seedling { animation: grow 2s infinite !important; }
        .fa-cloud-sun { animation: weather 4s infinite !important; }
        .fa-bug { animation: wiggle 2s infinite !important; }
        .fa-calendar-alt { animation: pulse 2s infinite !important; }
        .fa-robot { animation: wave 2s infinite !important; }

        /* Keep existing animation keyframes */
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        @keyframes grow {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.2); }
        }

        @keyframes weather {
            0% { transform: translateX(0) rotate(0); }
            25% { transform: translateX(3px) rotate(15deg); }
            75% { transform: translateX(-3px) rotate(-15deg); }
            100% { transform: translateX(0) rotate(0); }
        }

        @keyframes wiggle {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(3px); }
            75% { transform: translateX(-3px); }
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        @keyframes wave {
            0%, 100% { transform: rotate(0); }
            25% { transform: rotate(-20deg); }
            75% { transform: rotate(20deg); }
        }

        /* Update AI assistant styling for a more professional look */
        .ai-assistant {
            position: fixed;
            right: 30px;
            bottom: 30px;
            width: 600px;
            height: 400px;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            overflow: hidden;
            transition: all 0.3s ease;
            z-index: 1000;
            border: 1px solid rgba(0, 0, 0, 0.08);
        }

        .ai-assistant-header {
            padding: 16px 20px;
            background: var(--primary-color);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .ai-assistant-title {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            font-size: 16px;
        }

        .ai-assistant-title i {
            font-size: 18px;
        }

        .ai-controls button {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .ai-controls button:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .ai-assistant-body {
            height: calc(400px - 60px); /* Adjust based on header height */
            display: flex;
            flex-direction: column;
        }

        .ai-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
        }

        .ai-message {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
        }

        .ai-avatar {
            width: 36px;
            height: 36px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }

        .message-content {
            background: white;
            padding: 12px 16px;
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.08);
            max-width: 80%;
            font-size: 14px;
            line-height: 1.5;
            color: #2c3e50;
        }

        .user-message .message-content {
            background: var(--primary-color);
            color: white;
            margin-left: auto;
        }

        .ai-input {
            padding: 16px;
            background: white;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
            display: flex;
            gap: 12px;
        }

        .ai-input input {
            flex: 1;
            padding: 10px 16px;
            border: 1px solid rgba(0, 0, 0, 0.15);
            border-radius: 24px;
            font-size: 14px;
            transition: border-color 0.2s;
        }

        .ai-input input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .ai-input button {
            background: var(--primary-color);
            color: white;
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .ai-input button:hover {
            background: var(--primary-dark);
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            margin-top: 12px;
        }

        .quick-actions button {
            background: #f8f9fa;
            border: 1px solid rgba(0, 0, 0, 0.08);
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 13px;
            color: #2c3e50;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .quick-actions button:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .quick-actions button i {
            font-size: 14px;
        }

        /* Custom scrollbar for messages */
        .ai-messages::-webkit-scrollbar {
            width: 6px;
        }

        .ai-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .ai-messages::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .ai-messages::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .ai-assistant {
                width: 90%;
                height: 350px; /* Decreased height for mobile */
                right: 5%;
                left: 5%;
                bottom: 20px;
            }

            .ai-assistant-body {
                height: calc(350px - 60px);
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }
        }

        /* Add these additional styles to your existing CSS */
        .user-message {
            flex-direction: row-reverse;
        }

        .user-message .message-content {
            background: var(--primary-color);
            color: white;
            margin-left: 0;
        }

        .typing-dots {
            display: flex;
            gap: 4px;
            padding: 8px 0;
        }

        .typing-dots span {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary-color);
            opacity: 0.4;
            animation: typing 1.4s infinite;
        }

        .typing-dots span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-dots span:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typing {
            0%, 100% {
                transform: translateY(0);
                opacity: 0.4;
            }
            50% {
                transform: translateY(-4px);
                opacity: 1;
            }
        }

        /* Professional AI Assistant Design */
        .ai-assistant {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 300px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            z-index: 1000;
        }

        .ai-header {
            background: #2D5A27;
            color: white;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ai-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .minimize-btn {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px;
        }

        .ai-messages {
            height: 400px;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            background: #f5f5f5;
        }

        .message {
            max-width: 80%;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.4;
        }

        .ai-message {
            background: white;
            align-self: flex-start;
        }

        .user-message {
            background: #2D5A27;
            color: white;
            align-self: flex-end;
        }

        .ai-input-container {
            padding: 12px;
            display: flex;
            gap: 8px;
            border-top: 1px solid #eee;
            background: white;
        }

        .ai-input-container input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 20px;
            font-size: 14px;
        }

        .send-btn {
            background: #2D5A27;
            color: white;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .send-btn:hover {
            background: #234621;
        }

        .ai-assistant.minimized {
            height: 60px;
            overflow: hidden;
        }

        .ai-assistant-header {
            padding: 16px;
            background: var(--primary-color);
            color: white;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ai-assistant-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .ai-avatar {
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .ai-controls button {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .ai-controls button:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .ai-assistant-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .ai-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
        }

        .ai-message {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            opacity: 0;
            transform: translateY(20px);
            animation: messageAppear 0.3s ease forwards;
        }

        @keyframes messageAppear {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message-content {
            background: white;
            padding: 12px 16px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            max-width: 80%;
            line-height: 1.5;
        }

        .user-message {
            flex-direction: row-reverse;
        }

        .user-message .message-content {
            background: var(--primary-color);
            color: white;
        }

        .typing-dots {
            display: flex;
            gap: 4px;
            padding: 12px 16px;
            background: white;
            border-radius: 12px;
            width: fit-content;
        }

        .typing-dots span {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary-color);
            animation: typing 1s infinite;
        }

        .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
        .typing-dots span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typing {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        .ai-input {
            padding: 16px;
            background: white;
            border-top: 1px solid #eee;
            display: flex;
            gap: 12px;
        }

        .ai-input input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 24px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .ai-input input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(45, 90, 39, 0.1);
        }

        .ai-input button {
            background: var(--primary-color);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .ai-input button:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }

        .quick-actions {
            padding: 12px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            background: white;
            border-top: 1px solid #eee;
        }

        .quick-actions button {
            background: #f8f9fa;
            border: 1px solid #eee;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 13px;
            color: var(--text-color);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .quick-actions button:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .quick-actions button i {
            font-size: 14px;
        }

        /* Custom scrollbar for messages */
        .ai-messages::-webkit-scrollbar {
            width: 6px;
        }

        .ai-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .ai-messages::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }

        .ai-messages::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .ai-assistant {
                width: 90%;
                height: 350px; /* Decreased height for mobile */
                right: 5%;
                left: 5%;
                bottom: 20px;
            }

            .ai-assistant-body {
                height: calc(350px - 60px);
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }
        }

        /* Add these additional styles to your existing CSS */
        .user-message {
            flex-direction: row-reverse;
        }

        .user-message .message-content {
            background: var(--primary-color);
            color: white;
            margin-left: 0;
        }

        .typing-dots {
            display: flex;
            gap: 4px;
            padding: 8px 0;
        }

        .typing-dots span {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary-color);
            opacity: 0.4;
            animation: typing 1.4s infinite;
        }

        .typing-dots span:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-dots span:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typing {
            0%, 100% {
                transform: translateY(0);
                opacity: 0.4;
            }
            50% {
                transform: translateY(-4px);
                opacity: 1;
            }
        }

        /* Professional AI Assistant Design */
        .ai-assistant {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 300px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            z-index: 1000;
        }

        .ai-header {
            background: #2D5A27;
            color: white;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ai-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .minimize-btn {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px;
        }

        .ai-messages {
            height: 400px;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            background: #f5f5f5;
        }

        .message {
            max-width: 80%;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.4;
        }

        .ai-message {
            background: white;
            align-self: flex-start;
        }

        .user-message {
            background: #2D5A27;
            color: white;
            align-self: flex-end;
        }

        .ai-input-container {
            padding: 12px;
            display: flex;
            gap: 8px;
            border-top: 1px solid #eee;
            background: white;
        }

        .ai-input-container input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 20px;
            font-size: 14px;
        }

        .send-btn {
            background: #2D5A27;
            color: white;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .send-btn:hover {
            background: #234621;
        }

        .ai-assistant.minimized {
            height: 60px;
            overflow: hidden;
        }

        .ai-assistant-header {
            padding: 16px;
            background: var(--primary-color);
            color: white;
            border-radius: 12px 12px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .ai-assistant-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .ai-avatar {
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .ai-controls button {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .ai-controls button:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .ai-assistant-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .ai-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background: #f8f9fa;
        }

        .ai-message {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
            opacity: 0;
            transform: translateY(20px);
            animation: messageAppear 0.3s ease forwards;
        }

        @keyframes messageAppear {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message-content {
            background: white;
            padding: 12px 16px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            max-width: 80%;
            line-height: 1.5;
        }

        .user-message {
            flex-direction: row-reverse;
        }

        .user-message .message-content {
            background: var(--primary-color);
            color: white;
        }

        .typing-dots {
            display: flex;
            gap: 4px;
            padding: 12px 16px;
            background: white;
            border-radius: 12px;
            width: fit-content;
        }

        .typing-dots span {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary-color);
            animation: typing 1s infinite;
        }

        .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
        .typing-dots span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typing {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        .ai-input {
            padding: 16px;
            background: white;
            border-top: 1px solid #eee;
            display: flex;
            gap: 12px;
        }

        .ai-input input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 24px;
            font-size: 14px;
            transition: all 0.2s;
        }

        .ai-input input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(45, 90, 39, 0.1);
        }

        .ai-input button {
            background: var(--primary-color);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .ai-input button:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }

        .quick-actions {
            padding: 12px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
            background: white;
            border-top: 1px solid #eee;
        }

        .quick-actions button {
            background: #f8f9fa;
            border: 1px solid #eee;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 13px;
            color: var(--text-color);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .quick-actions button:hover {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
            transform: translateY(-2px);
        }

        /* Add these updated styles for better formatting */
        <style>
        .message-content {
            white-space: pre-line;
            font-size: 14px;
            line-height: 2;
            padding: 16px 20px;
            letter-spacing: 0.3px;
        }

        .message-content ul, 
        .message-content ol {
            margin: 12px 0;
            padding-left: 24px;
        }

        .message-content li {
            margin: 10px 0;
            padding-left: 8px;
        }

        .ai-message:first-child .message-content {
            background: linear-gradient(to bottom right, #f0f7f0, #ffffff);
            border-left: 4px solid var(--primary-color);
        }

        /* Add spacing between question blocks */
        .message-content p {
            margin: 12px 0;
        }

        /* Style for question formatting */
        .message-content [class^="Q"] {
            margin: 16px 0;
            padding-left: 16px;
            border-left: 2px solid var(--primary-color);
        }
        </style>
    </style>
</head>
<body>
    <!-- Add translate widget at the top of body -->
    <div class="translate-widget">
        <div id="google_translate_element"></div>
    </div>

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
                <?php if (isset($location_result['farm_location']) && !empty($location_result['farm_location'])): ?>
                    <div class="farmer-location">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($location_result['farm_location']); ?>
                    </div>
                <?php endif; ?>
            </div>
            <nav class="nav-menu">
                <div class="nav-menu-items">
                    <a href="farmer.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'farmer.php' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                    
                    <a href="soil_test.php?farmer_id=<?php echo $farmerData['farmer_id']; ?>" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'soil_test.php' ? 'active' : ''; ?>">
                        <i class="fas fa-flask"></i> Soil Test
                    </a>
                    
                    <a href="fertilizerrrr.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'fertilizer_recommendations.php' ? 'active' : ''; ?>">
                        <i class="fas fa-leaf"></i> Fertilizer Guide
                    </a>
                    
                    <a href="farm_analysis.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'farm_analysis.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i> Farm Analysis
                    </a>
                    
                    <a href="schedule.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'schedule.php' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar"></i> Schedule
                    </a>
                    <a href="weather.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'weather.php' ? 'active' : ''; ?>">
                        <i class="fas fa-cloud-sun"></i> Weather
                    </a>
                    <a href="settings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </div>
                <div class="nav-menu-bottom">
                    <a href="logout.php" class="nav-item logout-btn">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </nav>
            
        </div>

        <div class="main-content">
            <div class="weather-banner">
                <?php if ($weather_data): ?>
                    <div class="weather-banner-content">
                        <div class="weather-info-header">
                            <div class="weather-icon">
                                <?php
                                $weather_code = $weather_data['weather'][0]['id'];
                                $icon_class = 'fas ';
                                
                                // Map weather codes to Font Awesome icons
                                if ($weather_code >= 200 && $weather_code < 300) {
                                    $icon_class .= 'fa-bolt'; // Thunderstorm
                                } elseif ($weather_code >= 300 && $weather_code < 400) {
                                    $icon_class .= 'fa-cloud-rain'; // Drizzle
                                } elseif ($weather_code >= 500 && $weather_code < 600) {
                                    $icon_class .= 'fa-cloud-showers-heavy'; // Rain
                                } elseif ($weather_code >= 600 && $weather_code < 700) {
                                    $icon_class .= 'fa-snowflake'; // Snow
                                } elseif ($weather_code >= 700 && $weather_code < 800) {
                                    $icon_class .= 'fa-smog'; // Atmosphere
                                } elseif ($weather_code == 800) {
                                    $icon_class .= 'fa-sun'; // Clear sky
                                } else {
                                    $icon_class .= 'fa-cloud'; // Clouds
                                }
                                ?>
                                <i class="<?php echo $icon_class; ?>"></i>
                            </div>
                            <div class="weather-details-header">
                                <div class="weather-location">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <?php echo htmlspecialchars($location_result['farm_location']); ?>
                                </div>
                                <div class="weather-temp">
                                    <?php echo round($weather_data['main']['temp']); ?>°C
                                </div>
                                <div class="weather-description">
                                    <?php echo ucfirst($weather_data['weather'][0]['description']); ?>
                                </div>
                                <div class="weather-extra-info">
                                    <span><i class="fas fa-tint"></i> <?php echo $weather_data['main']['humidity']; ?>%</span>
                                    <span><i class="fas fa-wind"></i> <?php echo round($weather_data['wind']['speed']); ?> m/s</span>
                                </div>
                            </div>
                        </div>
                        <a href="weather.php" class="weather-link">
                            <i class="fas fa-chart-line"></i>
                            Detailed Forecast
                        </a>
                    </div>
                <?php else: ?>
                    <div class="weather-banner-content">
                        <div class="weather-info-header">
                            <div class="weather-icon">
                                <i class="fas fa-cloud-sun"></i>
                            </div>
                            <div class="weather-details-header">
                                <div class="weather-description">
                                    Weather information unavailable
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Add this new section right after the weather banner -->
            <div class="location-update-section">
                <div class="location-card-wrapper">
                    <div class="location-card-content">
                        <div class="location-header">
                            <div class="location-icon-wrapper">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="location-text">
                                <h3>Farm Location</h3>
                                <p class="current-location">
                                    <?php echo htmlspecialchars($location_result['farm_location'] ?? 'Location not set'); ?>
                                </p>
                            </div>
                        </div>
                        <button class="update-location-toggle" onclick="toggleLocationUpdate()">
                            <i class="fas fa-edit"></i> Update Location
                        </button>
                    </div>
                    
                    <form id="locationUpdateForm" method="POST" class="location-update-form">
                        <div class="form-content">
                            <div class="select-wrapper">
                                <i class="fas fa-map-pin select-icon"></i>
                                <select id="farm_location" name="farm_location" required onchange="handleDistrictChange(this.value)">
                                    <option value="">Select your district</option>
                                    <?php foreach ($kerala_districts as $district): ?>
                                        <option value="<?php echo htmlspecialchars($district); ?>" 
                                                <?php echo (isset($location_result['farm_location']) && 
                                                          $location_result['farm_location'] === $district) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($district); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Add sub-place selector (initially hidden) -->
                            <div class="select-wrapper" id="subPlaceWrapper" style="display: none;">
                                <i class="fas fa-map-marker-alt select-icon"></i>
                                <select id="sub_place" name="sub_place" onchange="getWeatherForLocation(this.value)">
                                    <option value="">Select location</option>
                                </select>
                            </div>

                            <button type="submit" name="update_location" class="submit-location-btn">
                                <i class="fas fa-check-circle"></i> Confirm Location
                            </button>
                        </div>

                        <!-- Add weather info container -->
                        <div id="weatherInfo" class="weather-info" style="display: none;">
                            <h3>Current Weather</h3>
                            <div class="weather-details" id="weatherDetails">
                                <!-- Weather details will be populated here -->
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Update the soil test alert section -->
            <div class="alert-soil-test">
                <div class="soil-test-icon">
                    <i class="fas fa-flask"></i>
                </div>
                <div class="soil-test-message">
                    <strong>Regular Soil Testing Required!</strong>
                    <p>
                        Monitor your soil health for optimal cardamom growth. Regular testing helps maintain ideal nutrient levels.
                        <a href="soil_test.php">
                            Test Now <i class="fas fa-arrow-right"></i>
                        </a>
                    </p>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['success']; 
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?php 
                    echo $_SESSION['error']; 
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Add Welcome Section -->
            <div class="welcome-section">
                <h1><i class="fas fa-hand-wave"></i> Welcome, <?php echo $username; ?>!</h1>
                <div class="welcome-cards">
                    <div class="welcome-card">
                        <i class="fas fa-leaf"></i>
                        <h3>About GrowGuide</h3>
                        <p>Your intelligent companion for cardamom farming. We provide personalized recommendations and insights to help you maximize your yield.</p>
                    </div>
                    <div class="welcome-card">
                        <i class="fas fa-lightbulb"></i>
                        <h3>Getting Started</h3>
                        <p>Update your farm data regularly and check the dashboard for real-time insights. Use the navigation menu to access different features.</p>
                    </div>
                    <div class="welcome-card">
                        <i class="fas fa-chart-line"></i>
                        <h3>Track Progress</h3>
                        <p>Monitor your farm's performance, soil conditions, and weather data. Set tasks and get timely reminders for important activities.</p>
                    </div>
                    
                    <!-- Add new card for AI Assistant instructions -->
                    <div class="welcome-card">
                        <i class="fas fa-robot"></i>
                        <h3>AI Farming Assistant</h3>
                        <p>Get instant help with our AI chat assistant. Click the chat icon in the bottom-right corner to:</p>
                        <ul class="assistant-tips">
                            <li>Ask about cardamom cultivation</li>
                            <li>Get disease management advice</li>
                            <li>Learn about irrigation & fertilizers</li>
                            <li>Check harvesting guidelines</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Add AI Assistant Guide section -->
            <div class="quick-guide">
                <h2><i class="fas fa-robot"></i> How to Use the AI Assistant</h2>
                <div class="guide-grid">
                    <div class="guide-item">
                        <i class="fas fa-comments"></i>
                        <h4>Ask Questions</h4>
                        <p>Type your farming-related questions in simple, clear language. For example: "How do I plant cardamom?" or "What are signs of pest infestation?"</p>
                    </div>
                    <div class="guide-item">
                        <i class="fas fa-list-ul"></i>
                        <h4>Key Topics</h4>
                        <p>Get information about: planting, irrigation, fertilizers, pests, diseases, harvesting, and market prices.</p>
                    </div>
                    <div class="guide-item">
                        <i class="fas fa-lightbulb"></i>
                        <h4>Sample Questions</h4>
                        <ul class="sample-questions">
                            <li>"When is the best time to plant cardamom?"</li>
                            <li>"How often should I water my plants?"</li>
                            <li>"What fertilizers should I use?"</li>
                            <li>"How do I identify plant diseases?"</li>
                        </ul>
                    </div>
                    <div class="guide-item">
                        <i class="fas fa-info-circle"></i>
                        <h4>Tips</h4>
                        <p>Be specific in your questions. The more detailed your query, the more accurate the response will be.</p>
                    </div>
                </div>
            </div>

            <!-- Modified layout for cardamom types -->
            <div class="cardamom-section">
                <h2><i class="fas fa-leaf"></i> Cardamom Varieties</h2>
                <div class="cardamom-types">
                    <div class="cardamom-card">
                        <h3>Malabar Cardamom</h3>
                        <img src="img/harvast.jpeg" alt="Malabar Cardamom">
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
                        <img src="img/pla.jpg" alt="Mysore Cardamom">
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
                        <img src="img/card45.jpg" alt="Vazhukka Cardamom">
                        <p><strong>Characteristics:</strong></p>
                        <ul>
                            <li>Small, light green pods</li>
                            <li>Intense aroma</li>
                            <li>Disease resistant</li>
                            <li>Harvest period: 90-110 days</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Two-column layout for cultivation tips and location weather -->
            <div class="info-columns">
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

                <div class="location-weather-section">
                    <div class="location-card">
                        <h2><i class="fas fa-map-marker-alt"></i> Farm Location</h2>
                        
                        <form method="POST" class="location-form">
                            <div class="input-group">
                                <label for="farm_location">Select District: <small>(not not suitable for cardamom cultivation)</small></label>
                                <select id="farm_location" name="farm_location" required>
                                    <option value="">Select a district</option>
                                    <?php foreach ($kerala_districts as $district): ?>
                                        <option value="<?php echo htmlspecialchars($district); ?>" 
                                                <?php echo (isset($location_result['farm_location']) && 
                                                          $location_result['farm_location'] === $district) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($district); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" name="update_location" class="location-btn">
                                <i class="fas fa-save"></i> Update Location
                            </button>
                        </form>

                        <?php if ($location_result && $location_result['farm_location']): ?>
                            <div class="current-location">
                                <p><strong>Current Location:</strong> <?php echo htmlspecialchars($location_result['farm_location']); ?></p>
                            </div>
                            
                            <?php if ($weather_data): ?>
                                <div class="weather-info">
                                    <h3>Current Weather</h3>
                                    <div class="weather-details">
                                        <p><i class="fas fa-temperature-high"></i> Temperature: <?php echo round($weather_data['main']['temp']); ?>°C</p>
                                        <p><i class="fas fa-tint"></i> Humidity: <?php echo $weather_data['main']['humidity']; ?>%</p>
                                        <p><i class="fas fa-wind"></i> Wind: <?php echo $weather_data['wind']['speed']; ?> m/s</p>
                                        <p><i class="fas fa-cloud"></i> Weather: <?php echo ucfirst($weather_data['weather'][0]['description']); ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Add this section before the closing </div> of main-content -->
            <div class="query-section">
                <h2><i class="fas fa-question-circle"></i> Ask an Expert</h2>
                <div class="query-container">
                    <form method="POST" class="query-form">
                        <div class="input-group">
                            <label for="query_type">Query Type:</label>
                            <select name="query_type" id="query_type" required>
                                <option value="">Select Query Type</option>
                                <option value="cultivation">Cultivation Advice</option>
                                <option value="disease">Disease Management</option>
                                <option value="harvest">Harvesting Tips</option>
                                <option value="market">Market Information</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="input-group">
                            <label for="query_text">Your Query:</label>
                            <textarea name="query_text" id="query_text" rows="4" required placeholder="Type your question here..."></textarea>
                        </div>
                        <button type="submit" name="submit_query" class="submit-query-btn">
                            <i class="fas fa-paper-plane"></i> Submit Query
                        </button>
                    </form>

                    <div class="recent-queries">
                        <h3>Recent Queries</h3>
                        <?php if (!empty($recent_queries)): ?>
                            <?php foreach ($recent_queries as $query): ?>
                                <div class="query-card">
                                    <div class="query-header">
                                        <span class="query-type"><?php echo ucfirst(htmlspecialchars($query['query_type'])); ?></span>
                                        <span class="query-status <?php echo $query['status']; ?>">
                                            <?php echo ucfirst($query['status']); ?>
                                        </span>
                                    </div>
                                    <p class="query-text"><?php echo htmlspecialchars($query['query_text']); ?></p>
                                    <div class="query-meta">
                                        <span class="query-date">
                                            <i class="fas fa-calendar-alt"></i>
                                            <?php echo date('M d, Y', strtotime($query['created_at'])); ?>
                                        </span>
                                    </div>
                                    <?php if ($query['status'] === 'answered' && !empty($query['response_text'])): ?>
                                        <div class="query-response">
                                            <strong>Response:</strong>
                                            <p><?php echo htmlspecialchars($query['response_text']); ?></p>
                                            <span class="response-date">
                                                <i class="fas fa-reply"></i>
                                                Answered on <?php echo date('M d, Y', strtotime($query['responded_at'])); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no-queries">No recent queries found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Add this section where you want to display notifications -->
            <div class="notifications-panel">
                <div class="panel-header">
                    <h3><i class="fas fa-bell"></i> Notifications</h3>
                </div>
                <div class="notifications-content">
                    <?php
                    // Get user's notification preferences
                    $stmt = $conn->prepare("
                        SELECT notification_preferences 
                        FROM farmers 
                        WHERE user_id = ?
                    ");

                    if ($stmt === false) {
                        // Log the error and handle gracefully
                        error_log("Error preparing statement: " . $conn->error);
                        $preferences = null;
                    } else {
                        if (!$stmt->bind_param("i", $_SESSION['user_id'])) {
                            error_log("Error binding parameters: " . $stmt->error);
                            $preferences = null;
                        } else {
                            if (!$stmt->execute()) {
                                error_log("Error executing statement: " . $stmt->error);
                                $preferences = null;
                            } else {
                                $result = $stmt->get_result();
                                $preferences = $result->fetch_assoc();
                            }
                        }
                        $stmt->close();
                    }
                    
                    if ($preferences && $preferences['notification_preferences']) {
                        $notifications = json_decode($preferences['notification_preferences'], true);
                        
                        // Display active notifications based on preferences
                        foreach ($notifications as $type) {
                            switch ($type) {
                                case 'weather_alerts':
                                    echo '<div class="notification-item weather">
                                            <i class="fas fa-cloud-sun"></i>
                                            <div class="notification-content">
                                                <h4>Weather Alert</h4>
                                                <p>Check today\'s weather forecast for your farm.</p>
                                            </div>
                                        </div>';
                                    break;
                                case 'harvest_reminders':
                                    echo '<div class="notification-item harvest">
                                            <i class="fas fa-seedling"></i>
                                            <div class="notification-content">
                                                <h4>Harvest Reminder</h4>
                                                <p>Your next harvest is scheduled in 2 weeks.</p>
                                            </div>
                                        </div>';
                                    break;
                                case 'market_updates':
                                    $prices = getLatestCardamomPrices();
                                    $latest = $prices[0]; // Get the most recent day's data
                                    
                                    echo '<div class="notification-item market">
                                            <i class="fas fa-chart-line"></i>
                                            <div class="notification-content">
                                                <h4>Cardamom Market Update</h4>
                                                <p>Latest Prices (as of ' . $latest['date'] . '):<br>
                                                   Small Cardamom: ₹' . $latest['small']['max'] . '/kg (Max)<br>
                                                   Large Cardamom: ₹' . $latest['large']['badadana'] . '/kg (Badadana)
                                                </p>
                                                <button class="show-more-prices" onclick="togglePriceHistory()">Show Price History</button>
                                                <div id="priceHistoryContainer" style="display: none;">
                                                    ' . displayCardamomPriceHistory($prices) . '
                                                </div>
                                                <small>Source: <a href="https://www.indianspices.com/indianspices/marketing/price/domestic/daily-price.html" target="_blank">Spices Board India</a></small>
                                            </div>
                                          </div>';
                                    break;
                            }
                        }
                    } else {
                        echo '<div class="no-notifications">
                                <i class="fas fa-bell-slash"></i>
                                <p>No notifications enabled. Configure them in settings.</p>
                              </div>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Replace the existing AI assistant HTML with this improved version -->
    <div class="ai-assistant" id="aiAssistant">
        <div class="ai-assistant-header">
            <div class="ai-assistant-title">
                <div class="ai-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <span>GrowGuide AI Assistant</span>
            </div>
            <div class="ai-controls">
                <button id="minimizeAI"><i class="fas fa-minus"></i></button>
            </div>
        </div>

        <div class="ai-assistant-body">
            <div class="ai-messages" id="aiMessages">
                <!-- Messages will be populated here -->
            </div>

            <div class="ai-input">
                <input type="text" id="aiQuery" placeholder="Ask me anything about farming...">
                <button id="sendQuery">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>

            <div class="quick-actions">
                <button onclick="askQuestion('What are the ideal conditions for cardamom growth?')">
                    <i class="fas fa-seedling"></i> Growing Conditions
                </button>
                <button onclick="askQuestion('How do I identify common cardamom diseases?')">
                    <i class="fas fa-bug"></i> Disease Help
                </button>
                <button onclick="askQuestion('When is the best time to harvest cardamom?')">
                    <i class="fas fa-clock"></i> Harvest Timing
                </button>
                <button onclick="askQuestion('What are today\'s cardamom market prices?')">
                    <i class="fas fa-chart-line"></i> Market Prices
                </button>
            </div>
        </div>
    </div>

    <!-- Add this JavaScript before the closing body tag -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const aiAssistant = document.getElementById('aiAssistant');
        const aiMessages = document.getElementById('aiMessages');
        const aiInput = document.getElementById('aiQuery');
        const sendButton = document.getElementById('sendQuery');
        const minimizeButton = document.getElementById('minimizeAI');
        
        // Replace the initial greeting with this better formatted version
        addAIMessage(`
👋 Welcome to GrowGuide AI!

Hello jiji jacob! I'm your dedicated Cardamom Farming Assistant.

🌿 I'm here to help you with:

1. 🌱 Planting & Cultivation
2. 💧 Irrigation Management 
3. 🔍 Disease Identification
4. 🐛 Pest Control
5. 🌿 Fertilizer Guidelines
6. 🌾 Harvesting Best Practices
7. ☀️ Weather Impact Analysis
8. 📊 Market Trends & Prices

💡 For the most helpful answers:
• Be specific with your questions
• Tell me your plants' growth stage
• Describe any symptoms or concerns

📝 Example Questions:
Q1: "What's the best spacing for new cardamom plants?"
Q2: "How do I treat yellowing cardamom leaves?"
Q3: "When should I harvest my cardamom pods?"
Q4: "What's the ideal watering schedule during flowering?"

🤝 Ready to assist you in growing healthy, productive cardamom!
Type your question below to get started...
        `);

        // Send message on button click or Enter key
        sendButton.addEventListener('click', handleUserMessage);
        aiInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                handleUserMessage();
            }
        });

        // Minimize/Maximize AI assistant
        minimizeButton.addEventListener('click', function() {
            aiAssistant.classList.toggle('minimized');
            minimizeButton.querySelector('i').classList.toggle('fa-minus');
            minimizeButton.querySelector('i').classList.toggle('fa-plus');
        });

        function handleUserMessage() {
            const message = aiInput.value.trim();
            if (!message) return;

            // Add user message
            addUserMessage(message);
            aiInput.value = '';

            // Show typing animation
            showTypingIndicator();

            // Simulate AI processing (replace with actual API call)
            setTimeout(() => {
                removeTypingIndicator();
                processUserQuery(message);
            }, 1500);
        }

        function addUserMessage(text) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'ai-message user-message';
            messageDiv.innerHTML = `
                <div class="message-content">${text}</div>
            `;
            aiMessages.appendChild(messageDiv);
            scrollToBottom();
        }

        function addAIMessage(text) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'ai-message';
            messageDiv.innerHTML = `
                <div class="ai-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="message-content">${text}</div>
            `;
            aiMessages.appendChild(messageDiv);
            scrollToBottom();
        }

        function showTypingIndicator() {
            const typingDiv = document.createElement('div');
            typingDiv.className = 'ai-message typing-indicator';
            typingDiv.innerHTML = `
                <div class="ai-avatar">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="typing-dots">
                    <span></span><span></span><span></span>
                </div>
            `;
            aiMessages.appendChild(typingDiv);
            scrollToBottom();
        }

        function removeTypingIndicator() {
            const typingIndicator = aiMessages.querySelector('.typing-indicator');
            if (typingIndicator) {
                typingIndicator.remove();
            }
        }

        function scrollToBottom() {
            aiMessages.scrollTop = aiMessages.scrollHeight;
        }

        function processUserQuery(query) {
            // Get AI response using PHP function
            fetch('get_ai_response.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'query=' + encodeURIComponent(query)
            })
            .then(response => response.text())
            .then(response => {
                addAIMessage(response);
            })
            .catch(error => {
                addAIMessage("I apologize, but I'm having trouble processing your request. Please try again.");
            });
        }

        // Function to handle quick action buttons
        window.askQuestion = function(question) {
            aiInput.value = question;
            handleUserMessage();
        }
    });
    </script>

    <!-- Add these additional styles to your existing CSS -->
    <style>
    .ai-assistant {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 380px;
        height: 600px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
        display: flex;
        flex-direction: column;
        transition: all 0.3s ease;
        z-index: 1000;
    }

    .ai-assistant.minimized {
        height: 60px;
        overflow: hidden;
    }

    .ai-assistant-header {
        padding: 16px;
        background: var(--primary-color);
        color: white;
        border-radius: 12px 12px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .ai-assistant-title {
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 500;
    }

    .ai-avatar {
        width: 36px;
        height: 36px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .ai-controls button {
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        padding: 4px;
        border-radius: 4px;
        transition: background-color 0.2s;
    }

    .ai-controls button:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .ai-assistant-body {
        flex: 1;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .ai-messages {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        background: #f8f9fa;
    }

    .ai-message {
        display: flex;
        gap: 12px;
        margin-bottom: 16px;
        opacity: 0;
        transform: translateY(20px);
        animation: messageAppear 0.3s ease forwards;
    }

    @keyframes messageAppear {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .message-content {
        background: white;
        padding: 12px 16px;
        border-radius: 12px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        max-width: 80%;
        line-height: 1.5;
    }

    .user-message {
        flex-direction: row-reverse;
    }

    .user-message .message-content {
        background: var(--primary-color);
        color: white;
    }

    .typing-dots {
        display: flex;
        gap: 4px;
        padding: 12px 16px;
        background: white;
        border-radius: 12px;
        width: fit-content;
    }

    .typing-dots span {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--primary-color);
        animation: typing 1s infinite;
    }

    .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
    .typing-dots span:nth-child(3) { animation-delay: 0.4s; }

    @keyframes typing {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-6px); }
    }

    .ai-input {
        padding: 16px;
        background: white;
        border-top: 1px solid #eee;
        display: flex;
        gap: 12px;
    }

    .ai-input input {
        flex: 1;
        padding: 12px 16px;
        border: 1px solid #ddd;
        border-radius: 24px;
        font-size: 14px;
        transition: all 0.2s;
    }

    .ai-input input:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(45, 90, 39, 0.1);
    }

    .ai-input button {
        background: var(--primary-color);
        color: white;
        border: none;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
    }

    .ai-input button:hover {
        background: var(--primary-dark);
        transform: scale(1.05);
    }

    .quick-actions {
        padding: 12px;
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 8px;
        background: white;
        border-top: 1px solid #eee;
    }

    .quick-actions button {
        background: #f8f9fa;
        border: 1px solid #eee;
        padding: 8px 12px;
        border-radius: 8px;
        font-size: 13px;
        color: var(--text-color);
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
    }

    .quick-actions button:hover {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
        transform: translateY(-2px);
    }
    </style>

    <!-- Only weather message element -->
    <div id="weatherMessage" class="running-message weather-message" style="display: none;">
        <i class="fas fa-cloud-sun fa-spin"></i>
        <span id="weatherText">Updating weather information...</span>
    </div>

    <script>
    // Weather message functions only
    function showWeatherMessage(message) {
        const weatherMsg = document.getElementById('weatherMessage');
        const weatherText = document.getElementById('weatherText');
        weatherText.textContent = message;
        weatherMsg.style.display = 'flex';
    }

    function hideWeatherMessage() {
        const weatherMsg = document.getElementById('weatherMessage');
        weatherMsg.style.display = 'none';
    }

    function updateWeather() {
        showWeatherMessage('Fetching latest weather data...');
        // Your weather update logic here
        setTimeout(() => {
            showWeatherMessage('Weather data updated successfully!');
            setTimeout(hideWeatherMessage, 3000);
        }, 2000);
    }

    // Auto-update weather periodically
    setInterval(() => {
        updateWeather();
    }, 1800000); // Update every 30 minutes

    // Initial weather update
    document.addEventListener('DOMContentLoaded', () => {
        updateWeather();
    });
    </script>
</body>
</html>