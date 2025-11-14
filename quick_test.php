<?php
// quick_test.php
echo "<h3>Quick System Test</h3>";

// Test 1: Notification functions file
$notification_path = $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/api/notification/notification_functions.php';
if (file_exists($notification_path)) {
    echo "✅ Notification functions file exists<br>";
} else {
    echo "❌ Create notification_functions.php first<br>";
    exit;
}

// Test 2: Composer autoload
if (file_exists('vendor/autoload.php')) {
    echo "✅ Composer autoload exists<br>";
} else {
    echo "❌ Run: composer install<br>";
}

echo "Ready for next steps!";
?>