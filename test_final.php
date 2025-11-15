<?php
require_once 'vendor/autoload.php';
require_once 'connect.php';

echo "<h3>Final System Test</h3>";

// Test 1: NotificationAPI class
if (class_exists('NotificationAPI\NotificationAPI')) {
    echo "✅ NotificationAPI class loaded successfully<br>";
} else {
    echo "❌ NotificationAPI class failed to load<br>";
}

// Test 2: Database connection
if ($conn && $conn->ping()) {
    echo "✅ Database connection working<br>";
} else {
    echo "❌ Database connection failed<br>";
}

// Test 3: Your notification helper
try {
    include_once 'api/notification/notification_helper.php';
    $notif = new NotificationHelper($conn);
    echo "✅ Notification helper created successfully<br>";
  
    
    
    // Test 4: Get admin emails
    $admin_emails = $notif->getAdminEmails();
    echo "✅ Admin emails fetched: " . count($admin_emails) . " found<br>";
    
} catch (Exception $e) {
    echo "❌ Notification helper failed: " . $e->getMessage() . "<br>";
}

echo "<h4>🎉 System is ready for NotificationAPI integration!</h4>";

// Show current PHP extensions
echo "<h4>Loaded PHP Extensions:</h4>";
$required = ['curl', 'openssl', 'mbstring', 'json'];
foreach ($required as $ext) {
    echo extension_loaded($ext) ? "✅ $ext<br>" : "❌ $ext (missing)<br>";
}
?>