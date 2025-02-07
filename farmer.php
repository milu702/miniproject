<?php
session_start();

// Ensure user is logged in and has the 'farmer' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: login.php");
    exit();
}

require_once 'config.php'; // Ensure this file contains the correct DB connection settings

// Debugging: Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sample Data - Replace with dynamic DB data in the future
$farmerData = [
    'total_crops' => 15,
    'active_farms' => 5,
    'pending_tasks' => 8,
    'weather_alerts' => 3,
    'chart_data' => [
        ['month' => 'Jan', 'growth' => 65, 'rainfall' => 40],
        ['month' => 'Feb', 'growth' => 70, 'rainfall' => 45],
        ['month' => 'Mar', 'growth' => 75, 'rainfall' => 50],
        ['month' => 'Apr', 'growth' => 80, 'rainfall' => 55],
        ['month' => 'May', 'growth' => 85, 'rainfall' => 60],
        ['month' => 'Jun', 'growth' => 90, 'rainfall' => 65],
    ],
];

$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Farmer';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }
        .dashboard-cards {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }
        .card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            flex: 1;
            text-align: center;
        }
        .chart-container {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Welcome, <?php echo $username; ?>!</h2>
        <div class="dashboard-cards">
            <div class="card">
                <h4>Total Crops</h4>
                <p><?php echo $farmerData['total_crops']; ?></p>
            </div>
            <div class="card">
                <h4>Active Farms</h4>
                <p><?php echo $farmerData['active_farms']; ?></p>
            </div>
            <div class="card">
                <h4>Pending Tasks</h4>
                <p><?php echo $farmerData['pending_tasks']; ?></p>
            </div>
            <div class="card">
                <h4>Weather Alerts</h4>
                <p><?php echo $farmerData['weather_alerts']; ?></p>
            </div>
        </div>
        <div class="chart-container">
            <canvas id="growthChart"></canvas>
        </div>
    </div>
    <script>
        var ctx = document.getElementById('growthChart').getContext('2d');
        var chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($farmerData['chart_data'], 'month')); ?>,
                datasets: [
                    {
                        label: 'Crop Growth',
                        borderColor: 'blue',
                        backgroundColor: 'rgba(0,0,255,0.2)',
                        data: <?php echo json_encode(array_column($farmerData['chart_data'], 'growth')); ?>
                    }, 
                    {
                        label: 'Rainfall',
                        borderColor: 'green',
                        backgroundColor: 'rgba(0,255,0,0.2)',
                        data: <?php echo json_encode(array_column($farmerData['chart_data'], 'rainfall')); ?>
                    }
                ]
            }
        });
    </script>
</body>
</html>
