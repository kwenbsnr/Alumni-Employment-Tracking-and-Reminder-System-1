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

 <!-- Stats Grid: 2x2 Layout (enhanced) -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-7xl mx-auto px-4">

    <!-- ==== PROFILE STATUS ==== -->
    <div class="relative overflow-hidden bg-gradient-to-br from-amber-50 via-amber-100 to-amber-200 
                rounded-2xl shadow-xl p-10 flex flex-col justify-between 
                hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300 
                border-t-4 <?php echo $is_profile_complete ? 'border-green-600' : 'border-amber-600'; ?>">
        <div class="absolute inset-0 opacity-10 pointer-events-none"
             style="background: radial-gradient(circle at 30% 30%, rgba(255,255,255,.8) 0%, transparent 70%);"></div>

        <div class="flex items-center justify-between mb-6">
            <h3 class="text-base font-bold tracking-wider text-gray-700 uppercase">Profile Status</h3>
            <i class="fas fa-user-check text-4xl <?php echo $is_profile_complete ? 'text-green-600' : 'text-amber-600'; ?>"></i>
        </div>

        <p class="text-5xl font-extrabold text-gray-900"><?php echo $is_profile_complete ? 'Complete' : 'Incomplete'; ?></p>
        <p class="mt-4 text-sm text-gray-600">
            <?php echo $is_profile_complete ? 'All required fields filled' : 'Required fields missing'; ?>
        </p>
    </div>

    <!-- ==== EMPLOYMENT STATUS ==== -->
    <div class="relative overflow-hidden bg-gradient-to-br from-emerald-50 via-emerald-100 to-emerald-200 
                rounded-2xl shadow-xl p-10 flex flex-col justify-between 
                hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300 
                border-t-4 border-emerald-600">
        <div class="absolute inset-0 opacity-10 pointer-events-none"
             style="background: radial-gradient(circle at 70% 30%, rgba(255,255,255,.8) 0%, transparent 70%);"></div>

        <div class="flex items-center justify-between mb-6">
            <h3 class="text-base font-bold tracking-wider text-gray-700 uppercase">Employment Status</h3>
            <i class="fas fa-briefcase text-4xl text-emerald-600"></i>
        </div>

        <p class="text-5xl font-extrabold text-gray-900"><?php echo htmlspecialchars($profile['employment_status']); ?></p>
        <p class="mt-4 text-sm text-gray-600">Latest reported status.</p>
    </div>

    <!-- ==== DOCUMENT STATUS ==== -->
    <div class="relative overflow-hidden bg-gradient-to-br from-sky-50 via-sky-100 to-sky-200 
                rounded-2xl shadow-xl p-10 flex flex-col justify-between 
                hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300 
                border-t-4 border-sky-600">
        <div class="absolute inset-0 opacity-10 pointer-events-none"
             style="background: radial-gradient(circle at 30% 70%, rgba(255,255,255,.8) 0%, transparent 70%);"></div>

        <div class="flex items-center justify-between mb-6">
            <h3 class="text-base font-bold tracking-wider text-gray-700 uppercase">Document Status</h3>
            <i class="fas fa-file-alt text-4xl text-sky-600"></i>
        </div>

        <p class="text-5xl font-extrabold text-gray-900"><?php echo htmlspecialchars($document['submission_status']); ?></p>
        <p class="mt-4 text-sm text-gray-600"><?php echo htmlspecialchars($document['message']); ?></p>
    </div>

    <!-- ==== DOCUMENTS UPLOADED ==== -->
    <div class="relative overflow-hidden 
                <?php echo $document['document_count'] > 0 
                    ? 'bg-gradient-to-br from-teal-50 via-teal-100 to-teal-200 border-t-4 border-teal-600' 
                    : 'bg-gradient-to-br from-rose-50 via-rose-100 to-rose-200 border-t-4 border-rose-600'; ?>
                rounded-2xl shadow-xl p-10 flex flex-col justify-between 
                hover:shadow-2xl transform hover:-translate-y-1 transition-all duration-300">
        <div class="absolute inset-0 opacity-10 pointer-events-none"
             style="background: radial-gradient(circle at 70% 70%, rgba(255,255,255,.8) 0%, transparent 70%);"></div>

        <div class="flex items-center justify-between mb-6">
            <h3 class="text-base font-bold tracking-wider text-gray-700 uppercase">Documents</h3>
            <i class="fas fa-paperclip text-4xl <?php echo $document['document_count'] > 0 ? 'text-teal-600' : 'text-rose-600'; ?>"></i>
        </div>

        <p class="text-5xl font-extrabold text-gray-900"><?php echo $document['document_count']; ?></p>
        <p class="mt-4 text-sm text-gray-600">
            <?php echo $document['document_count'] > 0 ? 'Files uploaded' : 'No documents yet'; ?>
        </p>
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