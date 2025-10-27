<?php
header('Content-Type: application/json');
include("../connect.php");

if (!isset($_GET['user_id']) || !is_numeric($_GET['user_id'])) {
    echo json_encode(['error' => 'Invalid or missing user ID']);
    exit();
}

$user_id = (int)$_GET['user_id'];

$query = "SELECT doc_id, document_type, file_path, document_status, rejection_reason 
          FROM alumni_documents 
          WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$documents = [];
while ($row = $result->fetch_assoc()) {
    $documents[] = $row;
}

if (empty($documents)) {
    echo json_encode(['error' => 'No documents found for this user']);
    exit();
}

echo json_encode($documents);
$stmt->close();
$conn->close();
?>