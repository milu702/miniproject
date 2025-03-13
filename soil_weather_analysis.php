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
            --primary-color: #2D5A27;
            --secondary-color: #4A7A43;
            --accent-color: #8B9D83;
            --warning-color: #FFA500;
            --danger-color: #FF4444;
            --success-color: #28a745;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .analysis-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .farmer-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .farmer-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }

        .farmer-header i {
            font-size: 24px;
            color: var(--primary-color);
            margin-right: 10px;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-left: 10px;
        }

        .warning { background-color: var(--warning-color); color: white; }
        .success { background-color: var(--success-color); color: white; }
        .danger { background-color: var(--danger-color); color: white; }

        .soil-params {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }

        .param-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .weather-info {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .back-button i {
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="employe.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>

        <h1><i class="fas fa-microscope"></i> Soil & Weather Analysis</h1>

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