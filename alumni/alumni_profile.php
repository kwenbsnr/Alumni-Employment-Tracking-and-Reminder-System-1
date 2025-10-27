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
    SELECT ap.*, u.email, a.street_details, a.zip_code, 
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

// Debug: Log employment data for troubleshooting
error_log("Employment data for user_id $user_id: " . json_encode($employment));

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
$docs = [];
$rejected_docs = [];
$can_reupload = false;
if (!empty($profile['user_id'])) {
    $stmt = $conn->prepare("SELECT document_type, file_path, document_status, rejection_reason, needs_reupload FROM alumni_documents WHERE user_id = ?");
    $stmt->bind_param("i", $profile['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($doc = $result->fetch_assoc()) {
        $docs[] = $doc;
        if ($doc['document_status'] === 'Rejected' && $doc['needs_reupload']) {
            $rejected_docs[] = $doc['document_type'];
            $can_reupload = true;
        }
    }
    $stmt->close();
}

// Check yearly update restriction
$can_update = empty($profile) || ($profile && ($profile['last_profile_update'] === null || strtotime($profile['last_profile_update'] . ' +1 year') <= time()));
$can_update = $can_update || $can_reupload;

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

<div class="space-y-6">
    <!-- Update Profile Box -->
    <div id="updateProfileBtn" class="bg-white p-6 rounded-xl shadow-lg flex flex-col justify-between hover:shadow-xl transition duration-200 border-t-4 border-green-500 cursor-pointer">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-lg font-semibold text-gray-600">Update Profile</h3>
            <i class="fas fa-user-edit text-xl text-green-500"></i>
        </div>
        <p class="text-sm text-gray-500">Click to edit your personal, employment, and educational details.</p>
    </div>

    <?php if (!empty($profile)): ?>
        <!-- Personal Information Card -->
        <div class="bg-white p-6 rounded-xl shadow-lg">
            <h3 class="text-lg font-semibold text-gray-600 mb-4">Personal Information</h3>
            <dl class="grid grid-cols-1 gap-4">
                <div class="flex justify-between">
                    <dt class="font-medium">Full Name</dt>
                    <dd><?php echo htmlspecialchars($full_name); ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="font-medium">Email</dt>
                    <dd><?php echo htmlspecialchars($profile['email'] ?? 'N/A'); ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="font-medium">Contact Number</dt>
                    <dd><?php echo htmlspecialchars($profile['contact_number'] ?? 'N/A'); ?></dd>
                </div>
                <div class="flex justify-between">
                    <dt class="font-medium">Year Graduated</dt>
                    <dd><?php echo htmlspecialchars($profile['year_graduated'] ?? 'N/A'); ?></dd>
                </div>
            </dl>
        </div>

        <!-- Address Card -->
        <div class="bg-white p-6 rounded-xl shadow-lg">
            <h3 class="text-lg font-semibold text-gray-600 mb-4">Address</h3>
            <dl class="grid grid-cols-1 gap-4">
                <div class="flex justify-between">
                    <dt class="font-medium">Address</dt>
                    <dd><?php echo htmlspecialchars(
                        ($profile['street_details'] ?? '') . ', ' .
                        ($profile['barangay_name'] ?? '') . ', ' .
                        ($profile['municipality_name'] ?? '') . ', ' .
                        ($profile['province_name'] ?? '') . ', ' .
                        ($profile['region_name'] ?? '') . ' ' .
                        ($profile['zip_code'] ?? '')
                    ); ?></dd>
                </div>
            </dl>
        </div>

        <!-- Employment/Academic Details Card -->
        <div class="bg-white p-6 rounded-xl shadow-lg">
            <h3 class="text-lg font-semibold text-gray-600 mb-4">Employment/Academic Details</h3>
            <dl class="grid grid-cols-1 gap-4">
                <div class="flex justify-between">
                    <dt class="font-medium">Employment Status</dt>
                    <dd><?php echo htmlspecialchars($profile['employment_status'] ?? 'Not Set'); ?></dd>
                </div>
                <?php if (in_array($profile['employment_status'] ?? '', ['Employed', 'Self-Employed', 'Employed & Student'])): ?>
                    <?php if ($profile['employment_status'] !== 'Self-Employed'): ?>
                        <div class="flex justify-between">
                            <dt class="font-medium">Job Title</dt>
                            <dd><?php echo htmlspecialchars($employment['job_title'] ?? 'N/A'); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium">Company Name</dt>
                            <dd><?php echo htmlspecialchars($employment['company_name'] ?? 'N/A'); ?></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="font-medium">Company Address</dt>
                            <dd><?php echo htmlspecialchars($employment['company_address'] ?? 'N/A'); ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if ($profile['employment_status'] === 'Self-Employed'): ?>
                        <div class="flex justify-between">
                            <dt class="font-medium">Business Type</dt>
                            <dd><?php 
                                $display_business_type = $employment['business_type'] ?? 'N/A';
                                // Handle "Others: " prefix for display
                                if (strpos($display_business_type, 'Others: ') === 0) {
                                    $display_business_type = 'Others (Please specify)';
                                }
                                echo htmlspecialchars($display_business_type); 
                            ?></dd>
                        </div>
                    <?php endif; ?>
                    <div class="flex justify-between">
                        <dt class="font-medium"><?php echo ($profile['employment_status'] === 'Self-Employed') ? 'Monthly Income Range' : 'Salary Range'; ?></dt>
                        <dd><?php echo htmlspecialchars($employment['salary_range'] ?? 'N/A'); ?></dd>
                    </div>
                <?php endif; ?>
                <?php if (in_array($profile['employment_status'] ?? '', ['Student', 'Employed & Student'])): ?>
                    <div class="flex justify-between">
                        <dt class="font-medium">School Name</dt>
                        <dd><?php echo htmlspecialchars($education['school_name'] ?? 'N/A'); ?></dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="font-medium">Degree Pursued</dt>
                        <dd><?php echo htmlspecialchars($education['degree_pursued'] ?? 'N/A'); ?></dd>
                    </div>
                <?php endif; ?>
                <?php if (($profile['employment_status'] ?? '') === 'Unemployed'): ?>
                    <dd>Currently Unemployed</dd>
                <?php endif; ?>
            </dl>
        </div>

        <!-- Documents Card -->
        <div class="bg-white p-6 rounded-xl shadow-lg">
            <h3 class="text-lg font-semibold text-gray-600 mb-4">Documents</h3>
            <?php if (empty($docs)): ?>
                <p class="text-sm text-gray-500">No documents uploaded.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($docs as $doc): ?>
                        <div class="flex justify-between items-center border-b pb-2">
                            <span class="font-medium"><?php echo htmlspecialchars($doc['document_type']); ?></span>
                            <span class="text-sm <?php echo $doc['document_status'] === 'Approved' ? 'text-green-600' : ($doc['document_status'] === 'Rejected' ? 'text-red-600' : 'text-yellow-600'); ?>"><?php echo htmlspecialchars($doc['document_status']); ?></span>
                            <a href="../<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="text-blue-600 hover:underline">View</a>
                        </div>
                        <?php if ($doc['document_status'] === 'Rejected' && $doc['rejection_reason']): ?>
                            <p class="text-sm text-red-600">Rejection Reason: <?php echo htmlspecialchars($doc['rejection_reason']); ?></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
                                <input type="text" name="first_name" autocomplete="given-name" value="<?php echo htmlspecialchars($profile['first_name'] ?? ''); ?>" class="w-full border rounded-lg p-2" required <?php if (!$can_update) echo 'disabled'; ?>>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Middle Name
                                <input type="text" name="middle_name" autocomplete="additional-name" value="<?php echo htmlspecialchars($profile['middle_name'] ?? ''); ?>" class="w-full border rounded-lg p-2" <?php if (!$can_update) echo 'disabled'; ?>>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Last Name
                                <input type="text" name="last_name" autocomplete="family-name" value="<?php echo htmlspecialchars($profile['last_name'] ?? ''); ?>" class="w-full border rounded-lg p-2" required <?php if (!$can_update) echo 'disabled'; ?>>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Contact Number
                                <input type="tel" name="contact_number" autocomplete="tel" value="<?php echo htmlspecialchars($profile['contact_number'] ?? ''); ?>" class="w-full border rounded-lg p-2" required pattern="[0-9]{10,11}" title="Contact number must be 10 or 11 digits" <?php if (!$can_update) echo 'disabled'; ?>>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Year Graduated
                                <select name="year_graduated" class="w-full border rounded-lg p-2" required <?php if (!$can_update) echo 'disabled'; ?>>
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
                                <select id="employmentStatusSelect" name="employment_status" class="w-full border rounded-lg p-2" required <?php if (!$can_update) echo 'disabled'; ?>>
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
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Street Details
                                <input type="text" name="street_details" autocomplete="street-address" value="<?php echo htmlspecialchars($profile['street_details'] ?? ''); ?>" class="w-full border rounded-lg p-2" <?php if (!$can_update) echo 'disabled'; ?>>
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Zip Code
                                <input type="text" name="zip_code" autocomplete="postal-code" value="<?php echo htmlspecialchars($profile['zip_code'] ?? ''); ?>" class="w-full border rounded-lg p-2" pattern="[0-9]{4}" title="Zip code must be 4 digits" <?php if (!$can_update) echo 'disabled'; ?>>
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
                    </div>
                </div>

                <!-- Supporting Documents Section -->
                <div id="supportingDocumentsSection" class="hidden bg-white p-6 rounded-xl shadow-lg">
                    <h3 class="text-lg font-medium text-gray-600 mb-4">Supporting Documents</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <?php if ($can_update || in_array('COE', $rejected_docs)): ?>
                            <div id="coeField" class="hidden">
                                <label class="block text-sm font-medium text-gray-700">Certificate of Employment (COE)
                                    <input type="file" name="coe_file" accept="application/pdf" class="w-full border rounded-lg p-2">
                                </label>
                            </div>
                        <?php endif; ?>
                        <?php if ($can_update || in_array('B_CERT', $rejected_docs)): ?>
                            <div id="businessCertField" class="hidden">
                                <label class="block text-sm font-medium text-gray-700">Business Certificate
                                    <input type="file" name="business_file" accept="application/pdf" class="w-full border rounded-lg p-2">
                                </label>
                            </div>
                        <?php endif; ?>
                        <?php if ($can_update || in_array('COR', $rejected_docs)): ?>
                            <div id="corField" class="hidden">
                                <label class="block text-sm font-medium text-gray-700">Certificate of Registration (COR)
                                    <input type="file" name="cor_file" accept="application/pdf" class="w-full border rounded-lg p-2">
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            <div class="flex justify-end">
                <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition duration-150 shadow-md">Submit</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
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
    const submitButton = document.querySelector('#alumniProfileForm button[type="submit"]');

    // Track loading state
    let isAddressLoading = false;

    // Modal toggle
    if (updateProfileBtn) {
        updateProfileBtn.addEventListener('click', () => {
            if (updateProfileModal) {
                updateProfileModal.classList.remove('hidden');
                updateProfileModal.classList.add('show', 'flex');
                loadAddressData();
            }
        });
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

    // Job title toggle for "Other"
    if (jobTitleSelect) {
        jobTitleSelect.addEventListener('change', () => {
            if (otherJobTitleDiv) {
                otherJobTitleDiv.style.display = jobTitleSelect.value === 'Other' ? 'block' : 'none';
            }
        });
    }

    // Business type toggle
    if (businessTypeSelect) {
        businessTypeSelect.addEventListener('change', () => {
            if (businessTypeOtherDiv) {
                businessTypeOtherDiv.style.display = businessTypeSelect.value === 'Others (Please specify)' ? 'block' : 'none';
            }
        });
    }

    // Employment status toggle
    function toggleEmploymentSections(status) {
        if (employmentDetailsSection) employmentDetailsSection.classList.add('hidden');
        if (unemployedSection) unemployedSection.classList.add('hidden');
        if (studentDetailsSection) studentDetailsSection.classList.add('hidden');
        if (jobTitleField) jobTitleField.classList.add('hidden');
        if (companyField) companyField.classList.add('hidden');
        if (companyAddressField) companyAddressField.classList.add('hidden');
        if (businessTypeField) businessTypeField.classList.add('hidden');
        if (coeField) coeField.classList.add('hidden');
        if (businessCertField) businessCertField.classList.add('hidden');
        if (corField) corField.classList.add('hidden');
        if (supportingDocumentsSection) supportingDocumentsSection.classList.add('hidden');

        if (status === 'Employed') {
            if (employmentDetailsSection) employmentDetailsSection.classList.remove('hidden');
            if (jobTitleField) jobTitleField.classList.remove('hidden');
            if (companyField) companyField.classList.remove('hidden');
            if (companyAddressField) companyAddressField.classList.remove('hidden');
            if (coeField) coeField.classList.remove('hidden');
            if (supportingDocumentsSection) supportingDocumentsSection.classList.remove('hidden');
        } else if (status === 'Self-Employed') {
            if (employmentDetailsSection) employmentDetailsSection.classList.remove('hidden');
            if (businessTypeField) businessTypeField.classList.remove('hidden');
            if (businessCertField) businessCertField.classList.remove('hidden');
            if (supportingDocumentsSection) supportingDocumentsSection.classList.remove('hidden');
        } else if (status === 'Unemployed') {
            if (unemployedSection) unemployedSection.classList.remove('hidden');
        } else if (status === 'Student') {
            if (studentDetailsSection) studentDetailsSection.classList.remove('hidden');
            if (corField) corField.classList.remove('hidden');
            if (supportingDocumentsSection) supportingDocumentsSection.classList.remove('hidden');
        } else if (status === 'Employed & Student') {
            if (employmentDetailsSection) employmentDetailsSection.classList.remove('hidden');
            if (studentDetailsSection) studentDetailsSection.classList.remove('hidden');
            if (jobTitleField) jobTitleField.classList.remove('hidden');
            if (companyField) companyField.classList.remove('hidden');
            if (companyAddressField) companyAddressField.classList.remove('hidden');
            if (coeField) coeField.classList.remove('hidden');
            if (corField) corField.classList.remove('hidden');
            if (supportingDocumentsSection) supportingDocumentsSection.classList.remove('hidden');
        }
    }

    if (employmentStatusSelect) {
        toggleEmploymentSections(employmentStatusSelect.value);
    }

    if (employmentStatusSelect) {
        employmentStatusSelect.addEventListener('change', () => {
            toggleEmploymentSections(employmentStatusSelect.value);
        });
    }

    // Address dropdown population (DB-driven)
    let regionsData;
    async function loadAddressData() {
        if (isAddressLoading) return;
        isAddressLoading = true;
        if (submitButton) submitButton.disabled = true; // Disable submit until loaded
        try {
            const regionsResponse = await fetch('../api/get_regions.php');
            if (!regionsResponse.ok) throw new Error('Failed to load regions: ' + regionsResponse.status);
            regionsData = await regionsResponse.json();
            console.log('Regions loaded:', regionsData);
            populateRegions();
        } catch (e) {
            console.error('Error loading address data:', e);
            alert('Failed to load address data. Please refresh and try again.');
        } finally {
            isAddressLoading = false;
            if (submitButton) submitButton.disabled = false;
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
        if (submitButton) submitButton.disabled = true;
        try {
            const response = await fetch(`../api/get_provinces.php?region_id=${encodeURIComponent(regionCode)}`);
            if (!response.ok) throw new Error('Failed to load provinces: ' + response.status);
            const provinces = await response.json();
            console.log('Provinces loaded:', provinces);
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
            alert('Failed to load provinces. Please try again.');
        } finally {
            isAddressLoading = false;
            if (submitButton) submitButton.disabled = false;
        }
    }

    async function filterMunicipalities() {
        if (!municipalitySelect) return;
        municipalitySelect.innerHTML = '<option value="">Select Municipality</option>';
        if (barangaySelect) barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        const provinceCode = provinceSelect ? provinceSelect.value : '';
        if (!provinceCode) return;

        isAddressLoading = true;
        if (submitButton) submitButton.disabled = true;
        try {
            const response = await fetch(`../api/get_municipalities.php?province_id=${encodeURIComponent(provinceCode)}`);
            if (!response.ok) throw new Error('Failed to load municipalities: ' + response.status);
            const municipalities = await response.json();
            console.log('Municipalities loaded:', municipalities);
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
            alert('Failed to load municipalities. Please try again.');
        } finally {
            isAddressLoading = false;
            if (submitButton) submitButton.disabled = false;
        }
    }

    async function filterBarangays() {
        if (!barangaySelect) return;
        barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        const municipalityCode = municipalitySelect ? municipalitySelect.value : '';
        if (!municipalityCode) return;

        isAddressLoading = true;
        if (submitButton) submitButton.disabled = true;
        try {
            const response = await fetch(`../api/get_barangays.php?municipality_id=${encodeURIComponent(municipalityCode)}`);
            if (!response.ok) throw new Error('Failed to load barangays: ' + response.status);
            const barangays = await response.json();
            console.log('Barangays loaded:', barangays.map(b => ({ id: b.brgy_code, name: b.name })));
            barangays.sort((a, b) => a.name.localeCompare(b.name));
            barangays.forEach(brgy => {
                const option = document.createElement('option');
                option.value = brgy.brgy_code || '';
                option.textContent = brgy.name;
                barangaySelect.appendChild(option);
            });
            <?php if (!empty($profile['barangay_id'])): ?>
                barangaySelect.value = '<?php echo htmlspecialchars($profile['barangay_id']); ?>';
                if (barangaySelect.value !== '<?php echo htmlspecialchars($profile['barangay_id']); ?>') {
                    console.warn('Barangay ID <?php echo htmlspecialchars($profile['barangay_id']); ?> not found in options for municipality_id:', municipalityCode);
                } else {
                    console.log('Barangay ID set:', barangaySelect.value);
                }
            <?php endif; ?>
        } catch (e) {
            console.error('Error fetching barangays:', e);
            alert('Failed to load barangays. Please try again.');
        } finally {
            isAddressLoading = false;
            if (submitButton) submitButton.disabled = false;
        }
    }

    // Event listeners for cascading dropdowns
    if (regionSelect) regionSelect.addEventListener('change', filterProvinces);
    if (provinceSelect) provinceSelect.addEventListener('change', filterMunicipalities);
    if (municipalitySelect) municipalitySelect.addEventListener('change', filterBarangays);

    // Custom form validation on submit
    const alumniProfileForm = document.getElementById('alumniProfileForm');
    if (alumniProfileForm) {
        alumniProfileForm.addEventListener('submit', async function(event) {
            if (isAddressLoading) {
                alert('Address data is still loading. Please wait.');
                event.preventDefault();
                return;
            }

            let isValid = true;
            const status = employmentStatusSelect ? employmentStatusSelect.value : '';
            const barangayId = barangaySelect ? barangaySelect.value.trim() : '';
            const municipalityId = municipalitySelect ? municipalitySelect.value.trim() : '';
            const streetDetails = document.querySelector('[name="street_details"]') ? document.querySelector('[name="street_details"]').value.trim() : '';
            const zipCode = document.querySelector('[name="zip_code"]') ? document.querySelector('[name="zip_code"]').value.trim() : '';

            // Validate personal fields
            if (!document.querySelector('[name="first_name"]') || !document.querySelector('[name="first_name"]').value.trim()) {
                alert('First Name is required.');
                isValid = false;
            }
            if (!document.querySelector('[name="last_name"]') || !document.querySelector('[name="last_name"]').value.trim()) {
                alert('Last Name is required.');
                isValid = false;
            }
            if (!document.querySelector('[name="contact_number"]') || !document.querySelector('[name="contact_number"]').value.trim()) {
                alert('Contact Number is required.');
                isValid = false;
            }
            if (!document.querySelector('[name="year_graduated"]') || !document.querySelector('[name="year_graduated"]').value.trim()) {
                alert('Year Graduated is required.');
                isValid = false;
            }
            if (!status) {
                alert('Employment Status is required.');
                isValid = false;
            }

            // Validate address fields for all statuses
            if (!regionSelect || !regionSelect.value) {
                alert('Region is required.');
                isValid = false;
            }
            if (!provinceSelect || !provinceSelect.value) {
                alert('Province is required.');
                isValid = false;
            }
            if (!municipalityId) {
                alert('Municipality is required.');
                isValid = false;
            }
            if (!barangayId) {
                alert('Barangay is required.');
                isValid = false;
            }
            if (!streetDetails) {
                alert('Street Details are required.');
                isValid = false;
            }
            if (!zipCode) {
                alert('Zip Code is required.');
                isValid = false;
            }
            if (barangayId && municipalityId) {
                try {
                    const response = await fetch(`../api/get_barangays.php?municipality_id=${encodeURIComponent(municipalityId)}`);
                    if (!response.ok) throw new Error(`Failed to validate barangay: ${response.status}`);
                    const barangays = await response.json();
                    console.log('Validating barangays for municipality_id', municipalityId, ':', barangays.map(b => ({ id: b.brgy_code, name: b.name })));
                    const validBarangay = barangays.some(b => b.brgy_code === barangayId);
                    if (!validBarangay) {
                        alert('Selected Barangay is invalid or does not match the selected Municipality. Please choose a valid option.');
                        console.warn('Invalid barangay_id:', barangayId, 'for municipality_id:', municipalityId);
                        isValid = false;
                    } else {
                        console.log('Valid barangay_id:', barangayId, 'for municipality_id:', municipalityId);
                    }
                } catch (e) {
                    console.error('Error validating barangay:', e);
                    alert('Failed to validate Barangay. Please refresh and try again.');
                    isValid = false;
                }
            }

            // Validate employment fields
            if (['Employed', 'Employed & Student'].includes(status)) {
                const jobTitle = jobTitleSelect ? jobTitleSelect.value : '';
                const companyName = document.querySelector('[name="company_name"]') ? document.querySelector('[name="company_name"]').value.trim() : '';
                if (!jobTitle) {
                    alert('Job Title is required for this employment status.');
                    isValid = false;
                }
                if (jobTitle === 'Other' && !document.querySelector('[name="other_job_title"]') || !document.querySelector('[name="other_job_title"]').value.trim()) {
                    alert('Please specify job title if "Other" is selected.');
                    isValid = false;
                }
                if (!companyName) {
                    alert('Company Name is required for this employment status.');
                    isValid = false;
                }
            }

            // Validate business type for Self-Employed
            if (status === 'Self-Employed') {
                const businessType = businessTypeSelect ? businessTypeSelect.value : '';
                const businessTypeOther = document.querySelector('[name="business_type_other"]') ? document.querySelector('[name="business_type_other"]').value.trim() : '';
                if (!businessType) {
                    alert('Business Type is required for Self-Employed status.');
                    isValid = false;
                }
                if (businessType === 'Others (Please specify)' && !businessTypeOther) {
                    alert('Please specify business type if "Others" is selected.');
                    isValid = false;
                }
            }

            // Validate education fields
            if (['Student', 'Employed & Student'].includes(status)) {
                const schoolName = document.querySelector('[name="school_name"]') ? document.querySelector('[name="school_name"]').value.trim() : '';
                const degreePursued = document.querySelector('[name="degree_pursued"]') ? document.querySelector('[name="degree_pursued"]').value.trim() : '';
                if (!schoolName) {
                    alert('School Name is required for this status.');
                    isValid = false;
                }
                if (!degreePursued) {
                    alert('Degree Pursued is required for this status.');
                    isValid = false;
                }
            }

            // Log for debugging
            console.log(`Submitting form with barangay_id: '${barangayId}' (hex: ${Array.from(new TextEncoder().encode(barangayId)).map(b => b.toString(16).padStart(2, '0')).join('')}), municipality_id: '${municipalityId}'`);
            console.log('All barangay options:', Array.from(barangaySelect ? barangaySelect.options : []).map(opt => `${opt.value}: ${opt.textContent}`));
            console.log(`Employment status: ${status}`);

            if (!isValid) {
                event.preventDefault();
            }
        });
    }
});
</script>

<?php
$page_content = ob_get_clean();
include("alumni_format.php");
$conn->close();
?>