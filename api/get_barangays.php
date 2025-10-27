<?php
include("../connect.php");

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$municipality_id = trim($_GET['municipality_id'] ?? '');

if (empty($municipality_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'municipality_id required']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT barangay_id AS brgy_code, barangay_name AS name FROM table_barangay WHERE municipality_id = ? ORDER BY barangay_name");
    $stmt->bind_param("s", $municipality_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $barangays = [];
    while ($row = $result->fetch_assoc()) {
        $row['brgy_code'] = trim($row['brgy_code']);
        $barangays[] = $row;
    }
    error_log("Returning " . count($barangays) . " barangays for municipality_id: '$municipality_id': " . json_encode(array_slice($barangays, 0, 5)));
    echo json_encode($barangays);
} catch (Exception $e) {
    error_log("Error fetching barangays for municipality_id '$municipality_id': " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
$conn->close();
?>