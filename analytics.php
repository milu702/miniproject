<?php
session_start();

// Ensure user is logged in and has the 'farmer' role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer') {
    header("Location: login.php");
    exit();
}

require_once 'config.php';

// Get user data
$user_id = $_SESSION['user_id'];
$username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Farmer';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Analytics</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base styles */
        :root {
            --primary-color: #2c5282;
            --secondary-color: #4299e1;
            --accent-color: #90cdf4;
        }

        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
            background: #f7fafc;
        }

        /* Sidebar styles */
        .sidebar {
            background: linear-gradient(180deg, #2c5282, #4299e1);
            width: 80px;
            padding: 20px 0;
            height: 100vh;
            position: fixed;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            overflow-y: auto;
            transition: width 0.3s ease;
        }

        .sidebar:hover {
            width: 200px;
        }

        .sidebar-header {
            text-align: center;
            color: white;
            margin-bottom: 30px;
        }

        .sidebar-header h2 {
            font-size: 1.2rem;
            margin: 0;
            display: none;
        }

        .sidebar:hover .sidebar-header h2 {
            display: block;
        }

        .nav-menu {
            width: 100%;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 15px;
            color: white;
            text-decoration: none;
            transition: background 0.3s;
            width: 100%;
            box-sizing: border-box;
        }

        .nav-item i {
            font-size: 1.5rem;
            min-width: 40px;
            text-align: center;
        }

        .nav-item span {
            display: none;
            margin-left: 10px;
            white-space: nowrap;
        }

        .sidebar:hover .nav-item span {
            display: inline;
        }

        .nav-item:hover, .nav-item.active {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Main content styles */
        .layout-container {
            display: flex;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            margin-left: 80px;
            padding: 20px;
        }

        /* Analytics specific styles */
        .farm-info-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 30px;
        }

        .farm-info-header {
            margin-bottom: 20px;
        }

        .farm-info-header h2 {
            color: var(--primary-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }

        .stat-card h3 {
            color: var(--primary-color);
            margin-top: 0;
            margin-bottom: 15px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #edf2f7;
        }

        .data-table th {
            background-color: #f7fafc;
            color: var(--primary-color);
            font-weight: 600;
        }

        .data-table tr:hover {
            background-color: #f7fafc;
        }
    </style>
</head>
<body>
    <div class="layout-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><i class="fas fa-seedling"></i> <span>GrowGuide</span></h2>
            </div>
            <nav class="nav-menu">
                <a href="farmer.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="analytics.php" class="nav-item active">
                    <i class="fas fa-chart-line"></i>
                    <span>Analytics</span>
                </a>
                <a href="schedule.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Schedule</span>
                </a>
                <a href="weather.php" class="nav-item">
                    <i class="fas fa-cloud-sun"></i>
                    <span>Weather</span>
                </a>
                
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="farm-info-card">
                <div class="farm-info-header">
                    <h2><i class="fas fa-chart-line"></i> Crop Performance Analytics</h2>
                </div>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3>Yield Analysis</h3>
                        <canvas id="yieldChart"></canvas>
                    </div>
                    <div class="stat-card">
                        <h3>Revenue Trends</h3>
                        <canvas id="revenueChart"></canvas>
                    </div>
                    <div class="stat-card">
                        <h3>Cost Analysis</h3>
                        <canvas id="costChart"></canvas>
                    </div>
                    <div class="stat-card">
                        <h3>Profit Margins</h3>
                        <canvas id="profitChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="farm-info-card">
                <div class="farm-info-header">
                    <h2><i class="fas fa-history"></i> Historical Data</h2>
                </div>
                <div class="data-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Crop</th>
                                <th>Yield (kg)</th>
                                <th>Revenue ($)</th>
                                <th>Costs ($)</th>
                                <th>Profit ($)</th>
                            </tr>
                        </thead>
                        <tbody id="historicalData">
                            <!-- Data will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Sample data - replace with actual data from your backend
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
            
            // Yield Chart
            const yieldCtx = document.getElementById('yieldChart').getContext('2d');
            new Chart(yieldCtx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Crop Yield (kg)',
                        data: [1200, 1350, 1100, 1400, 1300, 1500],
                        borderColor: '#2c5282',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Revenue ($)',
                        data: [5000, 5500, 4800, 6000, 5800, 6500],
                        backgroundColor: '#4299e1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // Cost Chart
            const costCtx = document.getElementById('costChart').getContext('2d');
            new Chart(costCtx, {
                type: 'bar',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Costs ($)',
                        data: [3000, 3200, 2900, 3500, 3400, 3800],
                        backgroundColor: '#90cdf4'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // Profit Chart
            const profitCtx = document.getElementById('profitChart').getContext('2d');
            new Chart(profitCtx, {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [{
                        label: 'Profit ($)',
                        data: [2000, 2300, 1900, 2500, 2400, 2700],
                        borderColor: '#48bb78',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // Populate historical data table
            const historicalData = document.getElementById('historicalData');
            const sampleData = [
                ['January', 'Wheat', '1200', '5000', '3000', '2000'],
                ['February', 'Wheat', '1350', '5500', '3200', '2300'],
                ['March', 'Corn', '1100', '4800', '2900', '1900'],
                ['April', 'Corn', '1400', '6000', '3500', '2500'],
                ['May', 'Soybeans', '1300', '5800', '3400', '2400'],
                ['June', 'Soybeans', '1500', '6500', '3800', '2700']
            ];

            sampleData.forEach(row => {
                const tr = document.createElement('tr');
                row.forEach(cell => {
                    const td = document.createElement('td');
                    td.textContent = cell;
                    tr.appendChild(td);
                });
                historicalData.appendChild(tr);
            });
        });
    </script>
</body>
</html>