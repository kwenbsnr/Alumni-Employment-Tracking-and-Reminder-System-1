<?php
include("../connect.php"); 

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // For local testing; restrict in production

try {
    $stmt = $conn->prepare("SELECT region_id AS reg_code, region_name AS name FROM table_region ORDER BY region_name");
    $stmt->execute();
    $result = $stmt->get_result();
    $regions = [];
    while ($row = $result->fetch_assoc()) {
        $regions[] = $row;
    }
    echo json_encode($regions);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
$conn->close();
?>