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

// Check if tables exist
$check_tables_sql = "
    SELECT TABLE_NAME 
    FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('farmer_queries', 'users')";
$tables_check = mysqli_query($conn, $check_tables_sql);

if (!$tables_check) {
    die("Error checking tables: " . mysqli_error($conn));
}

$existing_tables = [];
while ($table = mysqli_fetch_assoc($tables_check)) {
    $existing_tables[] = $table['TABLE_NAME'];
}

if (!in_array('farmer_queries', $existing_tables) || !in_array('users', $existing_tables)) {
    die("Required tables are missing. Please ensure both 'farmer_queries' and 'users' tables exist.");
}

// Handle email sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_email'])) {
    $query_id = $_POST['query_id'];
    $response = $_POST['response'];
    $farmer_email = $_POST['farmer_email'];
    $farmer_id = $_POST['farmer_id'];
    
    // Update query status in database
    $update_query = "UPDATE farmer_queries SET 
                    status = 'responded',
                    response = ?,
                    response_date = NOW() 
                    WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $update_query);
    mysqli_stmt_bind_param($stmt, "si", $response, $query_id);
    
    if (mysqli_stmt_execute($stmt)) {
        // Add notification
        $notification_sql = "INSERT INTO notifications (user_id, type, message, created_at, is_read) 
                           VALUES (?, 'query_response', ?, NOW(), 0)";
        $notify_stmt = mysqli_prepare($conn, $notification_sql);
        $notification_message = "Your query has been answered by our expert team.";
        mysqli_stmt_bind_param($notify_stmt, "is", $farmer_id, $notification_message);
        mysqli_stmt_execute($notify_stmt);

        // Send email
        $to = $farmer_email;
        $subject = "Response to Your Query - GrowGuide";
        $message = "Dear Farmer,\n\n" . $response . "\n\nBest regards,\nGrowGuide Team";
        $headers = "From: support@growguide.com";

        if (mail($to, $subject, $message, $headers)) {
            $_SESSION['success_message'] = "Response sent successfully!";
        } else {
            $_SESSION['error_message'] = "Error sending email.";
        }
    } else {
        $_SESSION['error_message'] = "Error updating query.";
    }
    
    header("Location: farmer_queries.php");
    exit();
}

// Fetch all queries with farmer information
$queries_sql = "SELECT q.*, u.username, u.email, 
                CASE 
                    WHEN q.status = 'pending' THEN 1 
                    WHEN q.status = 'in_progress' THEN 2
                    ELSE 3 
                END as status_order
                FROM farmer_queries q 
                JOIN users u ON q.farmer_id = u.id 
                ORDER BY status_order ASC, q.query_date DESC";

$queries = mysqli_query($conn, $queries_sql);

if (!$queries) {
    die("Query failed: " . mysqli_error($conn));
}

// Add error checking for query execution
//if (!$queries) {
    //die("Query failed: " . mysqli_error($conn));


?>  

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Farmer Queries - GrowGuide</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .queries-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        /* Header section with improved animations */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h1 i {
            transform-origin: center;
            animation: bounce 2s infinite;
            display: inline-block;
            margin-right: 10px;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .back-btn {
            background: var(--primary-color);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .back-btn:hover {
            background: var(--dark-color);
            transform: translateX(-5px);
        }

        .back-btn:hover i {
            animation: slideArrow 0.6s ease infinite;
        }

        @keyframes slideArrow {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(-3px); }
        }

        /* Query card with enhanced animations */
        .query-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            animation: fadeIn 0.5s ease-out;
            border: 2px solid transparent;
        }

        .query-card:hover {
            transform: translateY(-5px) scale(1.01);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
            border-color: var(--primary-color);
        }

        /* Status badge with dynamic animations */
        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            position: relative;
            overflow: hidden;
        }

        .status-badge i {
            font-size: 0.8em;
        }

        .status-pending i {
            animation: pulse 1.5s infinite;
        }

        .status-in_progress i {
            animation: spin 1.5s linear infinite;
        }

        .status-responded i {
            animation: checkmark 0.5s ease-in-out;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        @keyframes checkmark {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        /* Submit button with enhanced interactions */
        .submit-btn {
            background: var(--primary-color);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            position: relative;
            overflow: hidden;
        }

        .submit-btn:hover {
            background: var(--dark-color);
            transform: translateY(-2px);
        }

        .submit-btn:hover i {
            animation: flyPlane 0.6s ease infinite;
        }

        @keyframes flyPlane {
            0% { transform: translateX(0) rotate(0); }
            50% { transform: translateX(3px) rotate(5deg); }
            100% { transform: translateX(0) rotate(0); }
        }

        /* Alert animations */
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
            animation: slideInFade 0.5s ease-out;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert i {
            animation: alertIcon 0.5s ease-out;
        }

        @keyframes slideInFade {
            from { 
                transform: translateY(-20px);
                opacity: 0;
            }
            to { 
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes alertIcon {
            0% { transform: scale(0); }
            50% { transform: scale(1.2); }
            100% { transform: scale(1); }
        }

        /* Response form improvements */
        .response-form {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
            transition: all 0.3s ease;
        }

        .response-input {
            width: 100%;
            padding: 12px;
            margin-bottom: 15px;
            border: 2px solid #ddd;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .response-input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 10px rgba(var(--primary-color-rgb), 0.1);
            outline: none;
            transform: translateY(-2px);
        }

        /* Add these new styles */
        .dashboard-btn {
            background: #2ecc71;  /* Fresh green color */
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.2);
            margin-bottom: 20px;
        }

        .dashboard-btn:hover {
            background: #27ae60;  /* Darker green on hover */
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(46, 204, 113, 0.3);
        }

        .dashboard-btn i {
            transition: transform 0.3s ease;
        }

        .dashboard-btn:hover i {
            transform: translateX(-4px);
        }
    </style>
</head>
<body>
    <div class="queries-container">
        <a href="employe.php" class="dashboard-btn">
            <i class="fas fa-home"></i> Back to Dashboard
        </a>
        
        <div class="page-header">
            <h1><i class="fas fa-comments"></i> Farmer Queries</h1>
        </div>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php 
                    echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php while ($query = mysqli_fetch_assoc($queries)): ?>
            <div class="query-card">
                <div class="query-header">
                    <h3><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($query['username']); ?></h3>
                    <span class="status-badge status-<?php echo $query['status']; ?>">
                        <?php 
                        $icon = '';
                        switch($query['status']) {
                            case 'pending':
                                $icon = '<i class="fas fa-clock"></i>';
                                break;
                            case 'in_progress':
                                $icon = '<i class="fas fa-spinner fa-spin"></i>';
                                break;
                            case 'responded':
                                $icon = '<i class="fas fa-check"></i>';
                                break;
                        }
                        echo $icon . ' ' . ucfirst($query['status']); 
                        ?>
                    </span>
                </div>
                
                <p><strong>Query Date:</strong> <?php echo date('Y-m-d H:i', strtotime($query['query_date'])); ?></p>
                <p><strong>Query:</strong> <?php echo htmlspecialchars($query['query_text']); ?></p>
                
                <?php if ($query['status'] === 'responded'): ?>
                    <p><strong>Response:</strong> <?php echo htmlspecialchars($query['response']); ?></p>
                    <p><strong>Response Date:</strong> <?php echo date('Y-m-d H:i', strtotime($query['response_date'])); ?></p>
                <?php else: ?>
                    <form class="response-form" method="POST" action="">
                        <input type="hidden" name="query_id" value="<?php echo $query['id']; ?>">
                        <input type="hidden" name="farmer_email" value="<?php echo $query['email']; ?>">
                        <input type="hidden" name="farmer_id" value="<?php echo $query['farmer_id']; ?>">
                        <textarea name="response" class="response-input" rows="4" 
                                placeholder="Type your response here..." required></textarea>
                        <button type="submit" name="send_email" class="submit-btn">
                            <i class="fas fa-paper-plane"></i> Send Response
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    </div>
</body>
</html>