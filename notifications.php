<?php
session_start();

// Add database connection
$conn = mysqli_connect("localhost", "root", "", "growguide");

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Fetch notifications from database
$notifications = [];
$query = "SELECT n.*, u.username as from_user 
          FROM notifications n 
          LEFT JOIN users u ON n.from_user_id = u.id 
          ORDER BY n.created_at DESC 
          LIMIT 50";

$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $notifications[] = $row;
    }
    mysqli_free_result($result);
}

// Get unread notifications count
$unread_count = 0;
$query = "SELECT COUNT(*) as count FROM notifications WHERE read_status = 0";
$result = mysqli_query($conn, $query);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    $unread_count = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GrowGuide - Notifications</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        /* ... existing styles from admin.php ... */

        /* Additional Notification Styles */
        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .notification-filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .filter-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 20px;
            background: #f0f0f0;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-btn.active {
            background: #2e7d32;
            color: white;
        }

        .notification-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .notification-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            cursor: pointer;
        }

        .notification-card:hover {
            transform: translateY(-2px);
        }

        .notification-card.unread {
            border-left: 4px solid #2e7d32;
        }

        .notification-top {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            background: #e8f5e9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2e7d32;
        }

        .notification-content {
            flex: 1;
            margin: 0 15px;
        }

        .notification-title {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .notification-message {
            color: #666;
            font-size: 0.9em;
        }

        .notification-time {
            color: #999;
            font-size: 0.8em;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .action-btn {
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
        }

        .mark-read {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .delete-btn {
            background: #ffebee;
            color: #c62828;
        }

        .notification-summary {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .summary-card {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            background: #f8f9fa;
        }

        .summary-card h3 {
            color: #2e7d32;
            margin-bottom: 5px;
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

        /* Update the main-content style to use full width */
        .main-content {
            padding: 20px;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <!-- Main Content -->
    <div class="main-content" id="main-content" style="margin-left: 0;">
        <div class="notification-header">
            <h1>Notifications</h1>
            <button class="action-btn mark-read">Mark All as Read</button>
        </div>

        <!-- Add the admin dashboard link -->
        <a href="admin.php" class="admin-dashboard-link">
            <div class="icon-container">
                <i class="fas fa-user-shield"></i>
            </div>
            <span class="text">Admin Dashboard</span>
        </a>

        <div class="notification-summary">
            <div class="summary-card">
                <h3><?php echo $unread_count; ?></h3>
                <p>Unread Notifications</p>
            </div>
            <div class="summary-card">
                <h3>24h</h3>
                <p>Response Time</p>
            </div>
            <div class="summary-card">
                <h3>150</h3>
                <p>Total Notifications</p>
            </div>
        </div>

        <div class="notification-filters">
            <button class="filter-btn active">All</button>
            <button class="filter-btn">Unread</button>
            <button class="filter-btn">Soil Tests</button>
            <button class="filter-btn">Farmers</button>
            <button class="filter-btn">System</button>
        </div>

        <div class="notification-list">
            <?php if (empty($notifications)): ?>
                <div class="notification-card">
                    <p>No notifications found.</p>
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-card <?php echo $notification['read_status'] ? '' : 'unread'; ?>">
                        <div class="notification-top">
                            <div class="notification-icon">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                            </div>
                            <div class="notification-time">
                                <?php echo date('M j, Y H:i', strtotime($notification['created_at'])); ?>
                            </div>
                        </div>
                        <div class="notification-actions">
                            <?php if (!$notification['read_status']): ?>
                                <button class="action-btn mark-read">Mark as Read</button>
                            <?php endif; ?>
                            <button class="action-btn delete-btn">Delete</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.querySelectorAll('.notification-card').forEach(card => {
            card.addEventListener('click', function() {
                this.classList.remove('unread');
            });
        });

        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>
</body>
</html> 