<?php
$host = "localhost";   
$user = "root";        
$pass = "";            
$db   = "alumtrak_experiment"; 

//$conn = new mysqli($host, $user, $pass, $db);
$conn = new mysqli("127.0.0.1:3307", "root", "", "alumtrak_experiment");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>