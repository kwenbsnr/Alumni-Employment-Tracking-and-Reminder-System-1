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
            ei.business_type,
            ei.company_address,
            edu.school_name,
            edu.degree_pursued,
            edu.start_year,
            edu.end_year,
            CONCAT(tb.barangay_name, ', ', tm.municipality_name, ', ', tp.province_name, ', ', tr.region_name) as full_address,
            ad1.file_path as cor_path,
            ad2.file_path as coe_path,
            ad3.file_path as b_cert_path
        FROM alumni_profile ap
        LEFT JOIN users u ON ap.user_id = u.user_id
        LEFT JOIN employment_info ei ON ap.user_id = ei.user_id
        LEFT JOIN job_titles jt ON ei.job_title_id = jt.job_title_id
        LEFT JOIN education_info edu ON ap.user_id = edu.user_id
        LEFT JOIN address a ON ap.address_id = a.address_id
        LEFT JOIN table_barangay tb ON a.barangay_id = tb.barangay_id
        LEFT JOIN table_municipality tm ON tb.municipality_id = tm.municipality_id
        LEFT JOIN table_province tp ON tm.province_id = tp.province_id
        LEFT JOIN table_region tr ON tp.region_id = tr.region_id
        LEFT JOIN alumni_documents ad1 ON ap.user_id = ad1.user_id AND ad1.document_type = 'COR'
        LEFT JOIN alumni_documents ad2 ON ap.user_id = ad2.user_id AND ad2.document_type = 'COE'
        LEFT JOIN alumni_documents ad3 ON ap.user_id = ad3.user_id AND ad3.document_type = 'B_CERT'
        WHERE ap.user_id = ?
        LIMIT 1
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $alumni = $result->fetch_assoc();
    
    if ($alumni) {
        // Determine documents based on employment status
        $submitted_docs = [];
        $employment_status = $alumni['employment_status'];
        
        // COR for Students and Employed & Student
        if (!empty($alumni['cor_path']) && in_array($employment_status, ['Student', 'Employed & Student'])) {
            $submitted_docs[] = ['type' => 'Certificate of Registration', 'path' => $alumni['cor_path']];
        }
        
        // COE for Employed and Employed & Student
        if (!empty($alumni['coe_path']) && in_array($employment_status, ['Employed', 'Employed & Student'])) {
            $submitted_docs[] = ['type' => 'Certificate of Employment', 'path' => $alumni['coe_path']];
        }
        
        // Business Certificate for Self-Employed
        if (!empty($alumni['b_cert_path']) && $employment_status == 'Self-Employed') {
            $submitted_docs[] = ['type' => 'Business Certificate', 'path' => $alumni['b_cert_path']];
        }
        
        // Birth Certificate for Unemployed (if provided)
        if (!empty($alumni['b_cert_path']) && $employment_status == 'Unemployed') {
            $submitted_docs[] = ['type' => 'Birth Certificate', 'path' => $alumni['b_cert_path']];
        }
        ?>
        <div class="max-w-4xl mx-auto bg-white rounded-xl shadow-2xl">
            <!-- Header with Photo and Basic Info -->
            <div class="flex items-start space-x-6 p-6 border-b border-gray-200">
                <!-- Larger Profile Photo -->
                <div class="flex-shrink-0">
                    <?php if (!empty($alumni['photo_path'])): ?>
                        <img class="h-24 w-24 rounded-full object-cover border-4 border-blue-100 shadow-lg" 
                            src="../<?php echo $alumni['photo_path']; ?>" 
                            alt="Profile Photo">
                    <?php else: ?>
                        <div class="h-24 w-24 rounded-full bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center border-4 border-blue-50 shadow-lg">
                            <i class="fas fa-user text-blue-400 text-3xl"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Basic Information -->
                <div class="flex-1">
                    <h2 class="text-2xl font-bold text-gray-800 mb-2">
                        <?php echo htmlspecialchars($alumni['first_name'] . ' ' . ($alumni['middle_name'] ? $alumni['middle_name'] . ' ' : '') . $alumni['last_name']); ?>
                    </h2>
                    <div class="grid grid-cols-2 gap-x-8 gap-y-2 text-sm">
                        <div>
                            <span class="font-medium text-gray-600">Email:</span>
                            <span class="text-gray-800"><?php echo htmlspecialchars($alumni['email']); ?></span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-600">Batch:</span>
                            <span class="text-gray-800"><?php echo $alumni['year_graduated']; ?></span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-600">Status:</span>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo getSubmissionStatusColor($alumni['submission_status']); ?>">
                                <?php echo $alumni['submission_status']; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="p-6 space-y-6">
                <!-- Personal Information - Same for all employment statuses -->
                <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl p-5 border border-blue-100">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-user-circle text-blue-500 mr-3 text-xl"></i>
                        Personal Information
                    </h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-gray-600">Employment Status:</span>
                            <span class="text-gray-800"><?php echo $alumni['employment_status']; ?></span>
                        </div>
                        <?php if (!empty($alumni['contact_number'])): ?>
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-gray-600">Contact Number:</span>
                            <span class="text-gray-800"><?php echo htmlspecialchars($alumni['contact_number']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($alumni['full_address'])): ?>
                        <div class="flex justify-between items-start">
                            <span class="font-medium text-gray-600">Address:</span>
                            <span class="text-gray-800 text-right max-w-xs"><?php echo htmlspecialchars($alumni['full_address']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Employment Information for Employed, Self-Employed, and Employed & Student -->
                <?php if (in_array($employment_status, ['Employed', 'Self-Employed', 'Employed & Student'])): ?>
                <div class="bg-gradient-to-br from-green-50 to-teal-50 rounded-xl p-5 border border-green-100">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-briefcase text-green-500 mr-3 text-xl"></i>
                        Employment Information
                    </h3>
                    <div class="space-y-3 text-sm">
                        <?php if ($employment_status == 'Employed' || $employment_status == 'Employed & Student'): ?>
                            <?php if (!empty($alumni['job_title'])): ?>
                            <div class="flex justify-between items-center">
                                <span class="font-medium text-gray-600">Job Title:</span>
                                <span class="text-gray-800"><?php echo htmlspecialchars($alumni['job_title']); ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($alumni['company_name'])): ?>
                            <div class="flex justify-between items-center">
                                <span class="font-medium text-gray-600">Company Name:</span>
                                <span class="text-gray-800"><?php echo htmlspecialchars($alumni['company_name']); ?></span>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php if ($employment_status == 'Self-Employed' && !empty($alumni['business_type'])): ?>
                            <div class="flex justify-between items-center">
                                <span class="font-medium text-gray-600">Business Type:</span>
                                <span class="text-gray-800"><?php echo htmlspecialchars($alumni['business_type']); ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($alumni['salary_range'])): ?>
                            <div class="flex justify-between items-center">
                                <span class="font-medium text-gray-600">Salary Range:</span>
                                <span class="text-gray-800"><?php echo $alumni['salary_range']; ?></span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($alumni['company_address'])): ?>
                            <div class="flex justify-between items-start">
                                <span class="font-medium text-gray-600">
                                    <?php echo $employment_status == 'Self-Employed' ? 'Business Address:' : 'Company Address:'; ?>
                                </span>
                                <span class="text-gray-800 text-right max-w-xs"><?php echo htmlspecialchars($alumni['company_address']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Education Information for Student and Employed & Student -->
                <?php if (in_array($employment_status, ['Student', 'Employed & Student']) && !empty($alumni['school_name'])): ?>
                <div class="bg-gradient-to-br from-purple-50 to-pink-50 rounded-xl p-5 border border-purple-100">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-graduation-cap text-purple-500 mr-3 text-xl"></i>
                        Education Information
                    </h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-gray-600">School Name:</span>
                            <span class="text-gray-800"><?php echo htmlspecialchars($alumni['school_name']); ?></span>
                        </div>
                        <?php if (!empty($alumni['degree_pursued'])): ?>
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-gray-600">Degree Pursued:</span>
                            <span class="text-gray-800"><?php echo htmlspecialchars($alumni['degree_pursued']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($alumni['start_year']) || !empty($alumni['end_year'])): ?>
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-gray-600">Academic Period:</span>
                            <span class="text-gray-800">
                                <?php echo $alumni['start_year']; ?> - <?php echo $alumni['end_year'] ?: 'Present'; ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Unemployed Status Message -->
                <?php if ($employment_status == 'Unemployed'): ?>
                <div class="bg-gradient-to-br from-gray-50 to-blue-50 rounded-xl p-5 border border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-briefcase text-gray-500 mr-3 text-xl"></i>
                        Employment Information
                    </h3>
                    <div class="text-center py-4">
                        <i class="fas fa-search text-gray-400 text-3xl mb-2"></i>
                        <p class="text-gray-600 font-medium">Currently seeking employment opportunities</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Submitted Documents - Only show if there are submitted documents -->
                <?php if (!empty($submitted_docs)): ?>
                <div class="bg-gradient-to-br from-yellow-50 to-orange-50 rounded-xl p-5 border border-yellow-100">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-file-alt text-yellow-500 mr-3 text-xl"></i>
                        Submitted Documents
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <?php foreach ($submitted_docs as $doc): ?>
                            <div class="bg-white rounded-lg p-3 border border-gray-200 hover:border-yellow-300 transition-colors">
                                <div class="flex items-center justify-between">
                                    <span class="font-medium text-gray-700 text-sm"><?php echo $doc['type']; ?></span>
                                    <a href="../<?php echo $doc['path']; ?>" target="_blank" 
                                       class="text-blue-600 hover:text-blue-800 flex items-center text-sm bg-blue-50 px-2 py-1 rounded hover:bg-blue-100 transition-colors">
                                        <i class="fas fa-external-link-alt mr-1 text-xs"></i> View
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Last Update -->
            <?php if (!empty($alumni['last_profile_update'])): ?>
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
                <p class="text-xs text-gray-500 text-center">
                    <i class="fas fa-clock mr-1"></i>
                    Last updated: <?php echo date('F j, Y g:i A', strtotime($alumni['last_profile_update'])); ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    } else {
        echo '<div class="text-center py-8 bg-white rounded-xl">';
        echo '<i class="fas fa-exclamation-triangle text-4xl text-red-400 mb-4"></i>';
        echo '<p class="text-red-500 text-lg">Alumni not found.</p>';
        echo '</div>';
    }
} else {
    echo '<div class="text-center py-8 bg-white rounded-xl">';
    echo '<i class="fas fa-exclamation-circle text-4xl text-red-400 mb-4"></i>';
    echo '<p class="text-red-500 text-lg">Invalid user ID.</p>';
    echo '</div>';
}

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
?>