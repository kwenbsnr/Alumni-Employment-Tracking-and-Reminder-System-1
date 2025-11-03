<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

    // DEBUGGING PURPS
    /*echo "<pre>"; var_dump($_GET); exit;*/

    
// Validate and fetch user_id with DB check in a single query
$user_id = null;
if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $user_id = (int)$_GET['user_id'];
    $_SESSION['last_edited_user_id'] = $user_id; // Store for refresh
} elseif (isset($_SESSION['last_edited_user_id']) && is_numeric($_SESSION['last_edited_user_id'])) {
    $user_id = (int)$_SESSION['last_edited_user_id'];
}

if ($user_id === null) {
    header("Location: alumni_management.php?error=" . urlencode("Invalid user ID"));
    exit();
}

// Fetch alumni data with integrated validation
$query = "SELECT ap.*, u.email, ei.job_title_id, ei.company_name, ei.salary_range, jt.title, 
          a.barangay_id, a.street_details
          FROM alumni_profile ap 
          LEFT JOIN users u ON ap.user_id = u.user_id 
          LEFT JOIN employment_info ei ON ap.user_id = ei.user_id 
          LEFT JOIN job_titles jt ON ei.job_title_id = jt.job_title_id 
          LEFT JOIN address a ON ap.address_id = a.address_id 
          WHERE ap.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$alumni = $result->fetch_assoc();

if (!$alumni) { // Simplified check, handles NULL from any join failure
    unset($_SESSION['last_edited_user_id']); // Clear invalid session
    header("Location: alumni_management.php?error=" . urlencode("User or alumni profile not found"));
    exit();
}

// Proceed with document and job queries (unchanged)
$doc_query = "SELECT doc_id, document_type, file_path, document_status FROM alumni_documents WHERE user_id = ?";
$stmt = $conn->prepare($doc_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$doc_result = $stmt->get_result();
$documents = [];
while ($row = $doc_result->fetch_assoc()) {
    $documents[$row['document_type']] = $row;
}

$job_query = "SELECT job_title_id, title FROM job_titles";
$job_result = $conn->query($job_query);
$job_titles = [];
while ($row = $job_result->fetch_assoc()) {
    $job_titles[] = $row;
}


if (!$alumni || !$alumni['email']) {
    header("Location: alumni_management.php?error=" . urlencode("User or alumni profile not found"));
    exit();
}


$page_title = "Edit Alumni Profile";
$active_page = "alumni_management";
ob_start();
?>
<div class="bg-white p-6 rounded-xl shadow-lg stats-card card-hover">
    <h2 class="text-2xl font-bold text-gray-800 mb-6">Edit Alumni Profile</h2>
    <form id="editAlumniForm" action="update_alumni.php" method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">First Name</label>
                <input type="text" name="first_name" value="<?php echo htmlspecialchars($alumni['first_name']); ?>" required class="w-full p-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Middle Name</label>
                <input type="text" name="middle_name" value="<?php echo htmlspecialchars($alumni['middle_name'] ?? ''); ?>" class="w-full p-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Last Name</label>
                <input type="text" name="last_name" value="<?php echo htmlspecialchars($alumni['last_name']); ?>" required class="w-full p-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($alumni['email']); ?>" required class="w-full p-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Contact Number</label>
                <input type="text" name="contact_number" value="<?php echo htmlspecialchars($alumni['contact_number'] ?? ''); ?>" class="w-full p-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Year Graduated</label>
                <input type="number" name="year_graduated" value="<?php echo htmlspecialchars($alumni['year_graduated'] ?? ''); ?>" class="w-full p-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Employment Status</label>
                <select name="employment_status" id="employmentStatus" onchange="toggleFields()" class="w-full p-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option value="Employed" <?php echo $alumni['employment_status'] == 'Employed' ? 'selected' : ''; ?>>Employed</option>
                    <option value="Self-Employed" <?php echo $alumni['employment_status'] == 'Self-Employed' ? 'selected' : ''; ?>>Self-Employed</option>
                    <option value="Unemployed" <?php echo $alumni['employment_status'] == 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                    <option value="Student" <?php echo $alumni['employment_status'] == 'Student' ? 'selected' : ''; ?>>Student</option>
                    <option value="Employed & Student" <?php echo $alumni['employment_status'] == 'Employed & Student' ? 'selected' : ''; ?>>Employed & Student</option>
                </select>
            </div>
        </div>
        <div id="employmentFields" class="<?php echo in_array($alumni['employment_status'], ['Employed', 'Self-Employed', 'Employed & Student']) ? '' : 'hidden'; ?>">
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700">Job Title</label>
                <select name="job_title_id" class="w-full p-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Select Job Title</option>
                    <?php foreach ($job_titles as $job): ?>
                        <option value="<?php echo $job['job_title_id']; ?>" <?php echo ($alumni['job_title_id'] ?? '') == $job['job_title_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($job['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700">Company Name</label>
                <input type="text" name="company_name" value="<?php echo htmlspecialchars($alumni['company_name'] ?? ''); ?>" class="w-full p-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700">Salary Range</label>
                <select name="salary_range" class="w-full p-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Select Salary Range</option>
                    <option value="Below ₱20,000" <?php echo ($alumni['salary_range'] ?? '') == 'Below ₱20,000' ? 'selected' : ''; ?>>Below ₱20,000</option>
                    <option value="₱20,000 - ₱50,000" <?php echo ($alumni['salary_range'] ?? '') == '₱20,000 - ₱50,000' ? 'selected' : ''; ?>>₱20,000 - ₱50,000</option>
                    <option value="Above ₱50,000" <?php echo ($alumni['salary_range'] ?? '') == 'Above ₱50,000' ? 'selected' : ''; ?>>Above ₱50,000</option>
                </select>
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700">Profile Photo</label>
            <input type="file" name="photo" accept="image/*" class="w-full p-2 border rounded-lg">
            <?php if ($alumni['photo_path']): ?>
                <p class="text-sm text-gray-500">Current: <a href="../Uploads/<?php echo htmlspecialchars($alumni['photo_path']); ?>" target="_blank">View Photo</a></p>
            <?php endif; ?>
        </div>
        <!-- Address Fields (use code for barangay_id) -->
        <div class="mt-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Address Details</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Barangay (Select Code)</label>
                    <select name="barangay_id" class="w-full p-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select Barangay</option>
                        <!-- Populated by phil.min.js or fetch from DB -->
                        <?php
                        // Fetch options from table_barangay for pre-fill
                        $brgy_query = "SELECT barangay_id, barangay_name FROM table_barangay";
                        $brgy_result = $conn->query($brgy_query);
                        while ($brgy = $brgy_result->fetch_assoc()) {
                            $selected = ($alumni['barangay_id'] ?? '') == $brgy['barangay_id'] ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($brgy['barangay_id']) . "' $selected>" . htmlspecialchars($brgy['barangay_name']) . "</option>";
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Street Details</label>
                    <input type="text" name="street_details" value="<?php echo htmlspecialchars($alumni['street_details'] ?? ''); ?>" class="w-full p-2 border rounded-lg focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700">Certificate of Employment</label>
            <input type="file" name="coe" accept=".pdf" class="w-full p-2 border rounded-lg">
            <?php if (isset($documents['COE'])): ?>
                <p class="text-sm text-gray-500">Current: <a href="../Uploads/<?php echo htmlspecialchars($documents['COE']['file_path']); ?>" target="_blank">View COE</a> (Status: <?php echo $documents['COE']['document_status']; ?>)</p>
            <?php endif; ?>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700">Certificate of Registration</label>
            <input type="file" name="cor" accept=".pdf" class="w-full p-2 border rounded-lg">
            <?php if (isset($documents['COR'])): ?>
                <p class="text-sm text-gray-500">Current: <a href="../Uploads/<?php echo htmlspecialchars($documents['COR']['file_path']); ?>" target="_blank">View COR</a> (Status: <?php echo $documents['COR']['document_status']; ?>)</p>
            <?php endif; ?>
        </div>
        <div class="mt-4 <?php echo $alumni['employment_status'] == 'Self-Employed' ? '' : 'hidden'; ?>" id="businessCertField">
            <label class="block text-sm font-medium text-gray-700">Business Certificate</label>
            <input type="file" name="business_cert" accept=".pdf" class="w-full p-2 border rounded-lg">
            <?php if (isset($documents['B_CERT'])): ?>
                <p class="text-sm text-gray-500">Current: <a href="../Uploads/<?php echo htmlspecialchars($documents['B_CERT']['file_path']); ?>" target="_blank">View Business Certificate</a> (Status: <?php echo $documents['B_CERT']['document_status']; ?>)</p>
            <?php endif; ?>
        </div>
        <div class="mt-6 flex space-x-4">
            <button type="submit" class="bg-blue-600 text-white py-2 px-4 rounded-lg hover:bg-blue-700">Save Changes</button>
            <a href="alumni_management.php" class="bg-gray-600 text-white py-2 px-4 rounded-lg hover:bg-gray-700">Cancel</a>
        </div>
    </form>
</div>


<script>
function toggleFields() {
    const status = document.getElementById('employmentStatus').value;
    const employmentFields = document.getElementById('employmentFields');
    const businessCertField = document.getElementById('businessCertField');
    if (['Employed', 'Self-Employed', 'Employed & Student'].includes(status)) {
        employmentFields.classList.remove('hidden');
        businessCertField.classList.toggle('hidden', status !== 'Self-Employed');
    } else {
        employmentFields.classList.add('hidden');
        businessCertField.classList.add('hidden');
    }
}

function validateForm() {
    const form = document.getElementById('editAlumniForm');
    const status = document.getElementById('employmentStatus').value;
    const jobTitle = form.querySelector('[name="job_title_id"]').value;
    const companyName = form.querySelector('[name="company_name"]').value;
    const photo = form.querySelector('[name="photo"]').files[0];
    const coe = form.querySelector('[name="coe"]').files[0];
    const cor = form.querySelector('[name="cor"]').files[0];
    const businessCert = form.querySelector('[name="business_cert"]').files[0];

    if (['Employed', 'Self-Employed', 'Employed & Student'].includes(status)) {
        if (!jobTitle || !companyName) {
            showToast('Job title and company name are required for employed status', 'error');
            return false;
        }
    }
    if (photo && !['image/jpeg', 'image/png'].includes(photo.type)) {
        showToast('Profile photo must be JPEG or PNG', 'error');
        return false;
    }
    if (coe && coe.type !== 'application/pdf') {
        showToast('COE must be a PDF file', 'error');
        return false;
    }
    if (cor && cor.type !== 'application/pdf') {
        showToast('COR must be a PDF file', 'error');
        return false;
    }
    if (businessCert && businessCert.type !== 'application/pdf') {
        showToast('Business certificate must be a PDF file', 'error');
        return false;
    }
    return true;
}

document.addEventListener("DOMContentLoaded", () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        showToast(urlParams.get('success'));
    } else if (urlParams.has('error')) {
        showToast(urlParams.get('error'), 'error');
    }
});
</script>
<?php
$page_content = ob_get_clean();
include("admin_format.php");
?>