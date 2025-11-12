<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

$page_title = "Alumni Records";
$active_page = "alumni_management";

// Get search parameter for global search
$search = $_GET['search'] ?? '';

// Fetch distinct batch years with employment status counts
$batchQuery = "SELECT 
                year_graduated,
                COUNT(*) as total_count,
                SUM(CASE WHEN employment_status = 'Unemployed' THEN 1 ELSE 0 END) as unemployed_count,
                SUM(CASE WHEN employment_status = 'Self-Employed' THEN 1 ELSE 0 END) as self_employed_count,
                SUM(CASE WHEN employment_status = 'Employed' THEN 1 ELSE 0 END) as employed_count,
                SUM(CASE WHEN employment_status = 'Student' THEN 1 ELSE 0 END) as student_count,
                SUM(CASE WHEN employment_status = 'Employed & Student' THEN 1 ELSE 0 END) as employed_student_count
               FROM alumni_profile 
               WHERE year_graduated IS NOT NULL 
               GROUP BY year_graduated 
               ORDER BY year_graduated DESC";
$batchResult = $conn->query($batchQuery);

ob_start();
?>

<div class="space-y-6">
    <!-- Global Search Bar -->
    <div class="bg-white p-6 rounded-xl shadow-lg">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Search Alumni Across All Batches</h3>
        <form method="GET" action="" class="flex gap-4">
            <div class="flex-1">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" 
                       placeholder="Search by alumni name...">
            </div>
            <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors whitespace-nowrap">
                <i class="fas fa-search mr-2"></i>Search
            </button>
            <?php if (!empty($search)): ?>
                <a href="alumni_management.php" class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition-colors whitespace-nowrap">
                    Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Batch Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <?php 
        $filteredBatchResult = $batchResult;
        if (!empty($search)) {
            // If search is active, filter batches that have matching alumni
            $searchQuery = "SELECT DISTINCT year_graduated 
                           FROM alumni_profile 
                           WHERE year_graduated IS NOT NULL 
                           AND (first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ?)
                           ORDER BY year_graduated DESC";
            $searchStmt = $conn->prepare($searchQuery);
            $searchTerm = "%$search%";
            $searchStmt->bind_param('sss', $searchTerm, $searchTerm, $searchTerm);
            $searchStmt->execute();
            $filteredBatchResult = $searchStmt->get_result();
            
            // Store original batch data for display
            $batchResult->data_seek(0);
            $batchData = [];
            while ($batch = $batchResult->fetch_assoc()) {
                $batchData[$batch['year_graduated']] = $batch;
            }
        }
        
        $displayResult = !empty($search) ? $filteredBatchResult : $batchResult;
        
        while ($batch = $displayResult->fetch_assoc()): 
            $batch_year = $batch['year_graduated'];
            $batch_stats = !empty($search) ? $batchData[$batch_year] : $batch;
        ?>
            <a href="batch_alumni.php?batch=<?php echo $batch_year; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
               class="bg-white p-6 rounded-xl shadow-lg stats-card card-hover border-l-4 border-blue-500 transform hover:scale-105 transition-all duration-200">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-500 mr-4">
                            <i class="fas fa-graduation-cap text-xl"></i>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-800"><?php echo $batch_year; ?></p>
                            <p class="text-sm font-medium text-gray-600">Graduation Year</p>
                        </div>
                    </div>
                    <i class="fas fa-chevron-right text-gray-400"></i>
                </div>
                
                <!-- Total Count -->
                <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                    <p class="text-lg font-bold text-gray-800 text-center"><?php echo $batch_stats['total_count']; ?> Total Alumni</p>
                </div>
                
                <!-- Employment Status Breakdown -->
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Unemployed</span>
                        <span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded"><?php echo $batch_stats['unemployed_count']; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Self-Employed</span>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded"><?php echo $batch_stats['self_employed_count']; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Employed</span>
                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded"><?php echo $batch_stats['employed_count']; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Student</span>
                        <span class="px-2 py-1 bg-purple-100 text-purple-800 text-xs font-semibold rounded"><?php echo $batch_stats['student_count']; ?></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm text-gray-600">Student & Employed</span>
                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs font-semibold rounded"><?php echo $batch_stats['employed_student_count']; ?></span>
                    </div>
                </div>
            </a>
        <?php endwhile; ?>
    </div>

    <!-- Empty State -->
    <?php if ($displayResult->num_rows === 0): ?>
    <div class="bg-white p-12 rounded-xl shadow-lg text-center">
        <i class="fas fa-graduation-cap text-6xl text-gray-300 mb-4"></i>
        <h3 class="text-xl font-bold text-gray-600 mb-2">
            <?php echo !empty($search) ? 'No Batches Found' : 'No Alumni Batches Found'; ?>
        </h3>
        <p class="text-gray-500">
            <?php echo !empty($search) 
                ? 'No batches found matching your search criteria.' 
                : 'There are no graduation years with alumni records yet.'; ?>
        </p>
    </div>
    <?php endif; ?>
</div>

<?php
$page_content = ob_get_clean();
include("admin_format.php");
?>