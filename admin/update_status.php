<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}

include("../connect.php");
include_once "../api/notification/notification_helper.php";

$user_id = intval($_GET['user_id'] ?? 0);
$status = $_GET['status'] ?? '';
$reason = htmlspecialchars(trim($_GET['reason'] ?? ''));

if ($user_id && in_array($status, ['Approved', 'Rejected'])) {
    // Update alumni profile status
    if ($status == 'Rejected') {
        $updateQuery = "UPDATE alumni_profile SET submission_status = ?, rejection_reason = ?, rejected_at = NOW() WHERE user_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('ssi', $status, $reason, $user_id);
    } else {
        $updateQuery = "UPDATE alumni_profile SET submission_status = ?, rejection_reason = NULL, rejected_at = NULL WHERE user_id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param('si', $status, $user_id);
    }

    if ($stmt->execute()) {
        $stmt->close();

        // --- Log the action ---
        $update_type = strtolower($status);
        $logQuery = "INSERT INTO update_log (updated_by, updated_id, updated_table, update_type) VALUES (?, ?, 'alumni_profile', ?)";
        $logStmt = $conn->prepare($logQuery);
        $logStmt->bind_param('iis', $_SESSION['user_id'], $user_id, $update_type);
        $logStmt->execute();
        $logStmt->close();

        // --- Notification API ---
        $notif = new NotificationHelper();

        $stmt2 = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE user_id = ?");
        $stmt2->bind_param("i", $user_id);
        $stmt2->execute();
        $alumni = $stmt2->get_result()->fetch_assoc();
        $stmt2->close();

        if ($alumni) {
            $alumni_email = $alumni['email'];
            $alumni_name = $alumni['first_name'] . ' ' . $alumni['last_name'];
            $parameters = [
                "alumni_name" => $alumni_name,
                "status" => $status,
                "rejection_reason" => $reason
            ];

            try {
                if ($status === 'Rejected') {
                    $notif->sendNotification('alum_rejection_template', 'alumni_rejection', $alumni_email, $parameters);
                } else {
                    $notif->sendNotification('alum_approval_template', 'alumni_approved', $alumni_email, $parameters);
                }
            } catch (Exception $e) {
                error_log("Notification failed for user_id $user_id: " . $e->getMessage());
            }
        }

        // --- Redirect after success ---
        header("Location: alumni_management.php?success=" . urlencode("Alumni profile " . strtolower($status) . " successfully"));
        exit();
    } else {
        header("Location: alumni_management.php?error=Error updating alumni status");
        exit();
    }
} else {
    header("Location: alumni_management.php?error=Invalid parameters");
    exit();
}
?>
