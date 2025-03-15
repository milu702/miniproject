<?php
session_start();
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $product_id = $_POST['product_id'];
    $product_name = $_POST['product_name'];
    $price = $_POST['price'];
    $full_name = $_POST['full_name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $pincode = $_POST['pincode'];
    $delivery_time = $_POST['delivery_time'];
    $farmer_id = $_SESSION['user_id'];
    $order_date = date('Y-m-d H:i:s');

    // Insert order into database
    $query = "INSERT INTO orders (farmer_id, product_id, full_name, phone, address, pincode, 
              delivery_time, order_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iissssss", $farmer_id, $product_id, $full_name, $phone, 
                      $address, $pincode, $delivery_time, $order_date);
    
    if ($stmt->execute()) {
        // Update product stock
        $update_stock = "UPDATE products SET stock = stock - 1 WHERE id = ?";
        $stmt = $conn->prepare($update_stock);
        $stmt->bind_param("i", $product_id);
        $stmt->execute();

        // Add notification for employees
        $notify_query = "INSERT INTO notifications (type, message, created_at) 
                        VALUES ('new_order', 'New order received for $product_name', NOW())";
        mysqli_query($conn, $notify_query);

        // Redirect to success page
        header("Location: order_success.php");
        exit();
    } else {
        // Handle error
        header("Location: agri_store.php?error=1");
        exit();
    }
}
?> 