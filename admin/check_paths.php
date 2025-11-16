<?php
echo "<h3>Path Check</h3>";

$paths = [
    'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'],
    'First try' => $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/api/notification/notification_helper.php',
    'Second try' => __DIR__ . '/../api/notification/notification_helper.php',
    'Current dir' => __DIR__
];

foreach ($paths as $name => $path) {
    $exists = file_exists($path) ? '✅ EXISTS' : '❌ MISSING';
    echo "<b>$name:</b> $path - $exists<br>";
}

// Test if we can include the file
echo "<h3>Include Test</h3>";
$test_path = __DIR__ . '/../api/notification/notification_helper.php';
if (file_exists($test_path)) {
    include_once $test_path;
    echo "File included successfully<br>";
    
    if (class_exists('NotificationHelper')) {
        echo "✅ NotificationHelper class loaded!";
    } else {
        echo "❌ NotificationHelper class NOT found after include";
    }
} else {
    echo "File not found at: $test_path";
}
?>