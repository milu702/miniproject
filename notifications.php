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

// Mark notification as read if ID is provided
if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
    $notification_id = mysqli_real_escape_string($conn, $_POST['notification_id']);
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE id = '$notification_id'");
}

// Fetch all notifications
$notifications_query = "SELECT * FROM notifications ORDER BY created_at DESC";
$notifications = mysqli_query($conn, $notifications_query);

mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - GrowGuide</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .notifications-container {
            max-width: 800px;
            margin: 20px auto;
        }

        .notification-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
        }

        .notification-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .notification-item.unread {
            border-left: 4px solid #2B7A30;
            background: #f8fff9;
        }

        .notification-icon {
            font-size: 1.5em;
            color: #2B7A30;
        }

        .notification-content {
            flex: 1;
        }

        .notification-time {
            color: #666;
            font-size: 0.9em;
        }

        .mark-read-btn {
            background: none;
            border: none;
            color: #2B7A30;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .mark-read-btn:hover {
            background: #e8f5e9;
        }

        .no-notifications {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        /* Add these new sidebar styles */
        .sidebar {
            background-color: #1B4D1B;
            width: 250px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding: 20px 0;
        }

        .logo {
            color: white;
            font-size: 24px;
            padding: 0 20px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo i {
            color: #4CAF50;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            margin: 5px 0;
        }

        .nav-item:hover, .nav-item.active {
            background-color: #2B7A30;
        }

        .nav-item i {
            width: 24px;
            margin-right: 10px;
        }

        .logout-btn {
            position: absolute;
            bottom: 20px;
            width: 100%;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .logout-btn i {
            width: 24px;
            margin-right: 10px;
        }

        /* Adjust main content to accommodate sidebar */
        .content {
            margin-left: 250px;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-leaf"></i>
            <span>GrowGuide</span>
        </div>
        <a href="employe.php" class="nav-item">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="varieties.php" class="nav-item">
            <i class="fas fa-seedling"></i>
            <span>Varieties</span>
        </a>
        <a href="notifications.php" class="nav-item active">
            <i class="fas fa-bell"></i>
            <span>Notifications</span>
        </a>
        <a href="settings.php" class="nav-item">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
        <a href="products.php" class="nav-item">
            <i class="fas fa-box"></i>
            <span>Manage Products</span>
        </a>
        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>

    <div class="content">
        <div class="notifications-container">
            <h1><i class="fas fa-bell"></i> Notifications</h1>
            
            <?php if (mysqli_num_rows($notifications) > 0): ?>
                <?php while ($notification = mysqli_fetch_assoc($notifications)): ?>
                    <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                        <div class="notification-icon">
                            <?php
                            switch($notification['type']) {
                                case 'new_farmer':
                                    echo '<i class="fas fa-user-plus"></i>';
                                    break;
                                case 'soil_test':
                                    echo '<i class="fas fa-flask"></i>';
                                    break;
                                case 'query':
                                    echo '<i class="fas fa-question-circle"></i>';
                                    break;
                            }
                            ?>
                        </div>
                        <div class="notification-content">
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <span class="notification-time">
                                <?php echo date('F j, Y g:i a', strtotime($notification['created_at'])); ?>
                            </span>
                        </div>
                        <?php if (!$notification['is_read']): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                <button type="submit" name="mark_read" class="mark-read-btn">
                                    <i class="fas fa-check"></i> Mark as Read
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-notifications">
                    <i class="fas fa-bell-slash fa-3x"></i>
                    <p>No notifications to display</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 