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
        // Log the action
        $logQuery = "INSERT INTO update_log (updated_by, updated_id, updated_table, update_type) VALUES (?, ?, 'alumni_profile', 'approve')";
        $logStmt = $conn->prepare($logQuery);
        $logStmt->bind_param('ii', $_SESSION['user_id'], $user_id);
        $logStmt->execute();
        
        header("Location: alumni_management.php?success=Alumni profile " . strtolower($status) . " successfully");
    } else {
        header("Location: alumni_management.php?error=Error updating alumni status");
    }
} else {
    header("Location: alumni_management.php?error=Invalid parameters");
}
exit();
