<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit();
}

$success = $error = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = 'farmer';

    if (empty($username) || empty($email) || empty($_POST['password'])) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $email, $password, $role);

        if ($stmt->execute()) {
            $success = "Farmer added successfully!";
        } else {
            $error = "Error adding farmer. Try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Farmer | GrowGuide</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="styles.css"> 
    <style>
        /* General Styling */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Poppins', sans-serif;
}

body {
    display: flex;
    min-height: 100vh;
    background: #f5f5f5;
}

/* Sidebar */
.sidebar {
    width: 250px;
    background: rgb(82, 22, 4);
    padding: 20px;
    color: white;
    position: fixed;
    height: 100%;
}

.sidebar h2 {
    text-align: center;
    margin-bottom: 30px;
}

.sidebar a {
    display: block;
    color: white;
    padding: 15px;
    text-decoration: none;
    border-radius: 5px;
    margin-bottom: 10px;
    transition: 0.3s;
}

.sidebar a.active, .sidebar a:hover {
    background: rgb(100, 18, 7);
}

/* Main Content */
.content {
    margin-left: 270px;
    padding: 20px;
    flex: 1;
    background: white;
    border-radius: 10px;
}

/* Cards */
.card {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
}

/* Form Styling */
form {
    max-width: 400px;
    margin: auto;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    font-weight: bold;
}

input {
    width: 100%;
    padding: 10px;
    border-radius: 5px;
    border: 1px solid #ccc;
}

/* Buttons */
.btn {
    padding: 7px 12px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    display: inline-block;
    text-decoration: none;
}

.btn-add {
    background: #28a745;
    color: white;
    font-weight: bold;
}

.btn:hover {
    opacity: 0.8;
}

/* Messages */
.message {
    margin-top: 10px;
    font-size: 14px;
}

.error {
    color: red;
}

.success {
    color: green;
}

/* Logout Button */
.logout-btn {
    background: #e74c3c;
    color: white;
    padding: 10px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: 0.3s;
}

.logout-btn:hover {
    background: #c0392b;
}

    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <h2>GrowGuide</h2>
        <a href="employe.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="add_farmer.php" class="active"><i class="fas fa-user-plus"></i> Add Farmer</a>
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="content">
        <h1>Add New Farmer</h1>

        <?php if ($error): ?>
            <p class="message error"><?php echo $error; ?></p>
        <?php elseif ($success): ?>
            <p class="message success"><?php echo $success; ?></p>
        <?php endif; ?>

        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label>Username:</label>
                    <input type="text" name="username" required>
                </div>
                
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="email" required>
                </div>

                <div class="form-group">
                    <label>Password:</label>
                    <input type="password" name="password" required>
                </div>

                <button type="submit" class="btn btn-add">Add Farmer</button>
            </form>
        </div>
    </div>

</body>
</html>
