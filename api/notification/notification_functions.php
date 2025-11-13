<?php
// api/notification/notification_functions.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/vendor/autoload.php';

use NotificationAPI\NotificationAPI;

// Configuration
define('NOTIFICATIONAPI_CLIENT_ID', 'ls4kt1i6t2hhh7rxd51k00rjj3');
define('NOTIFICATIONAPI_CLIENT_SECRET', 'rtdiclclahiqxqr692c86zyk9in81pmlc2kol4j3n9x3gk7dyy3qco19av');

/**
 * Send notification via NotificationAPI
 */
function send_notification($templateId, $recipientEmail, $parameters = []) {
    try {
        // Initialize NotificationAPI
        $notificationapi = new NotificationAPI(
            NOTIFICATIONAPI_CLIENT_ID,
            NOTIFICATIONAPI_CLIENT_SECRET
        );

        // Enhanced email validation
        if (empty($recipientEmail)) {
            throw new Exception("Recipient email is empty");
        }
        
        $recipientEmail = filter_var($recipientEmail, FILTER_VALIDATE_EMAIL);
        if (!$recipientEmail) {
            throw new Exception("Invalid recipient email format");
        }

        // Enhanced parameter validation with defaults
        $defaultParameters = [
            "alumni_name" => "Alumni",
            "graduation_year" => "N/A",
            "original_rejection_date" => "N/A", 
            "submission_date" => date('Y-m-d H:i:s'),
            "current_position" => "N/A",
            "current_company" => "N/A",
            "alumni_email" => $recipientEmail,
            "previous_rejection_reason" => "N/A",
            "admin_review_link" => "http://your-domain.com/admin/alumni_management.php",
            "employment_status" => "N/A",
            "name" => "Alumni",
            "alumni_portal_link" => "http://your-domain.com/alumni/alumni_profile.php",
            "rejection_reason" => "N/A",
            "resubmission_link" => "http://your-domain.com/alumni/update_profile.php",
            "status" => "N/A"
        ];

        $safeParameters = array_merge($defaultParameters, $parameters);

        // Convert all values to strings and handle nulls
        foreach ($safeParameters as $key => $value) {
            if ($value === null) {
                $safeParameters[$key] = "N/A";
            } else {
                $safeParameters[$key] = (string)$value;
            }
        }

        // **CORRECTED: Use SAME notification type but different templateId**
        $result = $notificationapi->send([
            'type' => 'alumni_employment_tracking_update_your_profile', // SAME for all
            'to' => [
                'id' => $recipientEmail,
                'email' => $recipientEmail
            ],
            'parameters' => $safeParameters,
            'templateId' => $templateId // Different for each scenario
        ]);

        error_log("✅ Notification sent successfully - Type: alumni_employment_tracking_update_your_profile, Template: {$templateId}, To: {$recipientEmail}");
        return true;

    } catch (Exception $e) {
        error_log("❌ Notification failed - Template: {$templateId}, Email: {$recipientEmail}, Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all admin emails from database
 */
function get_admin_emails($conn) {
    try {
        $admin_emails = [];
        $stmt = $conn->prepare("SELECT email FROM users WHERE role = 'admin' AND email IS NOT NULL AND email != ''");
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            if (filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $admin_emails[] = $row['email'];
            }
        }
        $stmt->close();

        return $admin_emails;

    } catch (Exception $e) {
        error_log("Error fetching admin emails: " . $e->getMessage());
        return [];
    }
}

/**
 * Get alumni details by user_id
 */
function get_alumni_details($conn, $user_id) {
    try {
        $stmt = $conn->prepare("
            SELECT u.email, ap.first_name, ap.last_name, ap.year_graduated, 
                   ap.employment_status, ap.rejection_reason, ap.rejected_at,
                   ap.submitted_at, ei.company_name, jt.title as job_title
            FROM users u 
            LEFT JOIN alumni_profile ap ON u.user_id = ap.user_id 
            LEFT JOIN employment_info ei ON u.user_id = ei.user_id 
            LEFT JOIN job_titles jt ON ei.job_title_id = jt.job_title_id
            WHERE u.user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $alumni = $result->fetch_assoc();
        $stmt->close();

        return $alumni;

    } catch (Exception $e) {
        error_log("Error fetching alumni details: " . $e->getMessage());
        return null;
    }
}
?>