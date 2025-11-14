<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit();
}

include("../connect.php");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$user_id = $_SESSION['user_id'];
$page_title = "Profile Management";
$active_page = "profile";

// Fetch profile data
$stmt = $conn->prepare("
    SELECT ap.*, u.email, 
           tb.barangay_name, tm.municipality_name, tp.province_name, tr.region_name,
           tr.region_id, tp.province_id, tm.municipality_id, tb.barangay_id
    FROM alumni_profile ap
    LEFT JOIN users u ON ap.user_id = u.user_id
    LEFT JOIN address a ON ap.address_id = a.address_id
    LEFT JOIN table_barangay tb ON a.barangay_id = tb.barangay_id
    LEFT JOIN table_municipality tm ON tb.municipality_id = tm.municipality_id
    LEFT JOIN table_province tp ON tm.province_id = tp.province_id
    LEFT JOIN table_region tr ON tp.region_id = tr.region_id
    WHERE ap.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc() ?: [];
$stmt->close();

// Fetch employment info
$stmt = $conn->prepare("SELECT ei.*, jt.title AS job_title, ei.business_type FROM employment_info ei LEFT JOIN job_titles jt ON ei.job_title_id = jt.job_title_id WHERE ei.user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$employment = $result->fetch_assoc() ?: [];
$stmt->close();

// Process business_type for display
$business_type = $employment['business_type'] ?? '';
$business_type_other = '';
if (strpos($business_type, 'Others: ') === 0) {
    $business_type_other = substr($business_type, 8);
    $business_type = 'Others (Please specify)';
}

// Fetch education info
$stmt = $conn->prepare("SELECT * FROM education_info WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$education = $result->fetch_assoc() ?: [];
$stmt->close();

// Fetch documents
$stmt = $conn->prepare("SELECT document_type, file_path FROM alumni_documents WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$docs = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Update permissions logic
$is_profile_rejected = !empty($profile) && ($profile['submission_status'] ?? '') === 'Rejected';
$can_update_yearly = empty($profile) || 
                     ($profile && ($profile['last_profile_update'] === null || 
                      strtotime($profile['last_profile_update'] . ' +1 year') <= time()));
$can_reupload = $is_profile_rejected;
$can_update = empty($profile) || $can_update_yearly || $can_reupload;

// Debug permission logic
error_log("=== PROFILE PERMISSIONS DEBUG ===");
error_log("User ID: " . $user_id);
error_log("Profile exists: " . (!empty($profile) ? 'YES' : 'NO'));
error_log("Submission Status: " . ($profile['submission_status'] ?? 'NONE'));
error_log("Last Profile Update: " . ($profile['last_profile_update'] ?? 'NEVER'));
error_log("Is Profile Rejected: " . ($is_profile_rejected ? 'YES' : 'NO'));
error_log("Can Update Yearly: " . ($can_update_yearly ? 'YES' : 'NO'));
error_log("Can Reupload: " . ($can_reupload ? 'YES' : 'NO'));
error_log("Can Update: " . ($can_update ? 'YES' : 'NO'));

// Auto-modal opening only when coming from rejection
$auto_open_modal = isset($_SESSION['profile_rejected']) && $_SESSION['profile_rejected'];
if ($auto_open_modal) {
    unset($_SESSION['profile_rejected']); // Clear the flag after use
}

$full_name = 'Alumni';
if (!empty($profile)) {
    $full_name = trim(
        ($profile['first_name'] ?? '') . ' ' .
        ($profile['middle_name'] ?? '') . ' ' .
        ($profile['last_name'] ?? '')
    );
    if (empty($full_name)) {
        $full_name = 'Alumni';
    }
}

ob_start();
?>

<?php if (isset($_GET['success'])): ?>
    <div class="bg-green-100 p-4 rounded mb-4"><?php echo htmlspecialchars($_GET['success']); ?></div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
    <div class="bg-red-100 p-4 rounded mb-4"><?php echo htmlspecialchars($_GET['error']); ?></div>
<?php endif; ?>

<div class="space-y-6 mt-4 mb-6">
    
<!-- Update Profile Box -->
<div id="updateProfileBtn" class="bg-white p-6 rounded-xl shadow-lg flex flex-col justify-between hover:shadow-xl transition duration-200 border-t-4 <?php echo $can_update ? 'border-green-500 cursor-pointer' : 'border-yellow-500 cursor-not-allowed'; ?>">
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center space-x-3">
            <?php if (!$can_update): ?>
               
            <?php endif; ?>
            <h3 class="text-lg font-semibold <?php echo $can_update ? 'text-gray-600' : 'text-yellow-800'; ?>">
                <?php echo $can_update ? 'Update Profile' : 'Profile Update Not Available'; ?>
            </h3>
        </div>
        <i class="fas <?php echo $can_update ? 'fa-user-edit text-green-500' : 'fa-info-circle text-yellow-500'; ?> text-xl"></i>
    </div>
    <p class="text-sm <?php echo $can_update ? 'text-gray-500' : 'text-yellow-700'; ?>">
        <?php 
        if ($can_update) {
            echo 'Click to edit your personal, employment, and educational details.';
        } else {
            if (!empty($profile) && ($profile['submission_status'] ?? '') === 'Approved') {
                echo 'Your profile has been approved. You can update again after one year.';
            } else {
                echo 'Profile update is not available at this time.';
            }
        }
        ?>
    </p>
</div>

    <?php if (!empty($profile) && ($profile['submission_status'] ?? '') !== 'Rejected'): ?>
        <!-- Only show profile cards if not rejected -->
        
      <!-- Personal Information Card -->
<div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-blue-500">
    <div class="flex items-center space-x-3 mb-4 pb-2 border-b border-gray-100">
        <h3 class="text-xl font-bold text-gray-800">Personal Information</h3>
    </div>
    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="flex flex-col">
            <dt class="font-medium text-gray-500 text-sm mb-1">Full Name</dt>
            <dd class="font-semibold text-gray-700"><?php echo htmlspecialchars($full_name); ?></dd>
        </div>
        <div class="flex flex-col">
            <dt class="font-medium text-gray-500 text-sm mb-1">Email</dt>
            <dd class="font-semibold text-gray-700"><?php echo htmlspecialchars($profile['email'] ?? 'N/A'); ?></dd>
        </div>
        <div class="flex flex-col">
            <dt class="font-medium text-gray-500 text-sm mb-1">Contact Number</dt>
            <dd class="font-semibold text-gray-700"><?php echo htmlspecialchars($profile['contact_number'] ?? 'N/A'); ?></dd>
        </div>
        <div class="flex flex-col">
            <dt class="font-medium text-gray-500 text-sm mb-1">Year Graduated</dt>
            <dd class="font-semibold text-gray-700"><?php echo htmlspecialchars($profile['year_graduated'] ?? 'N/A'); ?></dd>
        </div>
    </dl>
</div>
<!-- Address Card -->
<div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-green-500">
    <div class="flex items-center space-x-3 mb-4 pb-2 border-b border-gray-100">
        <h3 class="text-xl font-bold text-gray-800">Address</h3>
    </div>
    <dl class="grid grid-cols-1 gap-4">
        <div class="flex flex-col">
            <dt class="font-medium text-gray-500 text-sm mb-1">Complete Address</dt>
            <dd class="font-semibold text-gray-700 leading-relaxed">
                <?php 
                $address_parts = [
                    $profile['barangay_name'] ?? '',
                    $profile['municipality_name'] ?? '',
                    $profile['province_name'] ?? '',
                    $profile['region_name'] ?? ''
                ];
                $address_parts = array_filter($address_parts);
                echo htmlspecialchars(implode(' , ', $address_parts));
                ?>
            </dd>
        </div>
    </dl>
</div>
<!-- Employment/Academic Details Card -->
<div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-purple-500">
    <div class="flex items-center space-x-3 mb-4 pb-2 border-b border-gray-100">
        <h3 class="text-xl font-bold text-gray-800">Employment/Academic Details</h3>
    </div>
    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="flex flex-col">
            <dt class="font-medium text-gray-500 text-sm mb-1">Employment Status</dt>
            <dd class="font-semibold text-gray-700"><?php echo htmlspecialchars($profile['employment_status'] ?? 'Not Set'); ?></dd>
        </div>
        <?php if (in_array($profile['employment_status'] ?? '', ['Employed', 'Self-Employed', 'Employed & Student'])): ?>
            <?php if (($profile['employment_status'] ?? '') !== 'Self-Employed'): ?>
                <div class="flex flex-col">
                    <dt class="font-medium text-gray-500 text-sm mb-1">Job Title</dt>
                    <dd class="font-semibold text-gray-700"><?php echo htmlspecialchars($employment['job_title'] ?? 'N/A'); ?></dd>
                </div>
                <div class="flex flex-col">
                    <dt class="font-medium text-gray-500 text-sm mb-1">Company Name</dt>
                    <dd class="font-semibold text-gray-700"><?php echo htmlspecialchars($employment['company_name'] ?? 'N/A'); ?></dd>
                </div>
                <div class="flex flex-col md:col-span-2">
                    <dt class="font-medium text-gray-500 text-sm mb-1">Company Address</dt>
                    <dd class="font-semibold text-gray-700"><?php echo htmlspecialchars($employment['company_address'] ?? 'N/A'); ?></dd>
                </div>
            <?php endif; ?>
            <?php if (($profile['employment_status'] ?? '') === 'Self-Employed'): ?>
                <div class="flex flex-col">
                    <dt class="font-medium text-gray-500 text-sm mb-1">Business Type</dt>
                    <dd class="font-semibold text-gray-700"><?php 
                        $display_business_type = $employment['business_type'] ?? 'N/A';
                        if (strpos($display_business_type, 'Others: ') === 0) {
                            $display_business_type = 'Others: ' . substr($display_business_type, 8);
                        }
                        echo htmlspecialchars($display_business_type); 
                    ?></dd>
                </div>
            <?php endif; ?>
            <div class="flex flex-col">
                <dt class="font-medium text-gray-500 text-sm mb-1"><?php echo (($profile['employment_status'] ?? '') === 'Self-Employed') ? 'Monthly Income Range' : 'Salary Range'; ?></dt>
                <dd class="font-semibold text-gray-700"><?php echo htmlspecialchars($employment['salary_range'] ?? 'N/A'); ?></dd>
            </div>
        <?php endif; ?>
        <?php if (in_array($profile['employment_status'] ?? '', ['Student', 'Employed & Student'])): ?>
            <div class="flex flex-col">
                <dt class="font-medium text-gray-500 text-sm mb-1">School Name</dt>
                <dd class="font-semibold text-gray-700"><?php echo htmlspecialchars($education['school_name'] ?? 'N/A'); ?></dd>
            </div>
            <div class="flex flex-col">
                <dt class="font-medium text-gray-500 text-sm mb-1">Degree Pursued</dt>
                <dd class="font-semibold text-gray-700"><?php echo htmlspecialchars($education['degree_pursued'] ?? 'N/A'); ?></dd>
            </div>
        <?php endif; ?>
        <?php if (($profile['employment_status'] ?? '') === 'Unemployed'): ?>
            <div class="flex flex-col md:col-span-2">
                <dd class="font-semibold text-gray-700">Currently Unemployed</dd>
            </div>
        <?php endif; ?>
    </dl>
</div>

   <!-- Documents Card -->
<div class="bg-white p-6 rounded-xl shadow-lg border-l-4 border-orange-500">
    <div class="flex items-center space-x-3 mb-4 pb-2 border-b border-gray-100">
        <h3 class="text-xl font-bold text-gray-800">Documents</h3>
    </div>
    <?php if (empty($docs)): ?>
        <p class="text-sm text-gray-500">No documents uploaded.</p>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <?php 
            foreach ($docs as $doc): 
                $doc_type_name = $doc['document_type'] === 'COE' ? 'Certificate of Employment' : 
                            ($doc['document_type'] === 'B_CERT' ? 'Business Certificate' : 
                            ($doc['document_type'] === 'COR' ? 'Certificate of Registration' : $doc['document_type']));
            ?>
                <div class="flex flex-col">
                    <span class="font-medium text-gray-500 text-sm mb-1"><?php echo htmlspecialchars($doc_type_name); ?></span>
                    <a href="../<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="text-blue-600 hover:underline font-semibold">View Document</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

    <?php endif; ?>
    <?php if (!empty($profile) && ($profile['submission_status'] ?? '') === 'Rejected'): ?>
        <div class="bg-yellow-100 p-6 rounded-xl shadow-lg border-l-4 border-yellow-600">
            <div class="flex items-center space-x-3">
                <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
                <div>
                    <h3 class="text-lg font-semibold text-yellow-800">Profile Submission Rejected</h3>
                    <p class="text-yellow-700">Your previous submission was rejected. Please update your profile and resubmit using the "Update Profile" button above.</p>
                    <?php if (!empty($profile['rejection_reason'])): ?>
                        <p class="text-yellow-700 mt-2"><strong>Reason:</strong> <?php echo htmlspecialchars($profile['rejection_reason']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Profile Update Modal (Hidden by default) -->
<div id="profileUpdateModal" class="hidden fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50 transition-opacity duration-300">
    <div class="bg-white p-8 rounded-xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-800">Update Your Profile</h3>
            <button id="closeProfileModal" class="text-gray-600 hover:text-red-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        
 <!-- Profile Form -->
        <form id="alumniProfileForm" class="space-y-6" action="update_profile.php" method="post" enctype="multipart/form-data">
            <!-- Profile Picture + Personal Details -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="bg-white p-6 rounded-xl shadow-lg flex flex-col items-center">
                    <div class="w-32 h-32 rounded-full overflow-hidden mb-4 border-4 border-gray-200">
                        <img id="profilePreview" src="<?php echo !empty($profile['photo_path']) ? '../' . htmlspecialchars($profile['photo_path']) : 'https://placehold.co/128x128/eeeeee/333333?text=Profile'; ?>" alt="Profile Picture" class="w-full h-full object-cover">
                    </div>
                    <button type="button" id="uploadPictureBtn" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-150 shadow-md">
                        Upload New Picture
                    </button>
                    <input type="file" id="profilePictureInput" name="profile_photo" accept="image/jpeg,image/png" class="hidden">
                </div>

                <!-- Personal Information -->
                <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-lg">
                    <h3 class="text-lg font-semibold text-gray-600 mb-4">Personal Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">First Name
                                <input type="text" name="first_name" autocomplete="given-name" value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" class="w-full border rounded-lg p-2" required <?php echo $can_update ? '' : 'disabled'; ?>>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Middle Name
                                <input type="text" name="middle_name" autocomplete="additional-name" value="<?php echo htmlspecialchars($profile['middle_name'] ?? ''); ?>" class="w-full border rounded-lg p-2" <?php echo $can_update ? '' : 'disabled'; ?>>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Last Name
                                <input type="text" name="last_name" autocomplete="family-name" value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" class="w-full border rounded-lg p-2" required <?php echo $can_update ? '' : 'disabled'; ?>>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Contact Number
                                <input type="tel" name="contact_number" autocomplete="tel" value="<?php echo htmlspecialchars($profile['contact_number'] ?? ''); ?>" class="w-full border rounded-lg p-2" required pattern="[0-9]{10,11}" title="Contact number must be 11 digits" <?php echo $can_update ? '' : 'disabled'; ?>>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Year Graduated
                                <select name="year_graduated" class="w-full border rounded-lg p-2" required <?php echo $can_update ? '' : 'disabled'; ?>>
                                    <option value="">Select Year</option>
                                    <?php
                                    $currentYear = date('Y');
                                    for ($y = $currentYear; $y >= 2000; $y--) {
                                        echo "<option value=\"$y\" " . (($profile['year_graduated'] ?? '') == $y ? 'selected' : '') . ">$y</option>";
                                    }
                                    ?>
                                </select>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Employment Status
                                <select id="employmentStatusSelect" name="employment_status" class="w-full border rounded-lg p-2" required <?php echo $can_update ? '' : 'disabled'; ?>>
                                    <option value="">Select Status</option>
                                    <option value="Employed" <?php echo ($profile['employment_status'] ?? '') === 'Employed' ? 'selected' : ''; ?>>Employed</option>
                                    <option value="Self-Employed" <?php echo ($profile['employment_status'] ?? '') === 'Self-Employed' ? 'selected' : ''; ?>>Self-Employed</option>
                                    <option value="Unemployed" <?php echo ($profile['employment_status'] ?? '') === 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                                    <option value="Student" <?php echo ($profile['employment_status'] ?? '') === 'Student' ? 'selected' : ''; ?>>Student</option>
                                    <option value="Employed & Student" <?php echo ($profile['employment_status'] ?? '') === 'Employed & Student' ? 'selected' : ''; ?>>Employed & Student</option>
                                </select>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Address Section -->
            <?php if ($can_update): ?>
                <div class="bg-white p-6 rounded-xl shadow-lg">
                    <h3 class="text-lg font-semibold text-gray-600 mb-4">Address</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Region
                                <select id="regionSelect" name="region_id" class="w-full border rounded-lg p-2" <?php if (!$can_update) echo 'disabled'; ?>>
                                    <option value="">Select Region</option>
                                </select>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Province
                                <select id="provinceSelect" name="province_id" class="w-full border rounded-lg p-2" <?php if (!$can_update) echo 'disabled'; ?>>
                                    <option value="">Select Province</option>
                                </select>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Municipality
                                <select id="municipalitySelect" name="municipality_id" class="w-full border rounded-lg p-2" <?php if (!$can_update) echo 'disabled'; ?>>
                                    <option value="">Select Municipality</option>
                                </select>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Barangay
                                <select id="barangaySelect" name="barangay_id" class="w-full border rounded-lg p-2" <?php if (!$can_update) echo 'disabled'; ?>>
                                    <option value="">Select Barangay</option>
                                </select>

                            </label>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Employment Details Section -->
            <?php if ($can_update): ?>
                <div id="employmentDetailsSection" class="hidden bg-white p-6 rounded-xl shadow-lg">
                    <h3 class="text-lg font-medium text-gray-600 mb-4">Employment Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div id="jobTitleField" class="hidden">
                            <label class="block text-sm font-medium text-gray-700">Job Title
                                <select id="jobTitleSelect" name="job_title" class="w-full border rounded-lg p-2" autocomplete="organization-title">
                                    <option value="">Select Job Title</option>
                                    <?php
                                    $stmt_titles = $conn->prepare("SELECT title FROM job_titles ORDER BY title ASC");
                                    $stmt_titles->execute();
                                    $result_titles = $stmt_titles->get_result();
                                    $existing_title = $employment['job_title'] ?? '';
                                    $is_other = true;
                                    while ($row_title = $result_titles->fetch_assoc()) {
                                        $title = $row_title['title'];
                                        $selected = ($existing_title === $title) ? 'selected' : '';
                                        if ($selected) $is_other = false;
                                        echo '<option value="' . htmlspecialchars($title) . '" ' . $selected . '>' . htmlspecialchars($title) . '</option>';
                                    }
                                    $stmt_titles->close();
                                    ?>
                                    <option value="Other" <?php if ($is_other && $existing_title) echo 'selected'; ?>>Other (Please specify)</option>
                                </select>
                            </label>
                            <div id="otherJobTitleDiv" class="mt-2" style="display: <?php echo ($is_other && $existing_title) ? 'block' : 'none'; ?>;">
                                <input type="text" id="otherJobTitleInput" name="other_job_title" placeholder="Enter custom job title" value="<?php echo ($is_other && $existing_title) ? htmlspecialchars($existing_title) : ''; ?>" class="w-full border rounded-lg p-2">
                            </div>
                        </div>
                        <div id="companyField" class="hidden">
                            <label class="block text-sm font-medium text-gray-700">Company Name
                                <input type="text" name="company_name" value="<?php echo htmlspecialchars($employment['company_name'] ?? ''); ?>" class="w-full border rounded-lg p-2" autocomplete="organization">
                            </label>
                        </div>
                        <div id="companyAddressField" class="hidden">
                            <label class="block text-sm font-medium text-gray-700">Company Address
                                <input type="text" name="company_address" value="<?php echo htmlspecialchars($employment['company_address'] ?? ''); ?>" class="w-full border rounded-lg p-2" autocomplete="street-address">
                            </label>
                        </div>
                        <div id="businessTypeField" class="hidden">
                            <label class="block text-sm font-medium text-gray-700">Business Type
                                <select id="businessTypeSelect" name="business_type" class="w-full border rounded-lg p-2">
                                    <option value="">Select Business Type</option>
                                    <option value="Food Service / Catering" <?php echo $business_type === 'Food Service / Catering' ? 'selected' : ''; ?>>Food Service / Catering</option>
                                    <option value="Retail / Online Selling" <?php echo $business_type === 'Retail / Online Selling' ? 'selected' : ''; ?>>Retail / Online Selling</option>
                                    <option value="Freelancer" <?php echo $business_type === 'Freelancer' ? 'selected' : ''; ?>>Freelancer</option>
                                    <option value="Marketing / Advertising" <?php echo $business_type === 'Marketing / Advertising' ? 'selected' : ''; ?>>Marketing / Advertising</option>
                                    <option value="Education / Tutoring" <?php echo $business_type === 'Education / Tutoring' ? 'selected' : ''; ?>>Education / Tutoring</option>
                                    <option value="Construction / Carpentry / Electrical" <?php echo $business_type === 'Construction / Carpentry / Electrical' ? 'selected' : ''; ?>>Construction / Carpentry / Electrical</option>
                                    <option value="Delivery Services" <?php echo $business_type === 'Delivery Services' ? 'selected' : ''; ?>>Delivery Services</option>
                                    <option value="Event Planning / Photography" <?php echo $business_type === 'Event Planning / Photography' ? 'selected' : ''; ?>>Event Planning / Photography</option>
                                    <option value="Real Estate / Property Leasing" <?php echo $business_type === 'Real Estate / Property Leasing' ? 'selected' : ''; ?>>Real Estate / Property Leasing</option>
                                    <option value="Others (Please specify)" <?php echo $business_type === 'Others (Please specify)' ? 'selected' : ''; ?>>Others (Please specify)</option>
                                </select>
                            </label>
                            <div id="businessTypeOtherDiv" class="mt-2" style="display: <?php echo ($business_type === 'Others (Please specify)') ? 'block' : 'none'; ?>;">
                                <input type="text" id="businessTypeOtherInput" name="business_type_other" value="<?php echo htmlspecialchars($business_type_other); ?>" class="w-full border rounded-lg p-2" placeholder="Specify Business Type">
                            </div>
                        </div>
                        <div id="salaryField">
                            <label class="block text-sm font-medium text-gray-700">Salary Range
                                <select name="salary_range" class="w-full border rounded-lg p-2">
                                    <option value="">Select Salary Range</option>
                                    <option value="Below ₱10,000" <?php echo ($employment['salary_range'] ?? '') === 'Below ₱10,000' ? 'selected' : ''; ?>>Below ₱10,000</option>
                                    <option value="₱10,000–₱20,000" <?php echo ($employment['salary_range'] ?? '') === '₱10,000–₱20,000' ? 'selected' : ''; ?>>₱10,000–₱20,000</option>
                                    <option value="₱20,000–₱30,000" <?php echo ($employment['salary_range'] ?? '') === '₱20,000–₱30,000' ? 'selected' : ''; ?>>₱20,000–₱30,000</option>
                                    <option value="₱30,000–₱40,000" <?php echo ($employment['salary_range'] ?? '') === '₱30,000–₱40,000' ? 'selected' : ''; ?>>₱30,000–₱40,000</option>
                                    <option value="₱40,000–₱50,000" <?php echo ($employment['salary_range'] ?? '') === '₱40,000–₱50,000' ? 'selected' : ''; ?>>₱40,000–₱50,000</option>
                                    <option value="Above ₱50,000" <?php echo ($employment['salary_range'] ?? '') === 'Above ₱50,000' ? 'selected' : ''; ?>>Above ₱50,000</option>
                                </select>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Unemployed Section -->
                <div id="unemployedSection" class="hidden bg-white p-6 rounded-xl shadow-lg">
                    <p>You are currently marked as unemployed.</p>
                </div>

                <!-- Student Details Section -->
                <div id="studentDetailsSection" class="hidden bg-white p-6 rounded-xl shadow-lg">
                    <h3 class="text-lg font-medium text-gray-600 mb-4">Student Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">School Name
                                <input type="text" name="school_name" value="<?php echo htmlspecialchars($education['school_name'] ?? ''); ?>" class="w-full border rounded-lg p-2" autocomplete="organization">
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Degree Pursued
                                <input type="text" name="degree_pursued" value="<?php echo htmlspecialchars($education['degree_pursued'] ?? ''); ?>" class="w-full border rounded-lg p-2" autocomplete="off">
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Start Year
                                <select name="start_year" class="w-full border rounded-lg p-2">
                                    <option value="">Select Start Year</option>
                                    <?php
                                    $currentYear = date('Y');
                                    for ($y = $currentYear; $y >= 2000; $y--) {
                                        $selected = ($education['start_year'] ?? '') == $y ? 'selected' : '';
                                        echo "<option value=\"$y\" $selected>$y</option>";
                                    }
                                    ?>
                                </select>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">End Year (Expected)
                                <select name="end_year" class="w-full border rounded-lg p-2">
                                    <option value="">Select End Year</option>
                                    <?php
                                    $currentYear = date('Y');
                                    for ($y = $currentYear + 5; $y >= 2000; $y--) {
                                        $selected = ($education['end_year'] ?? '') == $y ? 'selected' : '';
                                        echo "<option value=\"$y\" $selected>$y</option>";
                                    }
                                    ?>
                                </select>
                            </label>
                        </div>
                    </div>
                </div>


                <!-- Supporting Documents Section -->
                <div id="supportingDocumentsSection" class="hidden bg-white p-6 rounded-xl shadow-lg">
                    <h3 class="text-lg font-medium text-gray-600 mb-4">Supporting Documents</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- COE Field -->
                        <div id="coeField" class="hidden">
                            <label class="block text-sm font-medium text-gray-700">
                                Certificate of Employment (COE)
                                <?php if ($can_update): ?><span class="text-red-500">*</span><?php endif; ?>
                                <input type="file" name="coe_file" accept="application/pdf" class="w-full border rounded-lg p-2">
                            </label>
                        </div>

                        <!-- Business Certificate Field -->
                        <div id="businessCertField" class="hidden">
                            <label class="block text-sm font-medium text-gray-700">
                                Business Certificate
                                <?php if ($can_update): ?><span class="text-red-500">*</span><?php endif; ?>
                                <input type="file" name="business_file" accept="application/pdf" class="w-full border rounded-lg p-2">
                            </label>
                        </div>

                        <!-- COR Field -->
                        <div id="corField" class="hidden">
                            <label class="block text-sm font-medium text-gray-700">
                                Certificate of Registration (COR)
                                <?php if ($can_update): ?><span class="text-red-500">*</span><?php endif; ?>
                                <input type="file" name="cor_file" accept="application/pdf" class="w-full border rounded-lg p-2">
                            </label>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Submit Button - ONLY SHOW WHEN CAN UPDATE -->
            <div class="flex justify-end">
                <?php if ($can_update): ?>
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-150 shadow-md">Submit</button>
                <?php else: ?>
                    <button type="button" disabled class="bg-gray-400 text-white px-4 py-2 rounded-lg cursor-not-allowed opacity-60">Submit (Not Available)</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

    <?php if ($auto_open_modal): ?>
    // Auto-open modal only when specifically triggered by rejection
    const modal = document.getElementById('profileUpdateModal');
    if (modal) {
        // Small delay to ensure page is fully loaded
        setTimeout(() => {
            modal.classList.remove('hidden');
            modal.classList.add('show', 'flex');
            loadAddressData();
        }, 100);
    }
    <?php endif; ?>

    // Modal and form elements
    const updateProfileBtn = document.getElementById('updateProfileBtn');
    const updateProfileModal = document.getElementById('profileUpdateModal');
    const closeModalBtn = document.getElementById('closeProfileModal');
    const employmentStatusSelect = document.getElementById('employmentStatusSelect');
    const employmentDetailsSection = document.getElementById('employmentDetailsSection');
    const jobTitleField = document.getElementById('jobTitleField');
    const jobTitleSelect = document.getElementById('jobTitleSelect');
    const otherJobTitleDiv = document.getElementById('otherJobTitleDiv');
    const companyField = document.getElementById('companyField');
    const businessTypeField = document.getElementById('businessTypeField');
    const businessTypeSelect = document.getElementById('businessTypeSelect');
    const businessTypeOtherDiv = document.getElementById('businessTypeOtherDiv');
    const unemployedSection = document.getElementById('unemployedSection');
    const studentDetailsSection = document.getElementById('studentDetailsSection');
    const coeField = document.getElementById('coeField');
    const businessCertField = document.getElementById('businessCertField');
    const corField = document.getElementById('corField');
    const supportingDocumentsSection = document.getElementById('supportingDocumentsSection');
    const regionSelect = document.getElementById('regionSelect');
    const provinceSelect = document.getElementById('provinceSelect');
    const municipalitySelect = document.getElementById('municipalitySelect');
    const barangaySelect = document.getElementById('barangaySelect');
    const companyAddressField = document.getElementById('companyAddressField');
    const salaryField = document.getElementById('salaryField');

    // Track loading state
    let isAddressLoading = false;
    let addressDataLoaded = false;

    // Modal toggle - ONLY if user can update
    if (updateProfileBtn) {
        const canUpdate = <?php echo $can_update ? 'true' : 'false'; ?>;
        
            if (canUpdate) {
                updateProfileBtn.addEventListener('click', () => {
                    if (updateProfileModal) {
                        updateProfileModal.classList.remove('hidden');
                        updateProfileModal.classList.add('show', 'flex');
                        loadAddressData(); // Always load
                    }
                });
            } else {
            // Make it visually clear the button is disabled
            updateProfileBtn.style.cursor = 'not-allowed';
            updateProfileBtn.style.opacity = '0.6';
            
            updateProfileBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                if (!empty(<?php echo !empty($profile) ? 'true' : 'false'; ?>)) {
                    const status = '<?php echo $profile['submission_status'] ?? ''; ?>';
                    if (status === 'Approved') {
                        alert('Your profile has been approved. You can update again after one year from your last update.');
                    } else if (status === 'Pending') {
                        alert('Your profile is currently under review. Please wait for administrator approval.');
                    } else {
                        alert('Profile update is not available at this time.');
                    }
                } else {
                    alert('Profile update is not available at this time.');
                }
            });
        }
    }

    if (closeModalBtn) {
        closeModalBtn.addEventListener('click', () => {
            if (updateProfileModal) {
                updateProfileModal.classList.add('hidden');
                updateProfileModal.classList.remove('show', 'flex');
            }
        });
    }

    if (updateProfileModal) {
        updateProfileModal.addEventListener('click', (e) => {
            if (e.target === updateProfileModal) {
                updateProfileModal.classList.add('hidden');
                updateProfileModal.classList.remove('show', 'flex');
            }
        });
    }

    // Profile picture upload and preview
    const uploadBtn = document.getElementById("uploadPictureBtn");
    const fileInput = document.getElementById("profilePictureInput");
    const previewImg = document.getElementById("profilePreview");

    if (uploadBtn && fileInput) {
        uploadBtn.addEventListener("click", () => {
            fileInput.click();
        });
    }

    if (fileInput && previewImg) {
        fileInput.addEventListener("change", function () {
            const file = this.files[0];
            if (!file) return;

            // Validate type
            const validTypes = ["image/jpeg", "image/png"];
            if (!validTypes.includes(file.type)) {
                alert("Only JPG and PNG files are allowed.");
                this.value = "";
                return;
            }

            // Validate size (≤ 2MB)
            if (file.size > 2 * 1024 * 1024) {
                alert("File size exceeds 2MB.");
                this.value = "";
                return;
            }

            // Live preview
            const reader = new FileReader();
            reader.onload = e => {
                previewImg.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    // Job title toggle for "Other"
    if (jobTitleSelect && otherJobTitleDiv) {
        jobTitleSelect.addEventListener('change', () => {
            otherJobTitleDiv.style.display = jobTitleSelect.value === 'Other' ? 'block' : 'none';
        });
    }

    // Business type toggle
    if (businessTypeSelect && businessTypeOtherDiv) {
        businessTypeSelect.addEventListener('change', () => {
            businessTypeOtherDiv.style.display = businessTypeSelect.value === 'Others (Please specify)' ? 'block' : 'none';
        });
    }

    // Employment status toggle - COMPLETELY FIXED LOGIC
    function toggleEmploymentSections(status) {
        console.log('Toggling employment sections for:', status);
        
        // Hide all sections first
        if (employmentDetailsSection) employmentDetailsSection.classList.add('hidden');
        if (unemployedSection) unemployedSection.classList.add('hidden');
        if (studentDetailsSection) studentDetailsSection.classList.add('hidden');
        if (supportingDocumentsSection) supportingDocumentsSection.classList.add('hidden');
        
        // Hide individual fields
        if (jobTitleField) jobTitleField.classList.add('hidden');
        if (companyField) companyField.classList.add('hidden');
        if (companyAddressField) companyAddressField.classList.add('hidden');
        if (businessTypeField) businessTypeField.classList.add('hidden');
        if (salaryField) salaryField.classList.add('hidden');
        if (coeField) coeField.classList.add('hidden');
        if (businessCertField) businessCertField.classList.add('hidden');
        if (corField) corField.classList.add('hidden');

        // Show relevant sections based on status
        switch(status) {
            case 'Employed':
                if (employmentDetailsSection) employmentDetailsSection.classList.remove('hidden');
                if (supportingDocumentsSection) supportingDocumentsSection.classList.remove('hidden');
                if (jobTitleField) jobTitleField.classList.remove('hidden');
                if (companyField) companyField.classList.remove('hidden');
                if (companyAddressField) companyAddressField.classList.remove('hidden');
                if (salaryField) salaryField.classList.remove('hidden');
                if (coeField) coeField.classList.remove('hidden');
                break;
                
            case 'Self-Employed':
                if (employmentDetailsSection) employmentDetailsSection.classList.remove('hidden');
                if (supportingDocumentsSection) supportingDocumentsSection.classList.remove('hidden');
                if (businessTypeField) businessTypeField.classList.remove('hidden');
                if (salaryField) salaryField.classList.remove('hidden');
                if (businessCertField) businessCertField.classList.remove('hidden');
                break;
                
            case 'Unemployed':
                if (unemployedSection) unemployedSection.classList.remove('hidden');
                break;
                
            case 'Student':
                if (studentDetailsSection) studentDetailsSection.classList.remove('hidden');
                if (supportingDocumentsSection) supportingDocumentsSection.classList.remove('hidden');
                if (corField) corField.classList.remove('hidden');
                break;
                
            case 'Employed & Student':
                if (employmentDetailsSection) employmentDetailsSection.classList.remove('hidden');
                if (studentDetailsSection) studentDetailsSection.classList.remove('hidden');
                if (supportingDocumentsSection) supportingDocumentsSection.classList.remove('hidden');
                if (jobTitleField) jobTitleField.classList.remove('hidden');
                if (companyField) companyField.classList.remove('hidden');
                if (companyAddressField) companyAddressField.classList.remove('hidden');
                if (salaryField) salaryField.classList.remove('hidden');
                if (coeField) coeField.classList.remove('hidden');
                if (corField) corField.classList.remove('hidden');
                break;
                
            default:
                // Hide everything for unknown status
                break;
        }
    }

    // Initialize employment sections
    if (employmentStatusSelect) {
        toggleEmploymentSections(employmentStatusSelect.value);
        
        employmentStatusSelect.addEventListener('change', () => {
            toggleEmploymentSections(employmentStatusSelect.value);
        });
    }

    // Address dropdown population 
    let regionsData;
    async function loadAddressData() {
        if (isAddressLoading) return;
        isAddressLoading = true;
        
        try {
            const regionsResponse = await fetch('../api/get_regions.php');
            if (!regionsResponse.ok) throw new Error('Failed to load regions: ' + regionsResponse.status);
            regionsData = await regionsResponse.json();
            console.log('Regions loaded:', regionsData);
            populateRegions();
            addressDataLoaded = true;
        } catch (e) {
            console.error('Error loading address data:', e);
            alert('Failed to load address data. Please refresh and try again.');
        } finally {
            isAddressLoading = false;
        }
    }

    function populateRegions() {
        if (!regionSelect || !regionsData) return;
        regionSelect.innerHTML = '<option value="">Select Region</option>';
        regionsData.forEach(region => {
            const option = document.createElement('option');
            option.value = region.reg_code;
            option.textContent = region.name;
            regionSelect.appendChild(option);
        });
        <?php if (!empty($profile['region_id'])): ?>
            if (regionSelect) regionSelect.value = '<?php echo htmlspecialchars($profile['region_id']); ?>';
            filterProvinces();
        <?php endif; ?>
    }

    async function filterProvinces() {
        if (!provinceSelect) return;
        provinceSelect.innerHTML = '<option value="">Select Province</option>';
        if (municipalitySelect) municipalitySelect.innerHTML = '<option value="">Select Municipality</option>';
        if (barangaySelect) barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        const regionCode = regionSelect ? regionSelect.value : '';
        if (!regionCode) return;

        isAddressLoading = true;
        try {
            const response = await fetch(`../api/get_provinces.php?region_id=${encodeURIComponent(regionCode)}`);
            if (!response.ok) throw new Error('Failed to load provinces: ' + response.status);
            const provinces = await response.json();
            provinces.forEach(prov => {
                const option = document.createElement('option');
                option.value = prov.prov_code;
                option.textContent = prov.name;
                provinceSelect.appendChild(option);
            });
            <?php if (!empty($profile['province_id'])): ?>
                provinceSelect.value = '<?php echo htmlspecialchars($profile['province_id']); ?>';
                filterMunicipalities();
            <?php endif; ?>
        } catch (e) {
            console.error('Error fetching provinces:', e);
        } finally {
            isAddressLoading = false;
        }
    }

    async function filterMunicipalities() {
        if (!municipalitySelect) return;
        municipalitySelect.innerHTML = '<option value="">Select Municipality</option>';
        if (barangaySelect) barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        const provinceCode = provinceSelect ? provinceSelect.value : '';
        if (!provinceCode) return;

        isAddressLoading = true;
        try {
            const response = await fetch(`../api/get_municipalities.php?province_id=${encodeURIComponent(provinceCode)}`);
            if (!response.ok) throw new Error('Failed to load municipalities: ' + response.status);
            const municipalities = await response.json();
            municipalities.forEach(mun => {
                const option = document.createElement('option');
                option.value = mun.mun_code;
                option.textContent = mun.name;
                municipalitySelect.appendChild(option);
            });
            <?php if (!empty($profile['municipality_id'])): ?>
                municipalitySelect.value = '<?php echo htmlspecialchars($profile['municipality_id']); ?>';
                filterBarangays();
            <?php endif; ?>
        } catch (e) {
            console.error('Error fetching municipalities:', e);
        } finally {
            isAddressLoading = false;
        }
    }

    async function filterBarangays() {
        if (!barangaySelect) return;
        barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        const municipalityCode = municipalitySelect ? municipalitySelect.value : '';
        if (!municipalityCode) return;

        isAddressLoading = true;
        try {
            const response = await fetch(`../api/get_barangays.php?municipality_id=${encodeURIComponent(municipalityCode)}`);
            if (!response.ok) throw new Error('Failed to load barangays: ' + response.status);
            const barangays = await response.json();
            barangays.sort((a, b) => a.name.localeCompare(b.name));
            barangays.forEach(brgy => {
                const option = document.createElement('option');
                option.value = brgy.brgy_code || '';
                option.textContent = brgy.name;
                barangaySelect.appendChild(option);
            });
            <?php if (!empty($profile['barangay_id'])): ?>
                barangaySelect.value = '<?php echo htmlspecialchars($profile['barangay_id']); ?>';
            <?php endif; ?>
        } catch (e) {
            console.error('Error fetching barangays:', e);
        } finally {
            isAddressLoading = false;
        }
    }

    // Event listeners for cascading dropdowns
    if (regionSelect) regionSelect.addEventListener('change', filterProvinces);
    if (provinceSelect) provinceSelect.addEventListener('change', filterMunicipalities);
    if (municipalitySelect) municipalitySelect.addEventListener('change', filterBarangays);

    // SIMPLIFIED Form validation - FIXED VERSION
    const alumniProfileForm = document.getElementById('alumniProfileForm');
    if (alumniProfileForm) {
        alumniProfileForm.addEventListener('submit', function(event) {
            // Prevent address loading interference
            if (isAddressLoading) {
                alert('Address data is still loading. Please wait.');
                event.preventDefault();
                return;
            }

            /* // Basic permission check
            <?php if (!$can_update): ?>
            alert('You are not allowed to update your profile at this time. You can only update once per year unless your submission was rejected.');
            event.preventDefault();
            return;
            <?php endif; ?> */

            // Profile photo validation - FIXED
            const profilePhotoInput = document.getElementById('profilePictureInput');
            const hasExistingPhoto = '<?php echo !empty($profile['photo_path']) ? 'true' : 'false'; ?>';
            
            if (!profilePhotoInput.files.length && hasExistingPhoto === 'false') {
                alert('Please upload your profile picture before submitting.');
                event.preventDefault();
                return;
            }

            // Basic required field validation
            const requiredFields = [
                { field: 'first_name', message: 'First Name is required.' },
                { field: 'last_name', message: 'Last Name is required.' },
                { field: 'contact_number', message: 'Contact Number is required.' },
                { field: 'year_graduated', message: 'Year Graduated is required.' },
                { field: 'employment_status', message: 'Employment Status is required.' }
            ];

            let isValid = true;

            for (const { field, message } of requiredFields) {
                const element = document.querySelector(`[name="${field}"]`);
                if (element && !element.value.trim()) {
                    alert(message);
                    isValid = false;
                    break;
                }
            }

            if (!isValid) {
                event.preventDefault();
                return;
            }

            // Address validation
            const addressFieldIds = ['regionSelect', 'provinceSelect', 'municipalitySelect', 'barangaySelect'];
                        const addressMessages = ['Region', 'Province', 'Municipality', 'Barangay'];

                        let addressValid = true;
                        for (let i = 0; i < addressFieldIds.length; i++) {
                            const el = document.getElementById(addressFieldIds[i]);
                            if (el && !el.value.trim()) {
                                alert(addressMessages[i] + ' is required.');
                                addressValid = false;
                                break;
                            }
                        }

                        if (!addressValid) {
                            event.preventDefault();
                            return;
                        }

            // Employment-specific validation
            const status = employmentStatusSelect ? employmentStatusSelect.value : '';
            
            if (['Employed', 'Employed & Student'].includes(status)) {
                if (jobTitleSelect && !jobTitleSelect.value) {
                    alert('Job Title is required for this employment status.');
                    isValid = false;
                } else if (jobTitleSelect && jobTitleSelect.value === 'Other') {
                    const otherTitle = document.querySelector('[name="other_job_title"]');
                    if (otherTitle && !otherTitle.value.trim()) {
                        alert('Please specify job title if "Other" is selected.');
                        isValid = false;
                    }
                }
                
                const companyName = document.querySelector('[name="company_name"]');
                if (companyName && !companyName.value.trim()) {
                    alert('Company Name is required for this employment status.');
                    isValid = false;
                }
                
                const companyAddress = document.querySelector('[name="company_address"]');
                if (companyAddress && !companyAddress.value.trim()) {
                    alert('Company Address is required for this employment status.');
                    isValid = false;
                }
            }

            // Self-Employed validation
            if (status === 'Self-Employed') {
                if (businessTypeSelect && !businessTypeSelect.value) {
                    alert('Business Type is required for Self-Employed status.');
                    isValid = false;
                } else if (businessTypeSelect && businessTypeSelect.value === 'Others (Please specify)') {
                    const businessTypeOther = document.querySelector('[name="business_type_other"]');
                    if (businessTypeOther && !businessTypeOther.value.trim()) {
                        alert('Please specify business type if "Others" is selected.');
                        isValid = false;
                    }
                }
            }

            // Student validation
            if (['Student', 'Employed & Student'].includes(status)) {
                const studentFields = [
                    { field: 'school_name', message: 'School Name is required for student status.' },
                    { field: 'degree_pursued', message: 'Degree Pursued is required for student status.' },
                    { field: 'start_year', message: 'Start Year is required for student status.' },
                    { field: 'end_year', message: 'End Year is required for student status.' }
                ];

                for (const { field, message } of studentFields) {
                    const element = document.querySelector(`[name="${field}"]`);
                    if (element && !element.value.trim()) {
                        alert(message);
                        isValid = false;
                        break;
                    }
                }
            }

            // Salary validation for employment statuses
            if (['Employed', 'Self-Employed', 'Employed & Student'].includes(status)) {
                const salaryElement = document.querySelector('[name="salary_range"]');
                if (salaryElement && !salaryElement.value.trim()) {
                    const label = status === 'Self-Employed' ? 'Monthly Income Range' : 'Salary Range';
                    alert(`${label} is required for ${status} status.`);
                    isValid = false;
                }
            }

            if (!isValid) {
                event.preventDefault();
                return;
            }

            console.log('Form validation passed, submitting...');
        });
    }
});
</script>

<?php
$page_content = ob_get_clean();
include("alumni_format.php");
$conn->close();
?>