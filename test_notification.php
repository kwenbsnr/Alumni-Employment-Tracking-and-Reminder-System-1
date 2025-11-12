<?php
require __DIR__ . '/vendor/autoload.php';

use NotificationAPI\NotificationAPI;

$notificationapi = new NotificationAPI(
    "ls4kt1i6t2hhh7rxd51k00rjj3", // Client ID
    "rtdiclclahiqxqr692c86zyk9in81pmlc2kol4j3n9x3gk7dyy3qco19av" // Client Secret
);

try {
    $notificationapi->send([
        'type' => 'alumni_employment_tracking_update_your_profile',
        'to' => [
            'id' => 'bisnar.quien18@gmail.com',
            'email' => 'bisnar.quien18@gmail.com'
        ],
        'parameters' => [
            "alumni_name" => "Test Alumni",
            "graduation_year" => "2025",
            "submission_date" => date('Y-m-d H:i:s'),
            "current_position" => "Software Developer",
            "current_company" => "Tech Co.",
            "alumni_email" => "bisnar.quien18@gmail.com",
            "admin_review_link" => "http://localhost/admin/alumni_management.php"
        ],
        'templateId' => 'template_one'
    ]);
    echo "Notification sent successfully!";
} catch (Exception $e) {
    echo "Error sending notification: " . $e->getMessage();
}
