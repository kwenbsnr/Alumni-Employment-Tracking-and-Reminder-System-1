<?php
// final_system_test.php
echo "<h3>Final System Integration Test</h3>";

// Test 1: Database
include 'connect.php';
echo $conn ? "✅ Database connected<br>" : "❌ Database failed<br>";

// Test 2: Notification functions
$notification_path = $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/api/notification/notification_functions.php';
if (file_exists($notification_path)) {
    include_once $notification_path;
    echo "✅ Notification functions loaded<br>";
    
    // Test function availability
    $functions = ['send_notification', 'get_admin_emails', 'get_alumni_details'];
    foreach ($functions as $func) {
        echo function_exists($func) ? "✅ $func() available<br>" : "❌ $func() missing<br>";
    }
} else {
    echo "❌ Notification functions missing<br>";
}

// Test 3: Composer
echo file_exists('vendor/autoload.php') ? "✅ Composer ready<br>" : "❌ Composer missing<br>";

echo "<h4>Next: Test real scenarios</h4>";
echo "1. Submit alumni profile<br>";
echo "2. Approve/Reject as admin<br>";
echo "3. Check NotificationAPI dashboard<br>";
?>