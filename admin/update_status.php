<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

// Fix the path for notification helper
$notification_helper_path = $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/api/notification/notification_helper.php';
if (file_exists($notification_helper_path)) {
    include_once $notification_helper_path;
} else {
    // Alternative path if the above doesn't work
    $notification_helper_path = __DIR__ . '/../api/notification/notification_helper.php';
    if (file_exists($notification_helper_path)) {
        include_once $notification_helper_path;
    } else {
        error_log("Notification helper not found at: " . $notification_helper_path);
    }
}

// Sanitize input
$user_id = intval($_GET['user_id'] ?? 0);
$status = $_GET['status'] ?? '';
$reason = htmlspecialchars(trim($_GET['reason'] ?? ''));

// Validate parameters
if ($user_id && in_array($status, ['Approved', 'Rejected'])) {
    
    // Update alumni profile based on admin action
    if ($status === 'Rejected') {
        $updateQuery = "UPDATE alumni_profile 
                        SET submission_status = ?, rejection_reason = ?, rejected_at = NOW() 
                        WHERE user_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('ssi', $status, $reason, $user_id);
    } else {
        $updateQuery = "UPDATE alumni_profile 
                        SET submission_status = ?, rejection_reason = NULL, rejected_at = NULL 
                        WHERE user_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('si', $status, $user_id);
    }

    if ($stmt->execute()) {
        // Log the action correctly (without updated_table column)
        $update_type = ($status === 'Approved') ? 'approve' : 'reject';
        $logQuery = "INSERT INTO update_log (updated_by, updated_id, update_type) VALUES (?, ?, ?)";
        $logStmt = $conn->prepare($logQuery);
        $logStmt->bind_param('iis', $_SESSION['user_id'], $user_id, $update_type);
        $logStmt->execute();
        $logStmt->close();

        // Send Notification (only if NotificationHelper is available)
        if (class_exists('NotificationHelper')) {
            try {
                $notif = new NotificationHelper($conn);
                
                // Fetch alumni info
                $stmt2 = $conn->prepare("
                    SELECT u.email, ap.first_name, ap.last_name, ap.year_graduated,
                           ap.employment_status, ei.company_name, jt.title as job_title
                    FROM users u
                    JOIN alumni_profile ap ON u.user_id = ap.user_id
                    LEFT JOIN employment_info ei ON u.user_id = ei.user_id 
                    LEFT JOIN job_titles jt ON ei.job_title_id = jt.job_title_id
                    WHERE u.user_id = ?
                ");
                $stmt2->bind_param("i", $user_id);
                $stmt2->execute();
                $alumni = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();

                if ($alumni && !empty($alumni['email'])) {
                    $alumni_email = $alumni['email'];
                    $alumni_name = trim(($alumni['first_name'] ?? '') . ' ' . ($alumni['last_name'] ?? ''));

                    $parameters = [
                        "alumni_name" => $alumni_name,
                        "rejection_reason" => $reason,
                        "status" => $status,
                        "graduation_year" => $alumni['year_graduated'] ?? 'N/A',
                        "current_position" => $alumni['job_title'] ?? 'N/A',
                        "current_company" => $alumni['company_name'] ?? 'N/A',
                        "employment_status" => $alumni['employment_status'] ?? 'N/A',
                        "name" => $alumni_name
                    ];

                    if ($status === 'Rejected') {
                        $notif->sendNotification('template_rejected', 'alumni_rejection', $alumni_email, $parameters);
                    } else {
                        $notif->sendNotification('template_approved', 'alumni_approval', $alumni_email, $parameters);
                    }
                    
                    error_log("Notification sent for {$status} action to: {$alumni_email}");
                }
            } catch (Exception $e) {
                error_log("Notification failed for user_id $user_id: " . $e->getMessage());
            }
        } else {
            error_log("NotificationHelper class not available - proceeding without notifications");
        }

        // Redirect success
        header("Location: alumni_management.php?success=" . urlencode("Alumni profile " . strtolower($status) . " successfully"));
        exit();
    } else {
        header("Location: alumni_management.php?error=" . urlencode("Error updating alumni status"));
        exit();
    }

} else {
    header("Location: alumni_management.php?error=" . urlencode("Invalid parameters"));
    exit();
}
?>