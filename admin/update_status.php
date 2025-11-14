<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

$user_id = $_GET['user_id'] ?? 0;
$status = $_GET['status'] ?? '';
$reason = $_GET['reason'] ?? '';

if ($user_id && in_array($status, ['Approved', 'Rejected'])) {

    // Update alumni profile based on admin action
    if ($status === 'Rejected') {
        $updateQuery = "UPDATE alumni_profile 
                        SET submission_status = ?, rejection_reason = ?, rejected_at = NOW() 
                        WHERE user_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('ssi', $status, $reason, $user_id);
    } else {
        $updateQuery = "UPDATE alumni_profile SET submission_status = ?, rejection_reason = NULL, rejected_at = NULL WHERE user_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('si', $status, $user_id);
    }
    
    if ($stmt->execute()) {

        // Log the action correctly
        $update_type = ($status === 'Approved') ? 'approve' : 'reject';
        $logQuery = "INSERT INTO update_log (updated_by, updated_id, update_type) VALUES (?, ?, ?)";
        $logStmt = $conn->prepare($logQuery);
        $logStmt->bind_param('ii', $_SESSION['user_id'], $user_id);
        $logStmt->execute();
        $logStmt->close();

        // Send Notification
        include_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/api/notification/notification_helper.php';
        $notifHelper = new NotificationHelper($conn);

        // Fetch alumni info using the helper
        $alumniDetails = $notifHelper->getAlumniDetails($user_id);

        if ($alumniDetails && !empty($alumniDetails['email'])) {
            $alumni_email = $alumniDetails['email'];
            $alumni_name = trim(($alumniDetails['first_name'] ?? '') . ' ' . ($alumniDetails['last_name'] ?? ''));

            $parameters = [
                "alumni_name" => $alumni_name,
                "rejection_reason" => $reason,
                "status" => $status,
                "graduation_year" => $alumniDetails['year_graduated'] ?? 'N/A',
                "current_position" => $alumniDetails['job_title'] ?? 'N/A',
                "current_company" => $alumniDetails['company_name'] ?? 'N/A',
                "employment_status" => $alumniDetails['employment_status'] ?? 'N/A'
            ];

            try {
                if ($status === 'Rejected') {
                    $notifHelper->sendNotification('template_rejected', 'alumni_rejection', $alumni_email, $parameters);
                } else {
                    $notifHelper->sendNotification('template_approved', 'alumni_approval', $alumni_email, $parameters);
                }
            } catch (Exception $e) {
                error_log("Notification failed for user_id $user_id: " . $e->getMessage());
            }
        }

        // Redirect success
        header("Location: alumni_management.php?success=" . urlencode("Alumni profile " . strtolower($status) . " successfully"));
        exit();
    } else {
        header("Location: alumni_management.php?error=Error updating alumni status");
    }
} else {
    header("Location: alumni_management.php?error=Invalid parameters");
}
?>
