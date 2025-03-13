<?php
session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = mysqli_connect("localhost", "root", "", "growguide");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Get notifications for the user
$user_id = $_SESSION['user_id'];
$query = "SELECT n.*, 
          CASE 
            WHEN n.type = 'fertilizer_recommendation' THEN fr.recommendation_details
            WHEN n.type = 'soil_test' THEN st.test_results
   
            ELSE NULL 
          END as additional_details,
          u.username as sender_name
          FROM notifications n
          LEFT JOIN fertilizer_recommendations fr ON n.id = fr.recommendation_id
          LEFT JOIN soil_tests st ON n.id = st.test_id
          
          LEFT JOIN users u ON n.sender_id = u.id
          WHERE n.user_id = ?
          ORDER BY n.created_at DESC";

$stmt = mysqli_prepare($conn, $query);
if (!$stmt) {
    die("Prepare failed: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, "i", $user_id);
if (!mysqli_stmt_execute($stmt)) {
    die("Execute failed: " . mysqli_stmt_error($stmt));
}
$notifications = mysqli_stmt_get_result($stmt);

// Update to use is_read instead of status
$update_query = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
$update_stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($update_stmt, "i", $user_id);
mysqli_stmt_execute($update_stmt);

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
            padding: 20px;
        }

        .notification-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .notification-item:hover {
            transform: translateY(-2px);
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .notification-type {
            background: #2B7A30;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.9em;
        }

        .notification-time {
            color: #666;
            font-size: 0.9em;
        }

        .notification-content {
            margin-top: 10px;
        }

        .notification-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }

        .btn-view {
            background: #2B7A30;
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9em;
        }

        .unread {
            border-left: 4px solid #2B7A30;
        }
    </style>
</head>
<body>
    <div class="notifications-container">
        <div style="margin-bottom: 20px;">
            <a href="employe.php" class="btn-view" style="display: inline-block;">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        <h1><i class="fas fa-bell"></i> Notifications</h1>
        
        <?php if (mysqli_num_rows($notifications) > 0): ?>
            <?php while ($notification = mysqli_fetch_assoc($notifications)): ?>
                <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                    <div class="notification-header">
                        <span class="notification-type">
                            <?php 
                            switch($notification['type']) {
                                case 'fertilizer_recommendation':
                                    echo '<i class="fas fa-leaf"></i> Fertilizer Recommendation';
                                    break;
                                case 'soil_test':
                                    echo '<i class="fas fa-vial"></i> Soil Test Results';
                                    break;
                                case 'query':
                                    echo '<i class="fas fa-question-circle"></i> Query Response';
                                    break;
                                default:
                                    echo '<i class="fas fa-info-circle"></i> Notification';
                            }
                            ?>
                        </span>
                        <span class="notification-time">
                            <?php echo date('M d, Y H:i', strtotime($notification['created_at'])); ?>
                        </span>
                    </div>
                    
                    <div class="notification-content">
                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                        <?php if ($notification['additional_details']): ?>
                            <div class="notification-preview">
                                <p><strong>
                                    <?php
                                    switch($notification['type']) {
                                        case 'fertilizer_recommendation':
                                            echo 'Recommendation: ';
                                            break;
                                        case 'soil_test':
                                            echo 'Test Results: ';
                                            break;
                                        case 'query':
                                            echo 'Query Details: ';
                                            break;
                                    }
                                    ?>
                                </strong>
                                <?php echo htmlspecialchars(substr($notification['additional_details'], 0, 150)) . '...'; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="notification-actions">
                        <?php 
                        switch($notification['type']) {
                            case 'fertilizer_recommendation': ?>
                                <a href="view_recommendation.php?id=<?php echo $notification['related_id']; ?>" class="btn-view">
                                    View Full Recommendation
                                </a>
                                <?php break;
                            case 'soil_test': ?>
                                <a href="view_soil_test.php?id=<?php echo $notification['related_id']; ?>" class="btn-view">
                                    View Soil Test Results
                                </a>
                                <?php break;
                            case 'query': ?>
                                <a href="view_query.php?id=<?php echo $notification['related_id']; ?>" class="btn-view">
                                    View Full Query
                                </a>
                                <?php break;
                        } ?>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No notifications found.</p>
        <?php endif; ?>
    </div>
</body>
</html> 