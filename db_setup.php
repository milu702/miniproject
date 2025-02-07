<?php
$servername = "localhost";
$username = "root";
$password = "";

try {
    // Create connection
    $conn = new mysqli($servername, $username, $password);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS growguide";
    if ($conn->query($sql) === TRUE) {
        echo "Database created successfully<br>";
    } else {
        echo "Error creating database: " . $conn->error . "<br>";
    }
    
    // Select the database
    $conn->select_db("growguide");
    
    // Create users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'employee', 'farmer') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status BOOLEAN DEFAULT TRUE,
        first_name VARCHAR(50),
        last_name VARCHAR(50),
        phone VARCHAR(15)
    )";
    
    if ($conn->query($sql) === TRUE) {
        echo "Users table created successfully<br>";
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
    }
    
    // Create default admin user
    $admin_username = "admin";
    $admin_email = "admin@growguide.com";
    $admin_password = password_hash("admin123", PASSWORD_DEFAULT);
    $admin_role = "admin";
    
    $sql = "INSERT IGNORE INTO users (username, email, password, role) 
            VALUES (?, ?, ?, ?)";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $admin_username, $admin_email, $admin_password, $admin_role);
    
    if ($stmt->execute()) {
        echo "Default admin user created successfully<br>";
    } else {
        echo "Error creating admin user: " . $stmt->error . "<br>";
    }
    
    $stmt->close();
    $conn->close();
    
    echo "Database setup completed successfully!";
    
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>