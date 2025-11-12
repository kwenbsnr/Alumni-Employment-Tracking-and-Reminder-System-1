<?php
// api/notification/notification_helper.php

// Fix the path - use absolute path from document root
require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/config/notification_config.php';

use NotificationAPI\NotificationAPI;

class NotificationHelper {
    private $notificationapi;
    private $conn;

    public function __construct($database_connection = null) {
        // Initialize NotificationAPI
        $this->notificationapi = new NotificationAPI(
            NOTIFICATIONAPI_CLIENT_ID,
            NOTIFICATIONAPI_CLIENT_SECRET
        );
        
        // Store database connection if provided
        $this->conn = $database_connection;
    }

    /**
     * Send notification via NotificationAPI
     */
    public function sendNotification($templateId, $notificationId, $recipientEmail, $parameters = []) {
        try {
            // Validate required parameters
            if (empty($recipientEmail)) {
                throw new Exception("Recipient email is required");
            }

            if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid recipient email: " . $recipientEmail);
            }

            // Map template ID to NotificationAPI type
            $mappedNotificationId = TEMPLATE_MAPPINGS[$templateId] ?? $notificationId;
            
            if (empty($mappedNotificationId)) {
                throw new Exception("Notification ID not found for template: " . $templateId);
            }

            // Prepare parameters with safe defaults
            $safeParameters = $this->prepareSafeParameters($parameters);

            // Send notification
            $response = $this->notificationapi->send([
                'type' => $mappedNotificationId,
                'to' => [
                    'id' => $recipientEmail,
                    'email' => $recipientEmail
                ],
                'parameters' => $safeParameters,
                'templateId' => $templateId
            ]);

            // Log successful notification
            error_log("Notification sent successfully: {$templateId} to {$recipientEmail}");

            return true;

        } catch (Exception $e) {
            // Log error but don't break the application
            error_log("NotificationAPI Error - Template: {$templateId}, Email: {$recipientEmail}, Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Prepare safe parameters with defaults to avoid missing data
     */
    private function prepareSafeParameters($parameters) {
        $defaults = [
            "alumni_name" => "Alumni",
            "graduation_year" => "N/A",
            "original_rejection_date" => "N/A",
            "submission_date" => date('Y-m-d H:i:s'),
            "current_position" => "N/A",
            "current_company" => "N/A",
            "alumni_email" => "N/A",
            "previous_rejection_reason" => "N/A",
            "admin_review_link" => "#",
            "employment_status" => "N/A",
            "name" => "Alumni",
            "rejection_reason" => "N/A",
            "resubmission_link" => "#",
            "status" => "N/A"
        ];

        // Merge provided parameters with defaults
        $merged = array_merge($defaults, $parameters);

        // Ensure all values are strings and handle null values
        foreach ($merged as $key => $value) {
            if ($value === null) {
                $merged[$key] = "N/A";
            } elseif (is_array($value)) {
                $merged[$key] = json_encode($value);
            } else {
                $merged[$key] = (string)$value;
            }
        }

        return $merged;
    }

    /**
     * Get all admin emails from database
     */
    public function getAdminEmails() {
        if (!$this->conn) {
            error_log("Database connection not available for fetching admin emails");
            return [];
        }

        try {
            $admin_emails = [];
            $stmt = $this->conn->prepare("SELECT email FROM users WHERE role = 'admin' AND email IS NOT NULL AND email != ''");
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
    public function getAlumniDetails($user_id) {
        if (!$this->conn) {
            error_log("Database connection not available for fetching alumni details");
            return null;
        }

        try {
            $stmt = $this->conn->prepare("
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
}