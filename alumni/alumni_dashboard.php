<?php
// alumni_dashboard.php
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

// Determine profile completion
$is_profile_complete = !empty($profile_info) &&
    !empty($profile_info['first_name']) &&
    !empty($profile_info['last_name']) &&
    !empty($profile_info['contact_number']) &&
    !empty($profile_info['year_graduated']) &&
    !empty($profile_info['employment_status']) &&
    !empty($profile_info['address_id']);

// Annual update check
$needs_annual_update = !empty($profile_info) &&
    ($profile_info['last_profile_update'] === null ||
     strtotime($profile_info['last_profile_update'] . ' +1 year') <= time());

$needs_profile_update = empty($profile_info) || !$is_profile_complete || $needs_annual_update;

// Profile & Document status
$profile = [
    'employment_status' => $profile_info['employment_status'] ?? 'Not Set',
    'submission_status' => $profile_info['submission_status'] ?? 'Not Submitted'
];
$document = [
    'submission_status' => $profile_info['submission_status'] ?? 'No Profile',
    'document_count' => $profile_info['document_count'] ?? 0
];

// Enhanced document status
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
        <!-- existing warning -->
    <?php endif; ?>

    <!-- ADD SUCCESS CARD HERE -->
    <?php if (isset($_SESSION['profile_submission_success'])): ?>
        <div id="successCard" class="bg-green-100 p-6 rounded-xl shadow-lg border-l-4 border-green-600 flex items-center space-x-3 animate-fade-in">
            <div class="text-green-600 text-2xl">✅</div>
            <div>
                <h3 class="text-lg font-semibold text-green-800">Submission Successful!</h3>
                <p class="text-green-700">Your profile has been submitted for review.</p>
            </div>
            <button id="closeSuccessCard" class="ml-auto text-green-600 hover:text-green-800 text-xl font-bold">×</button>
        </div>
        <?php unset($_SESSION['profile_submission_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['show_welcome'])): ?>
        <div id="welcomeCard" class="bg-green-100 p-6 rounded-xl shadow-lg border-l-4 border-green-600">
            <h2 class="text-3xl font-bold text-green-800 mb-2">Welcome Back, <?php echo htmlspecialchars($full_name); ?>!</h2>
            <p class="text-green-700">Your network and resources are waiting. Check your quick stats below.</p>
        </div>
        <?php unset($_SESSION['show_welcome']); ?>
    <?php endif; ?>

    <!-- TWO-COLUMN LAYOUT: Stats (60%) + Quick Actions (40%) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-7xl mx-auto">
        <!-- LEFT: Stats Cards (2 columns, ~60%) -->
        <div class="lg:col-span-2 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <!-- Profile Status Card -->
                <div class="bg-white rounded-2xl shadow-md p-6 border-l-5 <?php echo $is_profile_complete ? 'border-green-500' : 'border-amber-500'; ?> hover:shadow-lg transition-all duration-300 dashboard-card">
                    <div class="flex items-center justify-between mb-3">
                        <div class="p-3 rounded-full <?php echo $is_profile_complete ? 'bg-green-100 text-green-600' : 'bg-amber-100 text-amber-600'; ?>">
                            <i class="fas fa-user-check text-xl"></i>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 rounded-full <?php echo $is_profile_complete ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'; ?>">
                            <?php echo $is_profile_complete ? 'Complete' : 'Incomplete'; ?>
                        </span>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-1">Profile Status</h3>
                    <p class="text-sm text-gray-600 leading-relaxed">
                        <?php echo $is_profile_complete ? 'Your profile is fully updated.' : 'Some required fields are missing.'; ?>
                    </p>
                    <div class="mt-4">
                        <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                            <span>Completion</span>
                            <span><?php echo $is_profile_complete ? '100' : '60'; ?>%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="progress-bar <?php echo $is_profile_complete ? 'bg-green-500' : 'bg-amber-500'; ?> h-2 rounded-full transition-all duration-700"
                                 style="width: <?php echo $is_profile_complete ? '100' : '60'; ?>%"></div>
                        </div>
                    </div>
                </div>

                <!-- Employment Status Card -->
                <div class="bg-white rounded-2xl shadow-md p-6 border-l-5 border-blue-500 hover:shadow-lg transition-all duration-300 dashboard-card">
                    <div class="flex items-center justify-between mb-3">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                            <i class="fas fa-briefcase text-xl"></i>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 rounded-full bg-blue-100 text-blue-700">Current</span>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-1">Employment</h3>
                    <p class="text-sm text-gray-600 font-medium"><?php echo htmlspecialchars($profile['employment_status']); ?></p>
                    <p class="text-xs text-gray-500 mt-3 flex items-center">
                        <i class="fas fa-sync-alt mr-1"></i> Updated recently
                    </p>
                </div>

                <!-- Document Status Card -->
                <div class="bg-white rounded-2xl shadow-md p-6 border-l-5 border-purple-500 hover:shadow-lg transition-all duration-300 dashboard-card">
                    <div class="flex items-center justify-between mb-3">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                            <i class="fas fa-file-alt text-xl"></i>
                        </div>
                        <?php
                        $doc_badge = match($document['submission_status']) {
                            'Approved' => ['bg-green-100 text-green-700', 'fas fa-check-circle'],
                            'Rejected' => ['bg-red-100 text-red-700', 'fas fa-times-circle'],
                            'Under Review' => ['bg-yellow-100 text-yellow-700', 'fas fa-clock'],
                            'Draft' => ['bg-blue-100 text-blue-700', 'fas fa-edit'],
                            default => ['bg-gray-100 text-gray-700', 'fas fa-exclamation-triangle']
                        };
                        ?>
                        <span class="text-xs font-bold px-3 py-1 rounded-full <?php echo $doc_badge[0]; ?>">
                            <?php echo htmlspecialchars($document['submission_status']); ?>
                        </span>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-1">Document Review</h3>
                    <p class="text-sm text-gray-600 leading-relaxed"><?php echo htmlspecialchars($document['message']); ?></p>
                    <div class="mt-3 flex items-center justify-between text-xs">
                        <span class="text-gray-500"><?php echo $document['document_count']; ?> file(s)</span>
                        <i class="<?php echo $doc_badge[1]; ?> text-lg"></i>
                    </div>
                </div>

                <!-- Files Uploaded Card -->
                <div class="bg-white rounded-2xl shadow-md p-6 border-l-5 <?php echo $document['document_count'] > 0 ? 'border-teal-500' : 'border-rose-500'; ?> hover:shadow-lg transition-all duration-300 dashboard-card">
                    <div class="flex items-center justify-between mb-3">
                        <div class="p-3 rounded-full <?php echo $document['document_count'] > 0 ? 'bg-teal-100 text-teal-600' : 'bg-rose-100 text-rose-600'; ?>">
                            <i class="fas fa-paperclip text-xl"></i>
                        </div>
                        <span class="text-xs font-bold px-3 py-1 rounded-full <?php echo $document['document_count'] > 0 ? 'bg-teal-100 text-teal-700' : 'bg-rose-100 text-rose-700'; ?>">
                            <?php echo $document['document_count'] > 0 ? 'Uploaded' : 'None'; ?>
                        </span>
                    </div>
                    <h3 class="text-lg font-bold text-gray-800 mb-1">Total Files</h3>
                    <div class="flex items-end justify-between mt-4">
                        <span class="text-3xl font-bold <?php echo $document['document_count'] > 0 ? 'text-teal-600' : 'text-rose-600'; ?>">
                            <?php echo $document['document_count']; ?>
                        </span>
                        <i class="fas <?php echo $document['document_count'] > 0 ? 'fa-folder-open text-teal-500' : 'fa-folder text-rose-500'; ?> text-2xl"></i>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Documents in your profile</p>
                </div>
            </div>
        </div>

        <!-- RIGHT: Quick Actions (~40%) -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-2xl shadow-md p-6 h-full">
                <h3 class="text-xl font-bold text-gray-800 mb-5 flex items-center">
                    <i class="fas fa-bolt text-yellow-500 mr-2"></i> Quick Actions
                </h3>
                <div class="space-y-4">
                    <a href="alumni_profile.php" class="flex items-center p-5 border-2 border-gray-200 rounded-xl hover:border-green-500 hover:bg-green-50 transition-all duration-300 group quick-action-card">
                        <div class="p-3 rounded-lg bg-green-100 text-green-600 mr-4 group-hover:bg-green-200 transition-colors">
                            <i class="fas fa-user-edit text-lg"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-bold text-gray-800 text-lg">Update Profile</h4>
                            <p class="text-sm text-gray-600">Edit personal info, employment, and contact</p>
                        </div>
                        <i class="fas fa-arrow-right ml-auto text-gray-400 group-hover:text-green-600"></i>
                    </a>
                    <a href="alumni_profile.php#documents" class="flex items-center p-5 border-2 border-gray-200 rounded-xl hover:border-blue-500 hover:bg-blue-50 transition-all duration-300 group quick-action-card">
                        <div class="p-3 rounded-lg bg-blue-100 text-blue-600 mr-4 group-hover:bg-blue-200 transition-colors">
                            <i class="fas fa-upload text-lg"></i>
                        </div>
                        <div class="flex-1">
                            <h4 class="font-bold text-gray-800 text-lg">Upload Documents</h4>
                            <p class="text-sm text-gray-600">Submit diploma, TOR, resume, and more</p>
                        </div>
                        <i class="fas fa-arrow-right ml-auto text-gray-400 group-hover:text-blue-600"></i>
                    </a>
                </div>
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
            setTimeout(() => welcomeCard.remove(), 1000);
        }, 5000);
    }
});
</script>
<?php
$page_content = ob_get_clean();
include("alumni_format.php");
?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const welcomeCard = document.getElementById('welcomeCard');
    if (welcomeCard) {
        setTimeout(() => {
            welcomeCard.style.transition = 'opacity 1s ease-out';
            welcomeCard.style.opacity = '0';
            setTimeout(() => welcomeCard.remove(), 1000);
        }, 5000);
    }

    // Success Card Auto-Hide & Close Button
    const successCard = document.getElementById('successCard');
    const closeSuccessBtn = document.getElementById('closeSuccessCard');

    if (successCard) {
        // Auto-hide after 4 seconds
        const autoHide = setTimeout(() => {
            successCard.style.transition = 'opacity 0.6s ease-out';
            successCard.style.opacity = '0';
            setTimeout(() => successCard.remove(), 600);
        }, 4000);

        // Manual close cancels auto-hide
        if (closeSuccessBtn) {
            closeSuccessBtn.addEventListener('click', () => {
                clearTimeout(autoHide);
                successCard.style.transition = 'opacity 0.4s ease-out';
                successCard.style.opacity = '0';
                setTimeout(() => successCard.remove(), 400);
            });
        }
    }
});
</script>