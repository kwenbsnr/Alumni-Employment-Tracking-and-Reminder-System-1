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

// Calculate dynamic completion percentage
$completed_fields = 0;
$total_fields = 7; // Total number of required profile fields

if (!empty($profile_info)) {
    if (!empty($profile_info['first_name'])) $completed_fields++;
    if (!empty($profile_info['last_name'])) $completed_fields++;
    if (!empty($profile_info['contact_number'])) $completed_fields++;
    if (!empty($profile_info['year_graduated'])) $completed_fields++;
    if (!empty($profile_info['employment_status'])) $completed_fields++;
    if (!empty($profile_info['address_id'])) $completed_fields++;
    
    // Photo is also required
    $stmt_photo = $conn->prepare("SELECT photo_path FROM alumni_profile WHERE user_id = ?");
    $stmt_photo->bind_param("i", $user_id);
    $stmt_photo->execute();
    $photo_result = $stmt_photo->get_result();
    $photo_data = $photo_result->fetch_assoc();
    $stmt_photo->close();
    
    if (!empty($photo_data['photo_path'])) {
        $completed_fields++;
    }
}

$completion_percentage = $total_fields > 0 ? round(($completed_fields / $total_fields) * 100) : 0;
$is_profile_complete = ($completion_percentage === 100);
// Annual update check
$needs_annual_update = !empty($profile_info) &&
    ($profile_info['last_profile_update'] === null ||
     strtotime($profile_info['last_profile_update'] . ' +1 year') <= time());

$needs_profile_update = empty($profile_info) || !$is_profile_complete || $needs_annual_update;

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
        <!-- LEFT: Stats Cards (2 columns, ~60%) -->
        <div class="lg:col-span-2 space-y-5">
            
            <!-- MODERN 2x2 DASHBOARD CARDS - Compact Size & No Scroll -->
            <div class="max-w-6xl mx-auto">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 auto-rows-fr">
                    
                    <!-- CARD 1: Profile Completion -->
                    <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden flex flex-col h-full hover:shadow-lg transition-all duration-300">
                        <div class="p-5 flex-1 flex flex-col">
                            <div class="flex items-start justify-between mb-4">
                                <div class="p-3 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl text-white shadow-md">
                                    <i class="fas fa-user-check text-xl"></i>
                                </div>
                                <span class="px-3 py-1 text-xs font-bold tracking-wider uppercase rounded-full <?php echo $is_profile_complete ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'; ?>">
                                    <?php echo $is_profile_complete ? 'Complete' : 'Incomplete'; ?>
                                </span>
                            </div>
                            <h3 class="text-lg font-bold text-gray-800 mb-2">Profile Completion</h3>
                            <p class="text-gray-600 text-xs leading-relaxed flex-1">
                                <?php echo $is_profile_complete 
                                    ? 'Amazing! Your profile is 100% complete and up to date.' 
                                    : 'Finish filling out your details to unlock full portal access.'; ?>
                            </p>

                            <!-- Completion Rate Display -->
                            <div class="mt-4">
                                <div class="flex justify-between text-xs text-gray-500 mb-1">
                                    <span>Completion Rate</span>
                                   <span class="font-bold"><?php echo $completion_percentage; ?>%</span>
                                </div>

                                <?php if ($is_profile_complete): ?>
                                    <!-- 100% Complete → Show clean checkmark instead of progress bar -->
                                    <div class="flex items-center justify-center py-3">
                                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                            <i class="fas fa-check text-2xl text-green-600 font-bold"></i>
                                        </div>
                                        <span class="ml-3 text-sm font-semibold text-green-700">Fully Completed</span>
                                    </div>
                                <?php else: ?>
                                    <!-- Incomplete → Keep progress bar -->
                                    <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden">
                                        <div class="h-full rounded-full bg-gradient-to-r from-green-500 to-emerald-600 transition-all duration-1000"
     style="width: <?php echo $completion_percentage; ?>%"></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <a href="alumni_profile.php" class="block text-center py-3 px-5 text-white font-semibold text-base
                            bg-gradient-to-r from-green-600 to-emerald-700 
                            hover:from-green-700 hover:to-emerald-800 
                            transition-all duration-300">
                            <?php echo $is_profile_complete ? 'View Profile' : 'Complete Profile Now'; ?> →
                        </a>
                    </div>

                    <!-- CARD 2: Employment Status -->
                    <div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden flex flex-col h-full hover:shadow-lg transition-all duration-300">
                        <div class="p-5 flex-1 flex flex-col">
                            <div class="flex items-start justify-between mb-4">
                                <div class="p-3 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl text-white shadow-md">
                                    <i class="fas fa-briefcase text-xl"></i>
                                </div>
                                <span class="px-3 py-1 text-xs font-bold tracking-wider uppercase rounded-full bg-blue-100 text-blue-700">
                                    Current Status
                                </span>
                            </div>
                            <h3 class="text-lg font-bold text-gray-800 mb-2">Employment</h3>
                            <div class="flex-1">
                                <p class="text-xl font-extrabold text-gray-900 mb-1">
                                    <?php echo !empty($profile_info['employment_status']) && $profile_info['employment_status'] !== 'Not Set' 
                                        ? htmlspecialchars($profile_info['employment_status']) 
                                        : 'Not Specified'; ?>
                                </p>
                                <p class="text-xs text-gray-500 flex items-center">
                                    <i class="fas fa-calendar-alt mr-1"></i>
                                    <?php echo !empty($profile_info['last_profile_update']) 
                                        ? 'Updated ' . date('M d, Y', strtotime($profile_info['last_profile_update'])) 
                                        : 'No updates yet'; ?>
                                </p>
                            </div>
                        </div>
                        <a href="alumni_profile.php#employment" class="block text-center py-3 px-5 text-white font-semibold text-base
                            bg-gradient-to-r from-blue-600 to-indigo-700 
                            hover:from-blue-700 hover:to-indigo-800 
                            transition-all duration-300">
                            <?php echo !empty($profile_info['employment_status']) && $profile_info['employment_status'] !== 'Not Set' ? 'Update Employment' : 'Add Employment'; ?> →
                        </a>
                    </div>

                 <!-- CARD 3: Document Review - Status Focused -->
<div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden flex flex-col h-full hover:shadow-lg transition-all duration-300">
    <div class="p-5 flex-1 flex flex-col">
        <div class="flex items-start justify-between mb-4">
            <div class="p-3 rounded-xl text-white shadow-md <?php 
                echo match($document['submission_status']) {
                    'Approved' => 'bg-gradient-to-br from-green-500 to-emerald-600',
                    'Rejected' => 'bg-gradient-to-br from-red-500 to-rose-600',
                    'Under Review' => 'bg-gradient-to-br from-amber-500 to-orange-600',
                    'Draft' => 'bg-gradient-to-br from-blue-500 to-indigo-600',
                    default => 'bg-gradient-to-br from-gray-500 to-gray-600'
                };
            ?>">
                <i class="fas fa-clipboard-check text-xl"></i>
            </div>
            <span class="px-3 py-1 text-xs font-bold tracking-wider uppercase rounded-full 
                <?php echo $document['submission_status'] === 'Approved' ? 'bg-green-100 text-green-700' :
                       ($document['submission_status'] === 'Rejected' ? 'bg-red-100 text-red-700' :
                       ($document['submission_status'] === 'Under Review' ? 'bg-amber-100 text-amber-700' :
                       ($document['submission_status'] === 'Draft' ? 'bg-blue-100 text-blue-700' :
                       'bg-gray-100 text-gray-600'))); ?>">
                <?php echo htmlspecialchars($document['submission_status']); ?>
            </span>
        </div>
        <h3 class="text-lg font-bold text-gray-800 mb-2">Document Review</h3>
        <p class="text-gray-600 text-xs leading-relaxed mb-4">
            <?php echo htmlspecialchars($document['message']); ?>
        </p>
        
        <!-- Review Details -->
        <div class="space-y-2 mt-auto">
            <div class="flex justify-between text-xs">
                <span class="text-gray-500">Reviewer Status:</span>
                <span class="font-medium text-gray-700">
                    <?php echo $document['submission_status'] === 'Draft' ? 'Not Submitted' : htmlspecialchars($document['submission_status']); ?>
                </span>
            </div>
            <div class="flex justify-between text-xs">
                <span class="text-gray-500">Last Updated:</span>
                <span class="font-medium text-gray-700">
                    <?php echo !empty($profile_info['last_profile_update']) 
                        ? date('M d, Y', strtotime($profile_info['last_profile_update']))
                        : 'Never'; ?>
                </span>
            </div>
            <div class="flex justify-between text-xs">
                <span class="text-gray-500">Documents in Review:</span>
                <span class="font-medium text-gray-700">
                    <?php echo $document['document_count']; ?> file<?php echo $document['document_count'] != 1 ? 's' : ''; ?>
                </span>
            </div>
        </div>
    </div>
    <a href="alumni_profile.php#documents" class="block text-center py-3 px-5 text-white font-semibold text-base
        <?php echo match($document['submission_status']) {
            'Approved' => 'bg-gradient-to-r from-green-600 to-emerald-700 hover:from-green-700 hover:to-emerald-800',
            'Rejected' => 'bg-gradient-to-r from-red-600 to-rose-700 hover:from-red-700 hover:to-rose-800',
            'Under Review' => 'bg-gradient-to-r from-amber-600 to-orange-700 hover:from-amber-700 hover:to-orange-800',
            default => 'bg-gradient-to-r from-blue-600 to-indigo-700 hover:from-blue-700 hover:to-indigo-800'
        }; ?> transition-all duration-300">
        <?php echo $document['submission_status'] === 'Approved' ? 'View Documents' : 'Manage Documents'; ?> →
    </a>
</div>

<!-- CARD 4: Uploaded Documents - File Count Focused -->
<div class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden flex flex-col h-full hover:shadow-lg transition-all duration-300">
    <div class="p-5 flex-1 flex flex-col">
        <div class="flex items-start justify-between mb-4">
            <div class="p-3 <?php echo $document['document_count'] > 0
                ? 'bg-gradient-to-br from-teal-500 to-cyan-600'
                : 'bg-gradient-to-br from-gray-400 to-gray-600'; ?> rounded-xl text-white shadow-md">
                <i class="fas fa-cloud-upload-alt text-xl"></i>
            </div>
            <div class="text-right">
                <div class="text-3xl font-extrabold text-gray-800">
                    <?php echo $document['document_count']; ?>
                </div>
                <div class="text-xs text-gray-500 uppercase tracking-wider">
                    Total Files
                </div>
            </div>
        </div>
        <h3 class="text-lg font-bold text-gray-800 mb-2">Uploaded Documents</h3>
        <p class="text-gray-600 text-xs leading-relaxed mb-4">
            <?php echo $document['document_count'] > 0
                ? 'You have uploaded <strong>' . $document['document_count'] . '</strong> document' . ($document['document_count'] != 1 ? 's' : '') . ' for review.'
                : 'No documents uploaded yet. Start adding your diploma, TOR, resume, etc.'; ?>
        </p>

        <!-- File Details -->
       <div class="space-y-2 mt-auto">
    <div class="flex justify-between text-xs">
        <span class="text-gray-500">Total Files Uploaded:</span>
        <span class="font-medium text-gray-700">
            <?php echo $document['document_count']; ?> file<?php echo $document['document_count'] != 1 ? 's' : ''; ?>
        </span>
    </div>
            <div class="flex justify-between text-xs">
                <span class="text-gray-500">Upload Status:</span>
                <span class="font-medium text-xs px-2 py-1 rounded-full 
                    <?php echo $document['submission_status'] === 'Approved' ? 'bg-green-100 text-green-700' :
                           ($document['submission_status'] === 'Rejected' ? 'bg-red-100 text-red-700' :
                           ($document['submission_status'] === 'Under Review' ? 'bg-amber-100 text-amber-700' :
                           ($document['submission_status'] === 'Draft' ? 'bg-blue-100 text-blue-700' :
                           'bg-gray-100 text-gray-600'))); ?>">
                    <?php echo htmlspecialchars($document['submission_status']); ?>
                </span>
            </div>
            <div class="flex justify-between text-xs">
                <span class="text-gray-500">Ready for Submission:</span>
                <span class="font-medium <?php echo $document['document_count'] > 0 ? 'text-green-600' : 'text-gray-600'; ?>">
                    <?php echo $document['document_count'] > 0 ? 'Yes' : 'No'; ?>
                </span>
            </div>
        </div>
    </div>
    <a href="alumni_profile.php#documents" class="block text-center py-3 px-5 text-white font-semibold text-base
        <?php echo $document['document_count'] > 0
            ? 'bg-gradient-to-r from-teal-600 to-cyan-700 hover:from-teal-700 hover:to-cyan-800'
            : 'bg-gradient-to-r from-gray-600 to-gray-700 hover:from-gray-700 hover:to-gray-800'; ?>
        transition-all duration-300">
        <?php echo $document['document_count'] > 0 ? 'View All Files' : 'Upload Documents'; ?> →
    </a>
</div>
                </div>
            </div>
        </div>
        
        <!-- RIGHT: Quick Actions Column -->
        <div class="space-y-5">
            
           <!-- Quick Actions Card -->
<div class="bg-white rounded-xl shadow-md border border-gray-100 p-5">
    <h3 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h3>
    <div class="space-y-2">
        <a href="alumni_profile.php" class="flex items-center p-4 bg-gray-100/50 hover:bg-green-100 rounded-xl transition-all duration-300 group">
            <div class="bg-green-100/70 p-3 rounded-xl mr-4 group-hover:bg-green-500 transition-colors duration-300">
                <i class="fas fa-user-edit text-green-700 text-xl group-hover:text-white"></i>
            </div>
            <div>
                <p class="font-bold text-gray-800">Update Profile</p>
                <p class="text-xs text-gray-600">Keep your information current</p>
            </div>
        </a>
        <a href="alumni_profile.php#documents" class="flex items-center p-4 bg-gray-100/50 hover:bg-blue-100 rounded-xl transition-all duration-300 group">
            <div class="bg-blue-100/70 p-3 rounded-xl mr-4 group-hover:bg-blue-500 transition-colors duration-300">
                <i class="fas fa-upload text-blue-700 text-xl group-hover:text-white"></i>
            </div>
            <div>
                <p class="font-bold text-gray-800">Upload Documents</p>
                <p class="text-xs text-gray-600">Submit required files</p>
            </div>
        </a>
    </div>
</div>

            <!-- Recent Activity Card -->
            <div class="bg-white rounded-xl shadow-md border border-gray-100 p-5">
                <h3 class="text-lg font-bold text-gray-800 mb-4">Recent Activity</h3>
                <div class="space-y-3">
                    <div class="flex items-start">
                        <div class="bg-amber-100 p-2 rounded-lg mr-3 mt-1">
                            <i class="fas fa-clock text-amber-600 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">Last Profile Update</p>
                            <p class="text-xs text-gray-600">
                                <?php echo !empty($profile_info['last_profile_update']) 
                                    ? date('M d, Y', strtotime($profile_info['last_profile_update']))
                                    : 'Never'; ?>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="bg-blue-100 p-2 rounded-lg mr-3 mt-1">
                            <i class="fas fa-file text-blue-600 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">Documents Status</p>
                            <p class="text-xs text-gray-600"><?php echo htmlspecialchars($document['submission_status']); ?></p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="bg-green-100 p-2 rounded-lg mr-3 mt-1">
                            <i class="fas fa-graduation-cap text-green-600 text-sm"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">Graduation Year</p>
                            <p class="text-xs text-gray-600">
                                <?php echo !empty($profile_info['year_graduated']) 
                                    ? htmlspecialchars($profile_info['year_graduated'])
                                    : 'Not specified'; ?>
                            </p>
                        </div>
                    </div>
                </div>
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