<?php
session_start();

// Ensure user is logged in and is an employee
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

// Add database connection
$conn = mysqli_connect("localhost", "root", "", "growguide");

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Handle form submission for adding/updating products
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $name = mysqli_real_escape_string($conn, $_POST['name']);
            $type = mysqli_real_escape_string($conn, $_POST['type']);
            $price = floatval($_POST['price']);
            $description = mysqli_real_escape_string($conn, $_POST['description']);
            $stock = intval($_POST['stock']);
            $unit = mysqli_real_escape_string($conn, $_POST['unit']);

            // Handle image upload
            $image_path = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                $upload_dir = 'img/';
                $image_name = basename($_FILES['image']['name']);
                $target_path = $upload_dir . $image_name;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                    $image_path = $target_path;
                }
            }

            $query = "INSERT INTO products (name, type, price, description, stock, unit, image, last_updated) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssdsiss", $name, $type, $price, $description, $stock, $unit, $image_path);
            
            if ($stmt->execute()) {
                // Add notification for farmers about new product
                $notify_query = "INSERT INTO notifications (type, message, created_at) 
                               VALUES ('new_product', 'New product \"$name\" has been added to the store.', NOW())";
                mysqli_query($conn, $notify_query);
                
                // Redirect to prevent form resubmission
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                exit();
            }
        } elseif ($_POST['action'] === 'update') {
            $id = intval($_POST['id']);
            $stock = intval($_POST['stock']);
            $price = floatval($_POST['price']);

            // Check if this is a full update
            if (isset($_POST['full_update'])) {
                $name = mysqli_real_escape_string($conn, $_POST['name']);
                $type = mysqli_real_escape_string($conn, $_POST['type']);
                $description = mysqli_real_escape_string($conn, $_POST['description']);
                $unit = mysqli_real_escape_string($conn, $_POST['unit']);

                // Handle image upload for full update
                $image_path = $_POST['current_image']; // Keep existing image by default
                if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                    $upload_dir = 'img/';
                    $image_name = basename($_FILES['image']['name']);
                    $target_path = $upload_dir . $image_name;
                    
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                        $image_path = $target_path;
                    }
                }

                $query = "UPDATE products SET name = ?, type = ?, price = ?, description = ?, stock = ?, unit = ?, image = ?, last_updated = NOW() WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssdsissi", $name, $type, $price, $description, $stock, $unit, $image_path, $id);
            } else {
                // Original stock and price update
                $query = "UPDATE products SET stock = ?, price = ?, last_updated = NOW() WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("idi", $stock, $price, $id);
            }
            $stmt->execute();

            // Add notification for farmers
            $notify_query = "INSERT INTO notifications (type, message, created_at) 
                           VALUES ('product_update', 'Product inventory has been updated. Check the latest recommendations.', NOW())";
            mysqli_query($conn, $notify_query);
        }
    }
}

// Fetch all products
$products_query = "SELECT * FROM products ORDER BY type, name";
$products = mysqli_query($conn, $products_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - GrowGuide</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4CAF50;  /* Green color */
            --dark-color: #388E3C;     /* Darker green */
            --primary-color-rgb: 76, 175, 80;  /* RGB values of primary color */
        }

        .product-form {
            background: white;
            padding: 30px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .product-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
            position: relative;
            border: 1px solid #eee;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }

        .product-type-icon {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            color: var(--primary-color);
        }

        .form-group {
            position: relative;
            margin-bottom: 20px;
        }

        .form-group i {
            position: absolute;
            left: 10px;
            top: 35px;
            color: #666;
        }

        .form-group input, 
        .form-group select, 
        .form-group textarea {
            padding-left: 35px;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(var(--primary-color-rgb), 0.1);
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .section-title i {
            font-size: 24px;
            color: var(--primary-color);
        }

        .submit-btn {
            background: #4CAF50;  /* Fallback if variable isn't working */
            background: var(--primary-color);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            font-size: 16px;
        }

        .submit-btn:hover {
            background: #388E3C;  /* Fallback */
            background: var(--dark-color);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .submit-btn:active {
            transform: translateY(1px);
        }

        /* Update the existing update-btn styles */
        .update-btn {
            background: #28a745;  /* Green color */
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s ease; /* Smooth transition effect */
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
            margin: 5px 0;
        }

        .update-btn:hover {
            background: #218838;  /* Darker green on hover */
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .update-btn:active {
            transform: translateY(1px); /* Slight push effect when clicked */
        }

        /* Update button styles */
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .update-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .form-group textarea {
            padding-left: 10px;  /* Override the 35px padding for textarea */
            white-space: pre-wrap;
            min-height: 100px;
            font-family: inherit;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="content">
        <div class="section-title">
            <i class="fas fa-box"></i>
            <h1>Manage Products</h1>
        </div>
        
        <!-- Update the Back button -->
        <a href="employe.php" class="update-btn" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none; margin-bottom: 20px;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <!-- Update Add Product Form -->
        <div class="product-form">
            <div class="section-title">
                <i class="fas fa-plus-circle"></i>
                <h2>Add New Product</h2>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Product Name</label>
                    <i class="fas fa-tag"></i>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <i class="fas fa-sitemap"></i>
                    <select name="type" required>
                        <option value="fertilizer">Fertilizer</option>
                        <option value="pesticide">Pesticide</option>
                        <option value="tools">Tools & Equipment</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Price (₹)</label>
                    <i class="fas fa-rupee-sign"></i>
                    <input type="number" name="price" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Unit</label>
                    <input type="text" name="unit" placeholder="e.g., per 50kg bag" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" required></textarea>
                </div>
                <div class="form-group">
                    <label>Stock</label>
                    <input type="number" name="stock" required>
                </div>
                <div class="form-group">
                    <label>Product Image</label>
                    <input type="file" name="image" accept="image/*" required>
                </div>
                <button type="submit" class="submit-btn">
                    <i class="fas fa-plus-circle"></i> Add Product
                </button>
            </form>
        </div>

        <!-- Update Products List -->
        <div class="section-title">
            <i class="fas fa-list"></i>
            <h2>Current Products</h2>
        </div>
        <div class="products-grid">
            <?php while ($product = mysqli_fetch_assoc($products)): ?>
                <div class="product-card">
                    <!-- Add type-specific icons -->
                    <div class="product-type-icon">
                        <?php
                        switch($product['type']) {
                            case 'fertilizer':
                                echo '<i class="fas fa-seedling"></i>';
                                break;
                            case 'pesticide':
                                echo '<i class="fas fa-spray-can"></i>';
                                break;
                            case 'tools':
                                echo '<i class="fas fa-tools"></i>';
                                break;
                        }
                        ?>
                    </div>
                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                    <p><i class="fas fa-tag"></i> <strong>Type:</strong> <?php echo ucfirst($product['type']); ?></p>
                    <p><i class="fas fa-rupee-sign"></i> <strong>Price:</strong> ₹<?php echo number_format($product['price'], 2); ?></p>
                    <p><i class="fas fa-boxes"></i> <strong>Stock:</strong> <?php echo $product['stock']; ?></p>
                    <p><i class="fas fa-info-circle"></i> <?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
                    
                    <!-- Update the buttons section -->
                    <div class="action-buttons">
                        <button type="button" onclick="showFullUpdateForm(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', '<?php echo $product['type']; ?>', <?php echo $product['price']; ?>, '<?php echo addslashes($product['unit']); ?>', '<?php echo addslashes($product['description']); ?>', <?php echo $product['stock']; ?>, '<?php echo $product['image']; ?>')" class="update-btn">
                            <i class="fas fa-edit"></i> Edit Details
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Full Update Modal Form -->
        <div id="updateModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; overflow-y: auto;">
            <div class="product-form" style="max-width: 600px; margin: 50px auto; position: relative;">
                <span onclick="closeModal()" style="position: absolute; right: 20px; top: 10px; cursor: pointer; font-size: 24px;">&times;</span>
                <h2>Update Product</h2>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="update_id">
                    <input type="hidden" name="full_update" value="1">
                    <input type="hidden" name="current_image" id="current_image">
                    <div class="form-group">
                        <label>Product Name</label>
                        <input type="text" name="name" id="update_name" required>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type" id="update_type" required>
                            <option value="fertilizer">Fertilizer</option>
                            <option value="pesticide">Pesticide</option>
                            <option value="tools">Tools & Equipment</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Price (₹)</label>
                        <input type="number" name="price" id="update_price" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>Unit</label>
                        <input type="text" name="unit" id="update_unit" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="update_description" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label>Stock</label>
                        <input type="number" name="stock" id="update_stock" required>
                    </div>
                    <div class="form-group">
                        <label>Product Image (Leave empty to keep current image)</label>
                        <input type="file" name="image" accept="image/*">
                    </div>
                    <button type="submit" class="update-btn" style="width: 100%;">Update Product</button>
                </form>
            </div>
        </div>

        <script>
            function showFullUpdateForm(id, name, type, price, unit, description, stock, image) {
                // Set values to form fields
                document.getElementById('update_id').value = id;
                document.getElementById('update_name').value = name;
                document.getElementById('update_type').value = type;
                document.getElementById('update_price').value = price;
                document.getElementById('update_unit').value = unit;
                document.getElementById('update_description').value = description.replace(/\\r\\n/g, "\n").replace(/\\n/g, "\n");
                document.getElementById('update_stock').value = stock;
                document.getElementById('current_image').value = image || '';
                
                // Show the modal
                document.getElementById('updateModal').style.display = 'block';
            }

            function closeModal() {
                document.getElementById('updateModal').style.display = 'none';
            }
        </script>
    </div>
</body>
</html>