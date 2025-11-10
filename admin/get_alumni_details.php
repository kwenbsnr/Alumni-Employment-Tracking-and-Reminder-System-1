<?php
session_start();
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../login/login.php");
    exit();
}
include("../connect.php");

$user_id = $_GET['user_id'] ?? 0;

if ($user_id) {
    $query = "
        SELECT 
            ap.*,
            u.email,
            ei.company_name,
            ei.salary_range,
            jt.title as job_title,
            edu.school_name,
            edu.degree_pursued,
            CONCAT(tb.barangay_name, ', ', tm.municipality_name, ', ', tp.province_name) as full_address
        FROM alumni_profile ap
        LEFT JOIN users u ON ap.user_id = u.user_id
        LEFT JOIN employment_info ei ON ap.user_id = ei.user_id
        LEFT JOIN job_titles jt ON ei.job_title_id = jt.job_title_id
        LEFT JOIN education_info edu ON ap.user_id = edu.user_id
        LEFT JOIN address a ON ap.address_id = a.address_id
        LEFT JOIN table_barangay tb ON a.barangay_id = tb.barangay_id
        LEFT JOIN table_municipality tm ON tb.municipality_id = tm.municipality_id
        LEFT JOIN table_province tp ON tm.province_id = tp.province_id
        WHERE ap.user_id = ?
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $alumni = $result->fetch_assoc();
    
    if ($alumni) {
        ?>
        <div class="space-y-4">
            <!-- Personal Info -->
            <div>
                <h4 class="font-semibold text-gray-800 mb-2">Personal Information</h4>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div><span class="text-gray-600">Name:</span> <?php echo htmlspecialchars($alumni['first_name'] . ' ' . $alumni['last_name']); ?></div>
                    <div><span class="text-gray-600">Email:</span> <?php echo htmlspecialchars($alumni['email']); ?></div>
                    <div><span class="text-gray-600">Contact:</span> <?php echo htmlspecialchars($alumni['contact_number']); ?></div>
                    <div><span class="text-gray-600">Batch:</span> <?php echo $alumni['year_graduated']; ?></div>
                </div>
            </div>
            
            <!-- Employment Info -->
            <?php if (!empty($alumni['company_name'])): ?>
            <div>
                <h4 class="font-semibold text-gray-800 mb-2">Employment Information</h4>
                <div class="grid grid-cols-1 gap-1 text-sm">
                    <div><span class="text-gray-600">Company:</span> <?php echo htmlspecialchars($alumni['company_name']); ?></div>
                    <div><span class="text-gray-600">Position:</span> <?php echo htmlspecialchars($alumni['job_title']); ?></div>
                    <div><span class="text-gray-600">Salary:</span> <?php echo $alumni['salary_range']; ?></div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Education Info -->
            <?php if (!empty($alumni['school_name'])): ?>
            <div>
                <h4 class="font-semibold text-gray-800 mb-2">Education Information</h4>
                <div class="grid grid-cols-1 gap-1 text-sm">
                    <div><span class="text-gray-600">School:</span> <?php echo htmlspecialchars($alumni['school_name']); ?></div>
                    <div><span class="text-gray-600">Degree:</span> <?php echo htmlspecialchars($alumni['degree_pursued']); ?></div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Address -->
            <?php if (!empty($alumni['full_address'])): ?>
            <div>
                <h4 class="font-semibold text-gray-800 mb-2">Address</h4>
                <div class="text-sm text-gray-600"><?php echo htmlspecialchars($alumni['full_address']); ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    } else {
        echo '<p class="text-red-500">Alumni not found.</p>';
    }
} else {
    echo '<p class="text-red-500">Invalid user ID.</p>';
}
?>