<?php
// Enable error reporting for development (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "alumni") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");
$page_title = "Dashboard";
$active_page = "dashboard";
$user_id = $_SESSION["user_id"];

// Fetch comprehensive alumni profile data
$stmt = $conn->prepare("
    SELECT
        ap.first_name,
        ap.middle_name,
        ap.last_name,
        ap.year_graduated,
        ap.last_profile_update,
        ap.employment_status,
        ap.submission_status,
        ap.address_id,
        ap.contact_number,
        COUNT(ad.doc_id) as document_count
    FROM alumni_profile ap
    LEFT JOIN alumni_documents ad ON ap.user_id = ad.user_id
    WHERE ap.user_id = ?
    GROUP BY ap.user_id
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile_info = $result->fetch_assoc() ?: [];
$stmt->close();

// Build full name
$full_name = 'Alumni';
if (!empty($profile_info)) {
    $full_name = trim(
        ($profile_info['first_name'] ?? '') . ' ' .
        ($profile_info['middle_name'] ?? '') . ' ' .
        ($profile_info['last_name'] ?? '')
    );
    if (empty($full_name)) {
        $full_name = 'Alumni';
    }
}

// Determine if profile needs completion
$is_profile_complete = !empty($profile_info) &&
                      !empty($profile_info['first_name']) &&
                      !empty($profile_info['last_name']) &&
                      !empty($profile_info['contact_number']) &&
                      !empty($profile_info['year_graduated']) &&
                      !empty($profile_info['employment_status']) &&
                      !empty($profile_info['address_id']);

// Determine if profile needs annual update
$needs_annual_update = !empty($profile_info) &&
                      ($profile_info['last_profile_update'] === null ||
                       strtotime($profile_info['last_profile_update'] . ' +1 year') <= time());

// Overall update needed
$needs_profile_update = empty($profile_info) || !$is_profile_complete || $needs_annual_update;

// Set profile and document status
$profile = [
    'employment_status' => $profile_info['employment_status'] ?? 'Not Set',
    'submission_status' => $profile_info['submission_status'] ?? 'Not Submitted'
];
$document = [
    'submission_status' => $profile_info['submission_status'] ?? 'No Profile',
    'document_count' => $profile_info['document_count'] ?? 0
];

// Enhanced document status logic
if (!empty($profile_info)) {
    if ($profile_info['submission_status'] === 'Approved') {
        $document['submission_status'] = 'Approved';
        $document['message'] = 'All documents approved';
    } elseif ($profile_info['submission_status'] === 'Rejected') {
        $document['submission_status'] = 'Rejected';
        $document['message'] = 'Needs resubmission';
    } elseif ($profile_info['submission_status'] === 'Pending') {
        $document['submission_status'] = 'Under Review';
        $document['message'] = 'Awaiting administrator review';
    } elseif ($document['document_count'] > 0) {
        $document['submission_status'] = 'Draft';
        $document['message'] = 'Ready for submission';
    } else {
        $document['submission_status'] = 'No Documents';
        $document['message'] = 'Upload required documents';
    }
} else {
    $document['submission_status'] = 'No Profile';
    $document['message'] = 'Complete your profile first';
}

ob_start();
?>
<!-- Dashboard Section -->
<div class="space-y-6">
    <?php if ($needs_profile_update): ?>
        <div class="bg-yellow-100 p-6 rounded-xl shadow-lg border-l-4 border-yellow-600 flex items-center space-x-3">
            <i class="fas fa-exclamation-circle text-yellow-600 text-xl"></i>
            <div>
                <h3 class="text-lg font-semibold text-yellow-800">
                    <?php
                    if (empty($profile_info)) {
                        echo 'Complete Your Profile';
                    } elseif (!$is_profile_complete) {
                        echo 'Profile Incomplete';
                    } else {
                        echo 'Annual Profile Update Required';
                    }
                    ?>
                </h3>
                <p class="text-yellow-700">
                    Please
                    <?php
                    if (empty($profile_info)) {
                        echo 'fill out your profile details';
                    } elseif (!$is_profile_complete) {
                        echo 'complete all required profile fields';
                    } else {
                        echo 'update your profile details';
                    }
                    ?>
                    in <a href="alumni_profile.php" class="text-green-600 hover:text-green-800 font-semibold">Profile Management</a>.
                </p>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['show_welcome'])): ?>
        <!-- Welcome Card (displayed briefly on login) -->
        <div id="welcomeCard" class="bg-green-100 p-6 rounded-xl shadow-lg border-l-4 border-green-600">
            <h2 class="text-3xl font-bold text-green-800 mb-2">Welcome Back, <?php echo htmlspecialchars($full_name); ?>!</h2>
            <p class="text-green-700">Your network and resources are waiting. Check your quick stats below.</p>
        </div>
        <?php unset($_SESSION['show_welcome']); ?>
    <?php endif; ?>

    <!-- Enhanced Stats Grid: Compact and Elegant -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 max-w-7xl mx-auto">
        
        <!-- Profile Status Card -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 <?php echo $is_profile_complete ? 'border-green-500' : 'border-amber-500'; ?> hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 rounded-xl <?php echo $is_profile_complete ? 'bg-green-50 text-green-600' : 'bg-amber-50 text-amber-600'; ?>">
                    <i class="fas fa-user-check text-lg"></i>
                </div>
                <span class="text-xs font-semibold px-2 py-1 rounded-full <?php echo $is_profile_complete ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'; ?>">
                    <?php echo $is_profile_complete ? 'Complete' : 'Action Needed'; ?>
                </span>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 mb-2">Profile</h3>
            <p class="text-sm text-gray-600 mb-1"><?php echo $is_profile_complete ? 'All details filled' : 'Required fields missing'; ?></p>
            <div class="w-full bg-gray-200 rounded-full h-2 mt-3">
                <div class="<?php echo $is_profile_complete ? 'bg-green-500' : 'bg-amber-500'; ?> h-2 rounded-full" style="width: <?php echo $is_profile_complete ? '100' : '60'; ?>%"></div>
            </div>
        </div>

        <!-- Employment Status Card -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-blue-500 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 rounded-xl bg-blue-50 text-blue-600">
                    <i class="fas fa-briefcase text-lg"></i>
                </div>
                <span class="text-xs font-semibold px-2 py-1 rounded-full bg-blue-100 text-blue-800">
                    Status
                </span>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 mb-2">Employment</h3>
            <p class="text-sm text-gray-600 mb-1"><?php echo htmlspecialchars($profile['employment_status']); ?></p>
            <div class="flex items-center mt-3 text-xs text-gray-500">
                <i class="fas fa-clock mr-1"></i>
                <span>Latest update</span>
            </div>
        </div>

        <!-- Document Status Card -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-purple-500 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 rounded-xl bg-purple-50 text-purple-600">
                    <i class="fas fa-file-alt text-lg"></i>
                </div>
                <?php
                $doc_status_class = 'bg-gray-100 text-gray-800';
                $doc_icon_class = 'text-gray-500';
                if ($document['submission_status'] === 'Approved') {
                    $doc_status_class = 'bg-green-100 text-green-800';
                    $doc_icon_class = 'text-green-500';
                } elseif ($document['submission_status'] === 'Rejected') {
                    $doc_status_class = 'bg-red-100 text-red-800';
                    $doc_icon_class = 'text-red-500';
                } elseif ($document['submission_status'] === 'Under Review') {
                    $doc_status_class = 'bg-yellow-100 text-yellow-800';
                    $doc_icon_class = 'text-yellow-500';
                }
                ?>
                <span class="text-xs font-semibold px-2 py-1 rounded-full <?php echo $doc_status_class; ?>">
                    <?php echo htmlspecialchars($document['submission_status']); ?>
                </span>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 mb-2">Documents</h3>
            <p class="text-sm text-gray-600 mb-1"><?php echo htmlspecialchars($document['message']); ?></p>
            <div class="flex items-center justify-between mt-3">
                <span class="text-xs text-gray-500"><?php echo $document['document_count']; ?> files</span>
                <i class="fas <?php echo $document['document_count'] > 0 ? 'fa-check-circle text-green-500' : 'fa-exclamation-triangle text-amber-500'; ?>"></i>
            </div>
        </div>

        <!-- Documents Count Card -->
        <div class="bg-white rounded-2xl shadow-lg p-6 border-l-4 border-teal-500 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
            <div class="flex items-center justify-between mb-4">
                <div class="p-3 rounded-xl bg-teal-50 text-teal-600">
                    <i class="fas fa-paperclip text-lg"></i>
                </div>
                <span class="text-xs font-semibold px-2 py-1 rounded-full <?php echo $document['document_count'] > 0 ? 'bg-teal-100 text-teal-800' : 'bg-rose-100 text-rose-800'; ?>">
                    <?php echo $document['document_count'] > 0 ? 'Uploaded' : 'Empty'; ?>
                </span>
            </div>
            <h3 class="text-2xl font-bold text-gray-800 mb-2">Files</h3>
            <p class="text-sm text-gray-600 mb-1">Total documents uploaded</p>
            <div class="flex items-center justify-between mt-3">
                <span class="text-3xl font-bold <?php echo $document['document_count'] > 0 ? 'text-teal-600' : 'text-rose-600'; ?>">
                    <?php echo $document['document_count']; ?>
                </span>
                <div class="text-2xl <?php echo $document['document_count'] > 0 ? 'text-teal-500' : 'text-rose-500'; ?>">
                    <i class="fas <?php echo $document['document_count'] > 0 ? 'fa-folder-open' : 'fa-folder'; ?>"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Section -->
    <div class="max-w-7xl mx-auto mt-8">
        <div class="bg-white rounded-2xl shadow-lg p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4">Quick Actions</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <a href="alumni_profile.php" class="flex items-center p-4 border border-gray-200 rounded-xl hover:border-green-500 hover:bg-green-50 transition-all duration-300 group">
                    <div class="p-3 rounded-lg bg-green-100 text-green-600 mr-4 group-hover:bg-green-200 transition-colors">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-800">Update Profile</h4>
                        <p class="text-sm text-gray-600">Keep your information current</p>
                    </div>
                </a>
                <a href="alumni_profile.php#documents" class="flex items-center p-4 border border-gray-200 rounded-xl hover:border-blue-500 hover:bg-blue-50 transition-all duration-300 group">
                    <div class="p-3 rounded-lg bg-blue-100 text-blue-600 mr-4 group-hover:bg-blue-200 transition-colors">
                        <i class="fas fa-upload"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-800">Upload Documents</h4>
                        <p class="text-sm text-gray-600">Submit required files</p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const welcomeCard = document.getElementById('welcomeCard');
    if (welcomeCard) {
        setTimeout(() => {
            welcomeCard.style.transition = 'opacity 1s ease-out';
            welcomeCard.style.opacity = '0';
            setTimeout(() => {
                welcomeCard.style.display = 'none';
            }, 1000);
        }, 5000);
    }
});
</script>
<?php
$page_content = ob_get_clean();
include("alumni_format.php");
?>