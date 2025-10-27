<?php
include("../connect.php");

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$region_id = $_GET['region_id'] ?? '';

if (empty($region_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'region_id required']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT province_id AS prov_code, province_name AS name FROM table_province WHERE region_id = ? ORDER BY province_name");
    $stmt->bind_param("s", $region_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $provinces = [];
    while ($row = $result->fetch_assoc()) {
        $provinces[] = $row;
    }
    echo json_encode($provinces);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
$conn->close();
?>