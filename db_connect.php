<?php
include "config.php";
$database="growguide";
$sql="CREATE DATABASE IF NOT EXISTS $database";
if(mysqli_query($conn,$sql))
{
    echo "connected sucessfully";
}
else{
    
    "connection failed ". mysqli_error($conn);
}
mysqli_close($conn);
?>
