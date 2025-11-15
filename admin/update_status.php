<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

// Enhanced cleanup function for rejected alumni
function cleanup_alumni_data($user_id, $conn) {
    // Delete all related data
    $tables = ['employment_info', 'education_info', 'alumni_documents'];
    
    foreach ($tables as $table) {
        $stmt = $conn->prepare("DELETE FROM $table WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Delete address record if exists
    $stmt = $conn->prepare("DELETE a FROM address a 
                        INNER JOIN alumni_profile ap ON a.address_id = ap.address_id 
                        WHERE ap.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Delete photo file if exists
    $stmt = $conn->prepare("SELECT photo_path FROM alumni_profile WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (!empty($row['photo_path']) && file_exists("../" . $row['photo_path'])) {
            unlink("../" . $row['photo_path']);
        }
    }
    $stmt->close();
    
    // Delete document files if exist
    $stmt = $conn->prepare("SELECT file_path FROM alumni_documents WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (!empty($row['file_path']) && file_exists("../" . $row['file_path'])) {
            unlink("../" . $row['file_path']);
        }
    }
    $stmt->close();
}

$user_id = $_GET['user_id'] ?? 0;
$status = $_GET['status'] ?? '';
$reason = $_GET['reason'] ?? '';

if ($user_id && in_array($status, ['Approved', 'Rejected'])) {
    
    // Start transaction for data consistency
    $conn->begin_transaction();
    
    try {
        // Update alumni profile based on admin action
        if ($status === 'Rejected') {
            // Clean up alumni data when rejecting - THIS HAPPENS IMMEDIATELY
            cleanup_alumni_data($user_id, $conn);
            
            $updateQuery = "UPDATE alumni_profile 
                            SET submission_status = ?, rejection_reason = ?, rejected_at = NOW(),
                            first_name = NULL, middle_name = NULL, last_name = NULL,
                            contact_number = NULL, year_graduated = NULL, employment_status = NULL,
                            photo_path = NULL, address_id = NULL, last_profile_update = NULL,
                            submitted_at = NULL
                            WHERE user_id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param('ssi', $status, $reason, $user_id);
        } else {
            $updateQuery = "UPDATE alumni_profile SET submission_status = ?, rejection_reason = NULL, rejected_at = NULL WHERE user_id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param('si', $status, $user_id);
        }
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to update alumni profile");
        }
        $stmt->close();

        // Log the action correctly
        $update_type = ($status === 'Approved') ? 'approve' : 'reject';
        $logQuery = "INSERT INTO update_log (updated_by, updated_id, update_type) VALUES (?, ?, ?)";
        $logStmt = $conn->prepare($logQuery);
        $logStmt->bind_param('iis', $_SESSION['user_id'], $user_id, $update_type);
        
        if (!$logStmt->execute()) {
            throw new Exception("Failed to log admin action");
        }
        $logStmt->close();

        // Commit transaction
        $conn->commit();

        // Redirect success
        header("Location: alumni_management.php?success=" . urlencode("Alumni profile " . strtolower($status) . " successfully"));
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Admin status update error: " . $e->getMessage());
        header("Location: alumni_management.php?error=Error updating alumni status: " . $e->getMessage());
        exit();
    }
    
} else {
    header("Location: alumni_management.php?error=Invalid parameters");
    exit();
}
?>