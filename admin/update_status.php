<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

if (!isset($_GET['id']) || !isset($_GET['status']) || !isset($_GET['user_id']) || !isset($_GET['doc_type'])) {
    header("Location: alumni_management.php?error=" . urlencode("Missing required parameters"));
    exit();
}

$doc_id = (int)$_GET['id'];
$status = $_GET['status'];
$reason = isset($_GET['reason']) ? $conn->real_escape_string($_GET['reason']) : '';
$user_id = (int)$_GET['user_id'];
$doc_type = $conn->real_escape_string($_GET['doc_type']);

if (!in_array($status, ['Pending', 'Approved', 'Rejected'])) {
    header("Location: alumni_management.php?error=" . urlencode("Invalid document status"));
    exit();
}

$needs_reupload = ($status == 'Rejected') ? 1 : 0;

$conn->begin_transaction();
try {
    $query = "UPDATE alumni_documents SET document_status = ?, rejection_reason = ?, needs_reupload = ? WHERE doc_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssii", $status, $reason, $needs_reupload, $doc_id);
    if (!$stmt->execute() || $stmt->affected_rows == 0) {
        throw new Exception("Failed to update document status");
    }

    $log_query = "INSERT INTO update_log (user_id, update_type, updated_by, update_description) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($log_query);
    $update_type = "Document Status Update";
    $updated_by = $_SESSION["user_id"];
    $description = "Document $doc_type status changed to $status" . ($reason ? " with reason: $reason" : "");
    $stmt->bind_param("isis", $user_id, $update_type, $updated_by, $description);
    $stmt->execute();

    $notif_query = "INSERT INTO notifications (user_id, message, created_at) VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($notif_query);
    $message = "Your $doc_type has been $status" . ($reason ? ". Reason: $reason" : "");
    $stmt->bind_param("is", $user_id, $message);
    $stmt->execute();

    $conn->commit();
    header("Location: alumni_management.php?success=" . urlencode("Document status updated successfully"));
} catch (Exception $e) {
    $conn->rollback();
    header("Location: alumni_management.php?error=" . urlencode("Error updating status: " . $e->getMessage()));
}

$stmt->close();
$conn->close();
?>