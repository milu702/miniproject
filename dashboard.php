<?php
session_start();

// Logout handling
if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Static data arrays remain the same
$recommendations = [
    ['title' => 'Apply NPK Fertilizer', 'description' => 'Use 17-17-17 NPK mixture', 'status' => 'Pending', 'date' => '2025-01-25'],
    ['title' => 'Pest Control', 'description' => 'Apply organic neem spray', 'status' => 'Upcoming', 'date' => '2025-01-28'],
    ['title' => 'Mulching', 'description' => 'Add organic mulch layer', 'status' => 'Pending', 'date' => '2025-01-30']
];

$tasks = [
    ['title' => 'Fertilizer Application', 'due_date' => '2025-01-25', 'priority' => 'urgent'],
    ['title' => 'Irrigation Check', 'due_date' => '2025-01-26', 'priority' => 'normal'],
    ['title' => 'Pest Monitoring', 'due_date' => '2025-01-28', 'priority' => 'pending']
];

$soil_health = [
    'ph' => '6.5',
    'nitrogen' => 'Medium',
    'phosphorus' => 'High',
    'potassium' => 'Medium'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title>Index</title>
  <meta name="description" content="">
  <meta name="keywords" content="">

  <!-- Favicons -->
  <link href="assets/img/favicon.png" rel="icon">
  <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

  <!-- Fonts -->
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link href="https://fonts.gstatic.com" rel="preconnect" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,300;1,400;1,500;1,600;1,700;1,800&family=Marcellus:wght@400&display=swap" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
  <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">

  <!-- Main CSS File -->
  <link href="assets/css/main.css" rel="stylesheet">

  <!-- =======================================================
  * Template Name: AgriCulture
  * Template URL: https://bootstrapmade.com/agriculture-bootstrap-website-template/
  * Updated: Aug 07 2024 with Bootstrap v5.3.3
  * Author: BootstrapMade.com
  * License: https://bootstrapmade.com/license/
  ======================================================== -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
</head>

<body class="index-page">

  <header id="header" class="header d-flex align-items-center position-relative">
    <div class="container-fluid container-xl position-relative d-flex align-items-center justify-content-between">

      <a href="index.html" class="logo d-flex align-items-center">
        <!-- Uncomment the line below if you also wish to use an image logo -->
        <img src="logo.webp" alt="AgriCulture">
        <!-- <h1 class="sitename">AgriCulture</h1>  -->
      </a>

      <nav id="navmenu" class="navmenu">
        <ul>
          <li><a href="index.html" class="active">Home</a></li>
          <li><a href="about.html">About Us</a></li>
          <li><a href="services.html">Our Services</a></li>
          <li><a href="testimonials.html">Testimonials</a></li>
          <li><a href="blog.html">Blog</a></li>
          <li class="dropdown">
            <a href="#"><span>soil analysis</span> <i class="bi bi-chevron-down toggle-dropdown"></i></a>
            <ul>
              <li><a href="https://soiltestpro.com/custom-soil-sampling-coverage-area/">soil analysis website</a></li>
              <li class="dropdown">
                
              </li>
              <li><a href="https://soiltestpro.com/soil-testing-labs/">soil sampling</a></li>
              <li><a href="#">soil sampling tips</a></li>
              <li><a href="https://www.mountain-forecast.com/peaks/Cardamom-Hills/forecasts/2637">Weather</a></li>
            </ul>
          </li>
          <li><a href="contact.html">Contact</a></li>
          <li class="nav-item dropdown">
            <a href="#" class="profile-icon nav-link dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-fill fs-4"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
                
                <li><a class="dropdown-item" href="login.php">Login</a></li>
                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
            </ul>
          </li>
          
        </ul>
        <i class="mobile-nav-toggle d-xl-none bi bi-list"></i>
      </nav>
      
      <style>
      .profile-icon {
          padding: 8px;
          display: flex;
          align-items: center;
      }
      
      .profile-icon i {
          font-size: 1.8rem; /* Increased from 1.2rem */
      }
      
      /* Add hover effect */
      .profile-icon:hover {
          opacity: 0.8;
          transition: opacity 0.3s ease;
      }
      </style>
    </div>
  </header>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Farmer Dashboard</title>
    <!-- Add link to Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Global Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        body {
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #007bff;
            padding: 20px;
            border-radius: 8px;
            color: white;
        }

        .dashboard-header h1 {
            font-size: 24px;
        }

        .logout-button {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .logout-button:hover {
            background-color: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            font-size: 20px;
            margin-bottom: 10px;
            color: #007bff;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
        }

        .metric-item {
            text-align: center;
            padding: 10px;
            background-color: #f1f3f5;
            border-radius: 6px;
        }

        .metric-label {
            font-size: 14px;
            color: #555;
        }

        .metric-value {
            font-size: 18px;
            font-weight: bold;
        }

        .task-list ul {
            list-style: none;
            padding: 0;
        }

        .task-list li {
            background: #f8f9fa;
            margin: 10px 0;
            padding: 10px;
            border-left: 5px solid #007bff;
            border-radius: 6px;
        }

        .task-title {
            font-weight: bold;
        }

        .priority-urgent {
            border-left-color: #dc3545;
        }

        .priority-normal {
            border-left-color: #ffc107;
        }

        .priority-pending {
            border-left-color: #28a745;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard-header">
            <div>
                <h1>Welcome to GrowGuide,<?php echo htmlspecialchars($_SESSION['username']); ?> </h1>
                <p>Your Cardamom Plantation Management Dashboard</p>
            </div>
            <a href="?logout=1" class="logout-button">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>

        <div class="grid-container">
            <div class="card">
                <h2>Weather Conditions</h2>
                <div class="metric-grid">
                    <div class="metric-item">
                        <div class="metric-label">Temperature</div>
                        <div class="metric-value">24°C</div>
                    </div>
                    <div class="metric-item">
                        <div class="metric-label">Humidity</div>
                        <div class="metric-value">75%</div>
                    </div>
                    <div class="metric-item">
                        <div class="metric-label">Rainfall</div>
                        <div class="metric-value">2.5mm</div>
                    </div>
                    <div class="metric-item">
                        <div class="metric-label">Forecast</div>
                        <div class="metric-value">Cloudy</div>
                    </div>
                </div>
            </div>

            <div class="card task-list">
                <h2>Tasks</h2>
                <ul>
                    <?php foreach ($tasks as $task): ?>
                        <li class="priority-<?php echo strtolower($task['priority']); ?>">
                            <div class="task-title"><?php echo $task['title']; ?></div>
                            <div>Due: <?php echo $task['due_date']; ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="card">
                <h2>Soil Health</h2>
                <ul>
                    <?php foreach ($soil_health as $key => $value): ?>
                        <li><?php echo ucfirst($key); ?>: <?php echo $value; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
