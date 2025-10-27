<?php

include("../connect.php"); 
// Temporarily disable foreign key checks to allow truncating referenced tables
$conn->query("SET foreign_key_checks = 0");

// Function to truncate table safely
function truncateTable($conn, $table) {
    $stmt = $conn->prepare("TRUNCATE TABLE $table");
    if (!$stmt->execute()) {
        throw new Exception("Failed to truncate $table: " . $conn->error);
    }
    $stmt->close();
}

// Function to insert with prepared statement (using INSERT IGNORE for duplicates)
function insertRecord($conn, $table, $columns, $values, $description = '') {
    $placeholders = str_repeat('?,', count($values) - 1) . '?';
    $sql = "INSERT IGNORE INTO $table (" . implode(',', $columns) . ") VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed for $table: " . $conn->error);
    }
    $stmt->bind_param(str_repeat('s', count($values)), ...$values);
    $success = $stmt->execute();
    if (!$success) {
        $error = $stmt->error;
        if (strpos($error, 'Duplicate entry') !== false) {
            echo "<p>⚠️ Skipped duplicate $description: " . ($values[1] ?? 'Unknown') . " (Error: $error)</p>";
            return false; // Handled as warning
        } else {
            throw new Exception("Insert failed for $table: $error");
        }
    }
    $stmt->close();
    return true;
}

// Base path to JSON files (relative from alumni/seed_address.php)
$jsonBasePath = '../assets/js/phil-address/json/';
$jsonFiles = [
    'regions' => $jsonBasePath . 'regions.json',
    'provinces' => $jsonBasePath . 'provinces.json',
    'municipalities' => $jsonBasePath . 'city-mun.json',
    'barangays' => $jsonBasePath . 'barangays.json'
];

$insertCounts = ['regions' => 0, 'provinces' => 0, 'municipalities' => 0, 'barangays' => 0];
$errors = [];

// Check if JSON files exist
foreach ($jsonFiles as $type => $file) {
    if (!file_exists($file)) {
        $errors[] = "Missing JSON file: $file (for $type)";
    }
}

if (!empty($errors)) {
    echo "<h2>❌ File Errors:</h2><ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul><p>Ensure JSON files are in assets/js/phil-address/json/.</p>";
    $conn->close();
    exit;
}

// Truncate all tables (now safe with FK checks disabled)
$tables = ['table_region', 'table_province', 'table_municipality', 'table_barangay'];
foreach ($tables as $table) {
    try {
        truncateTable($conn, $table);
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

try {
    // 1. Regions
    $regionsData = json_decode(file_get_contents($jsonFiles['regions']), true);
    if ($regionsData === null) {
        throw new Exception("Invalid or missing regions.json");
    }
    foreach ($regionsData as $region) {
        if (insertRecord($conn, 'table_region', ['region_id', 'region_name'], [$region['reg_code'], $region['name']], 'region ' . $region['name'])) {
            $insertCounts['regions']++;
        }
    }

    // 2. Provinces
    $provincesData = json_decode(file_get_contents($jsonFiles['provinces']), true);
    if ($provincesData === null) {
        throw new Exception("Invalid or missing provinces.json");
    }
    foreach ($provincesData as $prov) {
        if (insertRecord($conn, 'table_province', ['province_id', 'province_name', 'region_id'], [$prov['prov_code'], $prov['name'], $prov['reg_code']], 'province ' . $prov['name'])) {
            $insertCounts['provinces']++;
        }
    }

    // 3. Municipalities
    $munData = json_decode(file_get_contents($jsonFiles['municipalities']), true);
    if ($munData === null) {
        throw new Exception("Invalid or missing city-mun.json");
    }
    foreach ($munData as $mun) {
        if (insertRecord($conn, 'table_municipality', ['municipality_id', 'municipality_name', 'province_id'], [$mun['mun_code'], $mun['name'], $mun['prov_code']], 'municipality ' . $mun['name'])) {
            $insertCounts['municipalities']++;
        }
    }

    // 4. Barangays (group by mun_code, sort by name, generate brgy_id = mun_code + 001, 002, etc.)
    $brgyData = json_decode(file_get_contents($jsonFiles['barangays']), true);
    if ($brgyData === null) {
        throw new Exception("Invalid or missing barangays.json");
    }
    $brgyByMun = [];
    foreach ($brgyData as $brgy) {
        $munCode = $brgy['mun_code'];
        if (!isset($brgyByMun[$munCode])) {
            $brgyByMun[$munCode] = [];
        }
        $brgyByMun[$munCode][] = $brgy['name'];
    }
    // Fetch valid municipality_ids from table_municipality
    $valid_mun_stmt = $conn->query("SELECT municipality_id FROM table_municipality");
    $valid_mun_ids = [];
    while ($row = $valid_mun_stmt->fetch_assoc()) {
        $valid_mun_ids[] = $row['municipality_id'];
    }
    $valid_mun_stmt->close();

    foreach ($brgyByMun as $munCode => $brgys) {
        // Skip if mun_code doesn't exist in table_municipality
        if (!in_array($munCode, $valid_mun_ids)) {
            echo "<p>⚠️ Skipped barangays for invalid mun_code: $munCode (not found in table_municipality)</p>";
            $errors[] = "Skipped barangays for mun_code $munCode: not found in table_municipality";
            continue;
        }
        // Sort alphabetically by name (removes any JSON duplicates implicitly)
        $brgys = array_unique($brgys); // Handle potential name duplicates
        sort($brgys);
        foreach ($brgys as $index => $brgyName) {
            $brgyId = $munCode . str_pad($index + 1, 3, '0', STR_PAD_LEFT); // e.g., 012801001
            if (insertRecord($conn, 'table_barangay', ['barangay_id', 'barangay_name', 'municipality_id'], [$brgyId, $brgyName, $munCode], 'barangay ' . $brgyName . ' in ' . $munCode)) {
                $insertCounts['barangays']++;
            }
        }
    }

    // Re-enable foreign key checks
    $conn->query("SET foreign_key_checks = 1");

    echo "<h2>✅ Seeding Successful!</h2>";
    echo "<p>Database integration complete for address hierarchy. Ready for alumni profile management and employment tracking. Duplicates (e.g., NCR codes) were skipped automatically.</p>";
    echo "<p>Inserted records:</p><ul>";
    foreach ($insertCounts as $type => $count) {
        echo "<li>$type: $count</li>";
    }
    echo "</ul>";

} catch (Exception $e) {
    // Re-enable FK checks even on error
    $conn->query("SET foreign_key_checks = 1");
    $errors[] = $e->getMessage();
}

if (!empty($errors)) {
    echo "<h2>❌ Errors Encountered:</h2><ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>";
}

$conn->close();
?>