<?php
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "alumni") {
    header("Location: ../login/login.php");
    exit();
}

include("../connect.php");

$user_id = $_SESSION["user_id"];

// Fetch alumni info
$stmt = $conn->prepare("
    SELECT ap.first_name, ap.middle_name, ap.last_name, u.email
    FROM alumni_profile AS ap
    LEFT JOIN users AS u ON ap.user_id = u.user_id
    WHERE ap.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc() ?: [];

// Construct full name with proper checks
$full_name = 'Alumni'; // Default
if (!empty($profile)) {
    $full_name = trim(
        ($profile['first_name'] ?? '') . ' ' .
        ($profile['middle_name'] ?? '') . ' ' .
        ($profile['last_name'] ?? '')
    );
    if (empty($full_name)) {
        $full_name = 'Alumni';
    }
}

// Set default page title
$page_title = $page_title ?? "Alumni Page";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <!-- Tailwind CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="alumni_format.css">
    <script src="../assets/js/phil-address/phil.min.js"></script>

      <style>
        /* Optional smooth fade-in/out for modal */
        .hidden {
        display: none;
        }
        #profileUpdateModal {
            opacity: 0;
            transition: opacity 0.5s;
        }
        #profileUpdateModal.show {
            opacity: 1;
        }
    </style>
</head>

<body class="bg-gray-50 min-h-screen flex">
    <!-- Sidebar -->
    <div class="w-72 gradient-bg text-white flex-shrink-0 flex flex-col h-screen justify-between">
        <div class="p-6">
            <div class="flex items-center space-x-3 mb-8">
                <div class="w-10 h-10 rounded-full bg-white bg-opacity-20 flex items-center justify-center">
                    <i class="fas fa-graduation-cap text-lg" aria-hidden="true"></i>
                </div>
                <h2 class="font-bold text-lg">Alumni</h2>
            </div>
            <nav class="space-y-2">
                <a href="alumni_dashboard.php" class="sidebar-item <?php echo ($active_page ?? '') === 'dashboard' ? 'active' : ''; ?> flex items-center space-x-3 p-3 rounded-lg">
                    <i class="fas fa-tachometer-alt w-5" aria-hidden="true"></i>
                    <span>Dashboard</span>
                </a>
                <a href="alumni_profile.php" class="sidebar-item <?php echo ($active_page ?? '') === 'profile' ? 'active' : ''; ?> flex items-center space-x-3 p-3 rounded-lg">
                    <i class="fas fa-user w-5" aria-hidden="true"></i>
                    <span>Profile Management</span>
                </a>
            </nav>
        </div>

            <!-- Push Logout to Bottom -->
            <div class="p-6">
                <hr class="border-gray-400 my-6">
                <a href="../login/logout.php" class="flex items-center space-x-3 text-white-300 hover:text-red-500 p-3 rounded-lg">
                    <i class="fas fa-sign-out-alt text-xl" aria-hidden="true"></i>
                    <span>Logout</span>
                </a>
            </div>  
    </div>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col">
        <!-- Top Bar -->
        <header class="bg-white shadow-sm border-b p-4">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-800"><?php echo htmlspecialchars($page_title); ?></h1>
                <div class="flex items-center space-x-6">
                    <!-- Notification Button and Popup -->
                    <div class="relative">
                        <button id="notificationBtn" class="relative text-gray-600 hover:text-blue-600 flex items-center">
                            <i class="fas fa-bell text-xl"></i>
                            <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full"></span>
                        </button>
                        <div id="notifPopup" class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-2xl hidden z-50 overflow-hidden">
                            <div class="p-4 border-b font-bold text-gray-700 flex justify-between items-center sticky top-0 bg-white">
                                Notifications
                                <button id="markReadBtn" class="text-xs text-blue-600 hover:underline">Mark all as read</button>
                            </div>
                            <div class="p-3 space-y-4">
                                <div class="border-b pb-3 border-gray-100">
                                    <h2 class="font-semibold text-gray-800"><i class="fas fa-bell text-blue-500 mr-2"></i> New Job Fair Event</h2>
                                    <p class="text-sm text-gray-600 pl-6">Join us at the upcoming job fair this Sept 15, 2025 at the University Main Hall.</p>
                                    <span class="text-xs text-gray-400 pl-6">Posted: Sept 5, 2025</span>
                                </div>
                                <div class="border-b pb-3 border-gray-100">
                                    <h2 class="font-semibold text-gray-800"><i class="fas fa-bullhorn text-yellow-500 mr-2"></i> Alumni Gathering</h2>
                                    <p class="text-sm text-gray-600 pl-6">Reconnect with your batchmates at the Alumni Homecoming on Oct 10, 2025.</p>
                                    <span class="text-xs text-gray-400 pl-6">Posted: Sept 1, 2025</span>
                                </div>
                                <div>
                                    <h2 class="font-semibold text-gray-800"><i class="fas fa-briefcase text-green-500 mr-2"></i> Job Opportunity</h2>
                                    <p class="text-sm text-gray-600 pl-6">TechCorp Inc. is hiring Software Engineers. Apply now!</p>
                                    <span class="text-xs text-gray-400 pl-6">Posted: Aug 25, 2025</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profile Info -->
                    <div class="flex items-center space-x-3">
                        <div class="profile-avatar w-10 h-10 rounded-full flex items-center justify-center text-white font-bold">
                            <?php 
                            // Fetch name and email from users table
                            $stmt = $conn->prepare("SELECT name, email FROM users WHERE user_id = ?");
                            $stmt->bind_param("i", $user_id);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            $initials = 'AL';
                            $user_email = '';
                            if ($row = $result->fetch_assoc()) {
                                $initials = strtoupper(substr(trim($row['name'] ?: 'Alumni'), 0, 2));
                                $user_email = $row['email'];
                            }
                            $stmt->close();
                            echo htmlspecialchars($initials);
                            ?>
                        </div>
                        <div class="hidden md:block">
                            <p class="font-medium text-gray-800">
                                <?php echo htmlspecialchars($full_name); ?>
                            </p>
                            <p class="font-medium text-gray-700 text-sm">
                                <?php echo htmlspecialchars($user_email); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 p-6 overflow-auto">
            <?php echo $page_content ?? ''; ?>
        </main>
    </div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const notifButton = document.getElementById('notificationBtn');
    const notifPopup = document.getElementById('notifPopup');

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
});
</script>
</body>
</html>