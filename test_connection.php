<?php
require_once 'config.php';

$dbConfig = new DatabaseConfig();
$conn = $dbConfig->getConnection();

if ($conn) {
    echo "Database connection successful!";
    
    // Test query
    $result = mysqli_query($conn, "SHOW TABLES");
    echo "<br>Tables in database:<br>";
    while ($row = mysqli_fetch_array($result)) {
        echo $row[0] . "<br>";
    }
} else {
    echo "Connection failed: " . mysqli_connect_error();
}
?> 