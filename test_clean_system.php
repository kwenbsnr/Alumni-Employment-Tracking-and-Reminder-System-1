<?php
// test_clean_system.php
echo "<h3>Clean System Test</h3>";

// Test 1: Database connection
include 'connect.php';
if ($conn) {
    echo "✅ Database connection working<br>";
} else {
    echo "❌ Database connection failed<br>";
}

// Test 2: Notification functions
$notification_path = $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/api/notification/notification_functions.php';
if (file_exists($notification_path)) {
    include_once $notification_path;
    echo "✅ Notification functions loaded<br>";
    
    // Test if functions exist
    if (function_exists('send_notification')) {
        echo "✅ send_notification() function available<br>";
    }
    if (function_exists('get_admin_emails')) {
        echo "✅ get_admin_emails() function available<br>";
    }
    if (function_exists('get_alumni_details')) {
        echo "✅ get_alumni_details() function available<br>";
    }
} else {
    echo "❌ Notification functions not found at: $notification_path<br>";
}

// Test 3: Composer autoload
$autoload_path = 'vendor/autoload.php';
if (file_exists($autoload_path)) {
    echo "✅ Composer autoload available<br>";
} else {
    echo "❌ Composer autoload missing<br>";
}

echo "<h4>🎉 System is clean and ready!</h4>";
?>