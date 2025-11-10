<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

$page_title = "Dashboard";
$active_page = "dashboard";

// Fetch career data
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

// Fetch dashboard statistics
$statsQuery = "
    SELECT 
        (SELECT COUNT(*) FROM users WHERE role = 'alumni') as total_alumni,
        (SELECT COUNT(*) FROM alumni_profile WHERE submission_status = 'Approved') as approved_profiles,
        (SELECT COUNT(*) FROM alumni_profile WHERE submission_status = 'Pending') as pending_profiles,
        (SELECT COUNT(*) FROM alumni_profile WHERE submission_status = 'Rejected') as rejected_profiles,
        (SELECT COUNT(*) FROM employment_info) as employed_count,
        (SELECT COUNT(DISTINCT year_graduated) FROM alumni_profile WHERE year_graduated IS NOT NULL) as unique_graduation_years,
        (SELECT COUNT(*) FROM alumni_documents) as total_documents,
        (SELECT COUNT(*) FROM update_log WHERE DATE(updated_at) = CURDATE()) as today_updates
";

$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Fetch recent graduates data for line chart
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

// Fetch salary distribution
$salaryQuery = "
    SELECT salary_range, COUNT(*) as total 
    FROM employment_info 
    GROUP BY salary_range 
    ORDER BY 
        CASE salary_range
            WHEN 'Below ₱10,000' THEN 1
            WHEN '₱10,000–₱20,000' THEN 2
            WHEN '₱20,000–₱30,000' THEN 3
            WHEN '₱30,000–₱40,000' THEN 4
            WHEN '₱40,000–₱50,000' THEN 5
            WHEN 'Above ₱50,000' THEN 6
            ELSE 7
        END
";
$salaryResult = $conn->query($salaryQuery);
$salaryLabels = [];
$salaryData = [];
while ($row = $salaryResult->fetch_assoc()) {
    $salaryLabels[] = $row['salary_range'];
    $salaryData[] = $row['total'];
}

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
                    <p class="text-sm font-medium text-gray-600">Today's Updates</p>
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

        <!-- Salary Distribution Chart -->
        <div class="bg-white p-6 rounded-xl shadow-lg stats-card card-hover">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Salary Range Distribution</h3>
            <div class="relative w-full h-80">
                <canvas id="salaryChart"></canvas>
            </div>
        </div>

        <!-- Graduation Trends Chart -->
        <div class="bg-white p-6 rounded-xl shadow-lg stats-card card-hover lg:col-span-2">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Graduation Trends</h3>
            <div class="relative w-full h-80">
                <canvas id="graduationChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white p-6 rounded-xl shadow-lg stats-card">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <a href="admin_alumni.php?filter=pending" class="bg-yellow-50 border border-yellow-200 p-4 rounded-lg hover:bg-yellow-100 transition-colors">
                <div class="flex items-center">
                    <i class="fas fa-clock text-yellow-600 text-xl mr-3"></i>
                    <div>
                        <p class="font-semibold text-yellow-800">Review Pending</p>
                        <p class="text-sm text-yellow-600"><?php echo $stats['pending_profiles']; ?> profiles awaiting approval</p>
                    </div>
                </div>
            </a>
            <a href="admin_alumni.php" class="bg-blue-50 border border-blue-200 p-4 rounded-lg hover:bg-blue-100 transition-colors">
                <div class="flex items-center">
                    <i class="fas fa-list text-blue-600 text-xl mr-3"></i>
                    <div>
                        <p class="font-semibold text-blue-800">Manage Alumni</p>
                        <p class="text-sm text-blue-600">View all alumni profiles</p>
                    </div>
                </div>
            </a>
            <a href="admin_documents.php" class="bg-green-50 border border-green-200 p-4 rounded-lg hover:bg-green-100 transition-colors">
                <div class="flex items-center">
                    <i class="fas fa-file-alt text-green-600 text-xl mr-3"></i>
                    <div>
                        <p class="font-semibold text-green-800">Verify Documents</p>
                        <p class="text-sm text-green-600"><?php echo $stats['total_documents']; ?> documents submitted</p>
                    </div>
                </div>
            </a>
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

// Salary Distribution Chart
const salaryCtx = document.getElementById('salaryChart').getContext('2d');
new Chart(salaryCtx, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($salaryLabels); ?>,
        datasets: [{
            label: 'Number of Alumni',
            data: <?php echo json_encode($salaryData); ?>,
            backgroundColor: '#10b981',
            borderColor: '#059669',
            borderWidth: 1,
            borderRadius: 4
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
            },
            x: {
                ticks: {
                    maxRotation: 45
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
$page_content = ob_get_clean();
include("admin_format.php");
?>