<?php
ob_start();
session_start();
include("../connect.php");
include_once $_SERVER['DOCUMENT_ROOT'] . '/Alumni-Employment-Tracking-and-Reminder-System/api/notification/notification_helper.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!isset($_SESSION['user_id'])) {
    // Clean output buffer before redirect
    ob_end_clean();
    header("Location: ../login/login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

// ---- 1. Profile & Permissions ------------------------------------------------
$stmt = $conn->prepare("SELECT last_profile_update, last_name, user_id, photo_path, address_id, employment_status, submission_status 
                        FROM alumni_profile WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

// ---- 2. Profile Permissions --------------------------------------------------------
$is_profile_rejected = !empty($profile) && ($profile['submission_status'] ?? '') === 'Rejected';
$can_update_yearly = empty($profile) || 
                     ($profile && ($profile['last_profile_update'] === null || 
                      strtotime($profile['last_profile_update'] . ' +1 year') <= time()));

$can_update = $can_update_yearly || $is_profile_rejected;

// PERMISSION CHECK - PREVENT UNAUTHORIZED UPDATES
if (!$can_update) {
    header("Location: alumni_profile.php?error=" . urlencode(
        "You can only update once per year unless your submission was rejected."
    ));
    exit;
}

// If profile was rejected, clean up old data before new submission
if ($is_profile_rejected) {
    handle_rejection_cleanup($user_id, $conn);
    // Refresh profile data after cleanup
    $profile = [];
    $existing_address_id = null;
    $last_name = '';
    $current_employment_status = '';
}

if ($is_profile_rejected && !isset($_SESSION['profile_rejected'])) {
    $_SESSION['profile_rejected'] = true;
}

$alumni_id = $user_id;
$last_name = $profile['last_name'] ?? '';
$existing_address_id = $profile['address_id'] ?? null;
$current_employment_status = $profile['employment_status'] ?? '';

// ---- 3. Helper: file upload --------------------------------------------------
function upload_file($field, $dir, $surname, $type, $allowed = ['application/pdf']) {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        if ($_FILES[$field]['error'] === UPLOAD_ERR_INI_SIZE || $_FILES[$field]['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw new Exception("File size too large. Maximum allowed is 2MB.");
        }
        return null;
    }

    $fileType = $_FILES[$field]['type'];
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    $size = $_FILES[$field]['size'];

    $extMap = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'
    ];
    $allowedExt = array_map(fn($t) => $extMap[$t] ?? '', $allowed);

    if (!in_array($fileType, $allowed, true)) {
        throw new Exception("Invalid file type. Allowed: " . implode(', ', array_keys($extMap)));
    }
    
    if (!in_array($ext, $allowedExt, true)) {
        throw new Exception("Invalid file extension. Allowed: " . implode(', ', $allowedExt));
    }
    
    if ($size > 2 * 1024 * 1024) {
        throw new Exception("File size exceeds 2MB limit.");
    }

    if (!is_dir($dir)) {
        if (!mkdir($dir, 0777, true)) {
            throw new Exception("Could not create upload directory.");
        }
    }

    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', $surname) . '_' . $type . '.' . $ext;
    $target = rtrim($dir, '/') . '/' . $name;

    if (!move_uploaded_file($_FILES[$field]['tmp_name'], $target)) {
        throw new Exception("File upload failed. Please try again.");
    }
    
    return str_replace('../', '', $target);
}

// ---- 4. Document handler (DRY) -----------------------------------------------
function handle_document($field, $dir, $surname, $code) {
    global $conn, $user_id;
    
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $new_path = upload_file($field, $dir, $surname, strtolower($code), ['application/pdf']);
    if ($new_path) {
        // Delete old document if exists
        $stmt = $conn->prepare("DELETE FROM alumni_documents WHERE user_id = ? AND document_type = ?");
        $stmt->bind_param("is", $user_id, $code);
        $stmt->execute();
        $stmt->close();
        
        // Insert new document
        $stmt = $conn->prepare("INSERT INTO alumni_documents (user_id, document_type, file_path) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $code, $new_path);
        $stmt->execute();
        $stmt->close();
        return true;
    }
    return false;
}

// ---- 5. REJECTION HANDLER ---------------------------------------------
function handle_rejection_cleanup($user_id, $conn) {
    // Delete all user data except users table
    $tables = ['employment_info', 'education_info', 'alumni_documents'];
    
    foreach ($tables as $table) {
        $stmt = $conn->prepare("DELETE FROM $table WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->close();
    }
    
    // Reset alumni_profile but keep user_id and set submission_status to NULL
    $stmt = $conn->prepare("UPDATE alumni_profile SET 
        first_name = NULL, middle_name = NULL, last_name = NULL, 
        contact_number = NULL, year_graduated = NULL, employment_status = NULL,
        photo_path = NULL, address_id = NULL, last_profile_update = NULL,
        submission_status = NULL, rejection_reason = NULL, rejected_at = NULL, submitted_at = NULL
        WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Delete address record if exists
    $stmt = $conn->prepare("DELETE a FROM address a 
                           INNER JOIN alumni_profile ap ON a.address_id = ap.address_id 
                           WHERE ap.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
}

// ---- 6. POST handling --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // DEBUG: Log everything for troubleshooting
    error_log("=== PROFILE UPDATE START ===");
    error_log("POST Data: " . print_r($_POST, true));
    error_log("FILES Data: " . print_r($_FILES, true));
    error_log("User ID: " . $user_id);
    error_log("Can Update: " . ($can_update ? 'YES' : 'NO'));
    
    $conn->begin_transaction();
    
    try {
        // ---- 6.1 Retrieve & sanitise --------------------------------------------
        $first = htmlspecialchars(trim($_POST['first_name'] ?? ''));
        $middle = htmlspecialchars(trim($_POST['middle_name'] ?? ''));
        $last = htmlspecialchars(trim($_POST['last_name'] ?? ''));
        $contact = htmlspecialchars(trim($_POST['contact_number'] ?? ''));
        $year = htmlspecialchars(trim($_POST['year_graduated'] ?? ''));
        $status = htmlspecialchars(trim($_POST['employment_status'] ?? ''));

        // Address fields - using correct names from frontend
        $region_id = htmlspecialchars(trim($_POST['region_id'] ?? ''));
        $province_id = htmlspecialchars(trim($_POST['province_id'] ?? ''));
        $municipality_id = htmlspecialchars(trim($_POST['municipality_id'] ?? ''));
        $barangay_id = htmlspecialchars(trim($_POST['barangay_id'] ?? ''));

        // Employment fields
        $job_title = htmlspecialchars(trim($_POST['job_title'] ?? ''));
        if ($job_title === 'Other') $job_title = htmlspecialchars(trim($_POST['other_job_title'] ?? ''));

        $company = htmlspecialchars(trim($_POST['company_name'] ?? ''));
        $company_address = htmlspecialchars(trim($_POST['company_address'] ?? ''));
        $salary = htmlspecialchars(trim($_POST['salary_range'] ?? ''));

        $business_type = htmlspecialchars(trim($_POST['business_type'] ?? ''));
        if ($business_type === 'Others (Please specify)') {
            $business_type = 'Others: ' . htmlspecialchars(trim($_POST['business_type_other'] ?? ''));
        }

         // Debug employment status
        error_log("Employment Status: " . $status);
        error_log("Employment Fields - Job Title: " . $job_title);
        error_log("Employment Fields - Company: " . $company);

        // Education fields - including new ones
        $school = htmlspecialchars(trim($_POST['school_name'] ?? ''));
        $degree = htmlspecialchars(trim($_POST['degree_pursued'] ?? ''));
        $start_year = htmlspecialchars(trim($_POST['start_year'] ?? ''));
        $end_year = htmlspecialchars(trim($_POST['end_year'] ?? ''));

        // Validate year format
        if ($can_update && in_array($status, ['Student', 'Employed & Student'])) {
            if (!preg_match('/^\d{4}$/', $start_year) || $start_year < 2000 || $start_year > date('Y')) {
                throw new Exception("Invalid start year format.");
            }
            if (!preg_match('/^\d{4}$/', $end_year) || $end_year < $start_year || $end_year > (date('Y') + 5)) {
                throw new Exception("Invalid end year format.");
            }
        }

        // ---- 6.2 Backend validation (only when full update) --------------------
        if ($can_update) {
            // Required personal fields (middle name is optional per requirement #4)
            $required_personal = [$first, $last, $contact, $year, $status];
            if (in_array('', $required_personal)) {
                throw new Exception("All personal fields are required except middle name.");
            }

            // Address required for all statuses
            if (!$region_id || !$province_id || !$municipality_id || !$barangay_id) {
                throw new Exception("Complete address is required.");
            }

            // Employment-specific validation
            if (in_array($status, ['Employed', 'Employed & Student'])) {
                if (!$job_title) throw new Exception("Job title is required.");
                if (!$company) throw new Exception("Company name is required.");
                if (!$company_address) throw new Exception("Company address is required.");
            }
            
            if ($status === 'Self-Employed') {
                if (!$business_type) throw new Exception("Business type is required.");
                // Clear company fields for self-employed
                $company = '';
                $company_address = '';
            }

            // Education validation
            if (in_array($status, ['Student', 'Employed & Student'])) {
                if (!$school) throw new Exception("School name is required.");
                if (!$degree) throw new Exception("Degree pursued is required.");
                if (!$start_year) throw new Exception("Start year is required.");
                if (!$end_year) throw new Exception("End year is required.");
            }
        }

        // ---- 6.3 Photo – REQUIRED IN ALL CASES (Requirement #1) ---------------
        $photo_path = $profile['photo_path'] ?? null;

        // Check if this is a new submission or re-upload after rejection
        if (empty($_FILES['profile_photo']['name'])) {
            // Only require photo if this is a new profile or re-upload after rejection
            if (empty($profile) || $is_profile_rejected || empty($photo_path)) {
                throw new Exception("Profile photo is required.");
            }
            // Keep existing photo if no new one uploaded and we have an existing one
            $new_photo = $photo_path;
        } else {
            // Process new photo upload
            $new_photo = upload_file('profile_photo', '../uploads/photos/', $last, 'profile', ['image/jpeg','image/png']);
            if (!$new_photo) {
                throw new Exception("Photo upload failed. JPG/PNG only, max 2MB.");
            }

            // Delete old photo if exists and different from new one
            if ($photo_path && $photo_path !== $new_photo && file_exists('../' . $photo_path)) {
                unlink('../' . $photo_path);
            }
            $photo_path = $new_photo;
        }

        // ---- 6.4 Address Handling ---------------------------------------------
        $address_id = $existing_address_id;

        if ($barangay_id) {
            // Validate address hierarchy using the correct field names from your schema
            $valid_region = false;
            $valid_province = false;
            $valid_municipality = false;
            $valid_barangay = false;

            // Check region (use only region_id as per your schema)
            $stmt = $conn->prepare("SELECT 1 FROM table_region WHERE region_id = ?");
            $stmt->bind_param("s", $region_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) $valid_region = true;
            $stmt->close();

            if (!$valid_region) throw new Exception("Invalid region selected");

            // Check province (use only province_id and region_id as per your schema)
            $stmt = $conn->prepare("SELECT 1 FROM table_province WHERE province_id = ? AND region_id = ?");
            $stmt->bind_param("ss", $province_id, $region_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) $valid_province = true;
            $stmt->close();

            if (!$valid_province) throw new Exception("Invalid province selected for the chosen region");

            // Check municipality (use only municipality_id and province_id as per your schema)
            $stmt = $conn->prepare("SELECT 1 FROM table_municipality WHERE municipality_id = ? AND province_id = ?");
            $stmt->bind_param("ss", $municipality_id, $province_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) $valid_municipality = true;
            $stmt->close();

            if (!$valid_municipality) throw new Exception("Invalid municipality selected for the chosen province");

            // Check barangay (use only barangay_id and municipality_id as per your schema)
            $stmt = $conn->prepare("SELECT 1 FROM table_barangay WHERE barangay_id = ? AND municipality_id = ?");
            $stmt->bind_param("ss", $barangay_id, $municipality_id);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) $valid_barangay = true;
            $stmt->close();

            if (!$valid_barangay) throw new Exception("Invalid barangay selected for the chosen municipality");

            // Create/update address
            if ($address_id) {
                $stmt = $conn->prepare("UPDATE address SET barangay_id = ? WHERE address_id = ?");
                $stmt->bind_param("si", $barangay_id, $address_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO address (barangay_id) VALUES (?)");
                $stmt->bind_param("s", $barangay_id);
            }
            $stmt->execute();
            $address_id = $address_id ?: $conn->insert_id;
            $stmt->close();
        }

        // ---- 6.5 Profile INSERT / UPDATE ----------------------------------------
        if ($can_update) {
            if ($profile) {
                $stmt = $conn->prepare("UPDATE alumni_profile SET 
                    first_name=?, middle_name=?, last_name=?, contact_number=?, year_graduated=?,
                    employment_status=?, photo_path=?, last_profile_update=NOW(), address_id=?,
                    submission_status='Pending', submitted_at=NOW()
                    WHERE user_id=?");
                $stmt->bind_param("ssssssssi", $first, $middle, $last, $contact, $year, $status, $photo_path, $address_id, $user_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO alumni_profile 
                    (user_id, first_name, middle_name, last_name, contact_number, year_graduated,
                    employment_status, photo_path, last_profile_update, address_id, submission_status, submitted_at)
                    VALUES (?,?,?,?,?,?,?,?,NOW(),?,'Pending',NOW())");
                $stmt->bind_param("isssssssi", $user_id, $first, $middle, $last, $contact, $year, $status, $photo_path, $address_id);
            }
            $stmt->execute();
            $stmt->close();
        }

        // ---- 6.6 Employment ------------------------------------------------------
        if ($can_update) {
            // Delete existing employment info first (requirement #4)
            $stmt = $conn->prepare("DELETE FROM employment_info WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            error_log("Processing employment for status: " . $status);
            
            // Insert employment info for relevant statuses
            if (in_array($status, ['Employed', 'Self-Employed', 'Employed & Student'])) {
                $job_title_id = null;
                
                // Handle job title for employed statuses
                if (in_array($status, ['Employed', 'Employed & Student']) && !empty($job_title)) {
                    $stmt = $conn->prepare("SELECT job_title_id FROM job_titles WHERE title = ?");
                    $stmt->bind_param("s", $job_title);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $job_title_id = $row['job_title_id'];
                    } else {
                        $ins = $conn->prepare("INSERT INTO job_titles (title) VALUES (?)");
                        $ins->bind_param("s", $job_title);
                        $ins->execute();
                        $job_title_id = $conn->insert_id;
                        $ins->close();
                    }
                    $stmt->close();
                }

                // For Self-Employed, ensure company fields are empty and job_title_id is null
                if ($status === 'Self-Employed') {
                    $job_title_id = null;
                    $company = '';
                    $company_address = '';
                }

                // Ensure salary range is set
                if (empty($salary)) {
                    throw new Exception("Salary range is required.");
                }

                error_log("Inserting employment info - Job Title ID: " . ($job_title_id ?? 'NULL') . 
                         ", Company: '$company', Salary: '$salary', Business Type: '$business_type'");

                // Insert employment info
                $stmt = $conn->prepare("
                    INSERT INTO employment_info 
                    (user_id, job_title_id, company_name, company_address, business_type, salary_range)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "iissss",
                    $user_id,
                    $job_title_id,
                    $company,
                    $company_address,
                    $business_type,
                    $salary
                );
                
                if (!$stmt->execute()) {
                    error_log("Employment insert failed: " . $stmt->error);
                    throw new Exception("Failed to save employment info: " . $stmt->error);
                }
                $stmt->close();
                
                error_log("Employment info inserted successfully for user: " . $user_id);
            } else {
                error_log("Skipping employment insert for status: " . $status);
            }
        }

        // ---- 6.7 Education -------------------------------------------------------
        if ($can_update) {
            // Delete existing education info first (requirement #4)
            $stmt = $conn->prepare("DELETE FROM education_info WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            if (in_array($status, ['Student', 'Employed & Student'])) {
                $stmt = $conn->prepare("INSERT INTO education_info 
                    (user_id, school_name, degree_pursued, start_year, end_year)
                    VALUES (?,?,?,?,?)");
                $stmt->bind_param("issss", $user_id, $school, $degree, $start_year, $end_year);
                $stmt->execute();
                $stmt->close();
            }
        }

        // ---- 6.8 Documents – STATUS-BASED VALIDATION ----------------------------
        $required_docs = [];
        if (in_array($status, ['Employed', 'Employed & Student'])) {
            $required_docs['COE'] = 'coe_file';
        }
        if ($status === 'Self-Employed') {
            $required_docs['B_CERT'] = 'business_file';
        }
        if (in_array($status, ['Student', 'Employed & Student'])) {
            $required_docs['COR'] = 'cor_file';
        }

        error_log("Required documents for status {$status}: " . print_r($required_docs, true));
        error_log("FILES data for documents: " . print_r($_FILES, true));

        // Process required documents
        foreach ($required_docs as $code => $field) {
            // Check if file was uploaded and has no errors
            if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
                $doc_name = $code === 'COE' ? 'Certificate of Employment' : 
                        ($code === 'B_CERT' ? 'Business Certificate' : 'Certificate of Registration');
                throw new Exception("{$doc_name} is required for your employment status ({$status}).");
            }
            
            // Check if file has a name (was actually selected)
            if (empty($_FILES[$field]['name'])) {
                $doc_name = $code === 'COE' ? 'Certificate of Employment' : 
                        ($code === 'B_CERT' ? 'Business Certificate' : 'Certificate of Registration');
                throw new Exception("{$doc_name} is required for your employment status ({$status}). Please select a file.");
            }
            
            $dir = $code === 'COE' ? '../uploads/coe/' : 
                ($code === 'B_CERT' ? '../uploads/business/' : '../uploads/cor/');
            
            if (!handle_document($field, $dir, $last, $code)) {
                $doc_name = $code === 'COE' ? 'Certificate of Employment' : 
                        ($code === 'B_CERT' ? 'Business Certificate' : 'Certificate of Registration');
                throw new Exception("{$doc_name} upload failed. PDF only, max 2MB.");
            }
            
            error_log("Successfully processed document: {$code}");
        }

                $conn->commit();

        // ---- 7. SEND NOTIFICATIONS -----------------------------------------
        try {
            // Initialize notification helper with database connection
            $notifHelper = new NotificationHelper($conn);
            
            // Get alumni details
            $alumniDetails = $notifHelper->getAlumniDetails($user_id);
            
            if ($alumniDetails) {
                $alumni_email = $alumniDetails['email'];
                $alumni_fullname = trim(($alumniDetails['first_name'] ?? $first) . ' ' . ($alumniDetails['last_name'] ?? $last));
                
                // Prepare parameters
                $parameters = [
                    "alumni_name" => $alumni_fullname,
                    "graduation_year" => $alumniDetails['year_graduated'] ?? $year,
                    "original_rejection_date" => $alumniDetails['rejected_at'] ?? null,
                    "submission_date" => $alumniDetails['submitted_at'] ?? date('Y-m-d H:i:s'),
                    "current_position" => $alumniDetails['job_title'] ?? $job_title ?? 'N/A',
                    "current_company" => $alumniDetails['company_name'] ?? $company ?? 'N/A',
                    "alumni_email" => $alumni_email,
                    "previous_rejection_reason" => $alumniDetails['rejection_reason'] ?? null,
                    "admin_review_link" => "#",
                    "employment_status" => $alumniDetails['employment_status'] ?? $status,
                    "name" => $alumni_fullname,
                    "rejection_reason" => $alumniDetails['rejection_reason'] ?? null,
                    "resubmission_link" => "#"
                ];

                // Get admin emails
                $admin_emails = $notifHelper->getAdminEmails();

                // Send notifications based on scenario
                if ($is_profile_rejected) {
                    // Alumni resubmits after rejection → notify admin(s)
                    foreach ($admin_emails as $admin_email) {
                        $notifHelper->sendNotification('alum_resubmit_admin_notif', 'alumni_resubmit_after_rejection', $admin_email, $parameters);
                    }
                } elseif ($can_update_yearly) {
                    // Annual update → notify alumni + admin(s)
                    if (!empty($alumni_email)) {
                        $notifHelper->sendNotification('template_one', 'alumni_annual_profile_update', $alumni_email, $parameters);
                    }
                    foreach ($admin_emails as $admin_email) {
                        $notifHelper->sendNotification('alum_submit_update_admin_notif', 'alumni_annual_update_admin_review', $admin_email, $parameters);
                    }
                } else {
                    // Normal submission → notify admin(s)
                    foreach ($admin_emails as $admin_email) {
                        $notifHelper->sendNotification('template_admin_notif', 'alumni_new_submission', $admin_email, $parameters);
                    }
                }
            }

        } catch (Exception $e) {
            // Log the notification failure but DO NOT stop user flow
            error_log("Notification failed: " . $e->getMessage());
        }

        // finally redirect after commit and notification attempts
        header("Location: alumni_profile.php?success=Profile updated successfully!");
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        header("Location: alumni_profile.php?error=" . urlencode($e->getMessage()));
        exit;
    }
}

$conn->close();
// Clean and flush output buffer if we haven't redirected yet
ob_end_flush();
?>
