<?php
// test_notification_fixed.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/api/notification/notification_functions.php';

// Test with real email
$test_email = "bisnar.quien18@gmail.com";

$test_templates = [
    'template_approved' => [
        'alumni_name' => 'John Doe',
        'graduation_year' => '2020',
        'current_position' => 'Software Developer',
        'current_company' => 'Tech Corp'
    ],
    'template_rejected' => [
        'alumni_name' => 'Jane Smith',
        'graduation_year' => '2019', 
        'rejection_reason' => 'Incomplete employment information'
    ],
    'alum_resubmit_admin_notif' => [
        'alumni_name' => 'Mike Johnson',
        'graduation_year' => '2021',
        'previous_rejection_reason' => 'Missing documents'
    ],
    'template_admin_notif' => [
        'alumni_name' => 'Sarah Wilson',
        'graduation_year' => '2022',
        'employment_status' => 'Employed'
    ],
    'alum_update_admin_notif' => [
        'alumni_name' => 'David Brown',
        'graduation_year' => '2020',
        'employment_status' => 'Self-Employed'
    ],
    'template_one' => [
        'alumni_name' => 'Emily Davis',
        'graduation_year' => '2018'
    ]
];

foreach ($test_templates as $template => $params) {
    echo "=== Testing Template: {$template} ===\n";
    echo "Notification Type: alumni_employment_tracking_update_your_profile\n";
    echo "Recipient: {$test_email}\n";
    
    $result = send_notification($template, $test_email, $params);
    
    if ($result) {
        echo "✅ SUCCESS: Notification sent with template '{$template}'\n";
    } else {
        echo "❌ FAILED: Notification failed for template '{$template}'\n";
    }
    echo "Waiting 2 seconds...\n\n";
    sleep(2); // Avoid rate limiting
}

echo "=== ALL TESTS COMPLETED ===\n";
echo "Please check: {$test_email} for all test emails\n";
?>