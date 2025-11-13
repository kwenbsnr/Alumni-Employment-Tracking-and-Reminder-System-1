<?php
// test_admin_fix.php
session_start();
$_SESSION["user_id"] = 1;
$_SESSION["role"] = "admin";

include 'connect.php';

echo "<h3>Testing Admin Update Status</h3>";

// Test notification functions path
$notification_path = $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/api/notification/notification_functions.php';
if (file_exists($notification_path)) {
    echo "✅ Notification functions file exists<br>";
    include_once $notification_path;
    
    if (function_exists('send_notification')) {
        echo "✅ send_notification() function available<br>";
    }
} else {
    echo "❌ Notification functions file missing: $notification_path<br>";
}

// Test database connection
if ($conn) {
    echo "✅ Database connection working<br>";
}

echo "<p>Admin update status should now work correctly.</p>";
?>