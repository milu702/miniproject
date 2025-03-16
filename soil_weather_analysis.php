<?php
session_start();
require_once 'config.php';

// Ensure user is logged in and is an employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

// Initialize database connection
$dbConfig = new DatabaseConfig();
$conn = $dbConfig->getConnection();

// Get all farmers with their latest soil tests
$query = "SELECT 
            u.id as farmer_id,
            u.username as farmer_name,
            f.farm_location,
            st.test_date,
            st.ph_level,
            st.nitrogen_content,
            st.phosphorus_content,
            st.potassium_content
          FROM users u
          LEFT JOIN farmers f ON u.id = f.farmer_id
          LEFT JOIN (
              SELECT farmer_id, MAX(test_date) as latest_date
              FROM soil_tests
              GROUP BY farmer_id
          ) latest ON latest.farmer_id = u.id
          LEFT JOIN soil_tests st ON st.farmer_id = u.id 
              AND st.test_date = latest.latest_date
          WHERE u.role = 'farmer' AND u.status = 1
          ORDER BY u.username";

$result = mysqli_query($conn, $query);
$farmers = [];
while ($row = mysqli_fetch_assoc($result)) {
    $farmers[] = $row;
}

// Weather API key
$weather_api_key = "cc02c9dee7518466102e748f211bca05";

// Helper functions for analysis
function getSoilStatus($ph, $n, $p, $k) {
    $status = [];
    
    // pH analysis
    if ($ph < 5.5) $status['ph'] = ['level' => 'Low', 'class' => 'warning'];
    elseif ($ph > 6.5) $status['ph'] = ['level' => 'High', 'class' => 'warning'];
    else $status['ph'] = ['level' => 'Optimal', 'class' => 'success'];
    
    // Nitrogen analysis
    if ($n < 0.5) $status['nitrogen'] = ['level' => 'Low', 'class' => 'warning'];
    elseif ($n > 1.0) $status['nitrogen'] = ['level' => 'High', 'class' => 'warning'];
    else $status['nitrogen'] = ['level' => 'Optimal', 'class' => 'success'];
    
    // Phosphorus analysis
    if ($p < 0.05) $status['phosphorus'] = ['level' => 'Low', 'class' => 'warning'];
    elseif ($p > 0.2) $status['phosphorus'] = ['level' => 'High', 'class' => 'warning'];
    else $status['phosphorus'] = ['level' => 'Optimal', 'class' => 'success'];
    
    // Potassium analysis
    if ($k < 1.0) $status['potassium'] = ['level' => 'Low', 'class' => 'warning'];
    elseif ($k > 2.0) $status['potassium'] = ['level' => 'High', 'class' => 'warning'];
    else $status['potassium'] = ['level' => 'Optimal', 'class' => 'success'];
    
    return $status;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soil & Weather Analysis - GrowGuide</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-dark: #1a4a1d;
            --primary-color: #2B7A30;
            --hover-color: #3c8c40;
            --text-light: #ffffff;
            --sidebar-width: 250px;
            --card-bg: #ffffff;
            --badge-success: #2B7A30;
            --badge-warning: #ffa500;
            --badge-danger: #dc3545;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fa;
            overflow: hidden;
        }

        /* Compact Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: var(--text-light);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-header i {
            font-size: 24px;
        }

        .sidebar-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 500;
        }

        .nav-menu {
            display: flex;
            flex-direction: column;
            padding: 20px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 10px 24px;
            color: var(--text-light);
            text-decoration: none;
            transition: all 0.3s ease;
            gap: 12px;
        }

        .nav-item.active {
            background: rgba(255, 255, 255, 0.1);
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            padding-left: 28px;
        }

        .nav-item i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        .nav-item span {
            font-size: 16px;
        }

        .logout-btn {
            margin-top: auto;
            background: rgba(220, 53, 69, 0.1);
            color: #ff6b6b;
            border: none;
            cursor: pointer;
            margin: 20px;
            border-radius: 8px;
        }

        .logout-btn:hover {
            background: #ff6b6b;
            color: white;
        }

        /* Main Content Optimization */
        .container {
            margin-left: var(--sidebar-width);
            padding: 15px;
            height: 100vh;
            overflow-y: auto;
            box-sizing: border-box;
        }

        h1 {
            margin: 0 0 15px 0;
            font-size: 1.5rem;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Grid Layout Optimization */
        .analysis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            padding-bottom: 20px;
        }

        /* Compact Card Design */
        .farmer-card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s ease;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .farmer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }

        .farmer-header {
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }

        .farmer-header h3 {
            margin: 0;
            font-size: 1rem;
            flex: 1;
        }

        /* Compact Status Badges */
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
        }

        .success { background: var(--badge-success); color: white; }
        .warning { background: var(--badge-warning); color: white; }
        .danger { background: var(--badge-danger); color: white; }

        /* Soil Parameters Grid */
        .soil-params {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        .param-item {
            background: #f8f9fa;
            padding: 8px;
            border-radius: 6px;
            font-size: 0.9rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .param-item strong {
            font-size: 0.8rem;
        }

        /* Weather Info Section */
        .weather-info {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 10px;
            margin-top: 8px;
        }

        .weather-info h4 {
            margin: 0 0 8px 0;
            font-size: 0.9rem;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .weather-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            font-size: 0.85rem;
        }

        .weather-details p {
            margin: 0;
        }

        .last-test {
            font-size: 0.75rem;
            color: #666;
            margin-top: 5px;
        }

        /* Scrollbar Styling */
        .container::-webkit-scrollbar {
            width: 8px;
        }

        .container::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        .container::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .analysis-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .weather-details {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-seedling"></i>
            <h2>GrowGuide</h2>
        </div>
        
        <nav class="nav-menu">
            <a href="employe.php" class="nav-item">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="farmers_list.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>Farmers</span>
            </a>
            <a href="soil_weather_analysis.php" class="nav-item active">
                <i class="fas fa-microscope"></i>
                <span>Analysis</span>
            </a>
            <a href="recommendations.php" class="nav-item">
                <i class="fas fa-clipboard-list"></i>
                <span>Recommendations</span>
            </a>
            <a href="settings.php" class="nav-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            
            <a href="logout.php" class="nav-item logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>

    <div class="container">
        <h1>
            <i class="fas fa-microscope"></i>
            Soil & Weather Analysis
        </h1>
        
        <div class="analysis-grid">
            <?php foreach ($farmers as $farmer): ?>
                <div class="farmer-card">
                    <div class="farmer-header">
                        <i class="fas fa-user-circle"></i>
                        <h3><?php echo htmlspecialchars($farmer['farmer_name']); ?></h3>
                        <?php if ($farmer['test_date']): ?>
                            <span class="status-badge success">Active</span>
                        <?php else: ?>
                            <span class="status-badge warning">No Soil Test</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($farmer['test_date']): ?>
                        <div class="soil-params">
                            <?php
                            $soil_status = getSoilStatus(
                                $farmer['ph_level'],
                                $farmer['nitrogen_content'],
                                $farmer['phosphorus_content'],
                                $farmer['potassium_content']
                            );
                            ?>
                            <div class="param-item">
                                <strong>pH Level:</strong>
                                <span class="status-badge <?php echo $soil_status['ph']['class']; ?>">
                                    <?php echo number_format($farmer['ph_level'], 2); ?>
                                </span>
                            </div>
                            <div class="param-item">
                                <strong>Nitrogen:</strong>
                                <span class="status-badge <?php echo $soil_status['nitrogen']['class']; ?>">
                                    <?php echo number_format($farmer['nitrogen_content'], 2); ?>%
                                </span>
                            </div>
                            <div class="param-item">
                                <strong>Phosphorus:</strong>
                                <span class="status-badge <?php echo $soil_status['phosphorus']['class']; ?>">
                                    <?php echo number_format($farmer['phosphorus_content'], 2); ?>%
                                </span>
                            </div>
                            <div class="param-item">
                                <strong>Potassium:</strong>
                                <span class="status-badge <?php echo $soil_status['potassium']['class']; ?>">
                                    <?php echo number_format($farmer['potassium_content'], 2); ?>%
                                </span>
                            </div>
                        </div>
                        <div class="last-test">
                            <small>Last tested: <?php echo date('F j, Y', strtotime($farmer['test_date'])); ?></small>
                        </div>
                    <?php else: ?>
                        <p>No soil test data available</p>
                    <?php endif; ?>

                    <div class="weather-info">
                        <h4><i class="fas fa-cloud-sun"></i> Local Weather</h4>
                        <?php if ($farmer['farm_location']): ?>
                            <div class="weather-data">
                                <!-- Weather data will be loaded here via AJAX -->
                                <div id="weather-<?php echo $farmer['farmer_id']; ?>">
                                    Loading weather data...
                                </div>
                            </div>
                        <?php else: ?>
                            <p>No farm location specified</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        // Function to load weather data for each farmer
        function loadWeatherData(farmerId, location) {
            const weatherDiv = document.getElementById(`weather-${farmerId}`);
            
            fetch(`get_weather.php?location=${encodeURIComponent(location)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        weatherDiv.innerHTML = `<p class="error">${data.error}</p>`;
                        return;
                    }
                    
                    weatherDiv.innerHTML = `
                        <div class="weather-details">
                            <p><strong>Temperature:</strong> ${data.temp}°C</p>
                            <p><strong>Humidity:</strong> ${data.humidity}%</p>
                            <p><strong>Conditions:</strong> ${data.description}</p>
                        </div>
                    `;
                })
                .catch(error => {
                    weatherDiv.innerHTML = '<p class="error">Failed to load weather data</p>';
                });
        }

        // Load weather data for all farmers with locations
        document.addEventListener('DOMContentLoaded', () => {
            <?php foreach ($farmers as $farmer): ?>
                <?php if ($farmer['farm_location']): ?>
                    loadWeatherData(<?php echo $farmer['farmer_id']; ?>, 
                                  '<?php echo addslashes($farmer['farm_location']); ?>');
                <?php endif; ?>
            <?php endforeach; ?>
        });
    </script>
</body>
</html> 