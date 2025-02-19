<?php
session_start();
require_once 'config.php';

// Initialize database connection
$dbConfig = new DatabaseConfig();
$conn = $dbConfig->getConnection();

// Get soil tests data from database
$soil_tests = [];
$query = "SELECT st.*, u.username as farmer_name 
          FROM soil_tests st 
          JOIN users u ON st.farmer_id = u.id 
          ORDER BY st.test_date DESC";

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $soil_tests[] = $row;
    }
    mysqli_free_result($result);
}

// Get farmers for dropdown
$farmers = [];
$query = "SELECT id, username FROM users WHERE role = 'farmer' AND status = 1";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $farmers[] = $row;
    }
    mysqli_free_result($result);
}

// Form processing
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_soil_test'])) {
        $farmer_id = mysqli_real_escape_string($conn, $_POST['farmer_id']);
        $ph_level = mysqli_real_escape_string($conn, $_POST['ph_level']);
        $nitrogen = mysqli_real_escape_string($conn, $_POST['nitrogen']);
        $phosphorus = mysqli_real_escape_string($conn, $_POST['phosphorus']);
        $potassium = mysqli_real_escape_string($conn, $_POST['potassium']);
        $test_date = date('Y-m-d');
        
        $insert_query = "INSERT INTO soil_tests (farmer_id, ph_level, nitrogen, phosphorus, potassium, test_date) 
                        VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $insert_query);
        
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "idddds", $farmer_id, $ph_level, $nitrogen, $phosphorus, $potassium, $test_date);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = 'Soil test added successfully!';
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $message = 'Error adding soil test: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Soil Tests</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary-color: #2e7d32;
            --primary-dark: #1b5e20;
            --error-color: #dc3545;
            --success-color: #28a745;
        }

        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            background: #f5f5f5;
        }

        .sidebar {
            width: 250px;
            background: var(--primary-color);
            color: white;
            height: 100vh;
            padding: 10px;
            position: fixed;
            transition: width 0.3s;
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            padding: 10px;
        }

        .sidebar-header i {
            font-size: 1.5em;
            margin-right: 10px;
        }

        .sidebar-header span {
            font-size: 1.2em;
            font-weight: 600;
        }

        .menu {
            list-style: none;
            padding: 0;
        }

        .menu li {
            padding: 10px;
        }

        .menu li a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .menu li a i {
            margin-right: 10px;
        }

        .menu li.active a {
            font-weight: 600;
        }

        .toggle-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.5em;
            cursor: pointer;
            padding: 0;
            margin-left: auto;
        }

        .content {
            margin-left: 260px;
            padding: 20px;
            transition: margin-left 0.3s;
            width: calc(100% - 260px);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .form-group {
            position: relative;
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: all 0.3s;
            box-sizing: border-box;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
        }

        button[type="submit"] {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: background 0.3s;
            margin-top: 20px;
        }

        button[type="submit"]:hover {
            background: var(--primary-dark);
        }

        .test-results {
            margin-top: 30px;
        }

        .test-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }

        .test-card:hover {
            transform: translateY(-3px);
        }

        .test-card h4 {
            color: #333;
            margin-top: 0;
            margin-bottom: 15px;
        }

        .test-card p {
            color: #666;
            margin: 8px 0;
        }

        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            color: var(--error-color);
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .form-group input:invalid,
        .form-group select:invalid {
            border-color: var(--error-color);
        }
        .admin-dashboard-link {
    position: fixed;
    top: 20px;
    right: 20px;
    background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
    color: white;
    padding: 12px 24px;
    border-radius: 50px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
    box-shadow: 0 4px 15px rgba(46, 125, 50, 0.2);
    transition: all 0.3s ease;
    z-index: 1000;
    border: 2px solid rgba(255, 255, 255, 0.1);
}

.admin-dashboard-link:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(46, 125, 50, 0.3);
    background: linear-gradient(135deg, #33873b 0%, #1e6823 100%);
}

.admin-dashboard-link i {
    font-size: 20px;
}

.admin-dashboard-link .icon-container {
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
    width: 32px;
    height: 32px;
    transition: all 0.3s ease;
}

.admin-dashboard-link:hover .icon-container {
    transform: rotate(360deg);
    background: rgba(255, 255, 255, 0.2);
}

.admin-dashboard-link .text {
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

@media (max-width: 768px) {
    .admin-dashboard-link {
        padding: 10px 16px;
    }
    
    .admin-dashboard-link .text {
        display: none;
    }
    
    .admin-dashboard-link .icon-container {
        width: 28px;
        height: 28px;
    }
}
    </style>
</head>
<body>
<a href="admin.php" class="admin-dashboard-link">
    <div class="icon-container">
        <i class="fas fa-user-shield"></i>
    </div>
    <span class="text">Admin Dashboard</span>
</a>
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-seedling"></i>
            <span>GrowGuide</span>
        </div>
        <ul class="menu">
            <li>
                <a href="admin.php">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="farmers.php">
                    <i class="fas fa-users"></i>
                    <span>Farmers</span>
                </a>
            </li>
            <li class="active">
                <a href="soil_test.php">
                    <i class="fas fa-flask"></i>
                    <span>Soil Tests</span>
                </a>
            </li>
        </ul>
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="content">
        <h2>Soil Tests</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="POST" onsubmit="return validateForm()" novalidate>
            <div class="form-grid">
                <div class="form-group">
                    <label>Farmer</label>
                    <select name="farmer_id" id="farmer_id" required>
                        <option value="">Select Farmer</option>
                        <?php foreach ($farmers as $farmer): ?>
                            <option value="<?php echo $farmer['id']; ?>">
                                <?php echo htmlspecialchars($farmer['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="error-message">Please select a farmer</span>
                </div>
                <div class="form-group">
                    <label>pH Level</label>
                    <input type="number" name="ph_level" id="ph_level" step="0.1" required min="0" max="14">
                    <span class="error-message">Please enter a valid pH level (0-14)</span>
                </div>
                <div class="form-group">
                    <label>Nitrogen (N) %</label>
                    <input type="number" name="nitrogen" id="nitrogen" step="0.01" required min="0">
                    <span class="error-message">Please enter a valid nitrogen percentage</span>
                </div>
                <div class="form-group">
                    <label>Phosphorus (P) %</label>
                    <input type="number" name="phosphorus" id="phosphorus" step="0.01" required min="0">
                    <span class="error-message">Please enter a valid phosphorus percentage</span>
                </div>
                <div class="form-group">
                    <label>Potassium (K) %</label>
                    <input type="number" name="potassium" id="potassium" step="0.01" required min="0">
                    <span class="error-message">Please enter a valid potassium percentage</span>
                </div>
            </div>
            <button type="submit" name="add_soil_test">Add Soil Test</button>
        </form>

        <div class="test-results">
            <h3>Recent Soil Tests</h3>
            <?php if (empty($soil_tests)): ?>
                <p>No soil tests found.</p>
            <?php else: ?>
                <?php foreach ($soil_tests as $test): ?>
                    <div class="test-card">
                        <h4>Farmer: <?php echo htmlspecialchars($test['farmer_name']); ?></h4>
                        <p>Test Date: <?php echo date('F j, Y', strtotime($test['test_date'])); ?></p>
                        <p>pH Level: <?php echo $test['ph_level']; ?></p>
                        <p>NPK Values: <?php echo $test['nitrogen']; ?>% N, 
                           <?php echo $test['phosphorus']; ?>% P, 
                           <?php echo $test['potassium']; ?>% K</p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('collapsed');
        }

        function validateForm() {
            const fields = [
                { id: 'farmer_id', message: 'Please select a farmer' },
                { id: 'ph_level', message: 'Please enter a valid pH level (0-14)' },
                { id: 'nitrogen', message: 'Please enter a valid nitrogen percentage' },
                { id: 'phosphorus', message: 'Please enter a valid phosphorus percentage' },
                { id: 'potassium', message: 'Please enter a valid potassium percentage' }
            ];

            let isValid = true;

            // Hide all error messages first
            document.querySelectorAll('.error-message').forEach(error => {
                error.style.display = 'none';
            });

            // Validate each field
            fields.forEach(field => {
                const element = document.getElementById(field.id);
                const errorElement = element.nextElementSibling;

                if (!element.value) {
                    errorElement.style.display = 'block';
                    isValid = false;
                } else if (field.id === 'ph_level') {
                    const value = parseFloat(element.value);
                    if (value < 0 || value > 14) {
                        errorElement.style.display = 'block';
                        isValid = false;
                    }
                } else if (['nitrogen', 'phosphorus', 'potassium'].includes(field.id)) {
                    const value = parseFloat(element.value);
                    if (value < 0) {
                        errorElement.style.display = 'block';
                        isValid = false;
                    }
                }
            });

            return isValid;
        }

        // Add real-time validation
        document.querySelectorAll('input, select').forEach(element => {
            element.addEventListener('blur', function() {
                const errorElement = this.nextElementSibling;
                
                if (!this.value) {
                    errorElement.style.display = 'block';
                } else {
                    errorElement.style.display = 'none';
                }

                if (this.id === 'ph_level' && this.value) {
                    const value = parseFloat(this.value);
                    if (value < 0 || value > 14) {
                        errorElement.style.display = 'block';
                    }
                }

                if (['nitrogen', 'phosphorus', 'potassium'].includes(this.id) && this.value) {
                    const value = parseFloat(this.value);
                    if (value < 0) {
                        errorElement.style.display = 'block';
                    }
                }
            });
        });
    </script>
</body>
</html> 