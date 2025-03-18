<?php
session_start();

// Authentication check
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// Add session timeout check
$timeout = 1800; // 30 minutes
if (time() - $_SESSION['last_activity'] > $timeout) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION['last_activity'] = time();

// Database connection
$conn = mysqli_connect("localhost", "root", "", "growguide");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Fetch all queries with employee details, ordered by status (pending first) and date
$queries_query = "SELECT eq.*, u.username as employee_name, u.email as employee_email 
                 FROM employee_queries eq
                 JOIN users u ON eq.employee_id = u.id
                 ORDER BY 
                    CASE eq.status 
                        WHEN 'pending' THEN 1 
                        ELSE 2 
                    END,
                    eq.created_at DESC";
$queries_result = mysqli_query($conn, $queries_query);

// Count pending queries
$pending_count_query = "SELECT COUNT(*) as count FROM employee_queries WHERE status = 'pending'";
$pending_count_result = mysqli_query($conn, $pending_count_query);
$pending_count = mysqli_fetch_assoc($pending_count_result)['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Notifications - GrowGuide</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --primary-color: #1b5e20;
            --secondary-color: #2e7d32;
            --accent-color: #43a047;
            --background-color: #f1f8e9;
            --card-color: #ffffff;
            --text-primary: #1b5e20;
            --text-secondary: #33691e;
            --pending-color: #ff9800;
            --answered-color: #4caf50;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }

        body {
            background-color: var(--background-color);
            color: var(--text-primary);
        }

        .notifications-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .pending-badge {
            background: var(--pending-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .notification-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            border-left: 4px solid;
            animation: slideIn 0.3s ease-out;
        }

        .notification-card.pending {
            border-left-color: var(--pending-color);
        }

        .notification-card.answered {
            border-left-color: var(--answered-color);
        }

        .notification-card:hover {
            transform: translateY(-5px);
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #eee;
        }

        .employee-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .employee-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .employee-details h3 {
            color: var(--primary-color);
            margin-bottom: 0.2rem;
        }

        .employee-details p {
            color: #666;
            font-size: 0.9rem;
        }

        .notification-date {
            color: #666;
            font-size: 0.9rem;
        }

        .notification-content {
            color: #333;
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .notification-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .btn-respond {
            background: var(--primary-color);
            color: white;
        }

        .btn-respond:hover {
            background: var(--secondary-color);
        }

        .response-form {
            margin-top: 1rem;
            display: none;
        }

        .response-form textarea {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 1rem;
            resize: vertical;
            min-height: 100px;
        }

        .response-content {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 1rem;
        }

        .response-content h4 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .response-date {
            color: #666;
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .status-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-badge.pending {
            background: #fff3e0;
            color: var(--pending-color);
        }

        .status-badge.answered {
            background: #e8f5e9;
            color: var(--answered-color);
        }

        .back-button {
            text-decoration: none;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .back-button:hover {
            color: var(--secondary-color);
        }
    </style>
</head>
<body>
    <div class="notifications-container">
        <a href="admin.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <div class="header">
            <h1>
                <i class="fas fa-bell"></i>
                Notifications
            </h1>
            <?php if ($pending_count > 0): ?>
                <span class="pending-badge">
                    <?php echo $pending_count; ?> Pending
                </span>
            <?php endif; ?>
        </div>

        <?php if (mysqli_num_rows($queries_result) > 0): ?>
            <?php while ($query = mysqli_fetch_assoc($queries_result)): ?>
                <div class="notification-card <?php echo $query['status']; ?>">
                    <div class="notification-header">
                        <div class="employee-info">
                            <div class="employee-avatar">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="employee-details">
                                <h3><?php echo htmlspecialchars($query['employee_name']); ?></h3>
                                <p><?php echo htmlspecialchars($query['employee_email']); ?></p>
                            </div>
                        </div>
                        <div class="notification-meta">
                            <span class="status-badge <?php echo $query['status']; ?>">
                                <?php echo ucfirst($query['status']); ?>
                            </span>
                            <div class="notification-date">
                                <i class="far fa-clock"></i>
                                <?php echo date('M d, Y H:i', strtotime($query['created_at'])); ?>
                            </div>
                        </div>
                    </div>

                    <div class="notification-content">
                        <?php echo htmlspecialchars($query['query_text']); ?>
                    </div>

                    <?php if ($query['status'] === 'pending'): ?>
                        <div class="notification-actions">
                            <button class="btn btn-respond" onclick="toggleResponseForm('form-<?php echo $query['id']; ?>')">
                                <i class="fas fa-reply"></i> Respond
                            </button>
                        </div>
                        <form id="form-<?php echo $query['id']; ?>" class="response-form" method="POST" action="admin_respond_query.php">
                            <input type="hidden" name="query_id" value="<?php echo $query['id']; ?>">
                            <textarea name="response" placeholder="Type your response..." required></textarea>
                            <button type="submit" class="btn btn-respond">
                                <i class="fas fa-paper-plane"></i> Send Response
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="response-content">
                            <h4><i class="fas fa-reply"></i> Response:</h4>
                            <p><?php echo htmlspecialchars($query['response']); ?></p>
                            <div class="response-date">
                                <i class="far fa-clock"></i>
                                <?php echo date('M d, Y H:i', strtotime($query['response_date'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="notification-card">
                <div class="notification-content" style="text-align: center;">
                    <i class="fas fa-check-circle" style="font-size: 2rem; color: var(--primary-color);"></i>
                    <p style="margin-top: 1rem;">No notifications at this time.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleResponseForm(formId) {
            const form = document.getElementById(formId);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html> 