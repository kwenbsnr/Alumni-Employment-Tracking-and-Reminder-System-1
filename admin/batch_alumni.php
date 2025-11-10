<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

$batch_year = $_GET['batch'] ?? '';
if (empty($batch_year)) {
    header("Location: alumni_management.php");
    exit();
}

$page_title = "Batch $batch_year Alumni";
$active_page = "alumni_management";

// Get filter parameters
$search = $_GET['search'] ?? '';
$employment_status = $_GET['employment_status'] ?? '';
$submission_status = $_GET['submission_status'] ?? '';

// Fetch batch statistics for submission status only
$statsQuery = "SELECT 
                SUM(CASE WHEN submission_status = 'Approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN submission_status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN submission_status = 'Rejected' THEN 1 ELSE 0 END) as rejected_count
               FROM alumni_profile 
               WHERE year_graduated = ?";
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param('s', $batch_year);
$statsStmt->execute();
$statsResult = $statsStmt->get_result();
$batchStats = $statsResult->fetch_assoc();

// Fetch alumni data with filters
$whereConditions = ["ap.year_graduated = ?"];
$params = [$batch_year];
$types = 's';

if (!empty($search)) {
    $whereConditions[] = "(ap.first_name LIKE ? OR ap.middle_name LIKE ? OR ap.last_name LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    $types .= 'sss';
}

if (!empty($employment_status)) {
    $whereConditions[] = "ap.employment_status = ?";
    $params[] = $employment_status;
    $types .= 's';
}

if (!empty($submission_status)) {
    $whereConditions[] = "ap.submission_status = ?";
    $params[] = $submission_status;
    $types .= 's';
}

$whereClause = implode(" AND ", $whereConditions);

$alumniQuery = "
    SELECT 
        ap.user_id,
        ap.first_name,
        ap.middle_name,
        ap.last_name,
        ap.year_graduated,
        ap.employment_status,
        ap.submission_status,
        ap.photo_path,
        u.email,
        COUNT(ad.doc_id) as document_count
    FROM alumni_profile ap
    LEFT JOIN users u ON ap.user_id = u.user_id
    LEFT JOIN alumni_documents ad ON ap.user_id = ad.user_id
    WHERE $whereClause
    GROUP BY ap.user_id
    ORDER BY ap.last_name ASC, ap.first_name ASC
";

$stmt = $conn->prepare($alumniQuery);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$alumniResult = $stmt->get_result();

ob_start();
?>

<div class="space-y-6">
    <!-- Combined Header and Submission Status -->
    <div class="bg-white p-6 rounded-xl shadow-lg">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="alumni_management.php<?php echo !empty($_GET['search']) ? '?search=' . urlencode($_GET['search']) : ''; ?>" 
                   class="bg-gray-100 p-3 rounded-lg hover:bg-gray-200 transition-colors">
                    <i class="fas fa-arrow-left text-gray-600"></i>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Batch <?php echo $batch_year; ?> Alumni</h1>
                </div>
            </div>
            
            <!-- Submission Status Overview -->
            <div class="flex space-x-4">
                <div class="text-center">
                    <span class="inline-flex items-center px-3 py-2 rounded-full text-sm font-medium bg-green-100 text-green-800">
                        <i class="fas fa-check-circle mr-2"></i>
                        Approved: <?php echo $batchStats['approved_count']; ?>
                    </span>
                </div>
                <div class="text-center">
                    <span class="inline-flex items-center px-3 py-2 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                        <i class="fas fa-clock mr-2"></i>
                        Pending: <?php echo $batchStats['pending_count']; ?>
                    </span>
                </div>
                <div class="text-center">
                    <span class="inline-flex items-center px-3 py-2 rounded-full text-sm font-medium bg-red-100 text-red-800">
                        <i class="fas fa-times-circle mr-2"></i>
                        Rejected: <?php echo $batchStats['rejected_count']; ?>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="bg-white p-4 rounded-xl shadow-lg">
        <form method="GET" action="" class="flex flex-col sm:flex-row gap-3 items-start sm:items-end">
            <input type="hidden" name="batch" value="<?php echo $batch_year; ?>">
            
            <!-- Search -->
            <div class="flex-1 min-w-0">
                <label class="block text-sm font-medium text-gray-700 mb-1">Search Name</label>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                       placeholder="Enter name...">
            </div>
            
            <!-- Employment Status -->
            <div class="w-full sm:w-48">
                <label class="block text-sm font-medium text-gray-700 mb-1">Employment Status</label>
                <select name="employment_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Status</option>
                    <option value="Unemployed" <?php echo $employment_status == 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                    <option value="Self-Employed" <?php echo $employment_status == 'Self-Employed' ? 'selected' : ''; ?>>Self-Employed</option>
                    <option value="Employed" <?php echo $employment_status == 'Employed' ? 'selected' : ''; ?>>Employed</option>
                    <option value="Student" <?php echo $employment_status == 'Student' ? 'selected' : ''; ?>>Student</option>
                    <option value="Employed & Student" <?php echo $employment_status == 'Employed & Student' ? 'selected' : ''; ?>>Student & Employed</option>
                </select>
            </div>
            
            <!-- Submission Status -->
            <div class="w-full sm:w-48">
                <label class="block text-sm font-medium text-gray-700 mb-1">Submission Status</label>
                <select name="submission_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">All Status</option>
                    <option value="Pending" <?php echo $submission_status == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Approved" <?php echo $submission_status == 'Approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="Rejected" <?php echo $submission_status == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            
            <!-- Filter Buttons -->
            <div class="flex gap-2">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors whitespace-nowrap">
                    Apply Filters
                </button>
                <a href="batch_alumni.php?batch=<?php echo $batch_year; ?>" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors whitespace-nowrap">
                    Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Alumni Records - Expanded to full width -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-800">
                Alumni Records
                <span class="text-sm font-normal text-gray-600 ml-2">
                    (<?php echo $alumniResult->num_rows; ?> records found)
                </span>
            </h3>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alumni</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employment Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submission Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Documents</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if ($alumniResult->num_rows > 0): ?>
                        <?php while ($alumni = $alumniResult->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <?php if (!empty($alumni['photo_path'])): ?>
                                                <img class="h-10 w-10 rounded-full object-cover" src="../<?php echo $alumni['photo_path']; ?>" alt="">
                                            <?php else: ?>
                                                <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                                    <i class="fas fa-user text-gray-500"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 alumni-name-hover" 
                                                 data-user-id="<?php echo $alumni['user_id']; ?>">
                                                <?php echo htmlspecialchars($alumni['first_name'] . ' ' . $alumni['last_name']); ?>
                                            </div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($alumni['email']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo getEmploymentStatusColor($alumni['employment_status']); ?>">
                                        <?php echo $alumni['employment_status']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                        <?php echo getSubmissionStatusColor($alumni['submission_status']); ?>">
                                        <?php echo $alumni['submission_status']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($alumni['document_count'] > 0): ?>
                                        <button onclick="viewDocuments(<?php echo $alumni['user_id']; ?>)" 
                                                class="text-blue-600 hover:text-blue-900 flex items-center">
                                            <i class="fas fa-file-alt mr-1"></i>
                                            View (<?php echo $alumni['document_count']; ?>)
                                        </button>
                                    <?php else: ?>
                                        <span class="text-gray-400">No documents</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <?php if ($alumni['submission_status'] == 'Pending'): ?>
                                        <button onclick="approveAlumni(<?php echo $alumni['user_id']; ?>)" 
                                                class="text-green-600 hover:text-green-900 mr-3">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button onclick="rejectAlumni(<?php echo $alumni['user_id']; ?>)" 
                                                class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php elseif ($alumni['submission_status'] == 'Approved'): ?>
                                        <button onclick="rejectAlumni(<?php echo $alumni['user_id']; ?>)" 
                                                class="text-red-600 hover:text-red-900">
                                            <i class="fas fa-times"></i> Revoke
                                        </button>
                                    <?php else: ?>
                                        <button onclick="approveAlumni(<?php echo $alumni['user_id']; ?>)" 
                                                class="text-green-600 hover:text-green-900">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    <?php endif; ?>
                                    <a href="edit_alumni.php?id=<?php echo $alumni['user_id']; ?>" 
                                       class="text-blue-600 hover:text-blue-900 ml-3">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                No alumni records found matching your criteria.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Alumni Details Modal -->
<div id="alumniModal" class="fixed inset-0 flex items-center justify-center hidden z-50 pointer-events-none">
    <div class="pointer-events-auto max-w-4xl w-full mx-4">
        <div id="alumniModalContent" class="bg-white rounded-xl shadow-2xl max-h-[90vh] overflow-y-auto">
            <!-- Content will be loaded via AJAX -->
        </div>
    </div>
</div>

<script>
// Global variables for hover functionality
let hoverTimeout;
let isModalHovered = false;

function viewDocuments(userId) {
    window.location.href = `get_documents.php?user_id=${userId}`;
}

function approveAlumni(userId) {
    if (confirm('Are you sure you want to approve this alumni profile?')) {
        window.location.href = `update_status.php?user_id=${userId}&status=Approved`;
    }
}

function rejectAlumni(userId) {
    const reason = prompt('Please enter the reason for rejection:');
    if (reason !== null) {
        window.location.href = `update_status.php?user_id=${userId}&status=Rejected&reason=${encodeURIComponent(reason)}`;
    }
}

// Alumni details hover functionality
document.addEventListener('DOMContentLoaded', function() {
    const alumniNames = document.querySelectorAll('.alumni-name-hover');
    
    alumniNames.forEach(name => {
        name.addEventListener('mouseenter', function() {
            clearTimeout(hoverTimeout);
            const userId = this.getAttribute('data-user-id');
            showAlumniDetails(userId);
        });
        
        name.addEventListener('mouseleave', function() {
            // Start timeout to hide modal if not hovering over modal
            hoverTimeout = setTimeout(() => {
                if (!isModalHovered) {
                    closeAlumniModal();
                }
            }, 300);
        });
    });
});

function showAlumniDetails(userId) {
    const modal = document.getElementById('alumniModal');
    const modalContent = document.getElementById('alumniModalContent');
    
    // Fixed positioning - always center the modal
    modal.style.position = 'fixed';
    modal.style.top = '50%';
    modal.style.left = '50%';
    modal.style.transform = 'translate(-50%, -50%)';
    modal.style.margin = '0';
    modal.style.backgroundColor = 'transparent'; // Remove black background
    modal.style.boxShadow = 'none'; // Remove any shadow
    
    // Load alumni details via AJAX
    fetch(`get_alumni_details.php?user_id=${userId}`)
        .then(response => response.text())
        .then(data => {
            modalContent.innerHTML = data;
            modal.classList.remove('hidden');
            
            // Add hover events to modal content
            modalContent.addEventListener('mouseenter', function() {
                isModalHovered = true;
                clearTimeout(hoverTimeout);
            });
            
            modalContent.addEventListener('mouseleave', function() {
                isModalHovered = false;
                hoverTimeout = setTimeout(() => {
                    closeAlumniModal();
                }, 300);
            });
        })
        .catch(error => {
            modalContent.innerHTML = '<div class="text-center py-8 bg-white rounded-xl"><p class="text-red-500">Error loading alumni details.</p></div>';
            modal.classList.remove('hidden');
        });
}

function closeAlumniModal() {
    const modal = document.getElementById('alumniModal');
    modal.classList.add('hidden');
    isModalHovered = false;
}
</script>

<?php
// Helper functions for status colors
function getEmploymentStatusColor($status) {
    switch ($status) {
        case 'Unemployed': return 'bg-red-100 text-red-800';
        case 'Self-Employed': return 'bg-blue-100 text-blue-800';
        case 'Employed': return 'bg-green-100 text-green-800';
        case 'Student': return 'bg-purple-100 text-purple-800';
        case 'Employed & Student': return 'bg-yellow-100 text-yellow-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getSubmissionStatusColor($status) {
    switch ($status) {
        case 'Approved': return 'bg-green-100 text-green-800';
        case 'Pending': return 'bg-yellow-100 text-yellow-800';
        case 'Rejected': return 'bg-red-100 text-red-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

$page_content = ob_get_clean();
include("admin_format.php");
?>