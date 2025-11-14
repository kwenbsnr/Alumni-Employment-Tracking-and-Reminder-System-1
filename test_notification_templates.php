<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/api/notification/notification_functions.php';

use NotificationAPI\NotificationAPI;

// Test each template
$test_templates = [
    'template_approved',
    'template_rejected', 
    'alum_resubmit_admin_notif',
    'template_admin_notif'
];

$test_email = "bisnar.quien18@gmail.com"; 

foreach ($test_templates as $template) {
    echo "Testing template: {$template}\n";
    
    $result = send_notification($template, $test_email, [
        'alumni_name' => 'Test User',
        'graduation_year' => '2020',
        'current_position' => 'Software Developer',
        'current_company' => 'Test Company'
    ]);
    
    echo $result ? "✅ Success\n" : "❌ Failed\n";
    echo "---\n";
    sleep(1); // Avoid rate limiting
}
?>