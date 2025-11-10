<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

$page_title = "Activity Log";
$active_page = "activity_log";

// Pagination
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filters
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

// Build query with filters
$whereConditions = [];
$params = [];
$types = '';

if ($filter_type) {
    $whereConditions[] = "ul.update_type = ?";
    $params[] = $filter_type;
    $types .= 's';
}

if ($filter_date) {
    $whereConditions[] = "DATE(ul.updated_at) = ?";
    $params[] = $filter_date;
    $types .= 's';
}

$whereClause = $whereConditions ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM update_log ul $whereClause";
$countStmt = $conn->prepare($countQuery);
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalResult = $countStmt->get_result();
$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// Fetch activity data
$activityQuery = "
    SELECT 
        ul.log_id,
        ul.updated_by,
        ul.updated_id,
        ul.update_type,
        ul.updated_at,
        u.name as admin_name,
        ap.first_name,
        ap.last_name,
        ap.user_id as alumni_user_id
    FROM update_log ul
    LEFT JOIN users u ON ul.updated_by = u.user_id
    LEFT JOIN alumni_profile ap ON ul.updated_id = ap.user_id
    $whereClause
    ORDER BY ul.updated_at DESC
    LIMIT ? OFFSET ?
";

$params[] = $limit;
$params[] = $offset;
$types .= 'ii';

$activityStmt = $conn->prepare($activityQuery);
if ($params) {
    $activityStmt->bind_param($types, ...$params);
}
$activityStmt->execute();
$activityResult = $activityStmt->get_result();

ob_start();
?>
<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Activity Log</h1>
            <p class="text-gray-600">Track all system activities and changes</p>
        </div>
        <a href="admin_dashboard.php" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors">
            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
        </a>
    </div>

    <!-- Filters -->
    <div class="bg-white p-6 rounded-xl shadow-lg">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Filters</h3>
        <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Action Type</label>
                <select name="type" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500">
                    <option value="">All Actions</option>
                    <option value="update" <?php echo $filter_type === 'update' ? 'selected' : ''; ?>>Update</option>
                    <option value="approve" <?php echo $filter_type === 'approve' ? 'selected' : ''; ?>>Approve</option>
                    <option value="reject" <?php echo $filter_type === 'reject' ? 'selected' : ''; ?>>Reject</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                <input type="date" name="date" value="<?php echo $filter_date; ?>" 
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-purple-500">
            </div>
            <div class="flex items-end space-x-2">
                <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition-colors w-full">
                    <i class="fas fa-filter mr-2"></i>Apply Filters
                </button>
                <a href="activity_log.php" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600 transition-colors">
                    <i class="fas fa-redo mr-2"></i>Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Activity Log -->
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-bold text-gray-800">System Activities</h3>
            <p class="text-sm text-gray-600">Total <?php echo $totalRows; ?> activities found</p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Admin</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if ($activityResult->num_rows > 0): ?>
                        <?php while ($activity = $activityResult->fetch_assoc()): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="p-2 rounded-full <?php echo getActivityColor($activity['update_type']); ?> mr-3">
                                            <i class="fas fa-<?php echo getActivityIcon($activity['update_type']); ?> text-sm"></i>
                                        </div>
                                        <div>
                                            <span class="inline-block px-2 py-1 text-xs font-medium rounded-full <?php echo getActivityBadgeColor($activity['update_type']); ?>">
                                                <?php echo ucfirst($activity['update_type']); ?>
                                            </span>
                                            <p class="text-xs text-gray-500 mt-1">
                                                Alumni Profile
                                            </p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-sm font-medium text-gray-800">
                                        <?php echo getEnhancedActivityText($activity); ?>
                                    </p>
                                    <?php if (!empty($activity['alumni_user_id'])): ?>
                                        <a href="alumni_profile.php?id=<?php echo $activity['alumni_user_id']; ?>" 
                                           class="text-xs text-purple-600 hover:text-purple-800 mt-1 inline-block">
                                            <i class="fas fa-external-link-alt mr-1"></i>View Profile
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <p class="text-sm text-gray-800"><?php echo htmlspecialchars($activity['admin_name']); ?></p>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <p class="text-sm text-gray-800"><?php echo date('M j, Y g:i A', strtotime($activity['updated_at'])); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo time_elapsed_string($activity['updated_at']); ?></p>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500">
                                <i class="fas fa-inbox text-3xl mb-3"></i>
                                <p class="text-lg">No activities found</p>
                                <p class="text-sm mt-1">Try adjusting your filters</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="px-6 py-4 border-t border-gray-200">
                <div class="flex justify-between items-center">
                    <p class="text-sm text-gray-700">
                        Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $limit, $totalRows); ?> of <?php echo $totalRows; ?> results
                    </p>
                    <div class="flex space-x-2">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                               class="bg-white border border-gray-300 px-3 py-1 rounded-lg hover:bg-gray-50 transition-colors">
                                <i class="fas fa-chevron-left mr-1"></i>Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                               class="px-3 py-1 rounded-lg <?php echo $i == $page ? 'bg-purple-600 text-white' : 'bg-white border border-gray-300 hover:bg-gray-50'; ?> transition-colors">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                               class="bg-white border border-gray-300 px-3 py-1 rounded-lg hover:bg-gray-50 transition-colors">
                                Next<i class="fas fa-chevron-right ml-1"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Include the same helper functions from dashboard
function getActivityIcon($update_type) {
    switch ($update_type) {
        case 'approve': return 'check-circle';
        case 'reject': return 'times-circle';
        case 'update': return 'edit';
        default: return 'sync';
    }
}

function getActivityColor($update_type) {
    switch ($update_type) {
        case 'approve': return 'bg-green-100 text-green-500';
        case 'reject': return 'bg-red-100 text-red-500';
        case 'update': return 'bg-blue-100 text-blue-500';
        default: return 'bg-purple-100 text-purple-500';
    }
}

function getActivityBadgeColor($update_type) {
    switch ($update_type) {
        case 'approve': return 'bg-green-100 text-green-800';
        case 'reject': return 'bg-red-100 text-red-800';
        case 'update': return 'bg-blue-100 text-blue-800';
        default: return 'bg-gray-100 text-gray-800';
    }
}

function getEnhancedActivityText($activity) {
    $name = '';
    $details = '';
    
    // Get the affected user's name
    if (!empty($activity['first_name']) && !empty($activity['last_name'])) {
        $name = $activity['first_name'] . ' ' . $activity['last_name'];
    } else {
        $name = "Alumni";
    }
    
    $actions = [
        'approve' => 'Approved',
        'reject' => 'Rejected', 
        'update' => 'Updated'
    ];
    
    $action = $actions[$activity['update_type']] ?? 'Modified';
    
    return "{$action} {$name}'s profile";
}

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $string = [
        'y' => 'year',
        'm' => 'month',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    ];
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

$page_content = ob_get_clean();
include("admin_format.php");
?>