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
            $stmt->execute();
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
        .product-form {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .submit-btn {
            background: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .submit-btn:hover {
            background: var(--dark-color);
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
    </style>
</head>
<body>
    <div class="content">
        <h1>Manage Products</h1>
        
        <!-- Add Back to Dashboard button -->
        <a href="employe.php" class="update-btn" style="display: inline-block; text-decoration: none; margin-bottom: 20px;">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <!-- Add Product Form -->
        <div class="product-form">
            <h2>Add New Product</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Product Name</label>
                    <input type="text" name="name" required>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="type" required>
                        <option value="fertilizer">Fertilizer</option>
                        <option value="pesticide">Pesticide</option>
                        <option value="tools">Tools & Equipment</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Price (₹)</label>
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
                <button type="submit" class="submit-btn">Add Product</button>
            </form>
        </div>

        <!-- Products List -->
        <h2>Current Products</h2>
        <div class="products-grid">
            <?php while ($product = mysqli_fetch_assoc($products)): ?>
                <div class="product-card">
                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                    <p><strong>Type:</strong> <?php echo ucfirst($product['type']); ?></p>
                    <p><strong>Price:</strong> ₹<?php echo number_format($product['price'], 2); ?></p>
                    <p><strong>Stock:</strong> <?php echo $product['stock']; ?></p>
                    <p><?php echo htmlspecialchars($product['description']); ?></p>
                    
                    <!-- Quick Update Stock/Price Form -->
                    <form method="POST" style="margin-top: 10px;">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                        <div class="form-group">
                            <label>Update Stock</label>
                            <input type="number" name="stock" value="<?php echo $product['stock']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Update Price</label>
                            <input type="number" name="price" step="0.01" value="<?php echo $product['price']; ?>" required>
                        </div>
                        <button type="submit" class="update-btn">Quick Update</button>
                    </form>

                    <!-- Full Update Button - Fix the JSON encoding -->
                    <button onclick='showFullUpdateForm(<?php echo json_encode($product); ?>)' class="update-btn" style="margin-top: 10px;">Edit All Details</button>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Full Update Modal Form -->
        <div id="updateModal" class="modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
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
                    <button type="submit" class="update-btn">Update Product</button>
                </form>
            </div>
        </div>

        <script>
            function showFullUpdateForm(product) {
                // Add console.log for debugging
                console.log('Product data:', product);
                
                document.getElementById('updateModal').style.display = 'block';
                document.getElementById('update_id').value = product.id;
                document.getElementById('update_name').value = product.name;
                document.getElementById('update_type').value = product.type;
                document.getElementById('update_price').value = product.price;
                document.getElementById('update_unit').value = product.unit;
                document.getElementById('update_description').value = product.description;
                document.getElementById('update_stock').value = product.stock;
                document.getElementById('current_image').value = product.image || '';
            }

            function closeModal() {
                document.getElementById('updateModal').style.display = 'none';
            }
        </script>
    </div>
</body>
</html> 