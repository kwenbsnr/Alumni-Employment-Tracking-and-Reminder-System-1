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

ob_start();
?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Career Distribution Chart -->
    <div class="bg-white p-6 rounded-xl shadow-lg stats-card card-hover">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Alumni Employment Distribution</h3>
        <div class="relative w-full h-80">
            <canvas id="adminChart"></canvas>
        </div>
    </div>
</div>

<script>
const ctx = document.getElementById('adminChart').getContext('2d');
new Chart(ctx, {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($careerLabels); ?>,
        datasets: [{
            label: 'Career Distribution',
            data: <?php echo json_encode($careerData); ?>,
            backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
document.addEventListener("DOMContentLoaded", () => {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        showToast(urlParams.get('success'));
    } else if (urlParams.has('error')) {
        showToast(urlParams.get('error'), 'error');
    }
});
</script>
<?php
$page_content = ob_get_clean();
include("admin_format.php");
?>