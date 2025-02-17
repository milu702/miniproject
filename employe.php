<?php
session_start();

// Ensure user is logged in and is an employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - GrowGuide</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>GrowGuide</h2>
        <a href="employee.php" class="nav-item"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="soil_weather.php" class="nav-item"><i class="fas fa-seedling"></i> Soil & Weather</a>
        <a href="#" class="nav-item"><i class="fas fa-flask"></i> Fertilizer Suggestions</a>
        <a href="#" class="nav-item"><i class="fas fa-users"></i> Farmer Management</a>
        <a href="#" class="nav-item"><i class="fas fa-chart-bar"></i> Reports & Analytics</a>
        <div class="logout">
            <a href="login.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <div class="content">
        <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
        <p>This is your employee dashboard. Choose an option from the sidebar.</p>
    </div>

</body>
</html>
