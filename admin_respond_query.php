<?php
session_start();

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Database connection
$conn = mysqli_connect("localhost", "root", "", "growguide");
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $query_id = mysqli_real_escape_string($conn, $_POST['query_id']);
    $response = mysqli_real_escape_string($conn, $_POST['response']);
    
    // Update the query with response
    $update_query = "UPDATE employee_queries 
                    SET response = ?, 
                        status = 'answered', 
                        response_date = NOW() 
                    WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $update_query);
    
    // Check if prepare statement was successful
    if ($stmt === false) {
        $_SESSION['error_message'] = "Prepare failed: " . mysqli_error($conn);
        mysqli_close($conn);
        header("Location: employee.php");
        exit();
    }
    
    mysqli_stmt_bind_param($stmt, "si", $response, $query_id);
    
    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['success_message'] = "Response sent successfully!";
    } else {
        $_SESSION['error_message'] = "Error sending response: " . mysqli_error($conn);
    }
    
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
header("Location: admin_notifications.php");
exit();
?> 