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

// Fetch alumni info for full_name, year_graduated, and last_profile_update
$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, year_graduated, last_profile_update, employment_status FROM alumni_profile WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile_info = $result->fetch_assoc() ?: [];
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

// Determine if profile needs completion or annual update
$needs_profile_update = empty($profile_info) || ($profile_info && ($profile_info['last_profile_update'] === null || strtotime($profile_info['last_profile_update'] . ' +1 year') <= time()));

// Set defaults for profile and document (always, even if no profile)
$profile = ['employment_status' => $profile_info['employment_status'] ?? 'Not Set'];
$document = ['document_status' => 'No Document'];

// Fetch latest document status (if profile exists; else use default)
if (!empty($profile_info)) {
    $stmt = $conn->prepare("SELECT document_status FROM alumni_documents WHERE user_id = ? ORDER BY doc_id DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $document = $result->fetch_assoc() ?: ['document_status' => 'No Document'];
}

ob_start();
?>

<!-- Dashboard Section -->
<div class="space-y-6">
    <?php if ($needs_profile_update): ?>
        <div class="bg-yellow-100 p-6 rounded-xl shadow-lg border-l-4 border-yellow-600 flex items-center space-x-3">
            <i class="fas fa-exclamation-circle text-yellow-600 text-xl"></i>
            <div>
                <h3 class="text-lg font-semibold text-yellow-800"><?php echo empty($profile_info) ? 'Complete Your Profile' : 'Annual Profile Update Required'; ?></h3>
                <p class="text-yellow-700">Please <?php echo empty($profile_info) ? 'fill out' : 'update'; ?> your profile details in <a href="alumni_profile.php" class="text-green-600 hover:text-green-800"> <strong>Profile Management</strong></a> .</p>
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

    <!-- Stats Grid (always shown, with defaults if no profile) -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Employment Status -->
        <div class="bg-white p-6 rounded-xl shadow-lg flex flex-col justify-between hover:shadow-xl transition duration-200 border-t-4 border-green-500">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-600 uppercase">Employment Status</h3>
                <i class="fas fa-briefcase text-xl text-green-500"></i>
            </div>
            <p class="text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($profile['employment_status']); ?></p>
            <p class="text-sm text-gray-500 mt-2">Latest reported status.</p>
        </div>

        <!-- Document Status -->
        <div class="bg-white p-6 rounded-xl shadow-lg flex flex-col justify-between hover:shadow-xl transition duration-200 border-t-4 border-blue-500">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-600 uppercase">Document Status</h3>
                <i class="fas fa-file-alt text-xl text-blue-500"></i>
            </div>
            <p class="text-3xl font-bold text-gray-900"><?php echo htmlspecialchars($document['document_status']); ?></p>
            <p class="text-sm text-gray-500 mt-2">Awaiting administrator review.</p>
        </div>

        <!-- Job Opportunities -->
        <!-- <div class="bg-white p-6 rounded-xl shadow-lg flex flex-col justify-between hover:shadow-xl transition duration-200 border-t-4 border-yellow-500">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-600 uppercase">Job Opportunities</h3>
                <i class="fas fa-search-plus text-xl text-yellow-500"></i>
            </div>
            <p class="text-3xl font-bold text-gray-900">42</p>
            <p class="text-sm text-gray-500 mt-2">New postings this month.</p>
        </div> -->

        <!-- Upcoming Events -->
        <!-- <div class="bg-white p-6 rounded-xl shadow-lg flex flex-col justify-between hover:shadow-xl transition duration-200 border-t-4 border-red-500">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-600 uppercase">Upcoming Events</h3>
                <i class="fas fa-calendar-alt text-xl text-red-500"></i>
            </div>
            <p class="text-3xl font-bold text-gray-900">3</p>
            <p class="text-sm text-gray-500 mt-2">Career fair and gatherings.</p>
        </div> -->
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
            }, 1000); // Wait for fade-out animation
        }, 5000); // Display for 5 seconds
    }
});
</script>

<?php
$page_content = ob_get_clean();
include("alumni_format.php");
?>