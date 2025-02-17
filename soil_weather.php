<?php
session_start();

// Ensure user is logged in and is an employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

// Add API key for weather data (you'll need to sign up for a weather API service)
$weather_api_key = "YOUR_WEATHER_API_KEY";

// Function to fetch weather data
function getWeatherData($city) {
    global $weather_api_key;
    $url = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($city) . "&units=metric&appid=" . $weather_api_key;
    
    // Add error handling for the API request
    $response = @file_get_contents($url);
    if ($response === FALSE) {
        return false;
    }
    return json_decode($response, true);
}

$username = $_SESSION['username'];

// Handle soil data submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_soil'])) {
    $ph_level = mysqli_real_escape_string($conn, $_POST['ph_level']);
    $moisture = mysqli_real_escape_string($conn, $_POST['moisture']);
    $nitrogen = mysqli_real_escape_string($conn, $_POST['nitrogen']);
    $phosphorus = mysqli_real_escape_string($conn, $_POST['phosphorus']);
    $potassium = mysqli_real_escape_string($conn, $_POST['potassium']);
    
    $query = "INSERT INTO soil_data (ph_level, moisture, nitrogen, phosphorus, potassium, recorded_by) 
              VALUES ('$ph_level', '$moisture', '$nitrogen', '$phosphorus', '$potassium', '$username')";
    mysqli_query($conn, $query);
}

// Fetch recent soil data - Add error handling
$soil_query = "SELECT * FROM soil_data ORDER BY recorded_at DESC LIMIT 5";
$soil_result = mysqli_query($conn, $soil_query);

// Check if query was successful
if (!$soil_result) {
    echo "Error: " . mysqli_error($conn);
    $soil_result = []; // Prevent the while loop from failing
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soil & Weather - GrowGuide</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Copy the same styles from employee.php */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f4f4f4;
            margin: 0;
        }
        .sidebar {
            width: 250px;
            background: #2d6a4f;
            color: white;
            height: 100vh;
            position: fixed;
            padding: 20px;
        }
        .sidebar h2 {
            text-align: center;
            margin-bottom: 30px;
        }
        .nav-item {
            padding: 15px;
            display: flex;
            align-items: center;
            color: white;
            text-decoration: none;
            transition: 0.3s;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .nav-item i {
            margin-right: 10px;
        }
        .content {
            margin-left: 260px;
            padding: 20px;
        }
        .logout {
            background: red;
            padding: 10px;
            text-align: center;
            margin-top: 50px;
            border-radius: 5px;
        }
        .logout a {
            color: white;
            text-decoration: none;
            font-weight: bold;
        }
        .logout a:hover {
            text-decoration: underline;
        }
        
        .grid-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            background: #2d6a4f;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn:hover {
            background: #1a4731;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>Soil & Weather</h2>
        <a href="employe.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="soil_weather.php" class="nav-item"><i class="fas fa-seedling"></i> Soil & Weather</a>
        <a href="#" class="nav-item"><i class="fas fa-flask"></i> Fertilizer Suggestions</a>
        <a href="#" class="nav-item"><i class="fas fa-users"></i> Farmer Management</a>
        <a href="#" class="nav-item"><i class="fas fa-chart-bar"></i> Reports & Analytics</a>
        <div class="logout">
            <a href="login.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="content">
        <h1>Soil & Weather Information</h1>
        
        <div class="grid-container">
            <div class="card">
                <h2>Soil Data Entry</h2>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="ph_level">pH Level:</label>
                        <input type="number" step="0.1" name="ph_level" required>
                    </div>
                    <div class="form-group">
                        <label for="moisture">Moisture (%):</label>
                        <input type="number" step="0.1" name="moisture" required>
                    </div>
                    <div class="form-group">
                        <label for="nitrogen">Nitrogen (mg/kg):</label>
                        <input type="number" step="0.1" name="nitrogen" required>
                    </div>
                    <div class="form-group">
                        <label for="phosphorus">Phosphorus (mg/kg):</label>
                        <input type="number" step="0.1" name="phosphorus" required>
                    </div>
                    <div class="form-group">
                        <label for="potassium">Potassium (mg/kg):</label>
                        <input type="number" step="0.1" name="potassium" required>
                    </div>
                    <button type="submit" name="submit_soil" class="btn">Submit Soil Data</button>
                </form>
            </div>

            <div class="card">
                <h2>Weather Information</h2>
                <form method="GET" action="">
                    <div class="form-group">
                        <label for="city">City:</label>
                        <input type="text" name="city" required>
                    </div>
                    <button type="submit" name="get_weather" class="btn">Get Weather</button>
                </form>

                <?php
                if (isset($_GET['city'])) {
                    $weather_data = getWeatherData($_GET['city']);
                    if ($weather_data) {
                        echo "<div style='margin-top: 20px;'>";
                        echo "<h3>Current Weather in {$_GET['city']}</h3>";
                        echo "<p>Temperature: {$weather_data['main']['temp']}°C</p>";
                        echo "<p>Humidity: {$weather_data['main']['humidity']}%</p>";
                        echo "<p>Weather: {$weather_data['weather'][0]['description']}</p>";
                        echo "</div>";
                    }
                }
                ?>
            </div>
        </div>

        <div class="card" style="margin-top: 20px;">
            <h2>Recent Soil Data</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>pH Level</th>
                        <th>Moisture</th>
                        <th>Nitrogen</th>
                        <th>Phosphorus</th>
                        <th>Potassium</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if ($soil_result && mysqli_num_rows($soil_result) > 0) {
                        while ($row = mysqli_fetch_assoc($soil_result)) { 
                    ?>
                        <tr>
                            <td><?php echo $row['recorded_at']; ?></td>
                            <td><?php echo $row['ph_level']; ?></td>
                            <td><?php echo $row['moisture']; ?>%</td>
                            <td><?php echo $row['nitrogen']; ?> mg/kg</td>
                            <td><?php echo $row['phosphorus']; ?> mg/kg</td>
                            <td><?php echo $row['potassium']; ?> mg/kg</td>
                            <td><?php echo $row['recorded_by']; ?></td>
                        </tr>
                    <?php 
                        }
                    } else {
                        echo "<tr><td colspan='7'>No soil data available</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html> 