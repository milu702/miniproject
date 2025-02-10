<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
$username = $_SESSION['username'];

// Fetch employees and farmers data
$employees = $conn->query("SELECT * FROM users WHERE role = 'employee'");
$farmers = $conn->query("SELECT * FROM users WHERE role = 'farmer'");

// Delete user
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    $conn->query("DELETE FROM users WHERE id = $delete_id");
    header("Location: admin.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | GrowGuide</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        body {
            display: flex;
            min-height: 100vh;
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('log.jpeg') no-repeat center center/cover;
        }
        .sidebar {
            width: 280px;
            background: rgba(105, 29, 5, 0.95);
            padding: 30px;
            color: white;
            position: fixed;
            height: 100%;
            display: flex;
            flex-direction: column;
            gap: 25px;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.3);
        }
        .sidebar h2 {
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 10px;
        }
        .sidebar a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
            color: white;
            font-weight: 500;
        }
        .sidebar a:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(5px);
        }
        .content {
            margin-left: 300px;
            padding: 30px;
            flex: 1;
            background: rgba(255, 255, 255, 0.9);
            min-height: 100vh;
            border-radius: 0;
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            margin-bottom: 30px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        th, td {
            padding: 15px;
            border: none;
            border-bottom: 1px solid #eee;
            text-align: left;
            color: #333;
        }
        th {
            background: rgb(105, 29, 5);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9em;
        }
        tr:hover {
            background: #f8f8f8;
        }
        .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin: 0 5px;
        }
        .btn-edit {
            background: rgb(105, 29, 5);
            color: white;
        }
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        }
        h3 {
            color: rgb(105, 29, 5);
            font-size: 1.8em;
            margin: 20px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid rgb(105, 29, 5);
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>GrowGuide Admin,hello..<?php echo htmlspecialchars($_SESSION['username']); ?>  </h2>
        <a href="#"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="#"><i class="fas fa-users"></i> Manage Users</a>
        <a href="login.php" class="btn btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
    <div class="content">
        <h3 >Employees</h3>
        <table>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $employees->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td>
                        <a href="edit_user.php?id=<?php echo $row['id']; ?>" class="btn btn-edit">Edit</a>
                        <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>

        <h3>Farmers</h3>
        <table>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Actions</th>
            </tr>
            <?php while ($row = $farmers->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                    <td>
                        <a href="edit_user.php?id=<?php echo $row['id']; ?>" class="btn btn-edit">Edit</a>
                        <a href="?delete_id=<?php echo $row['id']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure?')">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </table>
    </div>
</body>
</html>
