<?php
function sendNotification($conn, $type, $message, $user_id = null) {
    // First verify the database connection
    if (!$conn || $conn->connect_error) {
        error_log("Database connection failed");
        return false;
    }

    // Check if notifications table exists
    $table_check = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
    if (mysqli_num_rows($table_check) == 0) {
        error_log("Notifications table does not exist");
        return false;
    }

    // Prepare the statement with error handling
    $query = "INSERT INTO notifications (type, message, user_id, created_at, is_read) 
              VALUES (?, ?, ?, NOW(), 0)";
    
    $stmt = mysqli_prepare($conn, $query);
    if ($stmt === false) {
        error_log("Prepare failed: " . mysqli_error($conn));
        return false;
    }
    
    // Bind parameters with error handling
    if (!mysqli_stmt_bind_param($stmt, "ssi", $type, $message, $user_id)) {
        error_log("Binding parameters failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }
    
    // Execute with error handling
    if (!mysqli_stmt_execute($stmt)) {
        error_log("Execute failed: " . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        return false;
    }
    
    // Close the statement and return success
    mysqli_stmt_close($stmt);
    return true;
}

// Check if function doesn't already exist before declaring it
if (!function_exists('getFarmerName')) {
    function getFarmerName($conn, $user_id) {
        $query = "SELECT username FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);
            return $row['username'] ?? 'Unknown Farmer';
        }
        return 'Unknown Farmer';
    }
}
?> 