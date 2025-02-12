<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
$username = $_SESSION['username'];

// Initialize variables with default values
$total_farmers = 0;
$total_land = 0;
$total_varieties = 0;
$total_employees = 0;
$total_soil_tests = 0;

// Fetch total farmers
$farmers_query = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='farmer'");
if ($farmers_query) {
    $total_farmers = $farmers_query->fetch_assoc()['count'];
}

// Fetch total land
$land_query = $conn->query("SELECT COALESCE(SUM(farm_size), 0) as total FROM farmers");
if ($land_query) {
    $total_land = $land_query->fetch_assoc()['total'];
}

// Fetch total varieties
$varieties_query = $conn->query("SELECT COUNT(*) as count FROM cardamom_variety");
if ($varieties_query) {
    $total_varieties = $varieties_query->fetch_assoc()['count'];
}

// Fetch total employees
$employees_query = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='employee'");
if ($employees_query) {
    $total_employees = $employees_query->fetch_assoc()['count'];
}

// Fetch total soil tests
$total_tests_query = $conn->query("SELECT COUNT(*) as count FROM soil_tests");
if ($total_tests_query) {
    $total_soil_tests = $total_tests_query->fetch_assoc()['count'];
}

// Fetch recent soil tests and recommendations with error handling
$recent_soil_tests = [];
$soil_tests_query = $conn->query("
    SELECT st.*, f.farm_location, u.username 
    FROM soil_tests st
    JOIN farmers f ON st.farmer_id = f.farmer_id
    JOIN users u ON f.user_id = u.user_id
    ORDER BY st.test_date DESC 
    LIMIT 5
");
if ($soil_tests_query) {
    while ($row = $soil_tests_query->fetch_assoc()) {
        $recent_soil_tests[] = $row;
    }
}

// Fetch recent fertilizer recommendations with error handling
$recent_recommendations = [];
$recommendations_query = $conn->query("
    SELECT fr.*, st.ph_level, u.username
    FROM fertilizer_recommendations fr
    JOIN soil_tests st ON fr.soil_test_id = st.soil_test_id
    JOIN farmers f ON st.farmer_id = f.farmer_id
    JOIN users u ON f.user_id = u.user_id
    ORDER BY fr.application_date DESC 
    LIMIT 5
");
if ($recommendations_query) {
    while ($row = $recommendations_query->fetch_assoc()) {
        $recent_recommendations[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide | Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        body {
            display: flex;
            background: #f4f6f9;
        }
        .sidebar {
            width: 250px;
            background: #2e7d32;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
        }
        .content {
            margin-left: 250px;
            width: calc(100% - 250px);
            padding: 20px;
        }
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .stat-icon {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 50%;
            font-size: 24px;
        }
        .section-title {
            margin: 20px 0 10px;
            color: #2e7d32;
        }
        .data-table {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .sidebar-nav a {
            display: block;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            transition: background 0.3s;
        }
        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(255,255,255,0.1);
        }
        .sidebar-logo {
            padding: 20px;
            text-align: center;
            color: white;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn-primary {
            background: #2e7d32;
            color: white;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        .error-message {
            color: red;
            margin: 10px 0;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
        }
        .modal-content {
            background: white;
            width: 80%;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            border-radius: 10px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px 0;
        }
        
        .user-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .user-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .user-card-actions {
            display: flex;
            gap: 10px;
        }
        
        .user-card-info {
            margin-bottom: 10px;
        }
        
        .user-card-info p {
            margin: 5px 0;
            color: #666;
        }
        
        .user-card-info strong {
            color: #333;
        }
        
        .form-error {
            color: red;
            font-size: 0.8em;
            margin-top: 5px;
        }

        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
        }

        .validation-message {
            color: #dc3545;
            font-size: 0.8em;
            margin-top: 5px;
            min-height: 1em;
        }

        .form-group input.invalid,
        .form-group select.invalid {
            border-color: #dc3545;
        }

        .form-group input.valid,
        .form-group select.valid {
            border-color: #28a745;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-logo">
            <h2>GrowGuide</h2>
            <p style="color: white; font-size: 14px; margin-top: 5px;">Welcome, <?php echo htmlspecialchars($username); ?></p>
        </div>
        <nav class="sidebar-nav">
            <a href="admin.php" class="nav-link" data-page="dashboard"><i class="fas fa-chart-line"></i> Dashboard</a>
            <a href="#" class="nav-link" data-page="farmers"><i class="fas fa-users"></i> Farmers</a>
            <a href="#" class="nav-link" data-page="employees"><i class="fas fa-user-tie"></i> Employees</a>
            <a href="#" class="nav-link" data-page="soil-tests"><i class="fas fa-flask"></i> Soil Tests</a>
            <a href="#" class="nav-link" data-page="varieties"><i class="fas fa-seedling"></i> Varieties</a>
        </nav>
    </div>
    
    <div class="content">
        <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h1>Cardamom Plantation Dashboard</h1>
            <div>
                <span>Welcome, <?php echo htmlspecialchars($username); ?></span>
                <a href="login.php" style="margin-left: 10px;"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </header>

        <?php if ($conn->connect_error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> 
                Database Connection Error: <?php echo $conn->connect_error; ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-stats">
            <div class="stat-card">
                <div>
                    <h4>Total Farmers</h4>
                    <h2><?php echo number_format($total_farmers); ?></h2>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-user-farmer"></i>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <h4>Total Land Area</h4>
                    <h2><?php echo number_format($total_land, 2); ?> hectares</h2>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-map"></i>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <h4>Cardamom Varieties</h4>
                    <h2><?php echo number_format($total_varieties); ?></h2>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-leaf"></i>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <h4>Total Employees</h4>
                    <h2><?php echo number_format($total_employees); ?></h2>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
            </div>
            <div class="stat-card">
                <div>
                    <h4>Total Soil Tests</h4>
                    <h2><?php echo number_format($total_soil_tests); ?></h2>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-flask"></i>
                </div>
            </div>
        </div>

        <div class="data-section">
            <h2 class="section-title">Recent Soil Tests</h2>
            <div class="data-table">
                <?php if (empty($recent_soil_tests)): ?>
                    <p>No recent soil tests found.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Farmer</th>
                                <th>Location</th>
                                <th>pH Level</th>
                                <th>Test Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_soil_tests as $test): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($test['username']); ?></td>
                                <td><?php echo htmlspecialchars($test['farm_location']); ?></td>
                                <td><?php echo htmlspecialchars($test['ph_level']); ?></td>
                                <td><?php echo htmlspecialchars($test['test_date']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="data-section">
            <h2 class="section-title">Recent Fertilizer Recommendations</h2>
            <div class="data-table">
                <?php if (empty($recent_recommendations)): ?>
                    <p>No recent fertilizer recommendations found.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Farmer</th>
                                <th>Fertilizer</th>
                                <th>Soil pH</th>
                                <th>Application Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_recommendations as $rec): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($rec['username']); ?></td>
                                <td><?php echo htmlspecialchars($rec['fertilizer_name']); ?></td>
                                <td><?php echo htmlspecialchars($rec['ph_level']); ?></td>
                                <td><?php echo htmlspecialchars($rec['application_date']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Move soil tests section here, before farmers section -->
        <div id="soil-tests" class="page-section" style="display: none;">
            <div class="section-header">
                <h2>Soil Tests Management</h2>
                <button class="btn btn-primary" onclick="openSoilTestModal()">
                    <i class="fas fa-plus"></i> Add Soil Test
                </button>
            </div>
            <div class="data-table">
                <table id="soil-tests-table">
                    <thead>
                        <tr>
                            <th>Farmer</th>
                            <th>Test Date</th>
                            <th>pH Level</th>
                            <th>Nitrogen (N)</th>
                            <th>Phosphorus (P)</th>
                            <th>Potassium (K)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Soil tests will be loaded here dynamically -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Farmers section -->
        <div id="farmers" class="page-section" style="display: none;">
            <div class="section-header">
                <h2>Farmers Management</h2>
                <button class="btn btn-primary" onclick="openModal('farmer')">
                    <i class="fas fa-plus"></i> Add Farmer
                </button>
            </div>
            <div class="data-table">
                <table id="farmers-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Farmers will be loaded here dynamically -->
                    </tbody>
                </table>
            </div>
        </div>

        <div id="employees" class="page-section" style="display: none;">
            <div class="section-header">
                <h2>Employees Management</h2>
                <button class="btn btn-primary" onclick="openModal('employee')">
                    <i class="fas fa-plus"></i> Add Employee
                </button>
            </div>
            <div class="data-table">
                <table id="employees-table">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Employees will be loaded here dynamically -->
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add the varieties section after employees section -->
        <div id="varieties" class="page-section" style="display: none;">
            <div class="section-header">
                <h2>Cardamom Varieties Management</h2>
                <button class="btn btn-primary" onclick="openVarietyModal()">
                    <i class="fas fa-plus"></i> Add Variety
                </button>
            </div>
            <div class="data-table">
                <table id="varieties-table">
                    <thead>
                        <tr>
                            <th>Variety Name</th>
                            <th>Scientific Name</th>
                            <th>Growing Period</th>
                            <th>Yield Rate</th>
                            <th>Disease Resistance</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Varieties will be loaded here dynamically -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add/Edit User Modal -->
    <div id="userModal" class="modal">
        <div class="modal-content">
            <h2 id="modalTitle">Add New User</h2>
            <form id="userForm" onsubmit="return saveUser(event)">
                <input type="hidden" id="userId" name="user_id">
                <input type="hidden" id="userRole" name="role">
                
                <div class="form-group">
                    <label for="username">Username*</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email*</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone*</label>
                    <input type="tel" id="phone" name="phone" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password*</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <div class="form-group">
                    <label for="confirmPassword">Confirm Password*</label>
                    <input type="password" id="confirmPassword" name="confirm_password" required>
                </div>

                <div class="form-group">
                    <label for="roleSelect">Role*</label>
                    <select id="roleSelect" name="assigned_role" required>
                        <option value="">Select Role</option>
                        <option value="farmer">Farmer</option>
                        <option value="employee">Employee</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-primary">Save</button>
                <button type="button" class="btn btn-danger" onclick="closeModal()">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Add Variety Modal -->
    <div id="varietyModal" class="modal">
        <div class="modal-content">
            <h2 id="varietyModalTitle">Add New Cardamom Variety</h2>
            <form id="varietyForm" onsubmit="return saveVariety(event)">
                <input type="hidden" id="varietyId" name="variety_id">
                
                <div class="form-group">
                    <label for="varietyName">Variety Name*</label>
                    <input type="text" id="varietyName" name="variety_name" required>
                </div>
                
                <div class="form-group">
                    <label for="scientificName">Scientific Name</label>
                    <input type="text" id="scientificName" name="scientific_name">
                </div>
                
                <div class="form-group">
                    <label for="description">Description*</label>
                    <textarea id="description" name="description" required rows="3"></textarea>
                </div>
                
                <div class="form-group">
                    <label for="growingPeriod">Growing Period (months)*</label>
                    <input type="number" id="growingPeriod" name="growing_period" min="1" required>
                </div>
                
                <div class="form-group">
                    <label for="yieldRate">Average Yield Rate (kg/ha)*</label>
                    <input type="number" id="yieldRate" name="yield_rate" min="0" step="0.01" required>
                </div>
                
                <div class="form-group">
                    <label for="optimalPh">Optimal pH Range</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="number" id="optimalPhMin" name="optimal_ph_min" min="0" max="14" step="0.1" placeholder="Min">
                        <input type="number" id="optimalPhMax" name="optimal_ph_max" min="0" max="14" step="0.1" placeholder="Max">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="diseaseResistance">Disease Resistance</label>
                    <select id="diseaseResistance" name="disease_resistance">
                        <option value="">Select Resistance Level</option>
                        <option value="High">High</option>
                        <option value="Medium">Medium</option>
                        <option value="Low">Low</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="maintenanceRequirements">Maintenance Requirements</label>
                    <textarea id="maintenanceRequirements" name="maintenance_requirements" rows="2"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Save Variety</button>
                <button type="button" class="btn btn-danger" onclick="closeVarietyModal()">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Soil Test Modal -->
    <div id="soilTestModal" class="modal">
        <div class="modal-content">
            <h2 id="soilTestModalTitle">Add New Soil Test</h2>
            <form id="soilTestForm" onsubmit="return validateAndSaveSoilTest(event)">
                <input type="hidden" id="soilTestId" name="soil_test_id">
                
                <div class="form-group">
                    <label for="farmer">Farmer*</label>
                    <select id="farmer" name="farmer_id" required onchange="validateField(this)">
                        <option value="">Select Farmer</option>
                    </select>
                    <div class="validation-message"></div>
                </div>
                
                <div class="form-group">
                    <label for="testDate">Test Date*</label>
                    <input type="date" id="testDate" name="test_date" required onchange="validateField(this)">
                    <div class="validation-message"></div>
                </div>
                
                <div class="form-group">
                    <label for="phLevel">pH Level* (0-14)</label>
                    <input type="number" id="phLevel" name="ph_level" step="0.1" min="0" max="14" required 
                           onchange="validateField(this)" oninput="validateField(this)">
                    <div class="validation-message"></div>
                </div>
                
                <div class="form-group">
                    <label for="nitrogen">Nitrogen (N) Level (mg/kg)*</label>
                    <input type="number" id="nitrogen" name="nitrogen_level" step="0.01" min="0" required 
                           onchange="validateField(this)" oninput="validateField(this)">
                    <div class="validation-message"></div>
                </div>
                
                <div class="form-group">
                    <label for="phosphorus">Phosphorus (P) Level (mg/kg)*</label>
                    <input type="number" id="phosphorus" name="phosphorus_level" step="0.01" min="0" required 
                           onchange="validateField(this)" oninput="validateField(this)">
                    <div class="validation-message"></div>
                </div>
                
                <div class="form-group">
                    <label for="potassium">Potassium (K) Level (mg/kg)*</label>
                    <input type="number" id="potassium" name="potassium_level" step="0.01" min="0" required 
                           onchange="validateField(this)" oninput="validateField(this)">
                    <div class="validation-message"></div>
                </div>
                
                <div class="form-group">
                    <label for="organicMatter">Organic Matter (%)</label>
                    <input type="number" id="organicMatter" name="organic_matter" step="0.01" min="0" max="100"
                           onchange="validateField(this)" oninput="validateField(this)">
                    <div class="validation-message"></div>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" name="notes" rows="3"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Save Soil Test</button>
                <button type="button" class="btn btn-danger" onclick="closeSoilTestModal()">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Navigation handling
            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const page = this.dataset.page;
                    showPage(page);
                    loadPageData(page);
                    
                    // Update URL hash without page reload
                    window.location.hash = page;
                });
            });
            
            // Handle initial page load based on URL hash
            const hash = window.location.hash.substring(1);
            if (hash) {
                showPage(hash);
                loadPageData(hash);
            } else {
                showPage('dashboard');
            }
            
            // Add click handler for soil test stat card
            document.querySelector('.stat-card:nth-child(5)').addEventListener('click', function() {
                showPage('soil-tests');
                loadPageData('soil-tests');
                window.location.hash = 'soil-tests';
            });
        });

        function showPage(pageId) {
            document.querySelectorAll('.page-section').forEach(section => {
                section.style.display = 'none';
            });
            document.getElementById(pageId).style.display = 'block';
            
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
                if (link.dataset.page === pageId) {
                    link.classList.add('active');
                }
            });
        }

        function loadPageData(page) {
            if (page === 'farmers') {
                fetchFarmers();
            } else if (page === 'employees') {
                fetchEmployees();
            } else if (page === 'varieties') {
                fetchVarieties();
            } else if (page === 'soil-tests') {
                fetchSoilTests();
                loadFarmersList();
            }
        }

        function fetchFarmers() {
            fetch('get_users.php?role=farmer')
                .then(response => response.json())
                .then(farmers => {
                    const tbody = document.querySelector('#farmers-table tbody');
                    tbody.innerHTML = '';
                    
                    farmers.forEach(farmer => {
                        const row = `
                            <tr>
                                <td>${escapeHtml(farmer.username)}</td>
                                <td>${escapeHtml(farmer.email)}</td>
                                <td>${escapeHtml(farmer.phone)}</td>
                                <td>${farmer.role}</td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="editUser(${farmer.user_id}, 'farmer')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteUser(${farmer.user_id}, 'farmer')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                        tbody.insertAdjacentHTML('beforeend', row);
                    });
                })
                .catch(error => console.error('Error:', error));
        }

        function fetchEmployees() {
            fetch('get_users.php?role=employee')
                .then(response => response.json())
                .then(employees => {
                    const tbody = document.querySelector('#employees-table tbody');
                    tbody.innerHTML = '';
                    
                    employees.forEach(employee => {
                        const row = `
                            <tr>
                                <td>${escapeHtml(employee.username)}</td>
                                <td>${escapeHtml(employee.email)}</td>
                                <td>${escapeHtml(employee.phone)}</td>
                                <td>${employee.role}</td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="editUser(${employee.user_id}, 'employee')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteUser(${employee.user_id}, 'employee')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                        tbody.insertAdjacentHTML('beforeend', row);
                    });
                })
                .catch(error => console.error('Error:', error));
        }

        function fetchVarieties() {
            fetch('get_varieties.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(varieties => {
                    const tbody = document.querySelector('#varieties-table tbody');
                    tbody.innerHTML = '';
                    
                    varieties.forEach(variety => {
                        const row = `
                            <tr>
                                <td>${escapeHtml(variety.variety_name)}</td>
                                <td>${escapeHtml(variety.scientific_name || 'N/A')}</td>
                                <td>${escapeHtml(variety.growing_period)} months</td>
                                <td>${escapeHtml(variety.yield_rate)} kg/ha</td>
                                <td>${escapeHtml(variety.disease_resistance || 'N/A')}</td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="editVariety(${variety.variety_id})">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteVariety(${variety.variety_id})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                        tbody.insertAdjacentHTML('beforeend', row);
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error fetching varieties. Please try again.');
                });
        }

        function fetchSoilTests() {
            fetch('get_soil_tests.php')
                .then(response => response.json())
                .then(tests => {
                    const tbody = document.querySelector('#soil-tests-table tbody');
                    tbody.innerHTML = '';
                    
                    tests.forEach(test => {
                        const row = `
                            <tr>
                                <td>${escapeHtml(test.username)}</td>
                                <td>${escapeHtml(test.test_date)}</td>
                                <td>${escapeHtml(test.ph_level)}</td>
                                <td>${escapeHtml(test.nitrogen_level)}</td>
                                <td>${escapeHtml(test.phosphorus_level)}</td>
                                <td>${escapeHtml(test.potassium_level)}</td>
                                <td>
                                    <button class="btn btn-primary btn-sm" onclick="editSoilTest(${test.soil_test_id})">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="deleteSoilTest(${test.soil_test_id})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                        tbody.insertAdjacentHTML('beforeend', row);
                    });
                })
                .catch(error => console.error('Error:', error));
        }

        function loadFarmersList() {
            fetch('get_users.php?role=farmer')
                .then(response => response.json())
                .then(farmers => {
                    const select = document.getElementById('farmer');
                    select.innerHTML = '<option value="">Select Farmer</option>';
                    
                    farmers.forEach(farmer => {
                        const option = `<option value="${farmer.user_id}">${escapeHtml(farmer.username)}</option>`;
                        select.insertAdjacentHTML('beforeend', option);
                    });
                })
                .catch(error => console.error('Error:', error));
        }

        function validateForm() {
            const form = document.getElementById('userForm');
            const password = form.password.value;
            const confirmPassword = form.confirm_password.value;
            const email = form.email.value;
            const phone = form.phone.value;
            const userId = form.user_id.value;
            const username = form.username.value;
            
            // Clear previous errors
            document.querySelectorAll('.form-error').forEach(error => error.remove());
            
            let isValid = true;
            
            // Validate username
            if (!username || username.trim().length < 3) {
                showError(form.username, 'Username must be at least 3 characters long');
                isValid = false;
            }
            
            // Validate email
            if (!email || !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                showError(form.email, 'Please enter a valid email address');
                isValid = false;
            }
            
            // Validate phone
            if (!phone || !phone.match(/^\+?[\d\s-]{10,}$/)) {
                showError(form.phone, 'Please enter a valid phone number (minimum 10 digits)');
                isValid = false;
            }
            
            // Only validate password for new users or if password field is not empty
            if (!userId || password || confirmPassword) {
                if (password.length < 6) {
                    showError(form.password, 'Password must be at least 6 characters long');
                    isValid = false;
                }
                
                if (password !== confirmPassword) {
                    showError(form.confirm_password, 'Passwords do not match');
                    isValid = false;
                }
            }
            
            return isValid;
        }

        function showError(input, message) {
            const error = document.createElement('div');
            error.className = 'form-error';
            error.textContent = message;
            input.parentNode.appendChild(error);
        }

        function saveUser(event) {
            event.preventDefault();
            
            if (!validateForm()) {
                return false;
            }
            
            const formData = new FormData(document.getElementById('userForm'));
            
            // Add the role from roleSelect to formData
            const roleSelect = document.getElementById('roleSelect');
            formData.set('role', roleSelect.value);
            
            // If editing (userId exists), add it to formData
            const userId = document.getElementById('userId').value;
            if (userId) {
                formData.append('user_id', userId);
            }
            
            fetch('save_user.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeModal();
                    // Reload the appropriate table based on the role
                    loadPageData(roleSelect.value + 's'); // Add 's' to match page IDs ('farmers', 'employees')
                    alert('User saved successfully!');
                } else {
                    throw new Error(data.message || 'Error saving user');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(error.message || 'Error saving user. Please try again.');
            });
            
            return false;
        }

        // Add event listener for role select
        document.getElementById('roleSelect').addEventListener('change', function() {
            const farmerFields = document.getElementById('farmerFields');
            const employeeFields = document.getElementById('employeeFields');
            
            farmerFields.style.display = this.value === 'farmer' ? 'block' : 'none';
            employeeFields.style.display = this.value === 'employee' ? 'block' : 'none';
        });

        function openModal(type) {
            document.getElementById('userModal').style.display = 'block';
            document.getElementById('modalTitle').textContent = `Add New ${type.charAt(0).toUpperCase() + type.slice(1)}`;
            document.getElementById('userForm').reset();
            document.getElementById('userId').value = '';
            document.getElementById('userRole').value = type;
            document.getElementById('roleSelect').value = type;
            
            // Show/hide relevant fields
            document.getElementById('farmerFields').style.display = type === 'farmer' ? 'block' : 'none';
            document.getElementById('employeeFields').style.display = type === 'employee' ? 'block' : 'none';
        }

        function closeModal() {
            document.getElementById('userModal').style.display = 'none';
        }

        function editUser(userId, type) {
            fetch(`get_user.php?id=${userId}`)
                .then(response => response.json())
                .then(user => {
                    document.getElementById('userModal').style.display = 'block';
                    document.getElementById('modalTitle').textContent = 'Edit User';
                    document.getElementById('userId').value = user.user_id;
                    document.getElementById('username').value = user.username;
                    document.getElementById('email').value = user.email;
                    document.getElementById('phone').value = user.phone || '';
                    document.getElementById('roleSelect').value = user.role;
                    
                    if (user.role === 'farmer') {
                        document.getElementById('farmerFields').style.display = 'block';
                        document.getElementById('employeeFields').style.display = 'none';
                    } else if (user.role === 'employee') {
                        document.getElementById('farmerFields').style.display = 'none';
                        document.getElementById('employeeFields').style.display = 'block';
                    }
                })
                .catch(error => console.error('Error:', error));
        }

        function deleteUser(userId, type) {
            if (confirm('Are you sure you want to delete this user?')) {
                fetch('save_user.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&user_id=${userId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadPageData(type);
                    } else {
                        alert(data.message || 'Error deleting user');
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }

        function openVarietyModal(varietyId = null) {
            document.getElementById('varietyModal').style.display = 'block';
            document.getElementById('varietyModalTitle').textContent = varietyId ? 'Edit Variety' : 'Add New Variety';
            document.getElementById('varietyForm').reset();
            document.getElementById('varietyId').value = varietyId || '';
        }

        function closeVarietyModal() {
            document.getElementById('varietyModal').style.display = 'none';
        }

        function saveVariety(event) {
            event.preventDefault();
            
            const formData = new FormData(document.getElementById('varietyForm'));
            
            // Add validation
            const varietyName = formData.get('variety_name');
            const description = formData.get('description');
            const growingPeriod = formData.get('growing_period');
            const yieldRate = formData.get('yield_rate');
            
            if (!varietyName || !description || !growingPeriod || !yieldRate) {
                alert('Please fill in all required fields');
                return false;
            }
            
            fetch('save_variety.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    closeVarietyModal();
                    fetchVarieties(); // Refresh the varieties table
                    alert('Variety saved successfully!');
                } else {
                    alert(data.message || 'Error saving variety');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving variety. Please try again.');
            });
            
            return false;
        }

        function editVariety(varietyId) {
            fetch(`get_variety.php?id=${varietyId}`)
                .then(response => response.json())
                .then(variety => {
                    openVarietyModal(varietyId);
                    document.getElementById('varietyName').value = variety.variety_name;
                    document.getElementById('scientificName').value = variety.scientific_name || '';
                    document.getElementById('description').value = variety.description;
                    document.getElementById('growingPeriod').value = variety.growing_period;
                    document.getElementById('yieldRate').value = variety.yield_rate;
                    document.getElementById('optimalPhMin').value = variety.optimal_ph_min || '';
                    document.getElementById('optimalPhMax').value = variety.optimal_ph_max || '';
                    document.getElementById('diseaseResistance').value = variety.disease_resistance || '';
                    document.getElementById('maintenanceRequirements').value = variety.maintenance_requirements || '';
                })
                .catch(error => console.error('Error:', error));
        }

        function deleteVariety(varietyId) {
            if (confirm('Are you sure you want to delete this variety?')) {
                fetch('save_variety.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&variety_id=${varietyId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        fetchVarieties();
                    } else {
                        alert(data.message || 'Error deleting variety');
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }

        function openSoilTestModal(testId = null) {
            document.getElementById('soilTestModal').style.display = 'block';
            document.getElementById('soilTestModalTitle').textContent = testId ? 'Edit Soil Test' : 'Add New Soil Test';
            document.getElementById('soilTestForm').reset();
            document.getElementById('soilTestId').value = testId || '';
            
            // Clear validation states
            const form = document.getElementById('soilTestForm');
            form.querySelectorAll('.validation-message').forEach(msg => msg.textContent = '');
            form.querySelectorAll('input, select').forEach(field => {
                field.classList.remove('valid', 'invalid');
            });
            
            if (!testId) {
                document.getElementById('testDate').valueAsDate = new Date();
            }
        }

        function closeSoilTestModal() {
            document.getElementById('soilTestModal').style.display = 'none';
        }

        function validateField(field) {
            const validationMessage = field.parentElement.querySelector('.validation-message');
            field.classList.remove('valid', 'invalid');
            validationMessage.textContent = '';

            if (field.required && !field.value) {
                field.classList.add('invalid');
                validationMessage.textContent = 'This field is required';
                return false;
            }

            switch (field.id) {
                case 'phLevel':
                    if (field.value < 0 || field.value > 14) {
                        field.classList.add('invalid');
                        validationMessage.textContent = 'pH level must be between 0 and 14';
                        return false;
                    }
                    break;
                case 'nitrogen':
                case 'phosphorus':
                case 'potassium':
                    if (field.value < 0) {
                        field.classList.add('invalid');
                        validationMessage.textContent = 'Value must be positive';
                        return false;
                    }
                    break;
                case 'organicMatter':
                    if (field.value && (field.value < 0 || field.value > 100)) {
                        field.classList.add('invalid');
                        validationMessage.textContent = 'Organic matter must be between 0 and 100%';
                        return false;
                    }
                    break;
            }

            field.classList.add('valid');
            return true;
        }

        function validateAndSaveSoilTest(event) {
            event.preventDefault();
            
            const form = document.getElementById('soilTestForm');
            const fields = form.querySelectorAll('input[required], select[required]');
            let isValid = true;

            fields.forEach(field => {
                if (!validateField(field)) {
                    isValid = false;
                }
            });

            if (!isValid) {
                return false;
            }

            const formData = new FormData(form);
            
            fetch('save_soil_test.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeSoilTestModal();
                    fetchSoilTests();
                    alert('Soil test saved successfully!');
                } else {
                    throw new Error(data.message || 'Error saving soil test');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert(error.message || 'Error saving soil test. Please try again.');
            });
            
            return false;
        }

        function editSoilTest(testId) {
            fetch(`get_soil_test.php?id=${testId}`)
                .then(response => response.json())
                .then(test => {
                    openSoilTestModal(testId);
                    document.getElementById('farmer').value = test.farmer_id;
                    document.getElementById('testDate').value = test.test_date;
                    document.getElementById('phLevel').value = test.ph_level;
                    document.getElementById('nitrogen').value = test.nitrogen_level;
                    document.getElementById('phosphorus').value = test.phosphorus_level;
                    document.getElementById('potassium').value = test.potassium_level;
                    document.getElementById('organicMatter').value = test.organic_matter || '';
                    document.getElementById('notes').value = test.notes || '';
                })
                .catch(error => console.error('Error:', error));
        }

        function deleteSoilTest(testId) {
            if (confirm('Are you sure you want to delete this soil test?')) {
                fetch('save_soil_test.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&soil_test_id=${testId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        fetchSoilTests();
                    } else {
                        alert(data.message || 'Error deleting soil test');
                    }
                })
                .catch(error => console.error('Error:', error));
            }
        }

        // Helper function to escape HTML and prevent XSS
        function escapeHtml(unsafe) {
            if (unsafe === null || unsafe === undefined) return '';
            return unsafe
                .toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    </script>
</body>
</html>