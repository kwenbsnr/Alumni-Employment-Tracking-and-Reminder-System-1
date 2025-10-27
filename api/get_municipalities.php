<?php
include("../connect.php");

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$province_id = $_GET['province_id'] ?? '';

if (empty($province_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'province_id required']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT municipality_id AS mun_code, municipality_name AS name FROM table_municipality WHERE province_id = ? ORDER BY municipality_name");
    $stmt->bind_param("s", $province_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $municipalities = [];
    while ($row = $result->fetch_assoc()) {
        $municipalities[] = $row;
    }
    echo json_encode($municipalities);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
$conn->close();
?>