<?php

    /* DEBUGGING PURPOSES
    var_dump($_POST); exit; */
    /* echo "<pre>"; print_r($_POST); exit; */

session_start();
include("../connect.php");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login/login.php");
    exit();
}

function upload_file($field, $dir, $surname, $type, $allowed_types = ['application/pdf']) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    if (!in_array($_FILES[$field]['type'], $allowed_types)) return null;
    if ($_FILES[$field]['size'] > 2097152) return null; // 2MB limit
    if (!is_dir($dir)) mkdir($dir, 0777, true);

    // Determine file extension based on MIME type
    $ext = '.pdf';
    if (in_array($_FILES[$field]['type'], ['image/jpeg', 'image/png'])) {
        $ext = $_FILES[$field]['type'] === 'image/jpeg' ? '.jpg' : '.png';
    }
    $file_name = $surname . '_' . $type . $ext;
    $target = $dir . $file_name;
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $target)) return str_replace('../', '', $target);
    return null;
}

$alumni_id = intval($_POST['user_id']);

    /* DEBUGGING PURPOSES
    echo "<pre>";
    print_r($_POST);
    exit;*/

// Fetch user_id and last_name
$stmt = $conn->prepare("SELECT user_id, last_name FROM alumni_profile WHERE user_id = ?");
$stmt->bind_param("i", $alumni_id);
$stmt->execute();
$result = $stmt->get_result();
$alumni_row = $result->fetch_assoc();
if (!$alumni_row) {
    $_SESSION['error'] = "Alumni not found";
    header("Location: alumni_management.php?error=1");
    exit;
}
$user_id = $alumni_row['user_id'];
$last_name = $alumni_row['last_name'];

// Update alumni_profile
$first = trim($_POST['first_name']);
$middle = trim($_POST['middle_name'] ?? null);
$last = trim($_POST['last_name']);
$email = trim($_POST['email']);
$contact = trim($_POST['contact_number']);
$barangay = trim($_POST['barangay_id']);
$year = trim($_POST['year_graduated']);
$status = trim($_POST['employment_status']);
$photo = upload_file('profile_photo', '../Uploads/photos/', $last, 'photo', ['image/jpeg', 'image/png']);


// Safe POST access for address fields matching schema
$barangay_id = trim($_POST['barangay_id'] ?? '');  // Use barangay_id code from form
$street_details = trim($_POST['street_details'] ?? '');

// Update alumni_profile with correct column name
$sql = "UPDATE alumni_profile SET first_name=?, middle_name=?, last_name=?, contact_number=?, year_graduated=?, employment_status=?, photo_path=COALESCE(?, photo_path), last_profile_update=NOW() WHERE user_id=?";
$q = $conn->prepare($sql);  // Line 75: Updated to use last_profile_update
$q->bind_param("sssssssi", $first, $middle, $last, $contact, $year, $status, $photo, $alumni_id);  // Line 76: 8 params, sssssssi
if (!$q->execute()) {
    $_SESSION['error'] = $conn->error;
    header("Location: edit_alumni.php?user_id=$alumni_id&error=1");
    exit;
}

// Handle address update (if any field provided)
if ($barangay_id || $street_details) {
    if (!$barangay_id || !$street_details) {
        $_SESSION['error'] = "All address fields required if any provided.";
        header("Location: edit_alumni.php?id=$alumni_id&error=1");
        exit;
    }
    $addr_id_query = "SELECT address_id FROM alumni_profile WHERE user_id = ?";
    $addr_stmt = $conn->prepare($addr_id_query);
    $addr_stmt->bind_param("i", $alumni_id);
    $addr_stmt->execute();
    $addr_result = $addr_stmt->get_result();
    $existing_address_id = $addr_result->fetch_assoc()['address_id'] ?? null;

    if ($existing_address_id) {
        $addr_sql = "UPDATE address SET barangay_id = ?, street_details = ? WHERE address_id = ?";
        $addr_q = $conn->prepare($addr_sql);
        $addr_q->bind_param("ssi", $barangay_id, $street_details, $existing_address_id);
        if (!$addr_q->execute()) {
            $_SESSION['error'] = $conn->error;
            header("Location: edit_alumni.php?id=$alumni_id&error=1");
            exit;
        }
    } else {
        $addr_sql = "INSERT INTO address (barangay_id, street_details) VALUES (?, ?)";
        $addr_q = $conn->prepare($addr_sql);
        $addr_q->bind_param("ss", $barangay_id, $street_details);
        if (!$addr_q->execute()) {
            $_SESSION['error'] = $conn->error;
            header("Location: edit_alumni.php?id=$alumni_id&error=1");
            exit;
        }
        $new_address_id = $conn->insert_id;
        $link_sql = "UPDATE alumni_profile SET address_id = ? WHERE user_id = ?";
        $link_q = $conn->prepare($link_sql);
        $link_q->bind_param("ii", $new_address_id, $alumni_id);
        $link_q->execute();
    }
}

// Update users (email)
$email_sql = "UPDATE users SET email=? WHERE user_id=?";
$email_stmt = $conn->prepare($email_sql);
$email_stmt->bind_param("si", $email, $user_id);
$email_stmt->execute();

// Handle employment/academic details
$job_title = trim($_POST['job_title'] ?? null);
$school_name = trim($_POST['school_name'] ?? null);
if ($job_title || $school_name) {
    $job_title_id = null;
    if ($job_title) {
        $jt_stmt = $conn->prepare("INSERT IGNORE INTO job_titles (title) VALUES (?)");
        $jt_stmt->bind_param("s", $job_title);
        $jt_stmt->execute();
        $job_title_id = $conn->insert_id;
        if (!$job_title_id) {
            $jt_fetch = $conn->prepare("SELECT job_title_id FROM job_titles WHERE title = ?");
            $jt_fetch->bind_param("s", $job_title);
            $jt_fetch->execute();
            $jt_result = $jt_fetch->get_result();
            $job_title_id = $jt_result->fetch_assoc()['job_title_id'];
        }
    }

    $company = trim($_POST['company_name'] ?? null);
    $salary = trim($_POST['salary_range'] ?? null);
    //$school_address = trim($_POST['school_address'] ?? null);
    $degree = trim($_POST['degree_pursued'] ?? null);
    $ei_sql = "INSERT INTO employment_info (user_id, job_title_id, company_name, salary_range, school_name, degree_pursued) 
               VALUES (?, ?, ?, ?, ?, ?) 
               ON DUPLICATE KEY UPDATE 
               job_title_id=VALUES(job_title_id), company_name=VALUES(company_name), salary_range=VALUES(salary_range),
               school_name=VALUES(school_name), degree_pursued=VALUES(degree_pursued)";
    $ei_stmt = $conn->prepare($ei_sql);
    $ei_stmt->bind_param("iissss", $alumni_id, $job_title_id, $company, $salary, $school_name, $degree);
    $ei_stmt->execute();
}

// Handle documents
$coe = upload_file('coe_file', '../Uploads/coe/', $last, 'COE', ['application/pdf']);
$business = upload_file('business_file', '../Uploads/business/', $last, 'B_CERT', ['application/pdf']);
$cor = upload_file('cor_file', '../Uploads/cor/', $last, 'COR', ['application/pdf']);

function save_doc($conn, $alumni_id, $user_id, $type, $path) {
    if (!$path) return;
    $sql = "INSERT INTO alumni_documents (user_id, user_id, document_type, file_path, document_status) 
            VALUES (?, ?, ?, ?, 'Pending') 
            ON DUPLICATE KEY UPDATE file_path=VALUES(file_path), document_status='Pending', uploaded_at=NOW(), rejection_reason=NULL";
    $st = $conn->prepare($sql);
    $st->bind_param("iiss", $alumni_id, $user_id, $type, $path);
    if (!$st->execute()) {
        $_SESSION['error'] = $conn->error;
    }
}
if ($coe) save_doc($conn, $alumni_id, $user_id, "COE", $coe);
if ($business) save_doc($conn, $alumni_id, $user_id, "B_CERT", $business);
if ($cor) save_doc($conn, $alumni_id, $user_id, "COR", $cor);

// Log update
$update_type = "Profile Update";
$updated_table = 'alumni_profile'; // Variable for updated_table
$log_sql = "INSERT INTO update_log (updated_by, updated_id, updated_table, update_type, updated_at) VALUES (?, ?, ?, ?, NOW())";
$log_stmt = $conn->prepare($log_sql);
$log_stmt->bind_param("iiss", $_SESSION['user_id'], $alumni_id, $updated_table, $update_type); // Line 181: Updated bind
$log_stmt->execute();

$conn->close();
header("Location: edit_alumni.php?id=$alumni_id&success=1");
exit;
?>