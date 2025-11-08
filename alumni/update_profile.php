<?php
session_start();
include("../connect.php");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
define('DEBUG_MODE', false); // change to true when debugging
/*if (DEBUG_MODE) {
    error_log("Raw POST data: " . json_encode($_POST));
}*/

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

// Check yearly update restriction and re-upload eligibility
$stmt = $conn->prepare("SELECT last_profile_update, last_name, user_id, photo_path, address_id FROM alumni_profile WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();
$can_update = !$profile || ($profile && ($profile['last_profile_update'] === null || strtotime($profile['last_profile_update'] . ' +1 year') <= time()));
$alumni_id = $profile ? $profile['user_id'] : null;
$last_name = $profile['last_name'] ?? '';
$existing_address_id = $profile['address_id'] ?? null;
$stmt->close();

// Check for rejected documents needing re-upload
$can_reupload = false;
$rejected_docs = [];
if ($alumni_id) {
    $stmt = $conn->prepare("SELECT document_type FROM alumni_documents WHERE user_id = ? AND document_status = 'Rejected' AND needs_reupload = 1");
    $stmt->bind_param("i", $alumni_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($doc = $result->fetch_assoc()) {
        $rejected_docs[] = $doc['document_type'];
        $can_reupload = true;
    }
    $stmt->close();
}
if (!$can_update && !$can_reupload) {
    header("Location: alumni_profile.php?error=You can only update your profile once per year, unless re-uploading rejected documents.");
    exit;
}

function upload_file($field, $dir, $surname, $type, $allowed_types = ['application/pdf']) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;

    // MIME and extension checks
    $fileType = $_FILES[$field]['type'];
    $fileName = $_FILES[$field]['name'];
    $fileSize = $_FILES[$field]['size'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Allowed extensions based on MIME types
    $allowed_extensions = [];
    foreach ($allowed_types as $t) {
        if ($t === 'image/jpeg') $allowed_extensions[] = 'jpg';
        elseif ($t === 'image/png') $allowed_extensions[] = 'png';
        elseif ($t === 'application/pdf') $allowed_extensions[] = 'pdf';
    }

    // Validate type and extension
    if (!in_array($fileType, $allowed_types) || !in_array($ext, $allowed_extensions)) return null;
    if ($fileSize > 2 * 1024 * 1024) return null; // 2MB limit

    if (!is_dir($dir)) mkdir($dir, 0777, true);

    // Create sanitized, unique filename
    $file_name = preg_replace('/[^a-zA-Z0-9_\-]/', '', $surname) . '_' . $type . '.' . $ext;
    $target = rtrim($dir, '/') . '/' . $file_name;

    // Move uploaded file
    if (move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        return str_replace('../', '', $target); // Relative path for DB
    }
    return null;
}


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve form data
    $first = trim($_POST['first_name'] ?? '');
    $middle = trim($_POST['middle_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $contact = trim($_POST['contact_number'] ?? '');
    $year_graduated = trim($_POST['year_graduated'] ?? '');
    $employment_status = trim($_POST['employment_status'] ?? '');
    $raw_barangay_id = $_POST['barangay_id'] ?? '';
    $barangay_id = trim($raw_barangay_id);
    $job_title = trim($_POST['job_title'] ?? '');
    if ($job_title === 'Other') {
        $job_title = trim($_POST['other_job_title'] ?? '');
    }
    $company = trim($_POST['company_name'] ?? '');
    $company_address = trim($_POST['company_address'] ?? '');
    $salary = trim($_POST['salary_range'] ?? '');
    $business_type = trim($_POST['business_type'] ?? '');
    if ($business_type === 'Others (Please specify)') {
        $business_type = 'Others: ' . trim($_POST['business_type_other'] ?? '');
    }
    $school = trim($_POST['school_name'] ?? '');
    $degree = trim($_POST['degree_pursued'] ?? '');

    // Backend validation for required fields
    if ($can_update) {
        if (empty($first) || empty($last) || empty($contact) || empty($year_graduated) || empty($employment_status)) {
            header("Location: alumni_profile.php?error=" . urlencode("All personal fields are required."));
            exit;
        }

        if (in_array($employment_status, ['Employed', 'Self-Employed', 'Employed & Student'])) {
            if (empty($barangay_id)) {
                header("Location: alumni_profile.php?error=" . urlencode("All address fields are required for this employment status."));
                exit;
            }
        }

        if (in_array($employment_status, ['Employed', 'Employed & Student'])) {
            if (empty($job_title) || empty($company) || empty($company_address)) {
                header("Location: alumni_profile.php?error=" . urlencode("Job Title, Company Name, and Company Address are required for this employment status."));
                exit;
            }
        }

        if ($employment_status === 'Self-Employed') {
            if (empty($business_type)) {
                header("Location: alumni_profile.php?error=" . urlencode("Business Type is required for Self-Employed status."));
                exit;
            }
        }

        if (in_array($employment_status, ['Student', 'Employed & Student'])) {
            if (empty($school) || empty($degree)) {
                header("Location: alumni_profile.php?error=" . urlencode("School Name and Degree Pursued are required for this status."));
                exit;
            }
        }
    }

    // Process address
    $address_id = $existing_address_id;
    $barangay_id = trim($_POST['barangay_id'] ?? '');
    $municipality_id = trim($_POST['municipality_id'] ?? '');

    error_log("Raw POST data for address: " . json_encode([
        'barangay_id' => $barangay_id,
        'municipality_id' => $municipality_id,
        'employment_status' => $employment_status
    ]));

    if ($can_update && ($barangay_id || $municipality_id)) {
        // Require all address fields
        if (empty($barangay_id) || empty($municipality_id)) {
            error_log("Incomplete address fields for user_id $user_id: barangay_id='$barangay_id', municipality_id='$municipality_id', employment_status='$employment_status'");
            header("Location: alumni_profile.php?error=" . urlencode("All address fields (Region, Province, Municipality, Barangay) are required."));
            exit;
        }

        error_log("Processing address for user_id $user_id: barangay_id='$barangay_id' (hex: " . bin2hex($barangay_id) . "), municipality_id='$municipality_id', employment_status='$employment_status'");

        // Start transaction
        $conn->begin_transaction();

        try {
            // Validate municipality_id exists
            $mun_check_stmt = $conn->prepare("SELECT municipality_id FROM table_municipality WHERE municipality_id = ?");
            $mun_check_stmt->bind_param("s", $municipality_id);
            $mun_check_stmt->execute();
            $mun_check_result = $mun_check_stmt->get_result();
            if ($mun_check_result->num_rows === 0) {
                throw new Exception("Invalid municipality_id: '$municipality_id' not found in table_municipality");
            }
            $mun_check_stmt->close();

            // Validate barangay_id exists and belongs to the municipality
            $check_stmt = $conn->prepare("SELECT barangay_id FROM table_barangay WHERE barangay_id = ? AND municipality_id = ?");
            $check_stmt->bind_param("ss", $barangay_id, $municipality_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows === 0) {
                // Log all valid barangay_ids for the municipality
                $debug_stmt = $conn->prepare("SELECT barangay_id, barangay_name FROM table_barangay WHERE municipality_id = ? ORDER BY barangay_name");
                $debug_stmt->bind_param("s", $municipality_id);
                $debug_stmt->execute();
                $debug_result = $debug_stmt->get_result();
                $valid_barangays = [];
                while ($row = $debug_result->fetch_assoc()) {
                    $valid_barangays[] = ['id' => $row['barangay_id'], 'name' => $row['barangay_name']];
                }
                $debug_stmt->close();
                error_log("Valid barangays for municipality_id '$municipality_id': " . json_encode($valid_barangays));
                throw new Exception("Invalid barangay_id: '$barangay_id' (hex: " . bin2hex($barangay_id) . ") not found in table_barangay for municipality_id '$municipality_id'");
            }
            $check_stmt->close();

            // Proceed with insert or update
            if ($address_id) {
                $stmt = $conn->prepare("UPDATE address SET barangay_id = ? WHERE address_id = ?");
                $stmt->bind_param("si", $barangay_id, $address_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO address (barangay_id) VALUES (?)");
                $stmt->bind_param("s", $barangay_id);
            }
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $address_id = $address_id ?: $conn->insert_id;
            $stmt->close();

            // Update alumni_profile with new address_id
            $profile_stmt = $conn->prepare("UPDATE alumni_profile SET address_id = ? WHERE user_id = ?");
            $profile_stmt->bind_param("ii", $address_id, $user_id);
            if (!$profile_stmt->execute()) {
                throw new Exception("Failed to update alumni_profile address_id: " . $profile_stmt->error);
            }
            $profile_stmt->close();

            // Commit transaction
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            error_log("Address error for user_id $user_id: " . $e->getMessage());
            header("Location: alumni_profile.php?error=" . urlencode("Address update failed: " . $e->getMessage()));
            exit;
        }
    } elseif ($can_update) {
        // Require address fields for all statuses
        error_log("Missing address fields for user_id $user_id: barangay_id='$barangay_id', municipality_id='$municipality_id', employment_status='$employment_status'");
        header("Location: alumni_profile.php?error=" . urlencode("All address fields (Region, Province, Municipality, Barangay) are required."));
        exit;
    }

   // Process profile (require photo upload)
$photo_path = $profile['photo_path'] ?? null;

if ($can_update) {
    // Require photo upload for all alumni
    if (empty($_FILES['profile_photo']['name']) && empty($photo_path)) {
        header("Location: alumni_profile.php?error=" . urlencode("Profile photo is required. Please upload a valid JPG or PNG file."));
        exit;
    }

    // Proceed with upload (2MB max enforced inside upload_file)
    $new_photo_path = upload_file('profile_photo', '../Uploads/photos/', $last, 'profile', ['image/jpeg', 'image/png']);

    // 🧹 Delete old file only after successful new upload
    if ($new_photo_path && $photo_path && file_exists('../' . $photo_path)) {
        unlink('../' . $photo_path);
    }

    // If a new file was uploaded successfully, use it; otherwise keep old
    $photo_path = $new_photo_path ?? $photo_path;

    // If still no valid photo path, block submission
    if (empty($photo_path)) {
        header("Location: alumni_profile.php?error=" . urlencode("Profile photo upload failed or missing. Please try again."));
        exit;
    }

    // Continue with profile update logic
    if ($profile) {
        $stmt = $conn->prepare("UPDATE alumni_profile 
            SET first_name = ?, middle_name = ?, last_name = ?, contact_number = ?, year_graduated = ?, 
                employment_status = ?, photo_path = ?, last_profile_update = NOW(), address_id = ? 
            WHERE user_id = ?");
        $stmt->bind_param("ssssssssi", $first, $middle, $last, $contact, $year_graduated, $employment_status, $photo_path, $address_id, $user_id);
    } else {
        $stmt = $conn->prepare("INSERT INTO alumni_profile 
            (user_id, first_name, middle_name, last_name, contact_number, year_graduated, employment_status, photo_path, last_profile_update, address_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
        $stmt->bind_param("isssssssi", $user_id, $first, $middle, $last, $contact, $year_graduated, $employment_status, $photo_path, $address_id);
    }
    $stmt->execute();
    $stmt->close();
    $alumni_id = $profile ? $profile['user_id'] : $user_id;
}


    // Process employment info
    $employment_id = null;
    if ($can_update && in_array($employment_status, ['Employed', 'Self-Employed', 'Employed & Student'])) {
        // Fetch existing employment_id if any
        $stmt = $conn->prepare("SELECT employment_id FROM employment_info WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $employment_id = $row['employment_id'];
        }
        $stmt->close();

        // Get or create job_title_id based on job_title string (not direct ID from POST)
        $job_title_id = null;
        if (!empty($job_title) && in_array($employment_status, ['Employed', 'Employed & Student'])) {
            $stmt = $conn->prepare("SELECT job_title_id FROM job_titles WHERE title = ?");
            $stmt->bind_param("s", $job_title);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $job_title_id = $row['job_title_id'];
            } else {
                $insert_stmt = $conn->prepare("INSERT INTO job_titles (title) VALUES (?)");
                $insert_stmt->bind_param("s", $job_title);
                $insert_stmt->execute();
                $job_title_id = $conn->insert_id;
                $insert_stmt->close();
            }
            $stmt->close();
        } elseif (empty($job_title) && in_array($employment_status, ['Employed', 'Employed & Student'])) {
            throw new Exception("Job Title is required for employment status '$employment_status'");
        }

        // For Self-Employed, ensure job_title_id is null and clear company fields
        if ($employment_status === 'Self-Employed') {
            $job_title_id = null;
            $company_name = '';
            $company_address = '';
        } else {
            $company_name = $company;
           // $company_address = $company_address; // Already from POST
        }

        error_log("Processing employment for user_id $user_id: job_title_id='$job_title_id', company_name='$company_name', business_type='$business_type', salary_range='$salary', employment_status='$employment_status'");

        // INSERT or UPDATE
        if ($employment_id) {
            $stmt = $conn->prepare("UPDATE employment_info SET job_title_id = ?, company_name = ?, company_address = ?, business_type = ?, salary_range = ? WHERE employment_id = ?");
            $stmt->bind_param("issssi", $job_title_id, $company_name, $company_address, $business_type, $salary, $employment_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO employment_info (user_id, job_title_id, company_name, company_address, business_type, salary_range) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iissss", $user_id, $job_title_id, $company_name, $company_address, $business_type, $salary);
        }
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $employment_id = $employment_id ?: $conn->insert_id;
        $stmt->close();
    }

    // Process education
    if ($can_update) {
        if ($school && ($employment_status === 'Student' || $employment_status === 'Employed & Student')) {
            $stmt = $conn->prepare("REPLACE INTO education_info (user_id, school_name, degree_pursued) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user_id, $school, $degree);
            $stmt->execute();
            $stmt->close();
        } elseif ($employment_status !== 'Student' && $employment_status !== 'Employed & Student') {
            $stmt = $conn->prepare("DELETE FROM education_info WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Process documents
if ($can_update || in_array('COE', $rejected_docs)) {

    // 🧹 OLD FILE CLEANUP — COE
    $oldFileStmt = $conn->prepare("SELECT file_path FROM alumni_documents WHERE user_id = ? AND document_type = 'COE'");
    $oldFileStmt->bind_param("i", $alumni_id);
    $oldFileStmt->execute();
    $oldResult = $oldFileStmt->get_result();
    if ($old = $oldResult->fetch_assoc()) {
        $oldPath = '../' . $old['file_path'];
        if (file_exists($oldPath)) unlink($oldPath);
    }
    $oldFileStmt->close();

    // File upload handling
    $coe_path = upload_file('coe_file', '../Uploads/coe/', $last_name, 'coe', ['application/pdf']);
    if ($coe_path) {
        $stmt = $conn->prepare("INSERT INTO alumni_documents (user_id, document_type, file_path, document_status, needs_reupload) VALUES (?, 'COE', ?, 'Pending', 0)");
        $stmt->bind_param("is", $alumni_id, $coe_path);
        if (!$stmt->execute()) {
            error_log("COE insert failed for user_id $alumni_id: " . $stmt->error);
            header("Location: alumni_profile.php?error=" . urlencode("COE document insert failed: " . $stmt->error));
            exit;
        }
        $stmt->close();
    } else if (!empty($_FILES['coe_file']['name'])) {
        error_log("COE file upload failed for user_id $alumni_id: " . ($_FILES['coe_file']['error'] ?? 'No file or invalid type/size'));
        header("Location: alumni_profile.php?error=" . urlencode("COE file upload failed: Invalid file type or size exceeded"));
        exit;
    }
}

if ($can_update || in_array('B_CERT', $rejected_docs)) {

    // 🧹 OLD FILE CLEANUP — BUSINESS CERTIFICATE
    $oldFileStmt = $conn->prepare("SELECT file_path FROM alumni_documents WHERE user_id = ? AND document_type = 'B_CERT'");
    $oldFileStmt->bind_param("i", $alumni_id);
    $oldFileStmt->execute();
    $oldResult = $oldFileStmt->get_result();
    if ($old = $oldResult->fetch_assoc()) {
        $oldPath = '../' . $old['file_path'];
        if (file_exists($oldPath)) unlink($oldPath);
    }
    $oldFileStmt->close();

    // File upload handling
    $business_path = upload_file('business_file', '../Uploads/business/', $last_name, 'business', ['application/pdf']);
    if ($business_path) {
        $stmt = $conn->prepare("INSERT INTO alumni_documents (user_id, document_type, file_path, document_status, needs_reupload) VALUES (?, 'B_CERT', ?, 'Pending', 0)");
        $stmt->bind_param("is", $alumni_id, $business_path);
        if (!$stmt->execute()) {
            error_log("B_CERT insert failed for user_id $alumni_id: " . $stmt->error);
            header("Location: alumni_profile.php?error=" . urlencode("Business certificate insert failed: " . $stmt->error));
            exit;
        }
        $stmt->close();
    } else if (!empty($_FILES['business_file']['name'])) {
        error_log("B_CERT file upload failed for user_id $alumni_id: " . ($_FILES['business_file']['error'] ?? 'No file or invalid type/size'));
        header("Location: alumni_profile.php?error=" . urlencode("Business certificate file upload failed: Invalid file type or size exceeded"));
        exit;
    }
}

if ($can_update || in_array('COR', $rejected_docs)) {

    // OLD FILE CLEANUP — COR (Certificate of Registration)
    $oldFileStmt = $conn->prepare("SELECT file_path FROM alumni_documents WHERE user_id = ? AND document_type = 'COR'");
    $oldFileStmt->bind_param("i", $alumni_id);
    $oldFileStmt->execute();
    $oldResult = $oldFileStmt->get_result();
    if ($old = $oldResult->fetch_assoc()) {
        $oldPath = '../' . $old['file_path'];
        if (file_exists($oldPath)) unlink($oldPath);
    }
    $oldFileStmt->close();

    // File upload handling
    $cor_path = upload_file('cor_file', '../Uploads/cor/', $last_name, 'cor', ['application/pdf']);
    if ($cor_path) {
        $stmt = $conn->prepare("INSERT INTO alumni_documents (user_id, document_type, file_path, document_status, needs_reupload) VALUES (?, 'COR', ?, 'Pending', 0)");
        $stmt->bind_param("is", $alumni_id, $cor_path);
        if (!$stmt->execute()) {
            error_log("COR insert failed for user_id $alumni_id: " . $stmt->error);
            header("Location: alumni_profile.php?error=" . urlencode("COR document insert failed: " . $stmt->error));
            exit;
        }
        $stmt->close();
    } else if (!empty($_FILES['cor_file']['name'])) {
        error_log("COR file upload failed for user_id $alumni_id: " . ($_FILES['cor_file']['error'] ?? 'No file or invalid type/size'));
        header("Location: alumni_profile.php?error=" . urlencode("COR file upload failed: Invalid file type or size exceeded"));
        exit;
    }
}

if ($can_update) {
    $update_stmt = $conn->prepare("UPDATE alumni_profile SET last_profile_update = NOW() WHERE user_id = ?");
    $update_stmt->bind_param("i", $user_id);
    $update_stmt->execute();
    $update_stmt->close();
}

header("Location: alumni_profile.php?success=Profile updated successfully!");
exit;
}

// close DB connection
$conn->close();