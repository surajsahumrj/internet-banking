<?php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');         
define('DB_NAME', 'securebank');

function connectDB() {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

    if ($conn->connect_error) {
        error_log("Database Connection Failed: " . $conn->connect_error);
        die("System is currently experiencing technical difficulties. Please try again later.");
    }

    $conn->set_charset("utf8mb4");
    return $conn;
}
?>

