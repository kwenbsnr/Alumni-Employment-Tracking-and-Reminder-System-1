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

// --- NEW CODE: CLEAR & CORRECT ---
// Check 7 required fields
$fields_complete = !empty($profile_info) &&
    !empty($profile_info['first_name']) &&
    !empty($profile_info['last_name']) &&
    !empty($profile_info['contact_number']) &&
    !empty($profile_info['year_graduated']) &&
    !empty($profile_info['employment_status']) &&
    !empty($profile_info['address_id']);

// Check photo
$has_photo = false;
$stmt_photo = $conn->prepare("SELECT photo_path FROM alumni_profile WHERE user_id = ?");
if ($stmt_photo) {
    $stmt_photo->bind_param("i", $user_id);
    $stmt_photo->execute();
    $photo_result = $stmt_photo->get_result();
    $photo_data = $photo_result->fetch_assoc();
    $stmt_photo->close();
    $has_photo = !empty($photo_data['photo_path']);
}

// Count completed items (7 fields + 1 photo = 8 total)
$completed_count = 0;
$total_fields = 8;

if (!empty($profile_info)) {
    if (!empty($profile_info['first_name'])) $completed_count++;
    if (!empty($profile_info['last_name'])) $completed_count++;
    if (!empty($profile_info['contact_number'])) $completed_count++;
    if (!empty($profile_info['year_graduated'])) $completed_count++;
    if (!empty($profile_info['employment_status'])) $completed_count++;
    if (!empty($profile_info['address_id'])) $completed_count++;
}
if ($has_photo) $completed_count++;

// Calculate percentage
$completion_percentage = $total_fields > 0 ? round(($completed_count / $total_fields) * 100) : 0;

// Profile is 100% only if all 7 fields + photo are filled
$is_profile_complete = $fields_complete && $has_photo;

// Final display status
$profile_status = 'Incomplete';
if ($is_profile_complete) {
    $submission_status = $profile_info['submission_status'] ?? 'Not Submitted';
    $profile_status = ($submission_status === 'Pending') ? 'Pending Approval' : 'Complete';
}

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
        <!-- Profile update warning would go here -->
    <?php endif; ?>

    <!-- Success Card -->
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

    <!-- Welcome Card -->
    <?php if (isset($_SESSION['show_welcome'])): ?>
        <div id="welcomeCard" class="bg-green-100 p-6 rounded-xl shadow-lg border-l-4 border-green-600">
            <h2 class="text-3xl font-bold text-green-800 mb-2">Welcome Back, <?php echo htmlspecialchars($full_name); ?>!</h2>
            <p class="text-green-700">Your network and resources are waiting. Check your quick stats below.</p>
        </div>
        <?php unset($_SESSION['show_welcome']); ?>
    <?php endif; ?>
<!-- TWO-COLUMN LAYOUT: Stats (60%) + Quick Actions (40%) -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-7xl mx-auto">
    <!-- LEFT: 4 Cards (2x2 Grid, ~60%) -->
    <div class="lg:col-span-2">
        <!-- MODERN 2x2 DASHBOARD CARDS - EQUAL HEIGHT, FULLY RESPONSIVE -->
        <div class="max-w-6xl mx-auto">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                
<!-- CARD 1: Profile Completion -->
<div class="h-full flex flex-col">
    <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg border <?php
        echo $profile_status === 'Complete' ? 'border-emerald-400 ring-2 ring-emerald-100' :
             ($profile_status === 'Pending Approval' ? 'border-amber-400 ring-2 ring-amber-100' : 'border-orange-500 ring-2 ring-orange-200');
    ?> overflow-hidden flex flex-col h-full hover:shadow-xl transition-all duration-400 group relative">
      
        <!-- ALERT: Incomplete (More noticeable animation) -->
        <?php if ($profile_status === 'Incomplete'): ?>
            <div class="absolute top-4 right-4 z-20 pointer-events-none">
                <div class="relative">
                    <!-- Triple pulse rings -->
                    <div class="absolute inset-0 w-12 h-12 bg-red-200 rounded-full opacity-80 animate-ping-slow"></div>
                    <div class="absolute inset-0 w-12 h-12 bg-orange-200 rounded-full opacity-60 animate-ping-slow delay-300"></div>
                    <div class="absolute inset-0 w-12 h-12 bg-orange-100 rounded-full opacity-40 animate-ping-slow delay-600"></div>
                    <!-- Main icon with bounce and glow -->
                    <div class="relative w-12 h-12 bg-gradient-to-br from-red-500 to-orange-600 rounded-full flex items-center justify-center shadow-lg ring-2 ring-red-300 animate-bounce-gentle">
                        <i class="fas fa-exclamation text-white text-base font-bold drop-shadow-sm"></i>
                    </div>
                    <!-- Floating dot -->
                    <div class="absolute -top-1 -right-1 w-3 h-3 bg-white rounded-full animate-pulse-fast"></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- SUCCESS CHECKMARK (More celebratory animation) -->
        <?php if ($profile_status === 'Complete'): ?>
            <div class="absolute top-4 right-4 z-20 pointer-events-none">
                <div class="relative">
                    <!-- Enhanced pulse rings -->
                    <div class="absolute inset-0 w-12 h-12 bg-emerald-200 rounded-full opacity-70 animate-ping-slow"></div>
                    <div class="absolute inset-0 w-12 h-12 bg-emerald-100 rounded-full opacity-50 animate-ping-slow delay-400"></div>
                    <div class="absolute inset-0 w-12 h-12 bg-emerald-50 rounded-full opacity-30 animate-ping-slow delay-800"></div>
                    <!-- Bouncing checkmark with glow -->
                    <div class="relative w-12 h-12 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-full flex items-center justify-center shadow-lg ring-2 ring-emerald-300 animate-bounce-gentle">
                        <i class="fas fa-check text-white text-lg font-bold drop-shadow-sm"></i>
                    </div>
                    <!-- Sparkle effects -->
                    <div class="absolute -top-1 -left-1 w-2 h-2 bg-yellow-300 rounded-full animate-ping-fast"></div>
                    <div class="absolute -bottom-1 -right-1 w-2 h-2 bg-yellow-200 rounded-full animate-ping-fast delay-300"></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- PENDING APPROVAL: Enhanced clock animation -->
        <?php if ($profile_status === 'Pending Approval'): ?>
            <div class="absolute top-4 right-4 z-20 pointer-events-none">
                <div class="relative">
                    <!-- Multi-layered pulse rings -->
                    <div class="absolute inset-0 w-12 h-12 bg-amber-200 rounded-full opacity-70 animate-ping-slow"></div>
                    <div class="absolute inset-0 w-12 h-12 bg-amber-150 rounded-full opacity-50 animate-ping-slow delay-200"></div>
                    <div class="absolute inset-0 w-12 h-12 bg-amber-100 rounded-full opacity-30 animate-ping-slow delay-400"></div>
                    <!-- Clock with enhanced spin and glow -->
                    <div class="relative w-12 h-12 bg-gradient-to-br from-amber-500 to-orange-500 rounded-full flex items-center justify-center shadow-lg ring-2 ring-amber-300 animate-spin-slow">
                        <div class="absolute inset-0 flex items-center justify-center">
                            <i class="fas fa-clock text-white text-base font-bold drop-shadow-sm"></i>
                        </div>
                    </div>
                    <!-- Moving dots around clock -->
                    <div class="absolute -top-1 -right-1 w-2 h-2 bg-yellow-300 rounded-full animate-orbit-slow"></div>
                    <div class="absolute -bottom-1 -left-1 w-2 h-2 bg-yellow-200 rounded-full animate-orbit-slow delay-500"></div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="p-6 pb-4 space-y-4 flex flex-col flex-1">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="p-3 <?php
                        echo $profile_status === 'Complete' ? 'bg-emerald-600' :
                             ($profile_status === 'Pending Approval' ? 'bg-amber-600' : 'bg-orange-600');
                    ?> rounded-xl text-white shadow-md transform group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-user-check text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-extrabold <?php echo $profile_status === 'Complete' ? 'text-gray-900' : 'text-orange-900'; ?>">
                            Profile Completion
                        </h3>
                        <div class="mt-1.5">
                            <span class="inline-flex items-center px-3 py-1 text-xs font-bold tracking-wider rounded-full uppercase <?php
                                echo $profile_status === 'Complete' ? 'bg-emerald-100 text-emerald-800' :
                                     ($profile_status === 'Pending Approval' ? 'bg-amber-100 text-amber-800' : 'bg-orange-100 text-orange-800');
                            ?> shadow-sm">
                                <?php echo $profile_status; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex-1">
                <p class="text-sm <?php echo $profile_status === 'Complete' ? 'text-gray-600' : 'text-orange-700'; ?> leading-relaxed font-medium">
                    <?php
                    if ($profile_status === 'Complete') {
                        echo 'Congratulations! Your profile is fully verified.';
                    } elseif ($profile_status === 'Pending Approval') {
                        echo 'Submitted! Admin review in progress...';
                    } else {
                        echo 'Action needed: Complete your profile to unlock all features.';
                    }
                    ?>
                </p>
            </div>

            <div class="space-y-3">
                <div class="flex justify-between text-xs font-semibold <?php echo $profile_status === 'Complete' ? 'text-gray-500' : 'text-orange-700'; ?>">
                    <span>Completion Progress</span>
                    <span><?php echo $completion_percentage; ?>%</span>
                </div>
                <?php if ($profile_status === 'Complete'): ?>
                    <div class="text-center text-emerald-600 text-xs font-medium">
                        <i class="fas fa-sparkles mr-1"></i> Fully Verified
                    </div>
                <?php else: ?>
                    <div class="relative w-full h-3 bg-gray-200 rounded-full overflow-hidden shadow-inner">
                        <div class="absolute inset-0 bg-gradient-to-r <?php
                            echo $completion_percentage >= 90 ? 'from-emerald-500 to-teal-500' :
                                 ($completion_percentage >= 70 ? 'from-orange-500 to-yellow-500' : 'from-orange-600 to-red-500');
                        ?> h-full rounded-full transition-all duration-1000 ease-out transform origin-left"
                             style="width: <?php echo $completion_percentage; ?>%"></div>
                        <div class="absolute inset-0 bg-white/30 backdrop-blur-sm h-full rounded-full animate-pulse"></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <a href="alumni_profile.php" class="mt-auto block text-center py-3.5 px-6 text-white text-sm font-bold tracking-wide
            <?php
            echo $profile_status === 'Complete' ? 'bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700' :
                 ($profile_status === 'Pending Approval' ? 'bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-700 hover:to-orange-700' : 'bg-gradient-to-r from-orange-600 to-red-600 hover:from-orange-700 hover:to-red-700');
            ?> transition-all duration-300 rounded-b-2xl flex items-center justify-center space-x-1 group">
            <span>
                <?php
                echo $profile_status === 'Complete' ? 'View Profile' :
                     ($profile_status === 'Pending Approval' ? 'Track Submission' : 'Complete Now');
                ?>
            </span>
            <i class="fas fa-arrow-right text-sm transform group-hover:translate-x-1 transition-transform"></i>
        </a>
    </div>
</div>

<!-- Enhanced Custom Animations -->
<style>
@keyframes spin-slow {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}
.animate-spin-slow {
  animation: spin-slow 2.5s linear infinite;
}

@keyframes ping-slow {
  0% { transform: scale(1); opacity: 0.7; }
  75%, 100% { transform: scale(2); opacity: 0; }
}
.animate-ping-slow {
  animation: ping-slow 2s cubic-bezier(0, 0, 0.2, 1) infinite;
}

@keyframes ping-fast {
  0% { transform: scale(1); opacity: 1; }
  50%, 100% { transform: scale(2); opacity: 0; }
}
.animate-ping-fast {
  animation: ping-fast 1s cubic-bezier(0, 0, 0.2, 1) infinite;
}

@keyframes bounce-gentle {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-5px); }
}
.animate-bounce-gentle {
  animation: bounce-gentle 2s ease-in-out infinite;
}

@keyframes orbit-slow {
  0% { transform: rotate(0deg) translateX(8px) rotate(0deg); }
  100% { transform: rotate(360deg) translateX(8px) rotate(-360deg); }
}
.animate-orbit-slow {
  animation: orbit-slow 3s linear infinite;
}

/* Enhanced glow effects */
.ring-2 {
  box-shadow: 0 0 10px rgba(255, 255, 255, 0.3);
}
.drop-shadow-sm {
  filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.3));
}
</style>
<!-- CARD 2: Employment -->
<div class="h-full flex flex-col">
    <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg border <?php
        echo !empty($profile_info['employment_status']) && $profile_info['employment_status'] !== 'Not Set'
            ? 'border-blue-400 ring-2 ring-blue-100'
            : 'border-gray-300 ring-2 ring-gray-100';
    ?> overflow-hidden flex flex-col h-full hover:shadow-xl transition-all duration-400 group relative">

        <!-- ALERT: Not Set (Upper-right inside card - NO ANIMATION, PROPERLY CENTERED) -->
        <?php if (empty($profile_info['employment_status']) || $profile_info['employment_status'] === 'Not Set'): ?>
            <div class="absolute top-3 right-3 w-9 h-9 bg-gradient-to-br from-gray-500 to-gray-700 rounded-full flex items-center justify-center text-white shadow-lg z-20">
                <i class="fas fa-question text-sm"></i>
            </div>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="p-6 pb-4 flex-1 flex flex-col justify-between space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="p-3 <?php
                        echo !empty($profile_info['employment_status']) && $profile_info['employment_status'] !== 'Not Set'
                            ? 'bg-gradient-to-br from-blue-600 to-cyan-600'
                            : 'bg-gradient-to-br from-gray-500 to-gray-700';
                    ?> rounded-xl text-white shadow-md transform group-hover:scale-110 transition-all duration-300">
                        <i class="fas fa-briefcase text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-extrabold text-gray-900">Employment</h3>
                        <div class="mt-1.5">
                            <span class="inline-flex items-center px-3 py-1 text-xs font-bold tracking-wider rounded-full uppercase shadow-sm <?php
                                echo !empty($profile_info['employment_status']) && $profile_info['employment_status'] !== 'Not Set'
                                    ? 'bg-blue-100 text-blue-800'
                                    : 'bg-gray-100 text-gray-700';
                            ?>">
                                <?php echo !empty($profile_info['employment_status']) && $profile_info['employment_status'] !== 'Not Set'
                                    ? 'Current' : 'Not Set'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-1 flex-1">
                <p class="text-base font-bold text-gray-800 truncate <?php
                    echo !empty($profile_info['employment_status']) && $profile_info['employment_status'] !== 'Not Set'
                        ? '' : 'italic text-gray-400';
                ?>">
                    <?php echo !empty($profile_info['employment_status']) && $profile_info['employment_status'] !== 'Not Set'
                        ? htmlspecialchars($profile_info['employment_status'])
                        : 'No employment info'; ?>
                </p>
                <p class="text-xs <?php
                    echo !empty($profile_info['last_profile_update'])
                        ? 'text-gray-500' : 'text-gray-400 italic';
                ?>">
                    <?php echo !empty($profile_info['last_profile_update'])
                        ? 'Updated ' . date('M d, Y', strtotime($profile_info['last_profile_update']))
                        : 'Never updated'; ?>
                </p>
            </div>

            <?php if (!empty($profile_info['employment_status']) && $profile_info['employment_status'] !== 'Not Set'): ?>
                <div class="flex items-center space-x-1 text-xs text-blue-600 font-medium">
                    <i class="fas fa-check-circle"></i>
                    <span>Visible to network</span>
                </div>
            <?php endif; ?>
        </div>

        <a href="alumni_profile.php#employment" class="mt-auto block text-center py-3.5 px-6 text-white text-sm font-bold tracking-wide
            <?php
            echo !empty($profile_info['employment_status']) && $profile_info['employment_status'] !== 'Not Set'
                ? 'bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700'
                : 'bg-gradient-to-r from-gray-500 to-gray-700 hover:from-gray-600 hover:to-gray-800';
            ?> transition-all duration-300 rounded-b-2xl flex items-center justify-center space-x-1 group">
            <span>
                <?php echo !empty($profile_info['employment_status']) && $profile_info['employment_status'] !== 'Not Set'
                    ? 'Update' : 'Add Employment'; ?>
            </span>
            <i class="fas fa-arrow-right text-sm transform group-hover:translate-x-1 transition-transform"></i>
        </a>
    </div>
</div>
<!-- CARD 3: Document Review -->
<div class="h-full flex flex-col">
    <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg border <?php
        echo $document['submission_status'] === 'Approved' ? 'border-emerald-400 ring-2 ring-emerald-100' :
             ($document['submission_status'] === 'Rejected' ? 'border-red-500 ring-2 ring-red-200' :
             ($document['submission_status'] === 'Under Review' ? 'border-amber-400 ring-2 ring-amber-100' :
             'border-gray-300 ring-2 ring-gray-100'));
    ?> overflow-hidden flex flex-col h-full hover:shadow-xl transition-all duration-400 group relative">

        <!-- ALERT: Rejected (Upper-right inside card - NO ANIMATION) -->
        <?php if ($document['submission_status'] === 'Rejected'): ?>
            <div class="absolute top-3 right-3 w-10 h-10 bg-gradient-to-br from-red-500 to-rose-600 rounded-full flex items-center justify-center text-white font-bold shadow-lg z-20">
                <i class="fas fa-times text-sm"></i>
            </div>
        <?php endif; ?>

        <!-- Main Content -->
        <div class="p-6 pb-4 space-y-4 flex flex-col flex-1">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="p-3 <?php
                        echo $document['submission_status'] === 'Approved' ? 'bg-gradient-to-br from-emerald-600 to-teal-600' :
                             ($document['submission_status'] === 'Rejected' ? 'bg-gradient-to-br from-red-600 to-rose-600' :
                             ($document['submission_status'] === 'Under Review' ? 'bg-gradient-to-br from-amber-600 to-orange-600' :
                             'bg-gradient-to-br from-gray-500 to-gray-700'));
                    ?> rounded-xl text-white shadow-md transform group-hover:scale-110 transition-all duration-300">
                        <i class="fas fa-clipboard-check text-xl"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-extrabold text-gray-900">Document Review</h3>
                        <div class="mt-1.5">
                            <span class="inline-flex items-center px-3 py-1 text-xs font-bold tracking-wider rounded-full uppercase shadow-sm <?php
                                echo $document['submission_status'] === 'Approved' ? 'bg-emerald-100 text-emerald-800' :
                                     ($document['submission_status'] === 'Rejected' ? 'bg-red-100 text-red-800' :
                                     ($document['submission_status'] === 'Under Review' ? 'bg-amber-100 text-amber-800' :
                                     'bg-gray-100 text-gray-700'));
                            ?>">
                                <?php echo htmlspecialchars($document['submission_status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex-1">
                <p class="text-sm font-medium <?php
                    echo $document['submission_status'] === 'Approved' ? 'text-emerald-700' :
                         ($document['submission_status'] === 'Rejected' ? 'text-red-700' :
                         ($document['submission_status'] === 'Under Review' ? 'text-amber-700' : 'text-gray-600'));
                ?> leading-relaxed">
                    <?php echo htmlspecialchars($document['message']); ?>
                </p>
            </div>

            <div class="flex items-center justify-between pt-3 border-t border-gray-100">
                <div class="flex items-center space-x-1.5 text-xs font-semibold <?php
                    echo $document['submission_status'] === 'Approved' ? 'text-emerald-600' :
                         ($document['submission_status'] === 'Rejected' ? 'text-red-600' :
                         ($document['submission_status'] === 'Under Review' ? 'text-amber-600' : 'text-gray-500'));
                ?>">
                    <i class="fas fa-paperclip"></i>
                    <span>Files:</span>
                    <span class="font-bold"><?php echo $document['document_count']; ?></span>
                </div>

                <div class="flex justify-center">
                    <?php if ($document['submission_status'] === 'Approved'): ?>
                        <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-check text-lg text-emerald-700"></i>
                        </div>
                    <?php elseif ($document['submission_status'] === 'Under Review'): ?>
                        <div class="relative w-10 h-10">
                            <svg class="w-10 h-10 transform -rotate-90">
                                <circle cx="20" cy="20" r="16" stroke="currentColor" stroke-width="3" fill="none" class="text-gray-200"/>
                                <circle cx="20" cy="20" r="16" stroke="currentColor" stroke-width="3" fill="none"
                                        class="text-amber-500"
                                        stroke-dasharray="100"
                                        stroke-dashoffset="<?php echo 100 - ($document['document_count'] > 0 ? 75 : 100); ?>"
                                        stroke-linecap="round"/>
                            </svg>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <i class="fas fa-hourglass-half text-xs text-amber-600"></i>
                            </div>
                        </div>
                    <?php elseif ($document['submission_status'] === 'Rejected'): ?>
                        <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-times text-lg text-red-700"></i>
                        </div>
                    <?php else: ?>
                        <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-question text-sm text-gray-500"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <a href="alumni_profile.php#documents" class="mt-auto block text-center py-3.5 px-6 text-white text-sm font-bold tracking-wide
            <?php
            $status = $document['submission_status'];
            echo $status === 'Approved' ? 'bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700' :
                 ($status === 'Rejected' ? 'bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700' :
                 ($status === 'Under Review' ? 'bg-gradient-to-r from-amber-600 to-orange-600 hover:from-amber-700 hover:to-orange-700' :
                 'bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700'));
            ?> transition-all duration-300 rounded-b-2xl flex items-center justify-center space-x-1 group">
            <span>
                <?php echo $document['submission_status'] === 'Approved' ? 'View Status' : 'Take Action'; ?>
            </span>
            <i class="fas fa-arrow-right text-sm transform group-hover:translate-x-1 transition-transform"></i>
        </a>
    </div>
</div>
<!-- CARD 4: Uploaded Documents -->
<div class="h-full flex flex-col">
    <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-lg border <?php
        echo $document['document_count'] > 0
            ? 'border-blue-400 ring-2 ring-blue-100'
            : 'border-gray-300 ring-2 ring-gray-100';
    ?> overflow-hidden flex flex-col h-full hover:shadow-xl transition-all duration-400 group relative">

        <!-- ALERT REMOVED: No Files (Upper-right badge completely removed) -->

        <!-- Main Content -->
        <div class="p-6 flex flex-col flex-1 space-y-5">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    <div class="p-3 <?php
                        echo $document['document_count'] > 0
                            ? 'bg-gradient-to-br from-blue-600 to-cyan-600'
                            : 'bg-gradient-to-br from-gray-500 to-gray-700';
                    ?> rounded-xl text-white shadow-md transform group-hover:scale-110 transition-all duration-300 relative overflow-hidden">
                        <i class="fas fa-cloud-upload-alt text-xl"></i>
                        <?php if ($document['document_count'] > 0): ?>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <div class="w-5 h-5 bg-white/30 rounded-sm transform rotate-12 translate-x-1 translate-y-1"></div>
                                <div class="w-5 h-5 bg-white/20 rounded-sm transform -rotate-6 -translate-x-1 -translate-y-1"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 class="text-lg font-extrabold text-gray-900">Uploaded Documents</h3>
                        <span class="inline-flex items-center px-3 py-1 text-xs font-bold tracking-wider rounded-full uppercase shadow-sm <?php
                            echo $document['document_count'] > 0
                                ? 'bg-blue-100 text-blue-800'
                                : 'bg-gray-100 text-gray-700';
                        ?>">
                            <?php echo $document['document_count'] > 0 ? 'Active' : 'Empty'; ?>
                        </span>
                    </div>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-extrabold <?php echo $document['document_count'] > 0 ? 'text-blue-600' : 'text-gray-400'; ?>">
                        <?php echo $document['document_count']; ?>
                    </div>
                    <div class="text-xs <?php echo $document['document_count'] > 0 ? 'text-blue-500' : 'text-gray-400'; ?> uppercase tracking-wider font-medium">
                        File<?php echo $document['document_count'] != 1 ? 's' : ''; ?>
                    </div>
                </div>
            </div>

            <p class="text-sm font-medium <?php
                echo $document['document_count'] > 0
                    ? 'text-blue-700'
                    : 'text-gray-500 italic';
            ?>">
                <?php echo $document['document_count'] > 0
                    ? 'You have <strong>' . $document['document_count'] . '</strong> file' . ($document['document_count'] != 1 ? 's' : '') . ' ready.'
                    : 'No files uploaded yet. Start now!'; ?>
            </p>

            <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                <div class="flex items-center space-x-1.5 text-xs font-semibold <?php
                    echo $document['document_count'] > 0 ? 'text-emerald-600' : 'text-gray-500';
                ?>">
                    <i class="fas fa-check-circle"></i>
                    <span>Ready:</span>
                    <span class="font-bold"><?php echo $document['document_count'] > 0 ? 'Yes' : 'No'; ?></span>
                </div>
                <div class="relative w-11 h-11">
                    <svg class="w-11 h-11 transform -rotate-90">
                        <circle cx="22" cy="22" r="18" stroke="currentColor" stroke-width="3" fill="none" class="text-gray-200"/>
                        <circle cx="22" cy="22" r="18" stroke="currentColor" stroke-width="3" fill="none"
                                class="<?php echo $document['document_count'] > 0 ? 'text-emerald-500' : 'text-gray-400'; ?>"
                                stroke-dasharray="113"
                                stroke-dashoffset="<?php echo $document['document_count'] > 0 ? '0' : '113'; ?>"
                                stroke-linecap="round"/>
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <i class="fas <?php echo $document['document_count'] > 0 ? 'fa-check text-xs text-emerald-600' : 'fa-times text-xs text-gray-400'; ?>"></i>
                    </div>
                </div>
            </div>
        </div>

        <a href="alumni_profile.php#documents" class="mt-auto block text-center py-3.5 text-white text-sm font-bold tracking-wide
            <?php
            echo $document['document_count'] > 0
                ? 'bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700'
                : 'bg-gradient-to-r from-gray-500 to-gray-700 hover:from-gray-600 hover:to-gray-800';
            ?> transition-all duration-300 rounded-b-2xl flex items-center justify-center space-x-1 group">
            <span><?php echo $document['document_count'] > 0 ? 'Manage Files' : 'Start Upload'; ?></span>
            <i class="fas fa-arrow-right text-sm transform group-hover:translate-x-1 transition-transform"></i>
        </a>
    </div>
</div>
    <!-- RIGHT: Quick Actions & Recent Activity (40%) -->
    <div class="space-y-5">

 <!-- Quick Actions Card -->
<div class="bg-gray-50 rounded-xl shadow-sm border border-gray-200/50 p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h3>
    <div class="space-y-0">
        <!-- Action 1: Update Profile -->
        <a href="alumni_profile.php" class="flex items-center p-4 bg-white hover:bg-green-50 rounded-t-xl transition-all duration-300 group border-b border-gray-200/70">
            <div class="bg-green-100/70 p-3 rounded-xl mr-4 group-hover:bg-green-500 transition-colors duration-300">
                <i class="fas fa-user-edit text-green-700 text-xl group-hover:text-white"></i>
            </div>
            <div class="flex-1">
                <p class="font-bold text-gray-800">Update Profile</p>
                <p class="text-xs text-gray-600">Keep your information current</p>
            </div>
            <i class="fas fa-chevron-right text-gray-400 text-sm group-hover:text-green-600 transition-colors"></i>
        </a>

        <!-- Action 2: View Profile -->
        <a href="alumni_profile.php" class="flex items-center p-4 bg-white hover:bg-purple-50 rounded-none transition-all duration-300 group border-b border-gray-200/70">
            <div class="bg-purple-100/70 p-3 rounded-xl mr-4 group-hover:bg-purple-500 transition-colors duration-300">
                <i class="fas fa-id-card text-purple-700 text-xl group-hover:text-white"></i>
            </div>
            <div class="flex-1">
                <p class="font-bold text-gray-800">View Profile</p>
                <p class="text-xs text-gray-600">See your full alumni details</p>
            </div>
            <i class="fas fa-chevron-right text-gray-400 text-sm group-hover:text-purple-600 transition-colors"></i>
        </a>

        <!-- Action 3: Check Review Status -->
        <a href="alumni_profile.php#documents" class="flex items-center p-4 bg-white hover:bg-amber-50 rounded-b-xl transition-all duration-300 group">
            <div class="bg-amber-100/70 p-3 rounded-xl mr-4 group-hover:bg-amber-500 transition-colors duration-300">
                <i class="fas fa-clipboard-list text-amber-700 text-xl group-hover:text-white"></i>
            </div>
            <div class="flex-1">
                <p class="font-bold text-gray-800">Check Review Status</p>
                <p class="text-xs text-gray-600">Track document approval</p>
            </div>
            <i class="fas fa-chevron-right text-gray-400 text-sm group-hover:text-amber-600 transition-colors"></i>
        </a>
    </div>
</div>
 <!-- Recent Activity Card -->
<div class="bg-white rounded-xl shadow-md border border-gray-100 p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-4">Recent Activity</h3>
    <div class="space-y-2">
        <!-- Item 1: Last Profile Update -->
        <div class="flex items-start gap-3">
            <div class="bg-amber-100 p-1.5 rounded-lg flex-shrink-0">
                <i class="fas fa-clock text-amber-600 text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 truncate">Last Profile Update</p>
                <p class="text-xs text-gray-600">
                    <?php echo !empty($profile_info['last_profile_update'])
                        ? date('M d, Y', strtotime($profile_info['last_profile_update']))
                        : 'Never'; ?>
                </p>
            </div>
        </div>

        <!-- Item 2: Document Status -->
        <div class="flex items-start gap-3">
            <div class="bg-blue-100 p-1.5 rounded-lg flex-shrink-0">
                <i class="fas fa-file text-blue-600 text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 truncate">Documents Status</p>
                <p class="text-xs text-gray-600"><?php echo htmlspecialchars($document['submission_status']); ?></p>
            </div>
        </div>

        <!-- Item 3: Graduation Year -->
        <div class="flex items-start gap-3">
            <div class="bg-green-100 p-1.5 rounded-lg flex-shrink-0">
                <i class="fas fa-graduation-cap text-green-600 text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-800 truncate">Graduation Year</p>
                <p class="text-xs text-gray-600">
                    <?php echo !empty($profile_info['year_graduated'])
                        ? htmlspecialchars($profile_info['year_graduated'])
                        : 'Not specified'; ?>
                </p>
            </div>
        </div>
    </div>
</div>
<!-- Help & Support Modal -->
<div id="helpModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full mx-4 transform transition-all duration-300 scale-95 opacity-0">
        <div class="bg-gradient-to-r from-green-600 to-emerald-700 p-6 rounded-t-2xl text-white">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold flex items-center">
                    <i class="fas fa-question-circle mr-3"></i>
                    Help & Support
                </h3>
                <button id="closeHelpModal" class="text-white hover:text-gray-200 text-xl font-bold transition-colors duration-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
<div class="space-y-6 min-h-0 p-6 bg-white rounded-2xl shadow-xl">
    <div class="grid grid-cols-2 gap-4">
        <div class="flex flex-col items-center p-3 bg-green-50 rounded-lg text-center border border-green-200 hover:shadow-md transition-shadow duration-300">
            <div class="bg-green-100 p-2 rounded-full mb-1">
                <i class="fas fa-envelope text-green-600 text-lg"></i>
            </div>
            <h4 class="font-semibold text-gray-800 text-sm whitespace-nowrap">Email Support</h4>
            <p class="text-xs text-gray-600 truncate max-w-full">main@jhcsc.edu.ph</p>
        </div>

        <div class="flex flex-col items-center p-3 bg-blue-50 rounded-lg text-center border border-blue-200 hover:shadow-md transition-shadow duration-300">
            <div class="bg-blue-100 p-2 rounded-full mb-1">
                <i class="fas fa-phone text-blue-600 text-lg"></i>
            </div>
            <h4 class="font-semibold text-gray-800 text-sm whitespace-nowrap">Phone Support</h4>
            <p class="text-xs text-gray-600 truncate max-w-full">0948 954 7078 - BSIT Faculty</p>
        </div>

        <div class="flex flex-col items-center p-3 bg-purple-50 rounded-lg text-center border border-purple-200 hover:shadow-md transition-shadow duration-300">
            <div class="bg-purple-100 p-2 rounded-full mb-1">
                <i class="fas fa-clock text-purple-600 text-lg"></i>
            </div>
            <h4 class="font-semibold text-gray-800 text-sm whitespace-nowrap">Support Hours</h4>
            <p class="text-xs text-gray-600 truncate max-w-full">Mon - Fri: 9AM - 5PM EST</p>
        </div>

        <div class="flex flex-col items-center p-3 bg-amber-50 rounded-lg text-center border border-amber-200 hover:shadow-md transition-shadow duration-300">
            <div class="bg-amber-100 p-2 rounded-full mb-1">
                <i class="fas fa-life-ring text-amber-600 text-lg"></i>
            </div>
            <h4 class="font-semibold text-gray-800 text-sm whitespace-nowrap">FAQs & Guides</h4>
            <p class="text-xs text-gray-600 truncate max-w-full">Visit our knowledge base</p>
        </div>
    </div>

    <div class="bg-gray-50 px-6 py-4 rounded-b-2xl flex justify-end space-x-3 -mx-6 -mb-6 mt-6 border-t">
        <button id="cancelHelp" class="px-4 py-2 text-gray-600 hover:text-gray-800 font-medium transition-colors duration-200">
            Close
        </button>
        <a href="mailto:support@alumniportal.edu" class="px-6 py-2 bg-gradient-to-r from-green-600 to-emerald-700 hover:from-green-700 hover:to-emerald-800 text-white font-semibold rounded-lg transition-all duration-300 shadow-md hover:shadow-lg">
            Contact Now
        </a>
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

    // Help & Support Modal Functionality
    const helpButton = document.getElementById('helpButton');
    const helpModal = document.getElementById('helpModal');
    const closeHelpModal = document.getElementById('closeHelpModal');
    const cancelHelp = document.getElementById('cancelHelp');
    const modalContent = helpModal.querySelector('.bg-white');

    function showHelpModal() {
        helpModal.classList.remove('hidden');
        setTimeout(() => {
            modalContent.classList.remove('scale-95', 'opacity-0');
            modalContent.classList.add('scale-100', 'opacity-100');
        }, 10);
    }

    function hideHelpModal() {
        modalContent.classList.remove('scale-100', 'opacity-100');
        modalContent.classList.add('scale-95', 'opacity-0');
        setTimeout(() => {
            helpModal.classList.add('hidden');
        }, 300);
    }

    if (helpButton) {
        helpButton.addEventListener('click', showHelpModal);
    }
    if (closeHelpModal) {
        closeHelpModal.addEventListener('click', hideHelpModal);
    }
    if (cancelHelp) {
        cancelHelp.addEventListener('click', hideHelpModal);
    }

    // Close modal when clicking outside
    helpModal.addEventListener('click', (e) => {
        if (e.target === helpModal) {
            hideHelpModal();
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !helpModal.classList.contains('hidden')) {
            hideHelpModal();
        }
    });

    // Existing notification functionality (preserved)
    const notifButton = document.getElementById('notificationBtn');
    const notifPopup = document.getElementById('notifPopup');
    if (notifButton && notifPopup) {
        notifButton.addEventListener('click', (e) => {
            e.stopPropagation();
            notifPopup.classList.toggle('hidden');
        });
        document.addEventListener('click', (e) => {
            if (!notifPopup.classList.contains('hidden') && !notifPopup.contains(e.target) && e.target !== notifButton) {
                notifPopup.classList.add('hidden');
            }
        });
        document.getElementById('markReadBtn').addEventListener('click', () => {
            notifButton.querySelector('span').classList.add('hidden');
        });
    }
});
</script>
<?php
$page_content = ob_get_clean();
include("alumni_format.php");
?>