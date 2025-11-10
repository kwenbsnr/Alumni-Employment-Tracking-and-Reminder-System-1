<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

$page_title = "Dashboard";
$active_page = "dashboard";

// Fetch employment status distribution (only approved alumni)
$careerQuery = "SELECT employment_status, COUNT(*) as total 
                FROM alumni_profile 
                WHERE submission_status = 'Approved'
                GROUP BY employment_status";
$result = $conn->query($careerQuery);
if (!$result) {
    $careerLabels = [];
    $careerData = [];
} else {
    $careerLabels = [];
    $careerData = [];
    while ($row = $result->fetch_assoc()) {
        $careerLabels[] = $row['employment_status'] ?: 'Unknown';
        $careerData[] = $row['total'];
    }
}

// Fetch accurate dashboard statistics
$statsQuery = "
    SELECT 
        (SELECT COUNT(*) FROM alumni_profile) as total_alumni,
        (SELECT COUNT(*) FROM alumni_profile WHERE submission_status = 'Approved') as approved_profiles,
        (SELECT COUNT(*) FROM alumni_profile WHERE submission_status = 'Pending') as pending_profiles,
        (SELECT COUNT(*) FROM alumni_profile WHERE submission_status = 'Rejected') as rejected_profiles,
        (SELECT COUNT(*) FROM employment_info WHERE user_id IN (SELECT user_id FROM alumni_profile WHERE submission_status = 'Approved')) as employed_count,
        (SELECT COUNT(DISTINCT year_graduated) FROM alumni_profile WHERE year_graduated IS NOT NULL AND submission_status = 'Approved') as unique_graduation_years,
        (SELECT COUNT(*) FROM alumni_documents WHERE user_id IN (SELECT user_id FROM alumni_profile WHERE submission_status = 'Approved')) as total_documents,
        (SELECT COUNT(*) FROM update_log WHERE DATE(updated_at) = CURDATE()) as today_updates
";

$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Fetch graduation trends (only approved alumni)
$graduatesQuery = "
    SELECT year_graduated, COUNT(*) as count 
    FROM alumni_profile 
    WHERE year_graduated IS NOT NULL 
    AND submission_status = 'Approved'
    GROUP BY year_graduated 
    ORDER BY year_graduated
";
$graduatesResult = $conn->query($graduatesQuery);
$gradYears = [];
$gradCounts = [];
while ($row = $graduatesResult->fetch_assoc()) {
    $gradYears[] = $row['year_graduated'];
    $gradCounts[] = $row['count'];
}

// Fetch enhanced recent activity from update_log with more details
$recentActivityQuery = "
    SELECT 
        ul.log_id,
        ul.updated_by,
        ul.updated_id,
        ul.updated_table,
        ul.update_type,
        ul.updated_at,
        u.name as admin_name,
        ap.first_name,
        ap.last_name,
        ad.document_type,
        ei.company_name,
        ed.school_name
    FROM update_log ul
    LEFT JOIN users u ON ul.updated_by = u.user_id
    LEFT JOIN alumni_profile ap ON ul.updated_id = ap.user_id AND ul.updated_table = 'alumni_profile'
    LEFT JOIN alumni_documents ad ON ul.updated_id = ad.doc_id AND ul.updated_table = 'alumni_documents'
    LEFT JOIN employment_info ei ON ul.updated_id = ei.employment_id AND ul.updated_table = 'employment_info'
    LEFT JOIN education_info ed ON ul.updated_id = ed.education_id AND ul.updated_table = 'education_info'
    ORDER BY ul.updated_at DESC
    LIMIT 10
";
$recentActivityResult = $conn->query($recentActivityQuery);

ob_start();
?>
<div class="space-y-6">
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Alumni -->
        <div class="bg-white p-6 rounded-xl shadow-lg stats-card card-hover border-l-4 border-blue-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                    <i class="fas fa-users text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Total Alumni</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_alumni']; ?></p>
                </div>
            </div>
        </div>

        <!-- Approved Profiles -->
        <div class="bg-white p-6 rounded-xl shadow-lg stats-card card-hover border-l-4 border-green-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-green-100 text-green-500 mr-4">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Approved Profiles</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['approved_profiles']; ?></p>
                </div>
            </div>
        </div>

        <!-- Pending Reviews -->
        <div class="bg-white p-6 rounded-xl shadow-lg stats-card card-hover border-l-4 border-yellow-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-yellow-100 text-yellow-500 mr-4">
                    <i class="fas fa-clock text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Pending Reviews</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['pending_profiles']; ?></p>
                </div>
            </div>
        </div>

        <!-- Today's Updates -->
        <div class="bg-white p-6 rounded-xl shadow-lg stats-card card-hover border-l-4 border-purple-500">
            <div class="flex items-center">
                <div class="p-3 rounded-full bg-purple-100 text-purple-500 mr-4">
                    <i class="fas fa-sync text-xl"></i>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-600">Today's Alumni Updates</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['today_updates']; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Employment Distribution Chart -->
        <div class="bg-white p-6 rounded-xl shadow-lg stats-card card-hover">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Employment Status Distribution</h3>
            <div class="relative w-full h-80">
                <canvas id="employmentChart"></canvas>
            </div>
        </div>

        <!-- Graduation Trends Chart -->
        <div class="bg-white p-6 rounded-xl shadow-lg stats-card card-hover">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Graduation Trends</h3>
            <div class="relative w-full h-80">
                <canvas id="graduationChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Activity - Expanded to full width -->
    <div class="bg-white p-6 rounded-xl shadow-lg stats-card">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-lg font-bold text-gray-800">Recent Activity</h3>
            <a href="activity_log.php" class="text-sm text-purple-600 hover:text-purple-800 font-medium">
                View All Activity <i class="fas fa-arrow-right ml-1"></i>
            </a>
        </div>
        <div class="space-y-4">
            <?php if ($recentActivityResult->num_rows > 0): ?>
                <?php while ($activity = $recentActivityResult->fetch_assoc()): ?>
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                        <div class="flex items-center space-x-4">
                            <div class="p-3 rounded-full <?php echo getActivityColor($activity['update_type']); ?>">
                                <i class="fas fa-<?php echo getActivityIcon($activity['update_type']); ?> text-lg"></i>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-800">
                                    <?php echo getEnhancedActivityText($activity); ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-user-shield mr-1"></i>
                                    by <?php echo htmlspecialchars($activity['admin_name']); ?> • 
                                    <i class="far fa-clock ml-2 mr-1"></i>
                                    <?php echo time_elapsed_string($activity['updated_at']); ?>
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span class="inline-block px-2 py-1 text-xs font-medium rounded-full <?php echo getActivityBadgeColor($activity['update_type']); ?>">
                                <?php echo ucfirst($activity['update_type']); ?>
                            </span>
                            <p class="text-xs text-gray-500 mt-1">
                                <?php echo ucfirst(str_replace('_', ' ', $activity['updated_table'])); ?>
                            </p>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-center py-8 text-gray-500">
                    <i class="fas fa-inbox text-4xl mb-3"></i>
                    <p class="text-lg">No recent activity</p>
                    <p class="text-sm mt-1">System updates will appear here</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Employment Distribution Chart
const employmentCtx = document.getElementById('employmentChart').getContext('2d');
new Chart(employmentCtx, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($careerLabels); ?>,
        datasets: [{
            data: <?php echo json_encode($careerData); ?>,
            backgroundColor: [
                '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#84cc16'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { 
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const label = context.label || '';
                        const value = context.raw || 0;
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = Math.round((value / total) * 100);
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        }
    }
});

// Graduation Trends Chart
const graduationCtx = document.getElementById('graduationChart').getContext('2d');
new Chart(graduationCtx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($gradYears); ?>,
        datasets: [{
            label: 'Graduates per Year',
            data: <?php echo json_encode($gradCounts); ?>,
            borderColor: '#8b5cf6',
            backgroundColor: 'rgba(139, 92, 246, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#8b5cf6',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Toast notification handling
document.addEventListener("DOMContentLoaded", () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        showToast(urlParams.get('success'), 'success');
    } else if (urlParams.has('error')) {
        showToast(urlParams.get('error'), 'error');
    }
});
</script>
<?php
// Enhanced Helper functions
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
    }
    
    // Add specific details based on the table and type
    switch ($activity['updated_table']) {
        case 'alumni_profile':
            $details = $name ? "for {$name}" : "alumni profile";
            break;
        case 'alumni_documents':
            $docType = !empty($activity['document_type']) ? " ({$activity['document_type']})" : '';
            $details = $name ? "{$name}'s document{$docType}" : "document";
            break;
        case 'employment_info':
            $company = !empty($activity['company_name']) ? " at {$activity['company_name']}" : '';
            $details = $name ? "{$name}'s employment{$company}" : "employment information";
            break;
        case 'education_info':
            $school = !empty($activity['school_name']) ? " at {$activity['school_name']}" : '';
            $details = $name ? "{$name}'s education{$school}" : "education information";
            break;
        default:
            $details = $activity['updated_table'];
    }
    
    $actions = [
        'approve' => 'Approved',
        'reject' => 'Rejected', 
        'update' => 'Updated'
    ];
    
    $action = $actions[$activity['update_type']] ?? 'Modified';
    
    return "{$action} {$details}";
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