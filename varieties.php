<?php
session_start();

// Authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// Database connection
$conn = mysqli_connect("localhost", "root", "", "growguide");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Add image_path column if it doesn't exist
$alter_query = "ALTER TABLE varieties ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) DEFAULT NULL";
mysqli_query($conn, $alter_query);

// Handle form submission for adding new variety
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_variety'])) {
        $variety_name = mysqli_real_escape_string($conn, $_POST['variety_name']);
        $description = mysqli_real_escape_string($conn, $_POST['description']);
        $market_price = floatval($_POST['market_price']);
        $growing_period = mysqli_real_escape_string($conn, $_POST['growing_period']);
        $yield_potential = mysqli_real_escape_string($conn, $_POST['yield_potential']);

        // Handle image upload
        $image_path = '';
        if (isset($_FILES['variety_image']) && $_FILES['variety_image']['error'] === 0) {
            $upload_dir = 'uploads/varieties/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['variety_image']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_extension;
            $target_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['variety_image']['tmp_name'], $target_path)) {
                $image_path = $target_path;
            }
        }

        $query = "INSERT INTO varieties (variety_name, description, market_price, growing_period, yield_potential, image_path, created_at) 
                 VALUES ('$variety_name', '$description', $market_price, '$growing_period', '$yield_potential', '$image_path', NOW())";
        
        if (mysqli_query($conn, $query)) {
            $_SESSION['success_message'] = "Variety added successfully!";
        } else {
            $_SESSION['error_message'] = "Error adding variety: " . mysqli_error($conn);
        }
        header("Location: varieties.php");
        exit();
    }

    // Handle variety deletion
    if (isset($_POST['delete_variety'])) {
        $variety_id = intval($_POST['variety_id']);
        $query = "DELETE FROM varieties WHERE id = $variety_id";
        
        if (mysqli_query($conn, $query)) {
            $_SESSION['success_message'] = "Variety deleted successfully!";
        } else {
            $_SESSION['error_message'] = "Error deleting variety: " . mysqli_error($conn);
        }
        header("Location: varieties.php");
        exit();
    }

    // Handle price update
    if (isset($_POST['update_price'])) {
        $variety_id = intval($_POST['variety_id']);
        $new_price = floatval($_POST['new_price']);
        
        $query = "UPDATE varieties SET market_price = $new_price WHERE id = $variety_id";
        
        if (mysqli_query($conn, $query)) {
            $_SESSION['success_message'] = "Price updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating price: " . mysqli_error($conn);
        }
        header("Location: varieties.php");
        exit();
    }
}

// Fetch all varieties
$query = "SELECT * FROM varieties ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);
$varieties = [];
while ($row = mysqli_fetch_assoc($result)) {
    $varieties[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cardamom Varieties Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary-color: #2e7d32;  /* Dark green */
            --secondary-color: #246528; /* Slightly darker green for hover */
            --text-primary: #333;
        }

        /* Updated sidebar styles */
        .sidebar {
            width: 250px;
            height: 100vh;
            background: var(--primary-color);
            position: fixed;
            left: 0;
            top: 0;
            padding: 20px 0;
            color: white;
        }

        .sidebar nav ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar nav ul li a {
            color: white;
            text-decoration: none;
            padding: 15px 25px;
            display: block;
            transition: background-color 0.2s;
            margin: 4px 8px;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar nav ul li a:hover,
        .sidebar nav ul li a.active {
            background: rgba(255, 255, 255, 0.2);
        }

        .sidebar nav ul li a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .sidebar .logo {
            text-align: left;
            padding: 20px 25px;
            font-size: 1.4em;
            color: white;
            font-weight: bold;
        }

        .sidebar .logo img {
            height: 24px;
            vertical-align: middle;
            margin-right: 10px;
        }

        /* Reuse your existing styles from admin.php */
        /* Add these new styles */
        .varieties-container {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .add-variety-form {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .form-group input, .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
        }

        .submit-btn {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s;
        }

        .submit-btn:hover {
            background: var(--secondary-color);
        }

        .varieties-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .variety-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .variety-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .variety-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .variety-name {
            font-size: 1.2rem;
            color: var(--primary-color);
            font-weight: 600;
        }

        .price-tag {
            background: #e8f5e9;
            color: var(--primary-color);
            padding: 5px 10px;
            border-radius: 15px;
            font-weight: 500;
        }

        .variety-details p {
            margin: 8px 0;
            color: #666;
        }

        .variety-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .edit-btn {
            background: #2196F3;
            color: white;
        }

        .delete-btn {
            background: #f44336;
            color: white;
        }

        .action-btn:hover {
            opacity: 0.9;
        }

        .alert {
            padding: 10px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #43a047;
        }

        .alert-error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef5350;
        }

        /* Modal styles */
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
            position: relative;
            background: white;
            width: 90%;
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .close-modal {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        /* Main content adjustment */
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }

        /* Variety image styles */
        .variety-image {
            margin-bottom: 15px;
            border-radius: 8px;
            overflow: hidden;
        }

        .variety-image img {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        /* File input styling */
        input[type="file"] {
            border: 1px solid #ddd;
            padding: 8px;
            border-radius: 5px;
            width: 100%;
        }

        /* Add these new icon animation styles */
        .fas {
            transition: transform 0.3s ease-in-out;
        }

        /* Hover animations for different icon types */
        .fa-seedling:hover {
            transform: scale(1.2) rotate(15deg);
            color: #4CAF50;
        }

        .fa-plus-circle:hover {
            transform: scale(1.2);
            color: #2196F3;
        }

        .fa-clock:hover {
            transform: rotate(360deg);
            color: #FF9800;
        }

        .fa-chart-line:hover {
            transform: translateY(-3px);
            color: #E91E63;
        }

        .fa-info-circle:hover {
            transform: scale(1.2);
            color: #2196F3;
        }

        .fa-edit:hover {
            transform: rotate(15deg);
        }

        .fa-trash:hover {
            transform: scale(1.1) rotate(-15deg);
        }

        /* Sidebar icon animations */
        .sidebar nav ul li a:hover i {
            transform: scale(1.2);
            transition: transform 0.3s ease;
        }

        /* Add floating animation for variety cards */
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
            100% { transform: translateY(0px); }
        }

        .variety-card {
            animation: float 3s ease-in-out infinite;
        }

        .variety-card:hover {
            animation-play-state: paused;
        }

        /* Add pulse animation for price tag */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .price-tag {
            animation: pulse 2s infinite;
        }

        .price-tag:hover {
            animation-play-state: paused;
        }

        /* Add shine effect for submit buttons */
        @keyframes shine {
            0% { background-position: -100px; }
            100% { background-position: 200px; }
        }

        .submit-btn {
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            background-size: 200px 100%;
            animation: shine 3s infinite linear;
            background-repeat: no-repeat;
            background-color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Add the sidebar before main-content div -->
    <div class="sidebar">
        <div class="logo">
            <img src="assets/images/logo.png" alt="GrowGuide Logo">
        </div>
        <nav>
            <ul>
                <li><a href="admin.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="varieties.php" class="active"><i class="fas fa-seedling"></i> Varieties</a></li>
                <li><a href="farmers.php"><i class="fas fa-users"></i> Farmers</a></li>
                <li><a href="plantations.php"><i class="fas fa-tree"></i> Plantations</a></li>
                <li><a href="harvests.php"><i class="fas fa-warehouse"></i> Harvests</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="admin_setting.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </div>

    <div class="main-content">
        <div class="varieties-container">
            <h1><i class="fas fa-seedling"></i> Cardamom Varieties Management</h1>
            
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <?php 
                        echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <?php 
                        echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']);
                    ?>
                </div>
            <?php endif; ?>

            <div class="add-variety-form">
                <h2><i class="fas fa-plus-circle"></i> Add New Variety</h2>
                <form action="varieties.php" method="POST" enctype="multipart/form-data">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="variety_name">Variety Name</label>
                            <input type="text" id="variety_name" name="variety_name" required>
                        </div>
                        <div class="form-group">
                            <label for="market_price">Market Price (per kg)</label>
                            <input type="number" id="market_price" name="market_price" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label for="growing_period">Growing Period</label>
                            <input type="text" id="growing_period" name="growing_period" required>
                        </div>
                        <div class="form-group">
                            <label for="yield_potential">Yield Potential</label>
                            <input type="text" id="yield_potential" name="yield_potential" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="variety_image">Variety Image</label>
                        <input type="file" id="variety_image" name="variety_image" accept="image/*">
                    </div>
                    <button type="submit" name="add_variety" class="submit-btn">
                        <i class="fas fa-plus"></i> Add Variety
                    </button>
                </form>
            </div>

            <div class="varieties-grid">
                <?php foreach ($varieties as $variety): ?>
                    <div class="variety-card">
                        <?php if (!empty($variety['image_path'])): ?>
                            <div class="variety-image">
                                <img src="<?php echo htmlspecialchars($variety['image_path']); ?>" alt="<?php echo htmlspecialchars($variety['variety_name']); ?>">
                            </div>
                        <?php endif; ?>
                        <div class="variety-header">
                            <span class="variety-name"><?php echo htmlspecialchars($variety['variety_name']); ?></span>
                            <span class="price-tag">₹<?php echo number_format($variety['market_price'], 2); ?>/kg</span>
                        </div>
                        <div class="variety-details">
                            <p><i class="fas fa-clock"></i> Growing Period: <?php echo htmlspecialchars($variety['growing_period']); ?></p>
                            <p><i class="fas fa-chart-line"></i> Yield: <?php echo htmlspecialchars($variety['yield_potential']); ?></p>
                            <p><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($variety['description']); ?></p>
                        </div>
                        <div class="variety-actions">
                            <button class="action-btn edit-btn" onclick="openPriceModal(<?php echo $variety['id']; ?>, <?php echo $variety['market_price']; ?>)">
                                <i class="fas fa-edit"></i> Update Price
                            </button>
                            <form action="varieties.php" method="POST" style="display: inline;">
                                <input type="hidden" name="variety_id" value="<?php echo $variety['id']; ?>">
                                <button type="submit" name="delete_variety" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this variety?')">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Price Update Modal -->
    <div id="priceModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closePriceModal()">&times;</span>
            <h2>Update Market Price</h2>
            <form action="varieties.php" method="POST">
                <input type="hidden" id="modal_variety_id" name="variety_id">
                <div class="form-group">
                    <label for="new_price">New Price (per kg)</label>
                    <input type="number" id="new_price" name="new_price" step="0.01" required>
                </div>
                <button type="submit" name="update_price" class="submit-btn">
                    <i class="fas fa-save"></i> Update Price
                </button>
            </form>
        </div>
    </div>

    <script>
        function openPriceModal(varietyId, currentPrice) {
            document.getElementById('priceModal').style.display = 'block';
            document.getElementById('modal_variety_id').value = varietyId;
            document.getElementById('new_price').value = currentPrice;
        }

        function closePriceModal() {
            document.getElementById('priceModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == document.getElementById('priceModal')) {
                closePriceModal();
            }
        }
    </script>
</body>
</html> 