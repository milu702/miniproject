<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide | Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/admin.css">
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-logo">
            <h2>GrowGuide</h2>
            <p style="color: white; font-size: 14px; margin-top: 5px;">Welcome, <?php echo htmlspecialchars($username); ?></p>
        </div>
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> Dashboard
            </a>
            <a href="farmers.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'farmers.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Farmers
            </a>
            <a href="employees.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'employees.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-tie"></i> Employees
            </a>
            <a href="soil-tests.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'soil-tests.php' ? 'active' : ''; ?>">
                <i class="fas fa-flask"></i> Soil Tests
            </a>
            <a href="varieties.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'varieties.php' ? 'active' : ''; ?>">
                <i class="fas fa-seedling"></i> Varieties
            </a>
        </nav>
    </div>
    
    <div class="content">
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1><?php echo $pageTitle ?? 'Admin Dashboard'; ?></h1>
            <div>
                <span>Welcome, <?php echo htmlspecialchars($username); ?></span>
                <a href="logout.php" style="margin-left: 10px;"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </header> 