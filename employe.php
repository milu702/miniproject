<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employee') {
    header("Location: employee_signup.pph");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch employee details
$stmt = $conn->prepare("SELECT users.username, employees.qualification, employees.profile_picture, employees.certificate 
                        FROM employees 
                        JOIN users ON employees.user_id = users.id 
                        WHERE employees.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

$username = $employee['username'];
$qualification = $employee['qualification'];
$profilePicture = $employee['profile_picture'];
$certificate = $employee['certificate'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard | GrowGuide</title>
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

/* Table Styling */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

th, td {
    padding: 10px;
    border: 1px solid #ddd;
    text-align: left;
}

th {
    background: rgb(99, 248, 62);
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

.btn-edit {
    background: rgb(73, 17, 2);
    color: white;
}

.btn-delete {
    background: rgb(1, 241, 1);
    color: white;
}

.btn:hover {
    opacity: 0.8;
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
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar">
        <h2>GrowGuide</h2>
        <a href="employee.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="add_farmer.php"><i class="fas fa-user-plus"></i> Add Farmer</a>
        <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="content">
        <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>

        <!-- Employee Profile Card -->
        <div class="card">
            <h3>Employee Profile</h3>
            <div style="text-align: center;">
                <img src="<?php echo htmlspecialchars($profilePicture); ?>" alt="Profile Picture" width="150" height="150" style="border-radius: 50%; border: 3px solid #ff9800;">
            </div>
            <p><strong>Qualification:</strong> <?php echo htmlspecialchars($qualification); ?></p>
            <p>
                <strong>Certificate:</strong> 
                <a href="<?php echo htmlspecialchars($certificate); ?>" target="_blank" class="btn btn-download">Download PDF</a>
            </p>
        </div>

        <h3>Registered Farmers</h3>
        <a href="add_farmer.php" class="btn btn-add">+ Add Farmer</a>

        <table>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Actions</th>
            </tr>
            <?php
            $farmers = $conn->query("SELECT * FROM users WHERE role = 'farmer'");
            while ($row = $farmers->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td>
                        <a href="edit_farmer.php?id=<?php echo $row['id']; ?>" class="btn btn-edit">Edit</a>
                        <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>

</body>
</html>
